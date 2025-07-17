<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include your environment-aware database connection
include 'db_connect.php';

// Initialize variables
$error = '';
$username_email = '';

// Check if user is logged in
$loggedIn = isset($_SESSION['user_id']);
$user = null;

// If user is logged in, fetch their information
if ($loggedIn) {
    // Prepare and execute query to get user details
    $stmt = $conn->prepare("SELECT id, fullname, username, email, profile_image FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    // If user exists, store their details
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
    }

    // Close statement and connection
    $stmt->close();
    $conn->close();
}



// Function to load cart items from XML file
function loadCartItems()
{
    $cartItems = [];
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        return $cartItems;
    }
    
    $userId = $_SESSION['user_id'];

    if (file_exists('cart.xml')) {
        $xml = simplexml_load_file('cart.xml');
        
        if ($xml) {
            foreach ($xml->item as $item) {
                if ((int) $item->user_id == $userId) {
                    $cartItems[] = [
                        'id' => (string) $item->id,
                        'product_id' => (string) $item->product_id,
                        'user_id' => (string) $item->user_id,
                        'product_name' => (string) $item->product_name,
                        'price' => (float) $item->price,
                        'quantity' => (int) $item->quantity,
                        'color' => (string) $item->color,
                        'size' => (string) $item->size,
                        'image' => (string) $item->image
                    ];
                }
            }
        }
    }

    return $cartItems;
}

// Process remove item request
if (isset($_POST['remove_item']) && isset($_POST['item_id'])) {
    $itemIdToRemove = $_POST['item_id'];

    if (file_exists('cart.xml')) {
        $xml = simplexml_load_file('cart.xml');

        $i = 0;
        $indexToRemove = -1;

        foreach ($xml->item as $item) {
            if ((string) $item->id == $itemIdToRemove && (int) $item->user_id == $_SESSION['user_id']) {
                $indexToRemove = $i;
                break;
            }
            $i++;
        }

        if ($indexToRemove >= 0) {
            unset($xml->item[$indexToRemove]);
            $xml->asXML('cart.xml');

            // Update cart count in session
            if (isset($_SESSION['cart_count']) && $_SESSION['cart_count'] > 0) {
                $_SESSION['cart_count']--;
            }

            // Redirect to prevent form resubmission
            header("Location: cart.php?removed=true");
            exit();
        }
    }
}

// Process multiple items deletion
if (isset($_POST['remove_selected']) && isset($_POST['selected_items'])) {
    $selectedItems = $_POST['selected_items'];

    if (file_exists('cart.xml') && !empty($selectedItems)) {
        $xml = simplexml_load_file('cart.xml');
        $itemsToRemove = [];

        // Find all items to remove
        $i = 0;
        foreach ($xml->item as $item) {
            if (in_array((string) $item->id, $selectedItems) && (int) $item->user_id == $_SESSION['user_id']) {
                $itemsToRemove[] = $i;
            }
            $i++;
        }

        // Remove items in reverse order to avoid index issues
        rsort($itemsToRemove);
        foreach ($itemsToRemove as $index) {
            unset($xml->item[$index]);
        }

        $xml->asXML('cart.xml');

        // Update cart count in session
        if (isset($_SESSION['cart_count'])) {
            $_SESSION['cart_count'] = max(0, $_SESSION['cart_count'] - count($itemsToRemove));
        }

        // Redirect to prevent form resubmission
        header("Location: cart.php?removed=true");
        exit();
    }
}

// Process update quantity request
if (isset($_POST['update_quantity']) && isset($_POST['item_id']) && isset($_POST['quantity'])) {
    $itemId = $_POST['item_id'];
    $newQuantity = (int) $_POST['quantity'];

    if ($newQuantity > 0 && file_exists('cart.xml')) {
        $xml = simplexml_load_file('cart.xml');

        foreach ($xml->item as $item) {
            if ((string) $item->id == $itemId && (int) $item->user_id == $_SESSION['user_id']) {
                $item->quantity = $newQuantity;
                break;
            }
        }

        $xml->asXML('cart.xml');

        // Redirect to prevent form resubmission
        header("Location: cart.php?updated=true");
        exit();
    }
}

// Process checkout
if (isset($_POST['checkout']) && isset($_POST['selected_for_checkout'])) {
    $selectedItems = $_POST['selected_for_checkout'];

    if (!empty($selectedItems) && file_exists('cart.xml')) {
        $xml = simplexml_load_file('cart.xml');
        $checkoutItems = [];
        $totalAmount = 0;

        // Find items for checkout and calculate total
        foreach ($xml->item as $item) {
            if (in_array((string) $item->id, $selectedItems) && (int) $item->user_id == $_SESSION['user_id']) {
                $itemTotal = (float) $item->price * (int) $item->quantity;
                $totalAmount += $itemTotal;

                $checkoutItems[] = [
                    'id' => (string) $item->id,
                    'product_id' => (string) $item->product_id,
                    'product_name' => (string) $item->product_name,
                    'price' => (float) $item->price,
                    'quantity' => (int) $item->quantity,
                    'color' => (string) $item->color,
                    'size' => (string) $item->size,
                    'subtotal' => $itemTotal
                ];
            }
        }

        // Add shipping fee if applicable
        $shippingFee = ($totalAmount >= 4000) ? 0 : 100;
        $finalTotal = $totalAmount + $shippingFee;

        // Generate transaction ID
        $transactionId = 'TRX-' . strtoupper(uniqid());

        // Create transaction record in transaction.xml
        if (!file_exists('transaction.xml')) {
            $transactionXml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><transactions></transactions>');
        } else {
            $transactionXml = simplexml_load_file('transaction.xml');
        }

        $transaction = $transactionXml->addChild('transaction');
        $transaction->addChild('transaction_id', $transactionId);
        $transaction->addChild('user_id', $_SESSION['user_id']);
        $transaction->addChild('transaction_date', date('Y-m-d H:i:s'));
        $transaction->addChild('status', 'pending');
        $transaction->addChild('payment_method', 'pending');
        $transaction->addChild('subtotal', $totalAmount);
        $transaction->addChild('shipping_fee', $shippingFee);
        $transaction->addChild('total_amount', $finalTotal);

        $items = $transaction->addChild('items');
        foreach ($checkoutItems as $item) {
            $itemNode = $items->addChild('item');
            $itemNode->addChild('product_id', $item['product_id']);
            $itemNode->addChild('product_name', $item['product_name']);
            $itemNode->addChild('price', $item['price']);
            $itemNode->addChild('quantity', $item['quantity']);
            $itemNode->addChild('color', $item['color']);
            $itemNode->addChild('size', $item['size']);
            $itemNode->addChild('subtotal', $item['subtotal']);
        }

        $transactionXml->asXML('transaction.xml');

        // Store transaction ID in session for checkout page
        $_SESSION['current_transaction'] = $transactionId;
        $_SESSION['checkout_items'] = $selectedItems;

        // Redirect to checkout page
        header("Location: checkout.php");
        exit();
    }
}

// Load cart items
$cartItems = loadCartItems();

// Calculate totals for all items
$allSubtotal = 0;
$allTotalItems = 0;

foreach ($cartItems as $item) {
    $allSubtotal += $item['price'] * $item['quantity'];
    $allTotalItems += $item['quantity'];
}

// Calculate totals for selected items (will be updated via JavaScript)
$selectedSubtotal = 0;
$selectedTotalItems = 0;
$shippingFee = 100; // Default shipping fee (₱100)

// Free shipping for orders over ₱4,000 (will be updated via JavaScript)
if ($selectedSubtotal >= 4000) {
    $shippingFee = 0;
}

$selectedTotal = $selectedSubtotal + $shippingFee;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - TechHub</title>    <link rel="icon" href="images/hf.png">
    <link rel="icon" href="images/hf.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/usershop.css">
<style>
/* Cart Page Styles - TechHub Theme */
:root {
            /* Main Color Palette */
            --primary: #0a0e1a;
            --secondary: #00d4ff;
            --accent: #0099cc;
            --light: #ccd6f6;
            --dark: #0a0e1a;
            --grey: #8892b0;
            --light-grey: #1a2332;
            --border-color: rgba(0, 212, 255, 0.2);
            --backdrop-blur: rgba(10, 14, 26, 0.95);
            --price-color: #00d4ff;
            --sale-color: #00d4ff;
            
            /* Background Gradients */
            --bg-gradient: linear-gradient(135deg, #0a0e1a 0%, #1a2332 100%);
            --card-bg: rgba(26, 35, 50, 0.6);
            --hover-bg: rgba(0, 212, 255, 0.1);
            
            /* Additional Colors */
            --success-color: #00cc88;
            --warning-color: #ffaa00;
            --danger-color: #ff4444;
            --shadow-light: 0 2px 8px rgba(0, 0, 0, 0.3);
            --shadow-medium: 0 4px 16px rgba(0, 0, 0, 0.4);
            --shadow-heavy: 0 8px 32px rgba(0, 0, 0, 0.5);
            --border-radius: 12px;
            --border-radius-small: 8px;
            --transition: all 0.3s ease;
        }

     
        /* Cart Section */
        .cart-section {
            padding: 20px 0 40px;
            background: var(--bg-gradient);
            min-height: calc(100vh - 200px);
            color: var(--light);
        }

        .cart-section .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .cart-section h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--light);
            margin-bottom: 20px;
            text-align: center;
            position: relative;
        }

        .cart-section h1::after {
            content: "";
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, var(--secondary), var(--accent));
            border-radius: 2px;
            box-shadow: 0 0 10px var(--secondary);
        }

        /* Empty Cart State */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            max-width: 500px;
            margin: 0 auto;
        }

        .empty-cart i {
            font-size: 3rem;
            color: var(--grey);
            margin-bottom: 20px;
            opacity: 0.7;
        }

        .empty-cart h2 {
            font-size: 1.5rem;
            color: var(--light);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .empty-cart p {
            color: var(--grey);
            font-size: 1rem;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .btn-primary {
            display: inline-block;
            background: linear-gradient(135deg, var(--secondary), var(--accent));
            color: var(--dark);
            padding: 15px 25px;
            border-radius: var(--border-radius-small);
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            box-shadow: var(--shadow-light);
            border: none;
            cursor: pointer;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            background: linear-gradient(135deg, var(--accent), var(--secondary));
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.3);
        }

        /* Cart Layout - Mobile First */
        .cart-layout {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 20px;
        }

        /* Cart Header */
        .cart-header {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            padding: 15px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .select-all {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .select-all input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--secondary);
            cursor: pointer;
        }

        .select-all label {
            font-weight: 600;
            color: var(--light);
            cursor: pointer;
            font-size: 1rem;
        }

        .cart-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn-outline-danger {
            background: transparent;
            color: var(--danger-color);
            border: 2px solid var(--danger-color);
            padding: 8px 15px;
            border-radius: var(--border-radius-small);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }

        .btn-outline-danger:hover {
            background: var(--danger-color);
            color: var(--light);
            transform: translateY(-1px);
            box-shadow: var(--shadow-light);
        }

        /* Cart Items Container */
        .cart-items {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        /* Individual Cart Item - Mobile Optimized */
        .cart-item {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .cart-item::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--secondary), var(--accent));
            opacity: 0;
            transition: var(--transition);
        }

        .cart-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            background: var(--hover-bg);
            border-color: var(--secondary);
        }

        .cart-item:hover::before {
            opacity: 1;
        }

        /* Item Header - Selection and Actions */
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-select {
            display: flex;
            align-items: center;
        }

        .item-checkbox {
            width: 18px;
            height: 18px;
            accent-color: var(--secondary);
            cursor: pointer;
        }

        .remove-btn {
            background: transparent;
            border: 2px solid var(--danger-color);
            color: var(--danger-color);
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .remove-btn:hover {
            background: var(--danger-color);
            color: var(--light);
            transform: scale(1.1);
        }

        /* Item Body */
        .item-body {
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }

        /* Item Image */
        .item-image {
            width: 100px;
            height: 100px;
            border-radius: var(--border-radius-small);
            overflow: hidden;
            box-shadow: var(--shadow-light);
            flex-shrink: 0;
            border: 1px solid var(--border-color);
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }

        .cart-item:hover .item-image img {
            transform: scale(1.05);
        }

        /* Item Details */
        .item-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .item-details h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--light);
            margin: 0;
            line-height: 1.3;
        }

        .item-variation {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 5px 0;
        }

        .item-variation span {
            font-size: 0.8rem;
            color: var(--grey);
            background: var(--light-grey);
            padding: 3px 8px;
            border-radius: 15px;
            font-weight: 500;
            border: 1px solid var(--border-color);
        }

        .item-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--price-color);
            margin-top: 5px;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }

        /* Item Footer - Quantity and Subtotal */
        .item-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
            border-top: 1px solid var(--border-color);
        }

        /* Quantity Controls */
        .item-quantity {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            background: var(--light-grey);
            border-radius: var(--border-radius-small);
            overflow: hidden;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
        }

        .quantity-btn {
            width: 35px;
            height: 35px;
            background: var(--light-grey);
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            color: var(--light);
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-btn:hover {
            background: var(--secondary);
            color: var(--dark);
        }

        .quantity-input {
            width: 50px;
            height: 35px;
            border: none;
            text-align: center;
            font-size: 1rem;
            font-weight: 600;
            background: var(--light-grey);
            color: var(--light);
        }

        .update-btn {
            background: var(--secondary);
            color: var(--dark);
            border: none;
            padding: 6px 10px;
            border-radius: var(--border-radius-small);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.8rem;
            font-weight: 600;
        }

        .update-btn:hover {
            background: var(--accent);
            transform: translateY(-1px);
            box-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }

        /* Item Subtotal */
        .item-subtotal {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--price-color);
            text-align: right;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }

        /* Cart Summary */
        .cart-summary {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-medium);
            padding: 20px;
            border: 1px solid var(--border-color);
            order: -1;
        }

        .cart-summary h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--light);
            margin-bottom: 20px;
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
        }

        .summary-row:last-of-type {
            border-bottom: none;
        }

        .summary-row span:first-child {
            color: var(--grey);
            font-weight: 500;
        }

        .summary-row span:last-child {
            font-weight: 600;
            color: var(--light);
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            margin-top: 10px;
            border-top: 2px solid var(--border-color);
            font-size: 1.1rem;
            font-weight: 700;
        }

        .total-amount {
            color: var(--price-color);
            font-size: 1.3rem;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }

        /* Free Shipping Notice */
        .free-shipping-notice {
            background: linear-gradient(135deg, var(--success-color), #00aa66);
            color: var(--light);
            padding: 12px;
            border-radius: var(--border-radius-small);
            margin: 15px 0;
            text-align: center;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.9rem;
            box-shadow: 0 0 15px rgba(0, 204, 136, 0.3);
        }

        .free-shipping-notice i {
            font-size: 1rem;
        }

        /* Shipping Threshold */
        .shipping-threshold {
            margin: 15px 0;
            padding: 15px;
            background: var(--light-grey);
            border-radius: var(--border-radius-small);
            border: 1px solid var(--border-color);
        }

        .threshold-bar {
            width: 100%;
            height: 6px;
            background: var(--light-grey);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 8px;
            border: 1px solid var(--border-color);
        }

        .threshold-progress {
            height: 100%;
            background: linear-gradient(90deg, var(--secondary), var(--success-color));
            transition: width 0.5s ease;
            border-radius: 3px;
            box-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }

        .shipping-threshold p {
            font-size: 0.8rem;
            color: var(--grey);
            text-align: center;
            margin: 0;
            font-weight: 500;
        }

        .shipping-threshold span {
            color: var(--secondary);
            font-weight: 700;
        }

        /* Checkout Button */
        .btn-checkout {
            width: 100%;
            background: linear-gradient(135deg, var(--secondary), var(--accent));
            color: var(--dark);
            border: none;
            padding: 15px;
            border-radius: var(--border-radius-small);
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            margin: 15px 0 10px;
            box-shadow: var(--shadow-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-checkout:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            background: linear-gradient(135deg, var(--accent), var(--secondary));
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.3);
        }

        .btn-checkout:disabled {
            background: var(--grey);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-continue-shopping {
            display: block;
            text-align: center;
            color: var(--secondary);
            text-decoration: none;
            font-weight: 600;
            padding: 10px;
            border: 2px solid var(--secondary);
            border-radius: var(--border-radius-small);
            transition: var(--transition);
        }

        .btn-continue-shopping:hover {
            background: var(--secondary);
            color: var(--dark);
            box-shadow: 0 0 15px rgba(0, 212, 255, 0.3);
        }

        /* Tablet and Desktop Responsive */
        @media (min-width: 768px) {
            .cart-section h1 {
                font-size: 2rem;
                margin-bottom: 30px;
            }

            .cart-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 20px;
            }

            .cart-actions {
                justify-content: flex-end;
            }

            .cart-item {
                padding: 20px;
            }

            .item-image {
                width: 120px;
                height: 120px;
            }

            .item-body {
                gap: 20px;
            }

            .item-details h3 {
                font-size: 1.2rem;
            }

            .cart-summary {
                padding: 25px;
            }
        }

        @media (min-width: 992px) {
            .cart-layout {
                display: grid;
                grid-template-columns: 1fr 350px;
                gap: 30px;
            }

            .cart-summary {
                position: sticky;
                top: 100px;
                order: 0;
                height: fit-content;
            }

            .cart-item {
                display: grid;
                grid-template-columns: auto 120px 1fr auto auto;
                grid-template-rows: auto auto;
                gap: 20px;
                align-items: center;
            }

            .item-header {
                grid-column: 1;
                grid-row: 1;
                justify-content: center;
            }

            .item-image {
                grid-column: 2;
                grid-row: 1 / span 2;
            }

            .item-details {
                grid-column: 3;
                grid-row: 1 / span 2;
            }

            .item-quantity {
                grid-column: 4;
                grid-row: 1 / span 2;
                align-items: center;
            }

            .item-subtotal {
                grid-column: 5;
                grid-row: 1;
                text-align: center;
            }

            .remove-btn {
                grid-column: 5;
                grid-row: 2;
                justify-self: center;
            }

            .item-footer {
                display: none;
            }
        }

        @media (min-width: 1200px) {
            .cart-layout {
                grid-template-columns: 1fr 400px;
                gap: 40px;
            }

            .cart-section h1 {
                font-size: 2.5rem;
                margin-bottom: 40px;
            }

            .cart-summary {
                padding: 30px;
            }
        }

        /* Animation for cart updates */
        @keyframes cartItemUpdate {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }

        .cart-item.updating {
            animation: cartItemUpdate 0.3s ease;
        }

        /* Loading states */
        .loading {
            opacity: 0.6;
            pointer-events: none;
            position: relative;
        }

        .loading::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-top: 2px solid var(--secondary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Success states */
        .success-message {
            background: var(--success-color);
            color: var(--light);
            padding: 12px;
            border-radius: var(--border-radius-small);
            margin: 15px 0;
            text-align: center;
            font-weight: 600;
            animation: slideDown 0.3s ease;
            box-shadow: 0 0 15px rgba(0, 204, 136, 0.3);
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
     /* Footer Styles */
.footer {
    background-color: var(--primary);
    color: var(--light);
    padding: 50px 0 20px;
    margin-top: 60px;
}

.footer-columns {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 40px;
    margin-bottom: 40px;
}

.footer-column h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 20px;
    color: var(--light);
}

.footer-column ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-column ul li {
    margin-bottom: 10px;
}

.footer-column ul li a {
    color: #cccccc;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.3s ease;
}

.footer-column ul li a:hover {
    color: var(--secondary);
}

.social-links {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
}

.social-links a {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    color: var(--light);
    text-decoration: none;
    transition: all 0.3s ease;
}

.social-links a:hover {
    background-color: var(--secondary);
    transform: translateY(-2px);
}

.newsletter h4 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 15px;
    color: var(--light);
}

.newsletter form {
    display: flex;
    gap: 10px;
}

.newsletter input {
    flex: 1;
    padding: 12px 15px;
    border: none;
    border-radius: 4px;
    background-color: rgba(255, 255, 255, 0.1);
    color: var(--light);
    font-size: 14px;
}

.newsletter input::placeholder {
    color: #cccccc;
}

.newsletter input:focus {
    outline: none;
    background-color: rgba(255, 255, 255, 0.2);
}

.newsletter button {
    padding: 12px 20px;
    background-color: var(--secondary);
    color: var(--light);
    border: none;
    border-radius: 4px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.newsletter button:hover {
    background-color: #005da6;
}

.footer-bottom {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.footer-bottom p {
    color: #cccccc;
    font-size: 14px;
    margin: 0;
}

.footer-links {
    display: flex;
    gap: 20px;
}

.footer-links a {
    color: #cccccc;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.3s ease;
}

.footer-links a:hover {
    color: var(--secondary);
}

        @media (min-width: 768px) {
            .footer-columns {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 992px) {
            .footer-columns {
                grid-template-columns: repeat(3, 1fr);
            }
        }
</style>
</head>

<body>

<?php
include('h.php')
?>
    <!-- Cart Section -->
    <section class="cart-section">
        <div class="container">
            <h1><i class="fas fa-shopping-cart"></i> Your Shopping Cart</h1>

            <?php if (empty($cartItems)): ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h2>Your cart is empty</h2>
                    <p>Looks like you haven't added any items to your cart yet. Start shopping to fill it up!</p>
                    <a href="usershop.php" class="btn-primary">
                        <i class="fas fa-arrow-left"></i> Continue Shopping
                    </a>
                </div>
            <?php else: ?>
                <div class="cart-layout">
                    <div class="cart-main">
                        <form action="cart.php" method="post" id="cartForm">
                            <div class="cart-header">
                                <div class="select-all">
                                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                                    <label for="selectAll">Select All Items</label>
                                </div>
                                <div class="cart-actions">
                                    <button type="submit" name="remove_selected" class="btn-outline-danger"
                                        onclick="return confirm('Are you sure you want to remove selected items?')">
                                        <i class="fas fa-trash"></i> Delete Selected
                                    </button>
                                </div>
                            </div>

                            <div class="cart-items">
                                <?php foreach ($cartItems as $item): ?>
                                    <div class="cart-item" data-id="<?php echo $item['product_id']; ?>"
                                        data-size="<?php echo $item['size']; ?>" data-color="<?php echo $item['color']; ?>">

                                        <div class="item-select">
                                            <input type="checkbox" name="selected_items[]" value="<?php echo $item['id']; ?>"
                                                class="item-checkbox" onchange="updateOrderSummary()">
                                            <input type="checkbox" name="selected_for_checkout[]" value="<?php echo $item['id']; ?>"
                                                class="checkout-checkbox" style="display: none;">
                                        </div>

                                        <div class="item-image">
                                            <img src="<?php echo htmlspecialchars($item['image']); ?>"
                                                alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                        </div>

                                        <div class="item-details">
                                            <h3><?php echo htmlspecialchars($item['product_name']); ?></h3>
                                            <div class="item-variation">
                                                <span><i class="fas fa-palette"></i> <?php echo htmlspecialchars($item['color']); ?></span>
                                                <span><i class="fas fa-ruler"></i> <?php echo htmlspecialchars($item['size']); ?></span>
                                            </div>
                                            <div class="item-price price-column">₱<?php echo number_format($item['price'], 2); ?></div>
                                        </div>

                                        <div class="item-quantity">
                                            <div class="quantity-control">
                                                <button type="button" class="quantity-btn minus"
                                                    onclick="decrementQuantity('<?php echo $item['id']; ?>')">-</button>
                                                <input type="text" id="quantity-<?php echo $item['id']; ?>"
                                                    name="quantity-<?php echo $item['id']; ?>" class="quantity-input"
                                                    value="<?php echo $item['quantity']; ?>" readonly>
                                                <button type="button" class="quantity-btn plus"
                                                    onclick="incrementQuantity('<?php echo $item['id']; ?>')">+</button>
                                            </div>

                                            <button class="update-btn" type="button" onclick="updateCartItem(this)">
                                                <i class="fas fa-sync-alt"></i> Update
                                            </button>

                                            <form action="cart.php" method="post" class="update-quantity-form"
                                                id="update-form-<?php echo $item['id']; ?>" style="display: none;">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <input type="hidden" name="quantity" id="update-quantity-<?php echo $item['id']; ?>"
                                                    value="<?php echo $item['quantity']; ?>">
                                                <input type="hidden" name="update_quantity" value="1">
                                            </form>
                                        </div>

                                        <div class="item-subtotal subtotal-column" data-price="<?php echo $item['price']; ?>"
                                            data-quantity="<?php echo $item['quantity']; ?>" data-id="<?php echo $item['id']; ?>">
                                            ₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                        </div>

                                        <div class="item-actions">
                                            <form action="cart.php" method="post" class="remove-item-form">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" name="remove_item" class="remove-btn"
                                                    onclick="return confirm('Are you sure you want to remove this item?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </form>
                    </div>

                    <div class="cart-summary">
                        <h2><i class="fas fa-receipt"></i> Order Summary</h2>
                        
                        <div class="summary-row">
                            <span>Subtotal (<span id="selected-items-count">0</span> items)</span>
                            <span id="selected-subtotal">₱0.00</span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Shipping Fee</span>
                            <span id="shipping-fee">₱100.00</span>
                        </div>
                        
                        <div class="free-shipping-notice" id="free-shipping-notice" style="display: none;">
                            <i class="fas fa-truck"></i> You've qualified for FREE shipping!
                        </div>
                        
                        <div class="shipping-threshold" id="shipping-threshold">
                            <div class="threshold-bar">
                                <div class="threshold-progress" id="threshold-progress" style="width: 0%"></div>
                            </div>
                            <p>Add <span id="amount-for-free-shipping">₱4,000.00</span> more to qualify for FREE shipping</p>
                        </div>
                        
                        <div class="summary-total">
                            <span>Total</span>
                            <span class="total-amount" id="total-amount">₱0.00</span>
                        </div>
                        
                        <button type="button" id="checkout-btn" class="btn-checkout" onclick="proceedToCheckout()" disabled>
                            <i class="fas fa-credit-card"></i> Proceed to Checkout
                        </button>
                        
                        <a href="usershop.php" class="btn-continue-shopping">
                            <i class="fas fa-arrow-left"></i> Continue Shopping
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-columns">
                <div class="footer-column">
                    <h3>Customer Support</h3>
                    <ul>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">Tech Support</a></li>
                        <li><a href="#">Warranty & Returns</a></li>
                        <li><a href="#">Product Manuals</a></li>
                        <li><a href="#">Live Chat</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>About TechHub</h3>
                    <ul>
                        <li><a href="#">Our Story</a></li>
                        <li><a href="#">Innovation Labs</a></li>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Press & Media</a></li>
                        <li><a href="#">Partner Program</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Connect With Us</h3>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                    </div>
                    <div class="newsletter">
                        <h4>Tech News & Updates</h4>
                        <form>
                            <input type="email" placeholder="Enter your email">
                            <button type="submit">Subscribe</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 TechHub. All Rights Reserved.</p>
                <div class="footer-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Cookie Policy</a>
                </div>
                <div class="payment-methods">
                    <i class="fab fa-cc-visa"></i>
                    <i class="fab fa-cc-mastercard"></i>
                    <i class="fab fa-cc-paypal"></i>
                    <i class="fab fa-cc-amex"></i>
                    <i class="fab fa-cc-apple-pay"></i>
                </div>
            </div>
        </div>
    </footer>

    <script src="js/home.js"></script>
<script>console.log('After home.js');</script>

<script>
       // Account dropdown functionality
       document.addEventListener('DOMContentLoaded', function () {
            const accountBtn = document.getElementById('accountBtn');
            const accountDropdown = document.getElementById('accountDropdown');

            if (accountBtn) {
                accountBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    accountDropdown.classList.toggle('active');
                });
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function (e) {
                if (accountDropdown && !accountDropdown.contains(e.target) && e.target !== accountBtn) {
                    accountDropdown.classList.remove('active');
                }
            });

            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const mainNav = document.getElementById('mainNav');

            if (mobileMenuToggle && mainNav) {
                mobileMenuToggle.addEventListener('click', function () {
                    mainNav.classList.toggle('active');
                });
            }
        });
</script>
    <script>
        document.querySelectorAll('.cart-item .update-button').forEach(button => {
            button.addEventListener('click', () => updateCartItem(button));
        });

        // Toggle mobile menu
        document.getElementById('mobileMenuToggle').addEventListener('click', function () {
            document.getElementById('mainNav').classList.toggle('active');
        });

        // Toggle account dropdown
        const accountBtn = document.getElementById('accountBtn');
        const accountDropdownContent = document.getElementById('accountDropdownContent');

        if (accountBtn) {
            accountBtn.addEventListener('click', function (e) {
                e.preventDefault();
                accountDropdownContent.classList.toggle('show');
            });

            // Close dropdown when clicking outside
            window.addEventListener('click', function (e) {
                if (!e.target.matches('#accountBtn') && !e.target.closest('#accountDropdownContent')) {
                    if (accountDropdownContent.classList.contains('show')) {
                        accountDropdownContent.classList.remove('show');
                    }
                }
            });
        }

        // Cart functions
        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const itemCheckboxes = document.querySelectorAll('.item-checkbox');

            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });

            updateOrderSummary();
        }

        function decrementQuantity(itemId) {
            const quantityInput = document.getElementById('quantity-' + itemId);
            let quantity = parseInt(quantityInput.value);

            if (quantity > 1) {
                quantity--;
                quantityInput.value = quantity;
                document.getElementById('update-quantity-' + itemId).value = quantity;
            }
        }

        function incrementQuantity(itemId) {
            const quantityInput = document.getElementById('quantity-' + itemId);
            let quantity = parseInt(quantityInput.value);

            quantity++;
            quantityInput.value = quantity;
            document.getElementById('update-quantity-' + itemId).value = quantity;
        }
        function updateCartItem(button) {
            const itemElement = button.closest('.cart-item');
            const productId = itemElement.getAttribute('data-id');
            const size = itemElement.getAttribute('data-size');
            const color = itemElement.getAttribute('data-color');

            const quantityInput = itemElement.querySelector('.quantity-input');
            const quantity = parseInt(quantityInput.value);

            const priceText = itemElement.querySelector('.price-column').innerText.replace(/[₱,]/g, '');
            const price = parseFloat(priceText);

            const subtotal = (quantity * price).toFixed(2);
            itemElement.querySelector('.subtotal-column').innerText = `₱${subtotal}`;

            fetch('update_cart_quantity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    productId: productId,
                    size: size,
                    color: color,
                    quantity: quantity,
                    price: price
                })
            })
                .then(response => response.text())
                .then(data => {
                    console.log(data);
                    alert("Cart updated!");
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert("Failed to update cart.");
                });
        }
        function updateQuantity(itemId) {
            document.getElementById('update-form-' + itemId).submit();
        }

        // Update order summary based on selected items
        function updateOrderSummary() {
            const itemCheckboxes = document.querySelectorAll('.item-checkbox');
            const checkoutCheckboxes = document.querySelectorAll('.checkout-checkbox');
            const subtotalElements = document.querySelectorAll('.item-subtotal');

            let selectedItemsCount = 0;
            let selectedSubtotal = 0;
            let shippingFee = 100;

            // Update checkout checkboxes to match item checkboxes
            itemCheckboxes.forEach((checkbox, index) => {
                checkoutCheckboxes[index].checked = checkbox.checked;

                if (checkbox.checked) {
                    const itemId = checkbox.value;
                    const subtotalElement = document.querySelector(`.item-subtotal[data-id="${itemId}"]`);

                    if (subtotalElement) {
                        const price = parseFloat(subtotalElement.getAttribute('data-price'));
                        const quantity = parseInt(subtotalElement.getAttribute('data-quantity'));

                        selectedItemsCount += quantity;
                        selectedSubtotal += price * quantity;
                    }
                }
            });

            // Free shipping if subtotal >= 4000
            if (selectedSubtotal >= 4000) {
                shippingFee = 0;
                document.getElementById('free-shipping-notice').style.display = 'block';
                document.getElementById('shipping-threshold').style.display = 'none';
            } else {
                document.getElementById('free-shipping-notice').style.display = 'none';
                document.getElementById('shipping-threshold').style.display = 'block';

                // Update threshold bar
                const thresholdPercentage = Math.min(100, (selectedSubtotal / 4000) * 100);
                document.getElementById('threshold-progress').style.width = `${thresholdPercentage}%`;

                // Update amount needed for free shipping
                const amountForFreeShipping = 4000 - selectedSubtotal;
                document.getElementById('amount-for-free-shipping').textContent = `₱${amountForFreeShipping.toFixed(2)}`;
            }

            // Update total
            const total = selectedSubtotal + shippingFee;

            // Update display
            document.getElementById('selected-items-count').textContent = selectedItemsCount;
            document.getElementById('selected-subtotal').textContent = `₱${selectedSubtotal.toFixed(2)}`;
            document.getElementById('shipping-fee').textContent = shippingFee > 0 ? `₱${shippingFee.toFixed(2)}` : 'FREE';
            document.getElementById('total-amount').textContent = `₱${total.toFixed(2)}`;

            // Enable/disable checkout button
            const checkoutBtn = document.getElementById('checkout-btn');
            checkoutBtn.disabled = selectedItemsCount === 0;
        }

        // Add this to the existing <script> section at the bottom of the file
function updateCartCount() {
    fetch('get_cart_count.php')
    .then(response => {
        if (!response.ok) {
            throw new Error('Failed to fetch cart count');
        }
        return response.json();
    })
    .then(data => {
        const cartCount = document.getElementById("cartCount");
        if (cartCount) {
            if (data.count !== undefined) {
                cartCount.textContent = data.count;
                console.log('Cart count updated to:', data.count);
            } else {
                console.error('Cart count data is undefined');
                cartCount.textContent = '0';
            }
        }
    })
    .catch(error => {
        console.error('Error fetching cart count:', error);
    });
}

        // Submit the form for checkout
        function proceedToCheckout() {
            // Create a new form
            const checkoutForm = document.createElement('form');
            checkoutForm.method = 'post';
            checkoutForm.action = 'cart.php';

            // Add a checkout input
            const checkoutInput = document.createElement('input');
            checkoutInput.type = 'hidden';
            checkoutInput.name = 'checkout';
            checkoutInput.value = '1';
            checkoutForm.appendChild(checkoutInput);

            // Add selected items
            const checkoutCheckboxes = document.querySelectorAll('.checkout-checkbox:checked');
            checkoutCheckboxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_for_checkout[]';
                input.value = checkbox.value;
                checkoutForm.appendChild(input);
            });

            // Submit the form
            document.body.appendChild(checkoutForm);
            checkoutForm.submit();
            
            // Update cart count
            updateCartCount();
        }

        // Initialize order summary on page load
        document.addEventListener('DOMContentLoaded', function() {
    updateOrderSummary();
    // Update cart count on page load
    updateCartCount();
});
    </script>
</body>

</html>
