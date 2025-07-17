<?php
// Start the session at the very beginning
session_start();
include 'db_connect.php';
// Initialize variables
$error = '';
$username_email = '';

// Check if the user is logged in
if (!isset($_SESSION['admin_id'])) {
    // Redirect to login page if not logged in
    header("Location: sign-in.php");
    exit;
}

// Get admin information from the database
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT admin_id, username, fullname, email, profile_image, role FROM admins WHERE admin_id = ? AND is_active = TRUE");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Admin not found or not active, destroy session and redirect to login
    session_destroy();
    header("Location: sign-in.php");
    exit;
}

// Get admin details
$admin = $result->fetch_assoc();

// Set default role if not present
if (!isset($admin['role'])) {
    $admin['role'] = 'Administrator';
}

// Update last login time
$update_stmt = $conn->prepare("UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE admin_id = ?");
$update_stmt->bind_param("i", $admin_id);
$update_stmt->execute();
$update_stmt->close();

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: sign-in.php");
    exit;
}

// Function to get profile image URL
function getProfileImageUrl($profileImage) {
    if (!empty($profileImage) && file_exists("uploads/profiles/" . $profileImage)) {
        return "uploads/profiles/" . $profileImage;
    } else {
        return "assets/images/default-avatar.png"; // Default image path
    }
}

// Load products from XML file - FIXED: Using the correct XML structure and file name
function loadProductsXml() {
    $productsFile = 'product.xml';
    if (file_exists($productsFile)) {
        return simplexml_load_file($productsFile);
    } else {
        return false;
    }
}

// Update product stock in XML file - FIXED: Updated to handle the correct XML structure
function updateProductStock($productId, $quantity) {
    $productsFile = 'product.xml';
    $products = simplexml_load_file($productsFile);
    
    if ($products === false) {
        return ['status' => 'error', 'message' => 'Products file not found'];
    }
    
    $productFound = false;
    
    // Access the products correctly within the store->products structure
    foreach ($products->products->product as $product) {
        if ((string)$product->id === (string)$productId) {
            $currentStock = (int)$product->stock;
            $newStock = max(0, $currentStock - $quantity);
            $product->stock = $newStock;
            $productFound = true;
            break;
        }
    }
    
    if (!$productFound) {
        return ['status' => 'error', 'message' => 'Product not found: ' . $productId];
    }
    
    // Save the updated XML back to file
    if ($products->asXML($productsFile)) {
        return ['status' => 'success', 'message' => 'Stock updated successfully for product ' . $productId];
    } else {
        return ['status' => 'error', 'message' => 'Failed to update stock for product ' . $productId];
    }
}

// Load orders from XML file
function loadOrdersXml() {
    $ordersFile = 'transaction.xml';
    if (file_exists($ordersFile)) {
        return simplexml_load_file($ordersFile);
    } else {
        return false;
    }
}

// Function to update order status in XML file
function updateOrderStatus($transactionId, $newStatus, $previousStatus) {
    $ordersFile = 'transaction.xml';
    $orders = loadOrdersXml();
    
    if ($orders === false) {
        return ['status' => 'error', 'message' => 'Orders file not found'];
    }
    
    $orderFound = false;
    $items = [];
    
    foreach ($orders->transaction as $transaction) {
        if ((string)$transaction->transaction_id === $transactionId) {
            // Store items information for stock management
            if (strtolower($newStatus) === 'shipped' && strtolower($previousStatus) !== 'shipped') {
                foreach ($transaction->items->item as $item) {
                    $items[] = [
                        'product_id' => (string)$item->product_id,
                        'quantity' => (int)$item->quantity
                    ];
                }
            }
            
            // Update the status
            $transaction->status = $newStatus;
            $orderFound = true;
            break;
        }
    }
    
    if (!$orderFound) {
        return ['status' => 'error', 'message' => 'Order not found'];
    }
    
    // Save the updated XML back to file
    if ($orders->asXML($ordersFile)) {
        $result = ['status' => 'success', 'message' => 'Order status updated successfully'];
        
        // Handle stock changes when changing to shipped
        if (strtolower($newStatus) === 'shipped' && strtolower($previousStatus) !== 'shipped') {
            $stockErrors = [];
            // Decrement stock when changing to shipped
            foreach ($items as $item) {
                $stockResult = updateProductStock($item['product_id'], $item['quantity']);
                if ($stockResult['status'] === 'error') {
                    $stockErrors[] = $stockResult['message'];
                }
            }
            
            if (!empty($stockErrors)) {
                $result['message'] .= ' Note: Some inventory updates failed: ' . implode('; ', $stockErrors);
                $result['status'] = 'warning';
            } else {
                $result['message'] .= ' Inventory has been updated.';
            }
        }
        
        return $result;
    } else {
        return ['status' => 'error', 'message' => 'Failed to update order status'];
    }
}

// Export orders to CSV
function exportOrdersToCSV($orders) {
    // Create a temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'orders_export_');
    
    // Open the file for writing
    $f = fopen($tempFile, 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fputs($f, "\xEF\xBB\xBF");
    
    // Write headers
    fputcsv($f, ['Order ID', 'Customer', 'Date', 'Total', 'Status', 'Payment Method', 'Items', 'Customer Email', 'Customer Phone', 'Shipping Address']);
    
    foreach ($orders->transaction as $order) {
        // Create item list string
        $items = [];
        if (isset($order->items->item)) {
            foreach ($order->items->item as $item) {
                $items[] = (string)$item->quantity . 'x ' . (string)$item->product_name . ' (' . (string)$item->color . ', ' . (string)$item->size . ')';
            }
        }
        $itemsStr = implode("; ", $items);
        
        // Get customer info
        $customerName = isset($order->shipping_info->fullname) ? (string)$order->shipping_info->fullname : '';
        $customerEmail = isset($order->shipping_info->email) ? (string)$order->shipping_info->email : '';
        $customerPhone = isset($order->shipping_info->phone) ? (string)$order->shipping_info->phone : '';
        
        // Get address info
        $address = isset($order->shipping_info->address) ? (string)$order->shipping_info->address : '';
        $city = isset($order->shipping_info->city) ? (string)$order->shipping_info->city : '';
        $postalCode = isset($order->shipping_info->postal_code) ? (string)$order->shipping_info->postal_code : '';
        $fullAddress = $address . ', ' . $city . ', ' . $postalCode;
        
        // Write order row
        fputcsv($f, [
            (string)$order->transaction_id,
            $customerName,
            (string)$order->transaction_date,
            (string)$order->total_amount,
            (string)$order->status,
            (string)$order->payment_method,
            $itemsStr,
            $customerEmail,
            $customerPhone,
            $fullAddress
        ]);
    }
    
    fclose($f);
    
    // Return the file path
    return $tempFile;
}

// Handle update order status request
$statusMessage = '';
$statusClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $transactionId = $_POST['transaction_id'];
    $newStatus = $_POST['new_status'];
    $previousStatus = $_POST['previous_status'];
    
    $result = updateOrderStatus($transactionId, $newStatus, $previousStatus);
    
    if ($result['status'] === 'success') {
        $statusMessage = $result['message'];
        $statusClass = 'success';
    } else if ($result['status'] === 'warning') {
        $statusMessage = $result['message'];
        $statusClass = 'warning';
    } else {
        $statusMessage = $result['message'];
        $statusClass = 'error';
    }
}

require_once('TCPDF-main/TCPDF-main/tcpdf.php');

function exportOrdersToPDF($orders) {
    $totalOrders = 0;
    $totalRevenue = 0;
    foreach ($orders->transaction as $order) {
        $totalOrders++;
        $totalRevenue += (float)$order->total_amount;
    }
    $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

    $pdf = new TCPDF();
    $pdf->SetCreator('TechHub');
    $pdf->SetAuthor('TechHub');
    $pdf->SetTitle('Orders Report');
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage();

    // HEADER SECTION
    $html = '
    <div style="text-align: center; font-family: helvetica;">
        <h2 style="background-color: #000000ff; color: white; margin: 0; font-size: 20px;">TechHub</h2>
        <p style="margin: 0; font-size: 12px;">TECHNOLOGY & ELECTRONICS</p>
        <h3 style="margin: 10px 0;">ORDER REPORT</h3>
        <small>Generated on: ' . date("F j, Y \\a\\t g:i A") . '</small>
    </div><br>';

    // SUMMARY SECTION
    $html .= '
    <div style="background-color: #2ecc71; color: white; padding: 12px; border-radius: 8px; font-size: 12px; margin-bottom: 10px;">
        <table width="100%" style="text-align: center;">
            <tr>
                <td><b>' . $totalOrders . '</b><br>Total Orders</td>
                <td><b>₱' . number_format($totalRevenue, 2) . '</b><br>Total Revenue</td>
                <td><b>₱' . number_format($avgOrderValue, 2) . '</b><br>Avg. Order Value</td>
            </tr>
        </table>
    </div>';

    // ORDER DETAILS
    foreach ($orders->transaction as $order) {
        $itemsHTML = '';
        $subtotal = 0;

        foreach ($order->items->item as $item) {
            $qty = (int)$item->quantity;
            $unitPrice = (float)$item->price;
            $lineTotal = $qty * $unitPrice;
            $subtotal += $lineTotal;

            $itemsHTML .= '
            <tr>
                <td>' . $item->product_name . '</td>
                <td>' . $item->color . '</td>
                <td>' . $item->size . '</td>
                <td style="text-align:right;">₱' . number_format($unitPrice, 2) . '</td>
                <td style="text-align:center;">' . $qty . '</td>
                <td style="text-align:right;">₱' . number_format($lineTotal, 2) . '</td>
            </tr>';
        }

        $shipping = isset($order->shipping_fee) ? (float)$order->shipping_fee : 100.00;
        $grandTotal = $subtotal + $shipping;

        $html .= '
        <div style="margin-top: 20px;">
            <h4 style="background-color: #000000ff; color: white; padding: 8px; border-radius: 5px; font-size: 12px;">
                ORDER #' . $order->transaction_id . ' - ' . strtoupper($order->status) . ' | ' . date("F j, Y g:i A", strtotime($order->transaction_date)) . '
            </h4>
            <p style="font-size: 10px; margin: 5px 0;">
                <b>Customer:</b> ' . $order->shipping_info->fullname . '<br>
                <b>Email:</b> ' . $order->shipping_info->email . '<br>
                <b>Phone:</b> ' . $order->shipping_info->phone . '<br>
                <b>Payment:</b> ' . $order->payment_method . '<br>
                <b>Address:</b> ' . $order->shipping_info->address . ', ' . $order->shipping_info->city . ' ' . $order->shipping_info->postal_code . '<br>
                <b>Notes:</b> ' . ($order->shipping_info->notes ?? 'None') . '
            </p>
            <table border="1" cellpadding="5" cellspacing="0" width="100%" style="font-size:9px; border-collapse: collapse;">
                <thead style="background-color: #495057; color: white;">
                    <tr>
                        <th>Product Name</th>
                        <th>Color</th>
                        <th>Size</th>
                        <th>Unit Price</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>' . $itemsHTML . '</tbody>
            </table>
            <div style="text-align: right; font-size: 10px; margin-top: 8px;">
                Subtotal: ₱' . number_format($subtotal, 2) . '<br>
                Shipping Fee: ₱' . number_format($shipping, 2) . '<br>
                <b style="color: #2980b9;">TOTAL AMOUNT: ₱' . number_format($grandTotal, 2) . '</b>
            </div>
        </div>';
    }

    $html .= '<div style="text-align: center; font-size: 8px; color: #888; margin-top: 20px;">TechHub E-commerce Platform - Confidential Report</div>';

    $pdf->writeHTML($html, true, false, true, false, '');

    $fileName = 'TechHub_Orders_Report_' . date('Y-m-d_H-i-s') . '.pdf';
    $filePath = sys_get_temp_dir() . '/' . $fileName;

    $pdf->Output($filePath, 'F');
    return $filePath;
}
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $orders = loadOrdersXml();
    if ($orders !== false) {
        $filePath = exportOrdersToPDF($orders);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        readfile($filePath);
        unlink($filePath);
        exit;
    }
}


// Get profile image URL
$profileImageUrl = getProfileImageUrl($admin['profile_image']);

// Close the statement
$stmt->close();

// Load orders
$orders = loadOrdersXml();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechHub - Order Management</title>    <link rel="icon" href="images/hf.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
 
   <style>
        :root {
            --primary: #0a0e1a;
            --secondary: #1a2332;
            --accent: #00d4ff;
            --accent-glow: rgba(0, 212, 255, 0.3);
            --light: #ffffff;
            --dark: #0a0e1a;
            --grey: #8892b0;
            --light-grey: #ccd6f6;
            --sidebar-width: 280px;
            --danger: #ff6b6b;
            --success: #64ffda;
            --warning: #ffd700;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #0a0e1a 0%, #1a2332 100%);
            display: flex;
            min-height: 100vh;
            color: var(--light-grey);
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #0f1419 0%, #1a2332 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
            border-right: 2px solid var(--accent);
            box-shadow: 4px 0 20px rgba(0, 212, 255, 0.15);
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #1a2332;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 10px;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(0, 212, 255, 0.2);
            background: rgba(0, 212, 255, 0.05);
            backdrop-filter: blur(10px);
        }
        
        .sidebar-logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--light);
            text-decoration: none;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-logo i {
            font-size: 28px;
            background: linear-gradient(45deg, var(--accent), #0099cc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .sidebar-logo span {
            color: var(--accent);
            text-shadow: 0 0 10px var(--accent-glow);
        }
        
        .sidebar-close {
            color: var(--accent);
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            display: none;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .sidebar-close:hover {
            background: rgba(0, 212, 255, 0.1);
            transform: rotate(90deg);
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .menu-title {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--accent);
            padding: 15px 20px 10px;
            margin-top: 15px;
            font-weight: 600;
            letter-spacing: 1.5px;
            border-bottom: 1px solid rgba(0, 212, 255, 0.1);
            margin-bottom: 10px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            color: var(--light-grey);
            text-decoration: none;
            padding: 15px 20px;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
            margin: 5px 15px;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
        }

        .sidebar-menu a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0, 212, 255, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .sidebar-menu a:hover::before {
            left: 100%;
        }
        
        .sidebar-menu a:hover {
            background: rgba(0, 212, 255, 0.1);
            color: var(--light);
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0, 212, 255, 0.2);
            border-left: 4px solid var(--accent);
        }
        
        .sidebar-menu a.active {
            background: linear-gradient(90deg, rgba(0, 212, 255, 0.2), rgba(0, 212, 255, 0.1));
            border-left: 4px solid var(--accent);
            color: var(--light);
            box-shadow: 0 4px 15px rgba(0, 212, 255, 0.3);
        }
        
        .sidebar-menu a i {
            margin-right: 12px;
            font-size: 18px;
            width: 20px;
            text-align: center;
            color: var(--accent);
        }

        .notification-badge {
            background: linear-gradient(45deg, #ff6b6b, #ff8e53);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: auto;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        /* Main Content Area */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #0a0e1a 0%, #1a2332 100%);
        }
        
        /* Top Navigation */
        .top-navbar {
            background: linear-gradient(90deg, rgba(15, 20, 25, 0.95), rgba(26, 35, 50, 0.95));
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 212, 255, 0.2);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0, 212, 255, 0.1);
        }
        
        .toggle-sidebar {
            background: none;
            border: none;
            color: var(--accent);
            font-size: 20px;
            cursor: pointer;
            display: none;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .toggle-sidebar:hover {
            background: rgba(0, 212, 255, 0.1);
        }
        
        .nav-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .navbar-title {
            font-weight: 600;
            color: var(--light);
            font-size: 20px;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }
        
        .welcome-text {
            font-size: 14px;
            color: var(--grey);
            padding: 10px 15px;
            background: rgba(0, 212, 255, 0.05);
            border-radius: 20px;
            border: 1px solid rgba(0, 212, 255, 0.2);
        }
        
        .welcome-text strong {
            color: var(--accent);
            font-weight: 600;
        }
        
        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .navbar-actions .nav-link {
            color: var(--grey);
            font-size: 18px;
            position: relative;
            padding: 10px;
            border-radius: 50%;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .navbar-actions .nav-link:hover {
            color: var(--accent);
            background: rgba(0, 212, 255, 0.1);
            transform: scale(1.1);
        }
        
        .notification-count {
            position: absolute;
            top: 5px;
            right: 5px;
            background: linear-gradient(45deg, #ff6b6b, #ff8e53);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-3px); }
            60% { transform: translateY(-2px); }
        }
        
        /* Admin Profile Styles */
        .admin-profile {
            display: flex;
            align-items: center;
            position: relative;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 12px;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 212, 255, 0.2);
            background: rgba(0, 212, 255, 0.05);
        }

        .admin-profile:hover {
            background: rgba(0, 212, 255, 0.1);
            box-shadow: 0 4px 15px rgba(0, 212, 255, 0.2);
        }

        .admin-avatar-container {
            position: relative;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            overflow: hidden;
            border: 2px solid var(--accent);
            margin-right: 12px;
            box-shadow: 0 0 15px rgba(0, 212, 255, 0.4);
        }

        .admin-avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .admin-info {
            display: flex;
            flex-direction: column;
        }

        .admin-name {
            font-weight: 600;
            font-size: 14px;
            display: block;
            line-height: 1.2;
            color: var(--light);
        }

        .admin-role {
            font-size: 12px;
            color: var(--accent);
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .admin-dropdown {
            position: relative;
        }

        .admin-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 55px;
            background: linear-gradient(180deg, #0f1419 0%, #1a2332 100%);
            min-width: 280px;
            box-shadow: 0 10px 30px rgba(0, 212, 255, 0.2);
            z-index: 1000;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(0, 212, 255, 0.3);
            backdrop-filter: blur(20px);
        }

        .admin-dropdown-header {
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.1), rgba(0, 153, 204, 0.1));
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(0, 212, 255, 0.2);
        }

        .admin-dropdown-avatar-container {
            border-radius: 50%;
            width: 60px;
            height: 60px;
            overflow: hidden;
            border: 3px solid var(--accent);
            margin-right: 15px;
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.4);
        }

        .admin-dropdown-avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .admin-dropdown-info {
            display: flex;
            flex-direction: column;
        }

        .admin-dropdown-name {
            font-weight: 600;
            font-size: 16px;
            color: var(--light);
        }

        .admin-dropdown-role {
            font-size: 12px;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .admin-dropdown-user {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(0, 212, 255, 0.1);
        }

        .admin-dropdown-user-name {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 16px;
            color: var(--light);
        }

        .admin-dropdown-user-email {
            color: var(--grey);
            font-size: 14px;
        }

        .admin-dropdown-content a {
            color: var(--light-grey);
            padding: 15px 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 14px;
            border-bottom: 1px solid rgba(0, 212, 255, 0.1);
            transition: all 0.3s ease;
        }

        .admin-dropdown-content a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            color: var(--accent);
        }

        .admin-dropdown-content a.logout {
            color: var(--danger);
        }

        .admin-dropdown-content a.logout i {
            color: var(--danger);
        }

        .admin-dropdown-content a:hover {
            background: rgba(0, 212, 255, 0.1);
            color: var(--light);
            padding-left: 25px;
        }

        .admin-dropdown.show .admin-dropdown-content {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Dashboard Container */
        .dashboard-container {
            padding: 30px;
        }

        .dashboard-welcome {
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.1), rgba(0, 153, 204, 0.05));
            padding: 30px;
            border-radius: 15px;
            border: 1px solid rgba(0, 212, 255, 0.2);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }

        .welcome-title {
            font-size: 28px;
            font-weight: bold;
            color: var(--light);
            margin-bottom: 10px;
        }

        .welcome-subtitle {
            color: var(--accent);
            font-size: 16px;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .welcome-text {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                width: var(--sidebar-width);
                transform: translateX(0);
            }
            
            .sidebar-close {
                display: block;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .toggle-sidebar {
                display: block;
            }
            
            .navbar-title {
                display: none;
            }

            .admin-dropdown-content {
                right: -20px;
                min-width: 250px;
            }
        }

        /* Orders Container */
.orders-container {
    padding: 30px;
    background-color: var(--primary);
    min-height: 100vh;
    color: var(--light-grey);
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.page-title {
    font-size: 28px;
    font-weight: 700;
    color: var(--light);
    background: linear-gradient(135deg, var(--light) 0%, var(--accent) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.order-filters {
    display: flex;
    gap: 20px;
    margin-bottom: 25px;
    padding: 20px;
    background: var(--secondary);
    border-radius: 12px;
    border: 1px solid rgba(0, 212, 255, 0.1);
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 12px;
}

.filter-label {
    font-weight: 600;
    font-size: 14px;
    color: var(--light-grey);
}

.filter-select {
    padding: 10px 14px;
    border: 1px solid rgba(0, 212, 255, 0.2);
    border-radius: 8px;
    font-size: 14px;
    background-color: var(--primary);
    color: var(--light-grey);
    transition: all 0.3s ease;
}

.filter-select:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
}

.order-search {
    flex: 1;
    max-width: 350px;
}

.search-input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid rgba(0, 212, 255, 0.2);
    border-radius: 8px;
    font-size: 14px;
    background-color: var(--primary);
    color: var(--light-grey);
    transition: all 0.3s ease;
}

.search-input::placeholder {
    color: var(--grey);
}

.search-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
}

.orders-table-container {
    background: linear-gradient(135deg, var(--secondary) 0%, rgba(26, 35, 50, 0.8) 100%);
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    border: 1px solid rgba(0, 212, 255, 0.1);
    backdrop-filter: blur(10px);
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
}

.orders-table th {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    padding: 16px 20px;
    text-align: left;
    font-weight: 600;
    color: var(--light);
    font-size: 14px;
    border-bottom: 2px solid var(--accent);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.orders-table td {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(0, 212, 255, 0.1);
    font-size: 14px;
    vertical-align: middle;
    color: var(--light-grey);
}

.orders-table tr:last-child td {
    border-bottom: none;
}

.orders-table tr:hover {
    background: rgba(0, 212, 255, 0.05);
    transform: translateY(-1px);
    transition: all 0.3s ease;
}

.order-id {
    font-weight: 700;
    color: var(--accent);
    font-family: 'Courier New', monospace;
}

.order-status {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 12px;
    text-align: center;
    min-width: 100px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: 1px solid transparent;
}

.status-processing {
    background: linear-gradient(135deg, var(--warning), #ffed4e);
    color: var(--primary);
    border-color: var(--warning);
}

.status-shipped {
    background: linear-gradient(135deg, var(--info), #33c3f0);
    color: var(--primary);
    border-color: var(--info);
}

.status-delivered {
    background: linear-gradient(135deg, var(--success), #7fffd4);
    color: var(--primary);
    border-color: var(--success);
}

.status-cancelled {
    background: linear-gradient(135deg, var(--danger), #ff8a8a);
    color: var(--light);
    border-color: var(--danger);
}

.order-actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    text-align: center;
    cursor: pointer;
    border: none;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: relative;
    overflow: hidden;
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.btn:hover::before {
    left: 100%;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--accent), #0099cc);
    color: var(--primary);
    box-shadow: 0 4px 15px rgba(0, 212, 255, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 212, 255, 0.4);
}

.btn-view {
    background: linear-gradient(135deg, var(--secondary), var(--primary));
    color: var(--light);
    border: 1px solid var(--accent);
}

.btn-view:hover {
    background: linear-gradient(135deg, var(--accent), var(--info));
    color: var(--primary);
    transform: translateY(-2px);
}

.btn-change-status {
    background: linear-gradient(135deg, var(--warning), #ffed4e);
    color: var(--primary);
    box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
}

.btn-change-status:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4);
}

.modal {
    display: none;
    position: fixed;
    z-index: 1050;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(10, 14, 26, 0.8);
    overflow: auto;
    transition: all 0.3s ease;
    backdrop-filter: blur(5px);
}

.modal.show {
    display: block;
}

.modal-dialog {
    max-width: 700px;
    margin: 50px auto;
}

.modal-content {
    position: relative;
    background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    padding: 30px;
    border: 1px solid rgba(0, 212, 255, 0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 20px;
    margin-bottom: 20px;
    border-bottom: 2px solid var(--accent);
}

.modal-title {
    font-size: 22px;
    font-weight: 700;
    color: var(--light);
    background: linear-gradient(135deg, var(--light) 0%, var(--accent) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--grey);
    transition: all 0.3s ease;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    color: var(--accent);
    background: rgba(0, 212, 255, 0.1);
}

.modal-body {
    margin-bottom: 25px;
    color: var(--light-grey);
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    padding-top: 20px;
    border-top: 1px solid rgba(0, 212, 255, 0.2);
}

.order-details h5 {
    margin-bottom: 15px;
    font-weight: 700;
    font-size: 18px;
    color: var(--light);
}

.order-meta {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.order-meta-item {
    display: flex;
    flex-direction: column;
    padding: 15px;
    background: rgba(0, 212, 255, 0.05);
    border-radius: 8px;
    border: 1px solid rgba(0, 212, 255, 0.1);
}

.order-meta-label {
    font-size: 12px;
    color: var(--grey);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.order-meta-value {
    font-weight: 700;
    font-size: 16px;
    color: var(--light);
}

.customer-info h5 {
    margin-bottom: 15px;
    font-weight: 700;
    font-size: 18px;
    color: var(--light);
}

.customer-details {
    background: rgba(0, 212, 255, 0.05);
    padding: 20px;
    border-radius: 12px;
    border: 1px solid rgba(0, 212, 255, 0.1);
}

.customer-details p {
    margin-bottom: 8px;
    font-size: 14px;
    color: var(--light-grey);
}

.customer-details strong {
    font-weight: 700;
    color: var(--light);
}

.items-list h5 {
    margin-bottom: 15px;
    font-weight: 700;
    font-size: 18px;
    color: var(--light);
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(0, 212, 255, 0.02);
    border-radius: 8px;
    overflow: hidden;
}

.items-table th {
    background: var(--primary);
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: var(--light);
    border-bottom: 1px solid var(--accent);
}

.items-table td {
    padding: 12px 15px;
    border-bottom: 1px solid rgba(0, 212, 255, 0.1);
    font-size: 13px;
    color: var(--light-grey);
}

.status-form {
    margin-top: 25px;
}

.status-form label {
    display: block;
    margin-bottom: 12px;
    font-weight: 600;
    color: var(--light);
}

.status-form select {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid rgba(0, 212, 255, 0.2);
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 20px;
    background-color: var(--primary);
    color: var(--light-grey);
    transition: all 0.3s ease;
}

.status-form select:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
}

.alert {
    padding: 15px 20px;
    margin-bottom: 25px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    border: 1px solid transparent;
}

.alert-success {
    background: linear-gradient(135deg, rgba(100, 255, 218, 0.1), rgba(100, 255, 218, 0.05));
    color: var(--success);
    border-color: var(--success);
}

.alert-error {
    background: linear-gradient(135deg, rgba(255, 107, 107, 0.1), rgba(255, 107, 107, 0.05));
    color: var(--danger);
    border-color: var(--danger);
}

.pagination {
    display: flex;
    justify-content: flex-end;
    margin-top: 30px;
    gap: 5px;
}

.pagination-link {
    padding: 10px 16px;
    border: 1px solid rgba(0, 212, 255, 0.2);
    background: var(--secondary);
    color: var(--light-grey);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    border-radius: 8px;
}

.pagination-link.active {
    background: linear-gradient(135deg, var(--accent), var(--info));
    color: var(--primary);
    border-color: var(--accent);
    box-shadow: 0 4px 15px rgba(0, 212, 255, 0.3);
}

.pagination-link:hover:not(.active) {
    background: rgba(0, 212, 255, 0.1);
    color: var(--accent);
    transform: translateY(-2px);
}

/* Responsive Design */
@media (max-width: 992px) {
    .welcome-text {
        display: none;
    }
    
    .order-meta {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .sidebar {
        width: 0;
        transform: translateX(-100%);
    }
    
    .sidebar.active {
        width: var(--sidebar-width);
        transform: translateX(0);
    }
    
    .sidebar-close {
        display: block;
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .toggle-sidebar {
        display: block;
    }
    
    .navbar-title {
        display: none;
    }
    
    .order-filters {
        flex-direction: column;
        gap: 15px;
    }
    
    .order-search {
        max-width: 100%;
    }
    
    .orders-table-container {
        overflow-x: auto;
    }
    
    .orders-table {
        min-width: 800px;
    }
    
    .orders-container {
        padding: 20px;
    }
    
    .modal-dialog {
        margin: 20px;
        max-width: none;
    }
    
    .modal-content {
        padding: 20px;
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 20px;
    margin-bottom: 20px;
    border-bottom: 2px solid var(--accent);
}

.modal-title {
    font-size: 22px;
    font-weight: 700;
    color: var(--light);
    background: linear-gradient(135deg, var(--light) 0%, var(--accent) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--grey);
    transition: all 0.3s ease;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    color: var(--accent);
    background: rgba(0, 212, 255, 0.1);
}

.modal-body {
    margin-bottom: 25px;
    color: var(--light-grey);
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    padding-top: 20px;
    border-top: 1px solid rgba(0, 212, 255, 0.2);
}

.order-details h5 {
    margin-bottom: 15px;
    font-weight: 700;
    font-size: 18px;
    color: var(--light);
}

.order-meta {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.order-meta-item {
    display: flex;
    flex-direction: column;
    padding: 15px;
    background: rgba(0, 212, 255, 0.05);
    border-radius: 8px;
    border: 1px solid rgba(0, 212, 255, 0.1);
}

.order-meta-label {
    font-size: 12px;
    color: var(--grey);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.order-meta-value {
    font-weight: 700;
    font-size: 16px;
    color: var(--light);
}

.customer-info h5 {
    margin-bottom: 15px;
    font-weight: 700;
    font-size: 18px;
    color: var(--light);
}

.customer-details {
    background: rgba(0, 212, 255, 0.05);
    padding: 20px;
    border-radius: 12px;
    border: 1px solid rgba(0, 212, 255, 0.1);
}

.customer-details p {
    margin-bottom: 8px;
    font-size: 14px;
    color: var(--light-grey);
}

.customer-details strong {
    font-weight: 700;
    color: var(--light);
}

.items-list h5 {
    margin-bottom: 15px;
    font-weight: 700;
    font-size: 18px;
    color: var(--light);
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(0, 212, 255, 0.02);
    border-radius: 8px;
    overflow: hidden;
}

.items-table th {
    background: var(--primary);
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: var(--light);
    border-bottom: 1px solid var(--accent);
}

.items-table td {
    padding: 12px 15px;
    border-bottom: 1px solid rgba(0, 212, 255, 0.1);
    font-size: 13px;
    color: var(--light-grey);
}

.status-form {
    margin-top: 25px;
}

.status-form label {
    display: block;
    margin-bottom: 12px;
    font-weight: 600;
    color: var(--light);
}

.status-form select {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid rgba(0, 212, 255, 0.2);
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 20px;
    background-color: var(--primary);
    color: var(--light-grey);
    transition: all 0.3s ease;
}

.status-form select:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
}

.alert {
    padding: 15px 20px;
    margin-bottom: 25px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    border: 1px solid transparent;
}

.alert-success {
    background: linear-gradient(135deg, rgba(100, 255, 218, 0.1), rgba(100, 255, 218, 0.05));
    color: var(--success);
    border-color: var(--success);
}

.alert-error {
    background: linear-gradient(135deg, rgba(255, 107, 107, 0.1), rgba(255, 107, 107, 0.05));
    color: var(--danger);
    border-color: var(--danger);
}

.pagination {
    display: flex;
    justify-content: flex-end;
    margin-top: 30px;
    gap: 5px;
}

.pagination-link {
    padding: 10px 16px;
    border: 1px solid rgba(0, 212, 255, 0.2);
    background: var(--secondary);
    color: var(--light-grey);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    border-radius: 8px;
}

.pagination-link.active {
    background: linear-gradient(135deg, var(--accent), var(--info));
    color: var(--primary);
    border-color: var(--accent);
    box-shadow: 0 4px 15px rgba(0, 212, 255, 0.3);
}

.pagination-link:hover:not(.active) {
    background: rgba(0, 212, 255, 0.1);
    color: var(--accent);
    transform: translateY(-2px);
}

/* Responsive Design */
@media (max-width: 992px) {
    .welcome-text {
        display: none;
    }
    
    .order-meta {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .sidebar {
        width: 0;
        transform: translateX(-100%);
    }
    
    .sidebar.active {
        width: var(--sidebar-width);
        transform: translateX(0);
    }
    
    .sidebar-close {
        display: block;
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .toggle-sidebar {
        display: block;
    }
    
    .navbar-title {
        display: none;
    }
    
    .order-filters {
        flex-direction: column;
        gap: 15px;
    }
    
    .order-search {
        max-width: 100%;
    }
    
    .orders-table-container {
        overflow-x: auto;
    }
    
    .orders-table {
        min-width: 800px;
    }
    
    .orders-container {
        padding: 20px;
    }
    
    .modal-dialog {
        margin: 20px;
        max-width: none;
    }
    
    .modal-content {
        padding: 20px;
    }
}



/* Additional Styles for Export and View */
.order-actions .btn-export {
    background: linear-gradient(135deg, var(--success), #7fffd4);
    color: var(--primary);
    box-shadow: 0 4px 15px rgba(100, 255, 218, 0.3);
}

.order-actions .btn-export:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(100, 255, 218, 0.4);
}

.export-button {
    margin-bottom: 20px;
    display: inline-block;
    background: linear-gradient(135deg, var(--success), #7fffd4);
    color: var(--primary);
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(100, 255, 218, 0.3);
    position: relative;
    overflow: hidden;
}

.export-button::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.export-button:hover::before {
    left: 100%;
}

.export-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(100, 255, 218, 0.4);
}

.order-meta {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
    background: linear-gradient(135deg, rgba(0, 212, 255, 0.05), rgba(0, 212, 255, 0.02));
    padding: 20px;
    border-radius: 12px;
    border: 1px solid rgba(0, 212, 255, 0.1);
}

.order-meta-item {
    display: flex;
    flex-direction: column;
    padding: 15px;
    background: rgba(0, 212, 255, 0.03);
    border-radius: 8px;
    border: 1px solid rgba(0, 212, 255, 0.08);
    transition: all 0.3s ease;
}

.order-meta-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0, 212, 255, 0.1);
}

.order-meta-label {
    font-size: 12px;
    color: var(--grey);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.order-meta-value {
    font-weight: 700;
    font-size: 16px;
    color: var(--light);
}

.customer-info, .items-list {
    background: linear-gradient(135deg, var(--secondary), rgba(26, 35, 50, 0.8));
    border: 1px solid rgba(0, 212, 255, 0.2);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.customer-info h5, .items-list h5 {
    border-bottom: 2px solid var(--accent);
    padding-bottom: 12px;
    margin-bottom: 20px;
    color: var(--light);
    font-weight: 700;
    font-size: 18px;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(0, 212, 255, 0.02);
    border-radius: 8px;
    overflow: hidden;
}

.items-table th, .items-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid rgba(0, 212, 255, 0.1);
    color: var(--light-grey);
}

.items-table th {
    background: var(--primary);
    font-weight: 700;
    color: var(--light);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 13px;
}

.alert {
    padding: 16px 20px;
    margin-bottom: 25px;
    border: 1px solid transparent;
    border-radius: 8px;
    font-weight: 600;
    backdrop-filter: blur(10px);
}

.alert-success {
    color: var(--success);
    background: linear-gradient(135deg, rgba(100, 255, 218, 0.1), rgba(100, 255, 218, 0.05));
    border-color: var(--success);
}

.alert-warning {
    color: var(--warning);
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(255, 215, 0, 0.05));
    border-color: var(--warning);
}

.alert-error {
    color: var(--danger);
    background: linear-gradient(135deg, rgba(255, 107, 107, 0.1), rgba(255, 107, 107, 0.05));
    border-color: var(--danger);
}

.status-processing {
    background: linear-gradient(135deg, var(--warning), #ffed4e);
    color: var(--primary);
    border: 1px solid var(--warning);
}

.status-shipped {
    background: linear-gradient(135deg, var(--info), #33c3f0);
    color: var(--primary);
    border: 1px solid var(--info);
}

.status-delivered {
    background: linear-gradient(135deg, var(--success), #7fffd4);
    color: var(--primary);
    border: 1px solid var(--success);
}

.status-cancelled {
    background: linear-gradient(135deg, var(--danger), #ff8a8a);
    color: var(--light);
    border: 1px solid var(--danger);
}

.status-pending {
    background: linear-gradient(135deg, var(--grey), var(--light-grey));
    color: var(--primary);
    border: 1px solid var(--grey);
}

/* Responsive modal dialog */
@media (min-width: 768px) {
    .modal-dialog {
        max-width: 750px;
    }
}

/* Responsive Design */
@media (max-width: 992px) {
    .welcome-text {
        display: none;
    }
    
    .order-meta {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .sidebar {
        width: 0;
        transform: translateX(-100%);
    }
    
    .sidebar.active {
        width: var(--sidebar-width);
        transform: translateX(0);
    }
    
    .sidebar-close {
        display: block;
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .toggle-sidebar {
        display: block;
    }
    
    .navbar-title {
        display: none;
    }
    
    .order-filters {
        flex-direction: column;
        gap: 15px;
    }
    
    .order-search {
        max-width: 100%;
    }
    
    .orders-table-container {
        overflow-x: auto;
    }
    
    .orders-table {
        min-width: 800px;
    }
    
    .orders-container {
        padding: 20px;
    }
    
    .modal-dialog {
        margin: 20px;
        max-width: none;
    }
    
    .modal-content {
        padding: 20px;
    }
}
    </style>
<body>
      <!-- Sidebar -->
      <aside class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-logo">
                <i class="fas fa-microchip"></i>
                Tech<span>Hub</span>
            </a>
            <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-title">Main</div>
            <a href="dashboard.php" class="active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="orders_admin.php">
                <i class="fas fa-shopping-cart"></i> Orders
                <span class="notification-badge">5</span>
            </a>
            <a href="payment-history.php">
                <i class="fas fa-credit-card"></i> Payment History
            </a>
            
            <div class="menu-title">Inventory</div>
            <a href="products.php">
                <i class="fas fa-cube"></i> Products & Categories
            </a>
            <a href="stock.php">
                <i class="fas fa-warehouse"></i> Stock Management
            </a>
            
            <div class="menu-title">Users</div>
            <a href="users.php">
                <i class="fas fa-users"></i> User Management
            </a>
            
            <div class="menu-title">Reports & Settings</div>
            <a href="reports.php">
                <i class="fas fa-chart-line"></i> Reports & Analytics
            </a>
        </div>
    </aside>
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="top-navbar">
            <div class="nav-left">
                <button class="toggle-sidebar" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <span class="navbar-title">Order Management</span>
                <div class="welcome-text">Welcome, <strong><?php echo htmlspecialchars($admin['fullname']); ?></strong>!</div>
            </div>
            
            <div class="navbar-actions">
                <!--<a href="notifications.php" class="nav-link">
                    <i class="fas fa-bell"></i>
                    <span class="notification-count">3</span>-->
                </a>
                <!-- <a href="messages.php" class="nav-link">
                    <i class="fas fa-envelope"></i>
                    <span class="notification-count">5</span>
                </a> -->
                
                <div class="admin-dropdown" id="adminDropdown">
                    <div class="admin-profile">
                        <div class="admin-avatar-container">
                            <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Admin" class="admin-avatar">
                        </div>
                        <div class="admin-info">
                            <span class="admin-name"><?php echo htmlspecialchars($admin['fullname']); ?></span>
                            <span class="admin-role"><?php echo htmlspecialchars($admin['role']); ?></span>
                        </div>
                    </div>
                    
                    <div class="admin-dropdown-content" id="adminDropdownContent">
                        <div class="admin-dropdown-header">
                            <div class="admin-dropdown-avatar-container">
                                <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Admin" class="admin-dropdown-avatar">
                            </div>
                            <div class="admin-dropdown-info">
                                <span class="admin-dropdown-name"><?php echo htmlspecialchars($admin['fullname']); ?></span>
                                <span class="admin-dropdown-role"><?php echo htmlspecialchars($admin['role']); ?></span>
                            </div>
                        </div>
                        <div class="admin-dropdown-user">
                            <h4 class="admin-dropdown-user-name"><?php echo htmlspecialchars($admin['fullname']); ?></h4>
                            <p class="admin-dropdown-user-email"><?php echo htmlspecialchars($admin['email']); ?></p>
                        </div>
                        <a href="profileAdmin.php"><i class="fas fa-user"></i> Profile Settings</a>
                       
                        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </nav>
        <!-- Orders Container -->
        <div class="orders-container">
            <div class="page-header">
                <h1 class="page-title">Order Management</h1>
            </div>
            
            <?php if (!empty($statusMessage)): ?>
                <div class="alert alert-<?php echo $statusClass; ?>">
                    <?php echo htmlspecialchars($statusMessage); ?>
                </div>
            <?php endif; ?>
            
            <div class="order-actions-top" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div class="filter-group">
                    <span class="filter-label">Status:</span>
                    <select class="filter-select" id="filterStatus">
                        <option value="all">All Orders</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="export-container">
    <a href="orders_admin.php?export=pdf" class="export-button">
        <i class="fas fa-file-export"></i> Export All Orders (PDF)
    </a>
</div>

            </div>
            
            <div class="order-search">
                <input type="text" class="search-input" id="orderSearch" placeholder="Search orders by ID, customer name, or status...">
            </div>
            
            <div class="orders-table-container">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders !== false): ?>
                            <?php foreach ($orders->transaction as $order): ?>
                                <tr class="order-row" data-status="<?php echo strtolower((string)$order->status); ?>">
                                    <td class="order-id"><?php echo htmlspecialchars((string)$order->transaction_id); ?></td>
                                    <td>
                                        <?php if (isset($order->shipping_info->fullname)): ?>
                                            <?php echo htmlspecialchars((string)$order->shipping_info->fullname); ?>
                                        <?php else: ?>
                                            <em>Customer Info Pending</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars((string)$order->transaction_date); ?></td>
                                    <td>₱<?php echo number_format((float)$order->total_amount, 2); ?></td>
                                    <td>
                                        <span class="order-status status-<?php echo strtolower((string)$order->status); ?>">
                                            <?php echo htmlspecialchars(ucfirst((string)$order->status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="order-actions">
                                            <button class="btn btn-sm btn-view" onclick="viewOrder('<?php echo htmlspecialchars((string)$order->transaction_id); ?>')">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-sm btn-change-status" 
                                                    onclick="changeStatus('<?php echo htmlspecialchars((string)$order->transaction_id); ?>', '<?php echo htmlspecialchars((string)$order->status); ?>')">
                                                <i class="fas fa-exchange-alt"></i> Status
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No orders found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="pagination">
                <a href="#" class="pagination-link">&laquo;</a>
                <a href="#" class="pagination-link active">1</a>
                <a href="#" class="pagination-link">2</a>
                <a href="#" class="pagination-link">3</a>
                <a href="#" class="pagination-link">&raquo;</a>
            </div>
        </div>
    </div>
    
    <!-- View Order Modal -->
    <div class="modal" id="viewOrderModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Order Details</h3>
                    <button class="modal-close" onclick="closeViewModal()">&times;</button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <!-- Order details will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button class="btn" onclick="closeViewModal()">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Change Status Modal -->
    <div class="modal" id="changeStatusModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Change Order Status</h3>
                    <button class="modal-close" onclick="closeStatusModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post" action="" class="status-form" id="statusForm">
                        <input type="hidden" name="transaction_id" id="statusOrderId">
                        <input type="hidden" name="previous_status" id="previousStatus">
                        
                        <div class="form-group">
                            <label for="current_status">Current Status:</label>
                            <input type="text" id="current_status" readonly class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_status">Select New Status:</label>
                            <select name="new_status" id="new_status" required class="form-control">
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        
                        <p class="warning-text" id="statusWarning" style="display: none; color: #856404; background-color: rgba(255, 193, 7, 0.15); padding: 10px; border-radius: 4px;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Changing to "Shipped" will update product inventory. This action cannot be undone. Please confirm.
                        </p>
                        
                        <button type="submit" name="update_status" class="btn btn-primary" style="margin-top: 15px; background: #4e73df; color: white; border: none; padding: 10px 15px; border-radius: 4px;">Update Status</button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn" onclick="closeStatusModal()">Cancel</button>
                </div>
            </div>
        </div>
    </div>


    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Admin dropdown toggle
        const adminDropdown = document.getElementById('adminDropdown');
        const adminDropdownContent = document.getElementById('adminDropdownContent');
        
        adminDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            adminDropdown.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!adminDropdown.contains(e.target)) {
                adminDropdown.classList.remove('show');
            }
        });
        
        // Sidebar toggle for responsive design
        const toggleSidebar = document.getElementById('toggleSidebar');
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebar = document.querySelector('.sidebar');
        
        toggleSidebar.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
        sidebarClose.addEventListener('click', function() {
            sidebar.classList.remove('active');
        });
    });
    </script>
   
    
    <script>
        // Toggle admin dropdown menu
        document.getElementById('adminDropdown').addEventListener('click', function() {
            document.getElementById('adminDropdownContent').classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        window.addEventListener('click', function(event) {
            if (!event.target.matches('.admin-profile') && !event.target.closest('.admin-profile')) {
                var dropdown = document.getElementById('adminDropdownContent');
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            }
        });
        
        // Toggle sidebar on mobile
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        document.getElementById('sidebarClose').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.remove('active');
        });
        
        // Filter orders by status
        document.getElementById('filterStatus').addEventListener('change', function() {
            var status = this.value;
            var rows = document.querySelectorAll('.order-row');
            
            rows.forEach(function(row) {
                if (status === 'all' || row.getAttribute('data-status') === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Search orders
        document.getElementById('orderSearch').addEventListener('keyup', function() {
            var searchText = this.value.toLowerCase();
            var rows = document.querySelectorAll('.order-row');
            
            rows.forEach(function(row) {
                var rowText = row.textContent.toLowerCase();
                if (rowText.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // View Order Details
        function viewOrder(orderId) {
            // In a real application, you would fetch order details using AJAX
            // For this example, we'll use XML data directly
            var orderDetails = getOrderDetails(orderId);
            document.getElementById('orderDetailsContent').innerHTML = orderDetails;
            document.getElementById('viewOrderModal').classList.add('show');
        }
        
        function getOrderDetails(orderId) {
            // In a production environment, this would be an AJAX call
            // For this example, we'll construct the HTML directly from XML data
            
            var orders = <?php echo json_encode(($orders !== false) ? $orders->asXML() : ''); ?>;
            var xmlDoc = new DOMParser().parseFromString(orders, "text/xml");
            var transactions = xmlDoc.getElementsByTagName("transaction");
            var orderHTML = '<p>Order not found</p>';
            
            for (var i = 0; i < transactions.length; i++) {
                var transaction = transactions[i];
                var transId = transaction.getElementsByTagName("transaction_id")[0].textContent;

                if (transId === orderId) {
                    // Basic order info
                    var status = transaction.getElementsByTagName("status")[0].textContent;
                    var date = transaction.getElementsByTagName("transaction_date")[0].textContent;
                    var payment = transaction.getElementsByTagName("payment_method")[0].textContent;
                    var subtotal = parseFloat(transaction.getElementsByTagName("subtotal")[0].textContent).toFixed(2);
                    var shippingFee = parseFloat(transaction.getElementsByTagName("shipping_fee")[0].textContent || "0").toFixed(2);
                    var totalAmount = parseFloat(transaction.getElementsByTagName("total_amount")[0].textContent).toFixed(2);
                    
                    // Customer info
                    var shippingInfo = transaction.getElementsByTagName("shipping_info")[0];
                    var customerName = shippingInfo.getElementsByTagName("fullname")[0] ? shippingInfo.getElementsByTagName("fullname")[0].textContent : "Not provided";
                    var customerEmail = shippingInfo.getElementsByTagName("email")[0] ? shippingInfo.getElementsByTagName("email")[0].textContent : "Not provided";
                    var customerPhone = shippingInfo.getElementsByTagName("phone")[0] ? shippingInfo.getElementsByTagName("phone")[0].textContent : "Not provided";
                    var address = shippingInfo.getElementsByTagName("address")[0] ? shippingInfo.getElementsByTagName("address")[0].textContent : "";
                    var city = shippingInfo.getElementsByTagName("city")[0] ? shippingInfo.getElementsByTagName("city")[0].textContent : "";
                    var postalCode = shippingInfo.getElementsByTagName("postal_code")[0] ? shippingInfo.getElementsByTagName("postal_code")[0].textContent : "";
                    var shippingAddress = address + ", " + city + ", " + postalCode;
                    
                    // Order items
                    var items = transaction.getElementsByTagName("items")[0];
                    var itemNodes = items.getElementsByTagName("item");
                    
                    var itemsHTML = '<table class="items-table">' +
                                    '<thead>' +
                                    '<tr>' +
                                    '<th>Product</th>' +
                                    '<th>Size</th>' +
                                    '<th>Color</th>' +
                                    '<th>Price</th>' +
                                    '<th>Qty</th>' +
                                    '<th>Total</th>' +
                                    '</tr>' +
                                    '</thead>' +
                                    '<tbody>';
                                    
                    for (var j = 0; j < itemNodes.length; j++) {
                        var item = itemNodes[j];
                        var productName = item.getElementsByTagName("product_name")[0].textContent;
                        var size = item.getElementsByTagName("size")[0].textContent;
                        var color = item.getElementsByTagName("color")[0].textContent;
                        var price = parseFloat(item.getElementsByTagName("price")[0].textContent).toFixed(2);
                        var quantity = item.getElementsByTagName("quantity")[0].textContent;
                        var itemTotal = parseFloat(price * quantity).toFixed(2);
                        
                        itemsHTML += '<tr>' +
                                    '<td>' + productName + '</td>' +
                                    '<td>' + size + '</td>' +
                                    '<td>' + color + '</td>' +
                                    '<td>₱' + price + '</td>' +
                                    '<td>' + quantity + '</td>' +
                                    '<td>₱' + itemTotal + '</td>' +
                                    '</tr>';
                    }
                    
                    itemsHTML += '</tbody></table>';
                    
                    // Construct the final HTML
                    orderHTML = '<div class="order-details">' +
                                '<div class="order-meta">' +
                                  '<div class="order-meta-item">' +
                                    '<div class="order-meta-label">Order ID</div>' +
                                    '<div class="order-meta-value">' + orderId + '</div>' +
                                  '</div>' +
                                  '<div class="order-meta-item">' +
                                    '<div class="order-meta-label">Date</div>' +
                                    '<div class="order-meta-value">' + date + '</div>' +
                                  '</div>' +
                                  '<div class="order-meta-item">' +
                                    '<div class="order-meta-label">Status</div>' +
                                    '<div class="order-meta-value"><span class="order-status status-' + status.toLowerCase() + '">' + status + '</span></div>' +
                                  '</div>' +
                                  '<div class="order-meta-item">' +
                                    '<div class="order-meta-label">Payment Method</div>' +
                                    '<div class="order-meta-value">' + payment + '</div>' +
                                  '</div>' +
                                '</div>' +
                                
                                '<div class="customer-info">' +
                                  '<h5>Customer Information</h5>' +
                                  '<div class="customer-details">' +
                                    '<p><strong>Name:</strong> ' + customerName + '</p>' +
                                    '<p><strong>Email:</strong> ' + customerEmail + '</p>' +
                                    '<p><strong>Phone:</strong> ' + customerPhone + '</p>' +
                                    '<p><strong>Shipping Address:</strong> ' + shippingAddress + '</p>' +
                                  '</div>' +
                                '</div>' +
                                
                                '<div class="items-list">' +
                                  '<h5>Order Items</h5>' +
                                  itemsHTML +
                                '</div>' +
                                
                                '<div class="order-summary" style="background-color: #f9f9f9; padding: 15px; border-radius: 5px;">' +
                                  '<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">' +
                                    '<span>Subtotal:</span>' +
                                    '<span>₱' + subtotal + '</span>' +
                                  '</div>' +
                                  '<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">' +
                                    '<span>Shipping Fee:</span>' +
                                    '<span>₱' + shippingFee + '</span>' +
                                  '</div>' +
                                  '<div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.1em; margin-top: 10px; padding-top: 10px; border-top: 1px solid #e0e0e0;">' +
                                    '<span>Total:</span>' +
                                    '<span>₱' + totalAmount + '</span>' +
                                  '</div>' +
                                '</div>' +
                              '</div>';
                              
                    break;
                }
            }
            
            return orderHTML;
        }
        
        function closeViewModal() {
            document.getElementById('viewOrderModal').classList.remove('show');
        }
        
        // Change Order Status
        function changeStatus(orderId, currentStatus) {
            document.getElementById('statusOrderId').value = orderId;
            document.getElementById('current_status').value = currentStatus;
            document.getElementById('previousStatus').value = currentStatus;
            
            var statusSelect = document.getElementById('new_status');
            for (var i = 0; i < statusSelect.options.length; i++) {
                if (statusSelect.options[i].value.toLowerCase() === currentStatus.toLowerCase()) {
                    statusSelect.options[i].selected = true;
                    break;
                }
            }
            
            document.getElementById('changeStatusModal').classList.add('show');
            
            // Add warning when selecting 'shipped' status
            document.getElementById('new_status').addEventListener('change', function() {
                var warningEl = document.getElementById('statusWarning');
                if (this.value === 'shipped' && currentStatus.toLowerCase() !== 'shipped') {
                    warningEl.style.display = 'block';
                } else {
                    warningEl.style.display = 'none';
                }
            });
        }
        
        function closeStatusModal() {
            document.getElementById('changeStatusModal').classList.remove('show');
        }
        
        // Close modals when clicking outside of them
        window.addEventListener('click', function(event) {
            var viewModal = document.getElementById('viewOrderModal');
            var statusModal = document.getElementById('changeStatusModal');
            
            if (event.target === viewModal) {
                viewModal.classList.remove('show');
            }
            
            if (event.target === statusModal) {
                statusModal.classList.remove('show');
            }
        });
        
        // Initialize any UI elements that need it
        document.addEventListener('DOMContentLoaded', function() {
            // Any initialization code would go here
            
            // Make status badges visually distinct
            var statusElements = document.querySelectorAll('.order-status');
            statusElements.forEach(function(el) {
                var status = el.classList[1];
                
                // Apply different styling based on status
                switch(status) {
                    case 'status-pending':
                        el.style.backgroundColor = '#e2e3e5';
                        el.style.color = '#383d41';
                        break;
                    case 'status-processing':
                        el.style.backgroundColor = '#ffeeba';
                        el.style.color = '#856404';
                        break;
                    case 'status-shipped':
                        el.style.backgroundColor = '#b8daff';
                        el.style.color = '#004085';
                        break;
                    case 'status-delivered':
                        el.style.backgroundColor = '#c3e6cb';
                        el.style.color = '#155724';
                        break;
                    case 'status-cancelled':
                        el.style.backgroundColor = '#f5c6cb';
                        el.style.color = '#721c24';
                        break;
                }
            });
        });
    </script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Admin dropdown toggle
        const adminDropdown = document.getElementById('adminDropdown');
        const adminDropdownContent = document.getElementById('adminDropdownContent');
        
        adminDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            adminDropdown.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!adminDropdown.contains(e.target)) {
                adminDropdown.classList.remove('show');
            }
        });
        
        // Sidebar toggle for responsive design
        const toggleSidebar = document.getElementById('toggleSidebar');
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebar = document.querySelector('.sidebar');
        
        toggleSidebar.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
        sidebarClose.addEventListener('click', function() {
            sidebar.classList.remove('active');
        });
    });
    </script>
</body>
</html>