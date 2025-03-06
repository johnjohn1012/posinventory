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

// Handle form submission for returns
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_return') {
        $purchase_item_id = $_POST['purchase_item_id'];
        $quantity_returned = $_POST['quantity_returned'];
        $return_reason = $_POST['return_reason'];
        
        $conn->begin_transaction();
        
        try {
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
            
            // Check if there's enough stock to return
            if ($purchase_item['raw_stock_quantity'] < $quantity_returned) {
                throw new Exception("Insufficient stock for return");
            }
            
            // Update raw ingredient stock
            $new_stock = $purchase_item['raw_stock_quantity'] - $quantity_returned;
            $stmt = $conn->prepare("UPDATE tbl_raw_ingredients SET raw_stock_quantity = ? WHERE raw_ingredient_id = ?");
            $stmt->bind_param("ii", $new_stock, $purchase_item['raw_ingredient_id']);
            $stmt->execute();
            
            // Create return record
            $stmt = $conn->prepare("INSERT INTO tbl_return_list (purchase_item_id, quantity_returned, return_reason, employee_id) 
                                  VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisi", $purchase_item_id, $quantity_returned, $return_reason, $_SESSION['employee_id']);
            $stmt->execute();
            
            // Log transaction
            $description = "Returned {$quantity_returned} units for purchase item ID: {$purchase_item_id} by " . $_SESSION['full_name'];
            $stmt = $conn->prepare("INSERT INTO tbl_transaction_log (transaction_type, transaction_description) VALUES ('purchase', ?)");
            $stmt->bind_param("s", $description);
            $stmt->execute();
            
            $conn->commit();
            $message = "Return created successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Get all received items that can be returned
$query = "SELECT pi.*, il.name as ingredient_name, il.unit_of_measure,
          ri.raw_stock_quantity, po.purchase_order_id, s.supplier_name
          FROM tbl_purchase_items pi
          JOIN tbl_raw_ingredients ri ON pi.raw_ingredient_id = ri.raw_ingredient_id
          JOIN tbl_item_list il ON ri.item_id = il.item_id
          JOIN tbl_purchase_order_list po ON pi.purchase_order_id = po.purchase_order_id
          JOIN tbl_suppliers s ON po.supplier_id = s.supplier_id
          WHERE pi.quantity_received > 0
          ORDER BY pi.created_at DESC";
$result = $conn->query($query);
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h2 class="mb-4">Returns</h2>
            
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
                        <h5 class="mb-0">Received Items</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>PO ID</th>
                                    <th>Supplier</th>
                                    <th>Item</th>
                                    <th>Unit</th>
                                    <th>Received</th>
                                    <th>Current Stock</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $item['purchase_order_id']; ?></td>
                                        <td><?php echo htmlspecialchars($item['supplier_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['ingredient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                                        <td><?php echo $item['quantity_received']; ?></td>
                                        <td><?php echo $item['raw_stock_quantity']; ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger create-return" 
                                                    data-id="<?php echo $item['purchase_item_id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($item['ingredient_name']); ?>"
                                                    data-stock="<?php echo $item['raw_stock_quantity']; ?>"
                                                    data-received="<?php echo $item['quantity_received']; ?>">
                                                <i class="bi bi-arrow-return-left"></i> Create Return
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

<!-- Create Return Modal -->
<div class="modal fade" id="createReturnModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Return</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="createReturnForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_return">
                    <input type="hidden" name="purchase_item_id" id="purchase_item_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Item</label>
                        <input type="text" class="form-control" id="item_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Stock</label>
                        <input type="number" class="form-control" id="current_stock" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quantity to Return</label>
                        <input type="number" class="form-control" name="quantity_returned" 
                               id="quantity_returned" required min="1">
                        <small class="text-muted">Maximum available: <span id="max_quantity"></span></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Return Reason</label>
                        <textarea class="form-control" name="return_reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Create Return</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle create return button click
    document.querySelectorAll('.create-return').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.dataset.id;
            const itemName = this.dataset.name;
            const currentStock = this.dataset.stock;
            const received = this.dataset.received;
            
            document.getElementById('purchase_item_id').value = itemId;
            document.getElementById('item_name').value = itemName;
            document.getElementById('current_stock').value = currentStock;
            document.getElementById('max_quantity').textContent = Math.min(currentStock, received);
            document.getElementById('quantity_returned').max = Math.min(currentStock, received);
            
            new bootstrap.Modal(document.getElementById('createReturnModal')).show();
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?> 