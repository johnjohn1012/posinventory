<?php
session_start();
require_once '../../config/database.php';

// Check if user is Cashier
if (!isset($_SESSION['user_id']) || $_SESSION['job_name'] !== 'Cashier') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['order_id']) || !isset($data['payment_method']) || !isset($data['amount_received'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID, payment method, and amount received are required']);
    exit();
}

// Validate payment method
$valid_methods = ['cash', 'credit', 'debit', 'gcash', 'online'];
if (!in_array($data['payment_method'], $valid_methods)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payment method']);
    exit();
}

try {
    $conn->begin_transaction();

    // Get order details and check if payment exists
    $check_query = "SELECT o.*, p.payment_id, p.payment_status 
                   FROM tbl_pos_orders o
                   LEFT JOIN tbl_payments p ON o.pos_order_id = p.pos_order_id
                   WHERE o.pos_order_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $data['order_id']);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception('Order not found');
    }

    if ($order['payment_id'] && $order['payment_status'] === 'completed') {
        throw new Exception('Payment has already been processed');
    }

    if ($data['amount_received'] < $order['order_total_amount']) {
        throw new Exception('Amount received is less than the total order amount');
    }

    // Calculate change amount
    $change_amount = $data['amount_received'] - $order['order_total_amount'];

    // Insert or update payment
    if ($order['payment_id']) {
        $payment_query = "UPDATE tbl_payments 
                         SET payment_method = ?, 
                             payment_amount = ?,
                             payment_status = 'completed'
                         WHERE payment_id = ?";
        $stmt = $conn->prepare($payment_query);
        $stmt->bind_param("sdi", $data['payment_method'], $data['amount_received'], $order['payment_id']);
    } else {
        $payment_query = "INSERT INTO tbl_payments 
                         (pos_order_id, payment_method, payment_amount, payment_status) 
                         VALUES (?, ?, ?, 'completed')";
        $stmt = $conn->prepare($payment_query);
        $stmt->bind_param("isd", $data['order_id'], $data['payment_method'], $data['amount_received']);
    }
    $stmt->execute();

    // Update order status if it was pending
    if ($order['order_status'] === 'pending') {
        $update_order = "UPDATE tbl_pos_orders SET order_status = 'completed' WHERE pos_order_id = ?";
        $stmt = $conn->prepare($update_order);
        $stmt->bind_param("i", $data['order_id']);
        $stmt->execute();
    }

    // Create or update receipt
    $receipt_query = "INSERT INTO tbl_receipts 
                     (pos_order_id, employee_id, receipt_total_amount, receipt_status)
                     VALUES (?, ?, ?, 'paid')
                     ON DUPLICATE KEY UPDATE 
                     receipt_status = 'paid',
                     employee_id = VALUES(employee_id)";
    $stmt = $conn->prepare($receipt_query);
    $stmt->bind_param("iid", $data['order_id'], $_SESSION['employee_id'], $order['order_total_amount']);
    $stmt->execute();

    // Log the transaction
    $log_query = "INSERT INTO tbl_transaction_logs (employee_id, transaction_type, description) 
                  VALUES (?, 'payment_processed', ?)";
    $description = "Processed payment for Order #{$data['order_id']}. " .
                  "Amount: ₱{$data['amount_received']}, Method: {$data['payment_method']}, " .
                  "Change: ₱{$change_amount}";
    $stmt = $conn->prepare($log_query);
    $stmt->bind_param("is", $_SESSION['employee_id'], $description);
    $stmt->execute();

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'data' => [
            'change_amount' => $change_amount,
            'payment_method' => $data['payment_method'],
            'amount_received' => $data['amount_received']
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>