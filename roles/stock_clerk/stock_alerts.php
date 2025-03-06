<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is Stock Clerk
if (!isset($_SESSION['user_id']) || $_SESSION['job_name'] !== 'Stock Clerk') {
    header("Location: ../../auth/login.php");
    exit();
}

// Get low stock items
$query = "SELECT ri.*, il.name as ingredient_name, il.unit_of_measure, il.supplier_id,
          s.supplier_name, s.contact_person, s.phone, s.email
          FROM tbl_raw_ingredients ri
          JOIN tbl_item_list il ON ri.item_id = il.item_id
          LEFT JOIN tbl_suppliers s ON il.supplier_id = s.supplier_id
          WHERE ri.raw_stock_quantity <= ri.raw_reorder_level
          ORDER BY ri.raw_stock_quantity ASC";
$result = $conn->query($query);

// Get out of stock items
$out_of_stock_query = "SELECT ri.*, il.name as ingredient_name, il.unit_of_measure, il.supplier_id,
                      s.supplier_name, s.contact_person, s.phone, s.email
                      FROM tbl_raw_ingredients ri
                      JOIN tbl_item_list il ON ri.item_id = il.item_id
                      LEFT JOIN tbl_suppliers s ON il.supplier_id = s.supplier_id
                      WHERE ri.raw_stock_quantity = 0
                      ORDER BY il.name ASC";
$out_of_stock_result = $conn->query($out_of_stock_query);
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h2 class="mb-4">Stock Alerts</h2>

            <!-- Out of Stock Items -->
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Out of Stock Items</h5>
                        <span class="badge bg-white text-danger">
                            <?php echo $out_of_stock_result->num_rows; ?> Items
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Unit</th>
                                    <th>Supplier</th>
                                    <th>Contact</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = $out_of_stock_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['ingredient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                                        <td><?php echo htmlspecialchars($item['supplier_name']); ?></td>
                                        <td>
                                            <?php if ($item['contact_person']): ?>
                                                <div><?php echo htmlspecialchars($item['contact_person']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($item['phone']): ?>
                                                <div><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($item['phone']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($item['email']): ?>
                                                <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($item['email']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="purchase_orders.php?action=new&item_id=<?php echo $item['raw_ingredient_id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-cart-plus"></i> Create Purchase Order
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php if ($out_of_stock_result->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No out of stock items</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Low Stock Items -->
            <div class="card">
                <div class="card-header bg-warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Low Stock Items</h5>
                        <span class="badge bg-white text-warning">
                            <?php echo $result->num_rows; ?> Items
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Unit</th>
                                    <th>Current Stock</th>
                                    <th>Reorder Level</th>
                                    <th>Supplier</th>
                                    <th>Contact</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['ingredient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                                        <td>
                                            <span class="badge bg-warning">
                                                <?php echo $item['raw_stock_quantity']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $item['raw_reorder_level']; ?></td>
                                        <td><?php echo htmlspecialchars($item['supplier_name']); ?></td>
                                        <td>
                                            <?php if ($item['contact_person']): ?>
                                                <div><?php echo htmlspecialchars($item['contact_person']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($item['phone']): ?>
                                                <div><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($item['phone']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($item['email']): ?>
                                                <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($item['email']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="purchase_orders.php?action=new&item_id=<?php echo $item['raw_ingredient_id']; ?>" 
                                               class="btn btn-sm btn-warning">
                                                <i class="bi bi-cart-plus"></i> Create Purchase Order
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php if ($result->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No low stock items</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?> 