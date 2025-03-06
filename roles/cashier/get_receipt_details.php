<?php
session_start();
require_once '../../config/database.php';

// Check if user is Cashier
if (!isset($_SESSION['user_id']) || $_SESSION['job_name'] !== 'Cashier') {
    http_response_code(403);
    echo "Unauthorized access";
    exit();
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo "Receipt ID is required";
    exit();
}

try {
    // Get receipt details
    $query = "SELECT r.*, po.order_type, po.order_status,
              CONCAT(e.first_name, ' ', e.last_name) as employee_name,
              c.customer_name, c.customer_type
              FROM tbl_receipts r
              JOIN tbl_pos_orders po ON r.pos_order_id = po.pos_order_id
              JOIN tbl_employee e ON r.employee_id = e.employee_id
              LEFT JOIN tbl_customer c ON po.cust_id = c.cust_id
              WHERE r.receipt_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_GET['id']);
    $stmt->execute();
    $receipt = $stmt->get_result()->fetch_assoc();

    if (!$receipt) {
        throw new Exception("Receipt not found");
    }

    // Get order items
    $items_query = "SELECT poi.*, p.product_name
                   FROM tbl_pos_order_items poi
                   JOIN tbl_products p ON poi.product_id = p.product_id
                   WHERE poi.pos_order_id = ?";
    $stmt = $conn->prepare($items_query);
    $stmt->bind_param("i", $receipt['pos_order_id']);
    $stmt->execute();
    $items = $stmt->get_result();

    // Start building the receipt HTML
    ?>
    <div class="receipt-container">
        <div class="text-center mb-4">
            <h4>Receipt #<?php echo $receipt['receipt_id']; ?></h4>
            <p class="mb-1"><?php echo date('F d, Y h:i A', strtotime($receipt['receipt_date'])); ?></p>
            <p class="mb-1">
                Customer: <?php echo htmlspecialchars($receipt['customer_name'] ?? 'Walk-in'); ?>
                <?php if ($receipt['customer_type']): ?>
                    (<?php echo ucfirst($receipt['customer_type']); ?>)
                <?php endif; ?>
            </p>
            <p class="mb-1">Cashier: <?php echo htmlspecialchars($receipt['employee_name']); ?></p>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = $items->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td class="text-center"><?php echo $item['quantity_sold']; ?></td>
                            <td class="text-end">₱<?php echo number_format($item['item_price'], 2); ?></td>
                            <td class="text-end">₱<?php echo number_format($item['item_total_amount'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Total Amount:</th>
                        <th class="text-end">₱<?php echo number_format($receipt['receipt_total_amount'], 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-4">
            <div class="row">
                <div class="col-6">
                    <p class="mb-1">
                        <strong>Order Type:</strong>
                        <span class="badge bg-info"><?php echo ucfirst($receipt['order_type']); ?></span>
                    </p>
                </div>
                <div class="col-6 text-end">
                    <p class="mb-1">
                        <strong>Status:</strong>
                        <span class="badge <?php echo $receipt['receipt_status'] === 'paid' ? 'bg-success' : 'bg-warning'; ?>">
                            <?php echo ucfirst($receipt['receipt_status']); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php
} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
?> 