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

// Get purchase order items
$query = "SELECT pi.*, il.name as ingredient_name, il.unit_of_measure,
          ri.raw_stock_quantity
          FROM tbl_purchase_items pi
          JOIN tbl_raw_ingredients ri ON pi.raw_ingredient_id = ri.raw_ingredient_id
          JOIN tbl_item_list il ON ri.item_id = il.item_id
          WHERE pi.purchase_order_id = ? AND pi.quantity_received < pi.quantity_ordered
          ORDER BY il.name";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $po_id);
$stmt->execute();
$items = $stmt->get_result();
?>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Item</th>
                <th>Unit</th>
                <th>Ordered</th>
                <th>Received</th>
                <th>Pending</th>
                <th>Current Stock</th>
                <th>Receive</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($item = $items->fetch_assoc()): 
                $pending = $item['quantity_ordered'] - $item['quantity_received'];
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['ingredient_name']); ?></td>
                    <td><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                    <td><?php echo $item['quantity_ordered']; ?></td>
                    <td><?php echo $item['quantity_received']; ?></td>
                    <td><?php echo $pending; ?></td>
                    <td><?php echo $item['raw_stock_quantity']; ?></td>
                    <td>
                        <div class="input-group input-group-sm" style="width: 150px;">
                            <input type="number" class="form-control" 
                                   name="items[<?php echo $item['purchase_item_id']; ?>][quantity_received]"
                                   min="1" max="<?php echo $pending; ?>" required>
                            <input type="hidden" name="items[<?php echo $item['purchase_item_id']; ?>][purchase_item_id]"
                                   value="<?php echo $item['purchase_item_id']; ?>">
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div> 