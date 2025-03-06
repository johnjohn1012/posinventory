<?php
session_start();
require_once '../../config/database.php';

// Check if user is Cashier
if (!isset($_SESSION['user_id']) || $_SESSION['job_name'] !== 'Cashier') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID is required']);
    exit();
}

try {
    // Get order details with customer info
    $query = "SELECT o.*, 
              c.customer_name, c.customer_type,
              p.payment_method, p.payment_status, p.payment_amount,
              r.receipt_status
              FROM tbl_pos_orders o
              LEFT JOIN tbl_customer c ON o.cust_id = c.cust_id
              LEFT JOIN tbl_payments p ON o.pos_order_id = p.pos_order_id
              LEFT JOIN tbl_receipts r ON o.pos_order_id = r.pos_order_id
              WHERE o.pos_order_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_GET['order_id']);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception("Order not found");
    }

    // Get order items
    $items_query = "SELECT poi.*, p.product_name
                   FROM tbl_pos_order_items poi
                   JOIN tbl_products p ON poi.product_id = p.product_id
                   WHERE poi.pos_order_id = ?";
    $stmt = $conn->prepare($items_query);
    $stmt->bind_param("i", $_GET['order_id']);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Return JSON response
    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>