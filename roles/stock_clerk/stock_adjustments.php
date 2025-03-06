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

// Handle form submission for stock adjustments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_adjustment') {
        $raw_ingredient_id = $_POST['raw_ingredient_id'];
        $adjustment_type = $_POST['adjustment_type']; // 'increase' or 'decrease'
        $quantity = $_POST['quantity'];
        $reason = $_POST['reason'];
        
        $conn->begin_transaction();
        
        try {
            // Get current raw ingredient details
            $stmt = $conn->prepare("SELECT ri.*, il.name as ingredient_name, il.unit_of_measure 
                                  FROM tbl_raw_ingredients ri 
                                  JOIN tbl_item_list il ON ri.item_id = il.item_id 
                                  WHERE ri.raw_ingredient_id = ?");
            $stmt->bind_param("i", $raw_ingredient_id);
            $stmt->execute();
            $raw_ingredient = $stmt->get_result()->fetch_assoc();
            
            if (!$raw_ingredient) {
                throw new Exception("Raw ingredient not found");
            }
            
            // Calculate new quantity
            $new_quantity = $raw_ingredient['raw_stock_quantity'];
            if ($adjustment_type === 'increase') {
                $new_quantity += $quantity;
            } else {
                if ($new_quantity < $quantity) {
                    throw new Exception("Insufficient stock for decrease adjustment");
                }
                $new_quantity -= $quantity;
            }
            
            // Update raw ingredient stock
            $stmt = $conn->prepare("UPDATE tbl_raw_ingredients SET raw_stock_quantity = ? WHERE raw_ingredient_id = ?");
            $stmt->bind_param("ii", $new_quantity, $raw_ingredient_id);
            $stmt->execute();
            
            // Update stock in/out based on adjustment type
            if ($adjustment_type === 'increase') {
                $stmt = $conn->prepare("UPDATE tbl_raw_ingredients SET raw_stock_in = raw_stock_in + ? WHERE raw_ingredient_id = ?");
                $stmt->bind_param("ii", $quantity, $raw_ingredient_id);
            } else {
                $stmt = $conn->prepare("UPDATE tbl_raw_ingredients SET raw_stock_out = raw_stock_out + ? WHERE raw_ingredient_id = ?");
                $stmt->bind_param("ii", $quantity, $raw_ingredient_id);
            }
            $stmt->execute();
            
            // Log transaction
            $description = "Stock {$adjustment_type} adjustment of {$quantity} units for {$raw_ingredient['ingredient_name']} by " . $_SESSION['full_name'] . 
                          ". Reason: {$reason}";
            $stmt = $conn->prepare("INSERT INTO tbl_transaction_log (transaction_type, transaction_description) VALUES ('adjustment', ?)");
            $stmt->bind_param("s", $description);
            $stmt->execute();
            
            $conn->commit();
            $message = "Stock adjustment created successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Get all raw ingredients for adjustment
$query = "SELECT ri.*, il.name as ingredient_name, il.unit_of_measure
          FROM tbl_raw_ingredients ri
          JOIN tbl_item_list il ON ri.item_id = il.item_id
          ORDER BY il.name ASC";
$result = $conn->query($query);
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h2 class="mb-4">Stock Adjustments</h2>
            
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
                        <h5 class="mb-0">Inventory Items</h5>
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
                                    <th>Stock In</th>
                                    <th>Stock Out</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['ingredient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                                        <td><?php echo $item['raw_stock_quantity']; ?></td>
                                        <td><?php echo $item['raw_stock_in']; ?></td>
                                        <td><?php echo $item['raw_stock_out']; ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary adjust-stock" 
                                                    data-id="<?php echo $item['raw_ingredient_id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($item['ingredient_name']); ?>"
                                                    data-stock="<?php echo $item['raw_stock_quantity']; ?>"
                                                    data-unit="<?php echo htmlspecialchars($item['unit_of_measure']); ?>">
                                                <i class="bi bi-sliders"></i> Adjust Stock
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

<!-- Adjust Stock Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjust Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="adjustStockForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_adjustment">
                    <input type="hidden" name="raw_ingredient_id" id="raw_ingredient_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Item</label>
                        <input type="text" class="form-control" id="item_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Stock</label>
                        <input type="text" class="form-control" id="current_stock" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Adjustment Type</label>
                        <select class="form-select" name="adjustment_type" id="adjustment_type" required>
                            <option value="increase">Increase Stock</option>
                            <option value="decrease">Decrease Stock</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" name="quantity" 
                               id="quantity" required min="1">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for Adjustment</label>
                        <textarea class="form-control" name="reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle adjust stock button click
    document.querySelectorAll('.adjust-stock').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.dataset.id;
            const itemName = this.dataset.name;
            const currentStock = this.dataset.stock;
            const unit = this.dataset.unit;
            
            document.getElementById('raw_ingredient_id').value = itemId;
            document.getElementById('item_name').value = itemName;
            document.getElementById('current_stock').value = `${currentStock} ${unit}`;
            
            new bootstrap.Modal(document.getElementById('adjustStockModal')).show();
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>