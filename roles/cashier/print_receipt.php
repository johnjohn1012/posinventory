<?php
session_start();
require_once '../../config/database.php';

// Check if user is Cashier
if (!isset($_SESSION['user_id']) || $_SESSION['job_name'] !== 'Cashier') {
    http_response_code(403);
    echo 'Unauthorized access';
    exit();
}

if (!isset($_GET['order_id'])) {
    http_response_code(400);
    echo 'Order ID is required';
    exit();
}

try {
    // Get order details with related information
    $query = "SELECT o.*, c.customer_name, c.customer_type,
                     p.payment_method, p.payment_amount, p.amount_received, p.change_amount,
                     CONCAT(e.first_name, ' ', e.last_name) as cashier_name
              FROM tbl_pos_orders o
              LEFT JOIN tbl_customer c ON o.cust_id = c.cust_id
              LEFT JOIN tbl_payments p ON o.pos_order_id = p.pos_order_id
              LEFT JOIN tbl_employee e ON o.employee_id = e.employee_id
              WHERE o.pos_order_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_GET['order_id']);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception('Order not found');
    }

    // Get order items
    $items_query = "SELECT i.*, p.product_name 
                   FROM tbl_pos_order_items i
                   LEFT JOIN tbl_products p ON i.product_id = p.product_id
                   WHERE i.pos_order_id = ?";
    
    $stmt = $conn->prepare($items_query);
    $stmt->bind_param("i", $_GET['order_id']);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Generate receipt HTML
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Receipt #<?php echo $order['pos_order_id']; ?></title>
        <style>
            body {
                font-family: 'Courier New', monospace;
                font-size: 12px;
                margin: 0;
                padding: 20px;
                width: 300px;
            }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .mb-1 { margin-bottom: 5px; }
            .mb-2 { margin-bottom: 10px; }
            .border-bottom { border-bottom: 1px dashed #000; }
            .store-name { font-size: 16px; font-weight: bold; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 3px 0; }
            .total-line { border-top: 1px solid #000; }
            @media print {
                body { margin: 0; padding: 0; }
                @page { margin: 0; }
            }
        </style>
    </head>
    <body>
        <div class="text-center mb-2">
            <div class="store-name mb-1">Your Store Name</div>
            <div>Store Address Line 1</div>
            <div>Store Address Line 2</div>
            <div>Phone: (123) 456-7890</div>
        </div>

        <div class="border-bottom mb-2">
            <div>Receipt #: <?php echo $order['pos_order_id']; ?></div>
            <div>Date: <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></div>
            <div>Cashier: <?php echo htmlspecialchars($order['cashier_name']); ?></div>
            <div>Customer: <?php echo htmlspecialchars($order['customer_name']); ?></div>
        </div>

        <table class="mb-2">
            <thead>
                <tr>
                    <th class="text-left">Item</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td class="text-right"><?php echo $item['quantity_sold']; ?></td>
                        <td class="text-right">₱<?php echo number_format($item['item_price'], 2); ?></td>
                        <td class="text-right">₱<?php echo number_format($item['item_total_amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="border-bottom mb-2">
            <table>
                <tr>
                    <td>Subtotal:</td>
                    <td class="text-right">₱<?php echo number_format($order['order_total_amount'], 2); ?></td>
                </tr>
                <?php if ($order['payment_method']): ?>
                    <tr>
                        <td>Payment Method:</td>
                        <td class="text-right"><?php echo ucfirst($order['payment_method']); ?></td>
                    </tr>
                    <?php if ($order['payment_method'] === 'cash'): ?>
                        <tr>
                            <td>Amount Received:</td>
                            <td class="text-right">₱<?php echo number_format($order['amount_received'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Change:</td>
                            <td class="text-right">₱<?php echo number_format($order['change_amount'], 2); ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endif; ?>
            </table>
        </div>

        <div class="text-center mb-2">
            <div>Thank you for your purchase!</div>
            <div>Please come again</div>
        </div>

        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    http_response_code(500);
    echo 'Error generating receipt: ' . $e->getMessage();
}
?>