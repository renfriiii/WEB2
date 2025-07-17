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

// If user is not logged in, redirect to login page
if (!$loggedIn) {
    header("Location:sign-in.php");
    exit();
}

// Check if there's a completed transaction
if (!isset($_SESSION['completed_transaction'])) {
    header("Location: cart.php");
    exit();
}

$transactionId = $_SESSION['completed_transaction'];


// Fetch user details
$stmt = $conn->prepare("SELECT id, fullname, username, email, address, phone, profile_image FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

// If user exists, store their details
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
}

$stmt->close();
$conn->close();

// Load transaction details from XML
function loadTransactionDetails($transactionId) {
    if (file_exists('transaction.xml')) {
        $xml = simplexml_load_file('transaction.xml');
        
        foreach ($xml->transaction as $transaction) {
            if ((string) $transaction->transaction_id == $transactionId && (int) $transaction->user_id == $_SESSION['user_id']) {
                return [
                    'transaction_id' => (string) $transaction->transaction_id,
                    'transaction_date' => (string) $transaction->transaction_date,
                    'status' => (string) $transaction->status,
                    'subtotal' => (float) $transaction->subtotal,
                    'shipping_fee' => (float) $transaction->shipping_fee,
                    'total_amount' => (float) $transaction->total_amount,
                    'payment_method' => (string) $transaction->payment_method,
                    'shipping_info' => parseShippingInfo($transaction->shipping_info),
                    'items' => parseTransactionItems($transaction->items)
                ];
            }
        }
    }
    
    return null;
}

function parseShippingInfo($shippingNode) {
    if (!$shippingNode) {
        return null;
    }
    
    return [
        'fullname' => (string) $shippingNode->fullname,
        'email' => (string) $shippingNode->email,
        'phone' => (string) $shippingNode->phone,
        'address' => (string) $shippingNode->address,
        'city' => (string) $shippingNode->city,
        'postal_code' => (string) $shippingNode->postal_code,
        'notes' => (string) $shippingNode->notes
    ];
}

function parseTransactionItems($itemsNode) {
    $items = [];
    
    foreach ($itemsNode->item as $item) {
        $items[] = [
            'product_id' => (string) $item->product_id,
            'product_name' => (string) $item->product_name,
            'price' => (float) $item->price,
            'quantity' => (int) $item->quantity,
            'color' => (string) $item->color,
            'size' => (string) $item->size,
            'subtotal' => (float) $item->subtotal
        ];
    }
    
    return $items;
}

// Function to format payment method display
function formatPaymentMethod($paymentMethod) {
    switch ($paymentMethod) {
        case 'cod':
            return 'Cash on Delivery';
        case 'gcash':
            return 'GCash';
        case 'paymaya':
            return 'PayMaya';
        case 'bank_transfer':
            return 'Bank Transfer';
        default:
            return ucfirst($paymentMethod);
    }
}

// Load the completed transaction
$transaction = loadTransactionDetails($transactionId);

if (!$transaction) {
    // Transaction not found, redirect to home
    header("Location: usershop.php");
    exit();
}

// Generate estimated delivery date (5-7 business days from now)
$deliveryMin = date('F d, Y', strtotime('+5 weekday'));
$deliveryMax = date('F d, Y', strtotime('+7 weekday'));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - TechHub</title>    <link rel="icon" href="images/hf.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/usershop.css">
    <link rel="stylesheet" href="style/checkout.css">
    <link rel="stylesheet" href="style/order_confirmation.css">
</head>

<body>
  <?php
  include('h.php')
  ?>

    <!-- Order Confirmation Section -->
    <section class="confirmation-section">
        <div class="container">
            <div class="checkout-header">
                <h1>Order Confirmation</h1>
                <div class="checkout-steps">
                    <div class="step completed">
                        <span class="step-number">1</span>
                        <span class="step-text">Shopping Cart</span>
                    </div>
                    <div class="step completed">
                        <span class="step-number">2</span>
                        <span class="step-text">Checkout</span>
                    </div>
                    <div class="step completed active">
                        <span class="step-number">3</span>
                        <span class="step-text">Order Confirmation</span>
                    </div>
                </div>
            </div>

            <div class="confirmation-content">
                <div class="confirmation-message">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2>Thank You for Your Order!</h2>
                    <p>Your order has been received and is now being processed. We've sent a confirmation email to <strong><?php echo htmlspecialchars($transaction['shipping_info']['email']); ?></strong> with your order details.</p>
                </div>

                <div class="order-details">
                    <div class="order-info-box">
                        <div class="order-info-item">
                            <h3>Order Number</h3>
                            <p><?php echo htmlspecialchars($transaction['transaction_id']); ?></p>
                        </div>
                        <div class="order-info-item">
                            <h3>Date</h3>
                            <p><?php echo date('F d, Y', strtotime($transaction['transaction_date'])); ?></p>
                        </div>
                        <div class="order-info-item">
                            <h3>Total</h3>
                            <p>₱<?php echo number_format($transaction['total_amount'], 2); ?></p>
                        </div>
                        <div class="order-info-item">
                            <h3>Payment Method</h3>
                            <p><?php echo formatPaymentMethod($transaction['payment_method']); ?></p>
                        </div>
                    </div>

                    <div class="delivery-info">
                        <div class="delivery-status">
                            <h3>Estimated Delivery Date</h3>
                            <p class="delivery-date"><?php echo $deliveryMin; ?> - <?php echo $deliveryMax; ?></p>
                            <div class="order-status">
                                <div class="status-line">
                                    <div class="status-point active">
                                        <div class="status-dot"></div>
                                        <span>Order Placed</span>
                                    </div>
                                    <div class="status-point">
                                        <div class="status-dot"></div>
                                        <span>Processing</span>
                                    </div>
                                    <div class="status-point">
                                        <div class="status-dot"></div>
                                        <span>Shipped</span>
                                    </div>
                                    <div class="status-point">
                                        <div class="status-dot"></div>
                                        <span>Delivered</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="shipping-address">
                            <h3>Shipping Address</h3>
                            <p><?php echo htmlspecialchars($transaction['shipping_info']['fullname']); ?></p>
                            <p><?php echo htmlspecialchars($transaction['shipping_info']['address']); ?></p>
                            <p><?php echo htmlspecialchars($transaction['shipping_info']['city']) . ' ' . htmlspecialchars($transaction['shipping_info']['postal_code']); ?></p>
                            <p>Phone: <?php echo htmlspecialchars($transaction['shipping_info']['phone']); ?></p>
                            <?php if (!empty($transaction['shipping_info']['notes'])): ?>
                                <p class="order-notes">Notes: <?php echo htmlspecialchars($transaction['shipping_info']['notes']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="order-summary">
                        <h3>Order Summary</h3>
                        <div class="order-items">
                            <?php foreach ($transaction['items'] as $item): ?>
                                <div class="order-item">
                                    <div class="item-details">
                                        <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                        <div class="item-specs">
                                            <span>Color: <?php echo htmlspecialchars($item['color']); ?></span>
                                            <span>Size: <?php echo htmlspecialchars($item['size']); ?></span>
                                        </div>
                                        <div class="item-price-qty">
                                            <span>₱<?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?></span>
                                        </div>
                                    </div>
                                    <div class="item-subtotal">
                                        ₱<?php echo number_format($item['subtotal'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="summary-totals">
                            <div class="summary-row">
                                <span>Subtotal</span>
                                <span>₱<?php echo number_format($transaction['subtotal'], 2); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Shipping</span>
                                <span>
                                    <?php if ($transaction['shipping_fee'] > 0): ?>
                                        ₱<?php echo number_format($transaction['shipping_fee'], 2); ?>
                                    <?php else: ?>
                                        FREE
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="summary-total">
                                <span>Total</span>
                                <span>₱<?php echo number_format($transaction['total_amount'], 2); ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($transaction['payment_method'] !== 'cod'): ?>
                    <div class="payment-instructions">
                        <h3>Payment Instructions</h3>
                        <?php if ($transaction['payment_method'] === 'gcash'): ?>
                            <div class="payment-method-details">
                                <div class="qr-code">
                                    <img src="assets/images/gcash-qr.png" alt="GCash QR Code">
                                </div>
                                <div class="payment-info">
                                    <p>Please complete your payment using GCash:</p>
                                    <p><strong>Account Name:</strong> TechHub Store</p>
                                    <p><strong>Account Number:</strong> 0917-123-4567</p>
                                    <p class="payment-note">After sending payment, please take a screenshot of your receipt and send it to our email: payments@htechHub.com with your order number in the subject line.</p>
                                </div>
                            </div>
                        <?php elseif ($transaction['payment_method'] === 'paymaya'): ?>
                            <div class="payment-method-details">
                                <div class="qr-code">
                                    <img src="assets/images/paymaya-qr.png" alt="PayMaya QR Code">
                                </div>
                                <div class="payment-info">
                                    <p>Please complete your payment using PayMaya:</p>
                                    <p><strong>Account Name:</strong> TechHub Store</p>
                                    <p><strong>Account Number:</strong> 0918-765-4321</p>
                                    <p class="payment-note">After sending payment, please take a screenshot of your receipt and send it to our email: payments@techHub.com with your order number in the subject line.</p>
                                </div>
                            </div>
                        <?php elseif ($transaction['payment_method'] === 'bank_transfer'): ?>
                            <div class="payment-method-details">
                                <div class="bank-info">
                                    <p>Please complete your payment using Bank Transfer:</p>
                                    <p><strong>Bank:</strong> BDO (Banco de Oro)</p>
                                    <p><strong>Account Name:</strong> TechHub Corporation</p>
                                    <p><strong>Account Number:</strong> 1234-5678-9012</p>
                                    <p class="payment-note">After making the transfer, please take a screenshot of your receipt and send it to our email: payments@techHub.com with your order number in the subject line.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="action-buttons">
                        <a href="orders.php" class="btn-primary"><i class="fas fa-box"></i> View My Orders</a>
                        <a   href="usershop.php" class="btn-secondary"><i class="fas fa-shopping-bag"></i> Continue Shopping</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer would go here -->

    <script>
        // Toggle mobile menu
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            document.getElementById('mainNav').classList.toggle('active');
        });

        // Toggle account dropdown
        const accountBtn = document.getElementById('accountBtn');
        const accountDropdownContent = document.getElementById('accountDropdownContent');

        if (accountBtn) {
            accountBtn.addEventListener('click', function(e) {
                e.preventDefault();
                accountDropdownContent.classList.toggle('show');
            });

            // Close dropdown when clicking outside
            window.addEventListener('click', function(e) {
                if (!e.target.matches('#accountBtn') && !e.target.closest('#accountDropdownContent')) {
                    if (accountDropdownContent.classList.contains('show')) {
                        accountDropdownContent.classList.remove('show');
                    }
                }
            });
        }

        // Clear the completed transaction from session after page is loaded
        // This prevents accessing the confirmation page after viewing it once
        window.addEventListener('load', function() {
            // Use setTimeout to ensure it happens after the page is fully loaded
            setTimeout(function() {
                // We'll implement this with a fetch request to a clearsession.php file
                // But for now just console log
                console.log('Session variable should be cleared');
                // Ideally you would create a small clearsession.php file that removes the completed_transaction session variable
            }, 5000);
        });
    </script>
</body>

</html>