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

if (!isset($data['order_id']) || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID and status are required']);
    exit();
}

// Validate status
$valid_statuses = ['completed', 'cancelled'];
if (!in_array($data['status'], $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status']);
    exit();
}

// If cancelling, require a reason
if ($data['status'] === 'cancelled' && empty($data['cancel_reason'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Cancellation reason is required']);
    exit();
}

try {
    $conn->begin_transaction();

    // Check current order status
    $check_query = "SELECT o.*, p.payment_status 
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

    if ($order['order_status'] === 'cancelled') {
        throw new Exception('Cannot update a cancelled order');
    }

    if ($data['status'] === 'cancelled' && $order['payment_status'] === 'completed') {
        throw new Exception('Cannot cancel a paid order');
    }

    // Update order status
    $update_query = "UPDATE tbl_pos_orders SET order_status = ? WHERE pos_order_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $data['status'], $data['order_id']);
    $stmt->execute();

    // If cancelling, restore product quantities
    if ($data['status'] === 'cancelled') {
        $items_query = "SELECT product_id, quantity_sold FROM tbl_pos_order_items WHERE pos_order_id = ?";
        $stmt = $conn->prepare($items_query);
        $stmt->bind_param("i", $data['order_id']);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($items as $item) {
            $restore_query = "UPDATE tbl_products 
                            SET product_quantity = product_quantity + ?
                            WHERE product_id = ?";
            $stmt = $conn->prepare($restore_query);
            $stmt->bind_param("ii", $item['quantity_sold'], $item['product_id']);
            $stmt->execute();
        }

        // Update payment and receipt status if they exist
        $update_payment = "UPDATE tbl_payments SET payment_status = 'failed' 
                          WHERE pos_order_id = ? AND payment_status = 'pending'";
        $stmt = $conn->prepare($update_payment);
        $stmt->bind_param("i", $data['order_id']);
        $stmt->execute();

        $update_receipt = "UPDATE tbl_receipts SET receipt_status = 'refunded' 
                          WHERE pos_order_id = ? AND receipt_status = 'unpaid'";
        $stmt = $conn->prepare($update_receipt);
        $stmt->bind_param("i", $data['order_id']);
        $stmt->execute();
    }

    // Log the action
    $log_query = "INSERT INTO tbl_transaction_logs (employee_id, transaction_type, description) 
                  VALUES (?, ?, ?)";
    $transaction_type = $data['status'] === 'completed' ? 'order_completed' : 'order_cancelled';
    $description = $data['status'] === 'completed' 
        ? "Completed Order #" . $data['order_id']
        : "Cancelled Order #" . $data['order_id'] . ". Reason: " . $data['cancel_reason'];
    
    $stmt = $conn->prepare($log_query);
    $stmt->bind_param("iss", $_SESSION['employee_id'], $transaction_type, $description);
    $stmt->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>