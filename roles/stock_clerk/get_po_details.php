<?php
session_start();
require_once '../../config/database.php';

// Check if user is Stock Clerk
if (!isset($_SESSION['user_id']) || $_SESSION['job_name'] !== 'Stock Clerk') {
    die("Unauthorized access");
}

if (!isset($_GET['id'])) {
    die("No purchase order ID provided");
}

$po_id = intval($_GET['id']);

// Get purchase order details
$query = "SELECT po.*, s.supplier_name, s.contact_person, s.phone, s.email,
          CONCAT(e.first_name, ' ', e.last_name) as employee_name
          FROM tbl_purchase_order_list po 
          LEFT JOIN tbl_suppliers s ON po.supplier_id = s.supplier_id
          LEFT JOIN tbl_employee e ON po.employee_id = e.employee_id
          WHERE po.purchase_order_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $po_id);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();

if (!$po) {
    die("Purchase order not found");
}

// Get purchase order items
$items_query = "SELECT pi.*, ri.raw_stock_quantity, il.name as ingredient_name, 
                il.unit_of_measure, il.cost as unit_cost
                FROM tbl_purchase_items pi
                JOIN tbl_raw_ingredients ri ON pi.raw_ingredient_id = ri.raw_ingredient_id
                JOIN tbl_item_list il ON ri.item_id = il.item_id
                WHERE pi.purchase_order_id = ?";

$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $po_id);
$stmt->execute();
$items = $stmt->get_result();
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="mb-3">Purchase Order Information</h6>
        <p><strong>PO ID:</strong> <?php echo $po['purchase_order_id']; ?></p>
        <p><strong>Status:</strong> 
            <span class="badge <?php 
                echo [
                    'ordered' => 'bg-primary',
                    'received' => 'bg-success',
                    'partially_received' => 'bg-warning',
                    'back_ordered' => 'bg-danger'
                ][$po['status']]; 
            ?>">
                <?php echo ucfirst(str_replace('_', ' ', $po['status'])); ?>
            </span>
        </p>
        <p><strong>Expected Delivery:</strong> <?php echo date('M d, Y', strtotime($po['purchase_expected_delivery_date'])); ?></p>
        <p><strong>Created By:</strong> <?php echo htmlspecialchars($po['employee_name']); ?></p>
        <p><strong>Created At:</strong> <?php echo date('M d, Y H:i', strtotime($po['purchase_created_at'])); ?></p>
    </div>
    <div class="col-md-6">
        <h6 class="mb-3">Supplier Information</h6>
        <p><strong>Supplier:</strong> <?php echo htmlspecialchars($po['supplier_name']); ?></p>
        <p><strong>Contact Person:</strong> <?php echo htmlspecialchars($po['contact_person']); ?></p>
        <p><strong>Phone:</strong> <?php echo htmlspecialchars($po['phone']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($po['email']); ?></p>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <h6 class="mb-3">Order Items</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Unit</th>
                        <th>Quantity Ordered</th>
                        <th>Quantity Received</th>
                        <th>Back Ordered</th>
                        <th>Current Stock</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_cost = 0;
                    while ($item = $items->fetch_assoc()): 
                        $item_total = $item['quantity_ordered'] * $item['unit_cost'];
                        $total_cost += $item_total;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['ingredient_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                            <td><?php echo $item['quantity_ordered']; ?></td>
                            <td><?php echo $item['quantity_received']; ?></td>
                            <td><?php echo $item['back_ordered_quantity']; ?></td>
                            <td><?php echo $item['raw_stock_quantity']; ?></td>
                            <td>₱<?php echo number_format($item['unit_cost'], 2); ?></td>
                            <td>₱<?php echo number_format($item_total, 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7" class="text-end"><strong>Total Cost:</strong></td>
                        <td><strong>₱<?php echo number_format($total_cost, 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div> 