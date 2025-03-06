<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is Stock Clerk
if (!isset($_SESSION['user_id']) || $_SESSION['job_name'] !== 'Stock Clerk') {
    header("Location: ../../auth/login.php");
    exit();
}

$message = '';
$error = '';

// Handle form submission for receiving orders
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'receive_items') {
        $purchase_order_id = $_POST['purchase_order_id'];
        $items = $_POST['items'];
        
        $conn->begin_transaction();
        
        try {
            foreach ($items as $item) {
                if (!empty($item['purchase_item_id']) && !empty($item['quantity_received'])) {
                    $purchase_item_id = $item['purchase_item_id'];
                    $quantity_received = $item['quantity_received'];
                    
                    // Get current purchase item details
                    $stmt = $conn->prepare("SELECT pi.*, ri.raw_ingredient_id, ri.raw_stock_quantity 
                                          FROM tbl_purchase_items pi 
                                          JOIN tbl_raw_ingredients ri ON pi.raw_ingredient_id = ri.raw_ingredient_id 
                                          WHERE pi.purchase_item_id = ?");
                    $stmt->bind_param("i", $purchase_item_id);
                    $stmt->execute();
                    $purchase_item = $stmt->get_result()->fetch_assoc();
                    
                    if (!$purchase_item) {
                        throw new Exception("Purchase item not found");
                    }
                    
                    // Update purchase item received quantity
                    $new_received = $purchase_item['quantity_received'] + $quantity_received;
                    $stmt = $conn->prepare("UPDATE tbl_purchase_items SET quantity_received = ? WHERE purchase_item_id = ?");
                    $stmt->bind_param("ii", $new_received, $purchase_item_id);
                    $stmt->execute();
                    
                    // Update raw ingredient stock
                    $new_stock = $purchase_item['raw_stock_quantity'] + $quantity_received;
                    $stmt = $conn->prepare("UPDATE tbl_raw_ingredients SET raw_stock_quantity = ? WHERE raw_ingredient_id = ?");
                    $stmt->bind_param("ii", $new_stock, $purchase_item['raw_ingredient_id']);
                    $stmt->execute();
                    
                    // Record receiving transaction
                    $stmt = $conn->prepare("INSERT INTO tbl_receiving_list (purchase_item_id, quantity_received, employee_id) VALUES (?, ?, ?)");
                    $stmt->bind_param("iii", $purchase_item_id, $quantity_received, $_SESSION['employee_id']);
                    $stmt->execute();
                    
                    // Log transaction
                    $description = "Received {$quantity_received} units for purchase item ID: {$purchase_item_id} by " . $_SESSION['full_name'];
                    $stmt = $conn->prepare("INSERT INTO tbl_transaction_log (transaction_type, transaction_description) VALUES ('purchase', ?)");
                    $stmt->bind_param("s", $description);
                    $stmt->execute();
                }
            }
            
            // Check if all items in the purchase order are received
            $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN quantity_received = quantity_ordered THEN 1 ELSE 0 END) as received 
                                  FROM tbl_purchase_items WHERE purchase_order_id = ?");
            $stmt->bind_param("i", $purchase_order_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            // Update purchase order status
            $status = 'partially_received';
            if ($result['total'] == $result['received']) {
                $status = 'received';
            }
            
            $stmt = $conn->prepare("UPDATE tbl_purchase_order_list SET status = ? WHERE purchase_order_id = ?");
            $stmt->bind_param("si", $status, $purchase_order_id);
            $stmt->execute();
            
            $conn->commit();
            $message = "Items received successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Get all purchase orders with items that need to be received
$query = "SELECT po.*, s.supplier_name, 
          CONCAT(e.first_name, ' ', e.last_name) as employee_name,
          COUNT(pi.purchase_item_id) as total_items,
          SUM(pi.quantity_ordered) as total_quantity,
          SUM(pi.quantity_received) as total_received
          FROM tbl_purchase_order_list po 
          LEFT JOIN tbl_suppliers s ON po.supplier_id = s.supplier_id
          LEFT JOIN tbl_employee e ON po.employee_id = e.employee_id
          LEFT JOIN tbl_purchase_items pi ON po.purchase_order_id = pi.purchase_order_id
          WHERE po.status IN ('ordered', 'partially_received')
          GROUP BY po.purchase_order_id
          ORDER BY po.purchase_expected_delivery_date ASC";
$result = $conn->query($query);
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h2 class="mb-4">Receive Orders</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Pending Deliveries</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>PO ID</th>
                                    <th>Supplier</th>
                                    <th>Status</th>
                                    <th>Expected Delivery</th>
                                    <th>Items</th>
                                    <th>Progress</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($po = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $po['purchase_order_id']; ?></td>
                                        <td><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                                        <td>
                                            <?php
                                            $status_class = [
                                                'ordered' => 'bg-primary',
                                                'partially_received' => 'bg-warning'
                                            ][$po['status']];
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $po['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($po['purchase_expected_delivery_date'])); ?></td>
                                        <td><?php echo $po['total_items']; ?> items</td>
                                        <td>
                                            <?php 
                                            $progress = $po['total_quantity'] > 0 ? 
                                                round(($po['total_received'] / $po['total_quantity']) * 100) : 0;
                                            ?>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?php echo $progress; ?>%"
                                                     aria-valuenow="<?php echo $progress; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    <?php echo $progress; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary receive-items" 
                                                    data-id="<?php echo $po['purchase_order_id']; ?>">
                                                <i class="bi bi-box-seam"></i> Receive Items
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Receive Items Modal -->
<div class="modal fade" id="receiveItemsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Receive Items</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="receiveItemsForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="receive_items">
                    <input type="hidden" name="purchase_order_id" id="purchase_order_id">
                    
                    <div id="itemsContainer">
                        <!-- Items will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Receive Items</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle receive items button click
    document.querySelectorAll('.receive-items').forEach(button => {
        button.addEventListener('click', function() {
            const poId = this.dataset.id;
            document.getElementById('purchase_order_id').value = poId;
            
            // Fetch purchase order items
            fetch(`get_po_items.php?id=${poId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('itemsContainer').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('receiveItemsModal')).show();
                })
                .catch(error => console.error('Error:', error));
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?> 