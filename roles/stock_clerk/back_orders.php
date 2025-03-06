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

// Handle form submission for back orders
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_back_order') {
        $purchase_item_id = $_POST['purchase_item_id'];
        $quantity_back_ordered = $_POST['quantity_back_ordered'];
        $expected_delivery_date = $_POST['expected_delivery_date'];
        
        $conn->begin_transaction();
        
        try {
            // Get current purchase item details
            $stmt = $conn->prepare("SELECT pi.*, po.purchase_order_id, po.supplier_id, s.supplier_name 
                                  FROM tbl_purchase_items pi 
                                  JOIN tbl_purchase_order_list po ON pi.purchase_order_id = po.purchase_order_id
                                  JOIN tbl_suppliers s ON po.supplier_id = s.supplier_id
                                  WHERE pi.purchase_item_id = ?");
            $stmt->bind_param("i", $purchase_item_id);
            $stmt->execute();
            $purchase_item = $stmt->get_result()->fetch_assoc();
            
            if (!$purchase_item) {
                throw new Exception("Purchase item not found");
            }
            
            // Check if quantity is valid
            $remaining = $purchase_item['quantity_ordered'] - $purchase_item['quantity_received'];
            if ($quantity_back_ordered > $remaining) {
                throw new Exception("Back order quantity cannot exceed remaining ordered quantity");
            }
            
            // Update purchase item back ordered quantity
            $new_back_ordered = $purchase_item['back_ordered_quantity'] + $quantity_back_ordered;
            $stmt = $conn->prepare("UPDATE tbl_purchase_items SET back_ordered_quantity = ? WHERE purchase_item_id = ?");
            $stmt->bind_param("ii", $new_back_ordered, $purchase_item_id);
            $stmt->execute();
            
            // Create back order record
            $stmt = $conn->prepare("INSERT INTO tbl_back_order_list (purchase_item_id, quantity_back_ordered, backorder_expected_delivery_date, employee_id) 
                                  VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisi", $purchase_item_id, $quantity_back_ordered, $expected_delivery_date, $_SESSION['employee_id']);
            $stmt->execute();
            
            // Update purchase order status if needed
            $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN back_ordered_quantity > 0 THEN 1 ELSE 0 END) as back_ordered 
                                  FROM tbl_purchase_items WHERE purchase_order_id = ?");
            $stmt->bind_param("i", $purchase_item['purchase_order_id']);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['total'] == $result['back_ordered']) {
                $stmt = $conn->prepare("UPDATE tbl_purchase_order_list SET status = 'back_ordered' WHERE purchase_order_id = ?");
                $stmt->bind_param("i", $purchase_item['purchase_order_id']);
                $stmt->execute();
            }
            
            // Log transaction
            $description = "Created back order for {$quantity_back_ordered} units of purchase item ID: {$purchase_item_id} by " . $_SESSION['full_name'];
            $stmt = $conn->prepare("INSERT INTO tbl_transaction_log (transaction_type, transaction_description) VALUES ('purchase', ?)");
            $stmt->bind_param("s", $description);
            $stmt->execute();
            
            $conn->commit();
            $message = "Back order created successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Get all purchase items that can be back ordered
$query = "SELECT pi.*, il.name as ingredient_name, il.unit_of_measure,
          po.purchase_order_id, s.supplier_name,
          (pi.quantity_ordered - pi.quantity_received) as remaining_quantity
          FROM tbl_purchase_items pi
          JOIN tbl_raw_ingredients ri ON pi.raw_ingredient_id = ri.raw_ingredient_id
          JOIN tbl_item_list il ON ri.item_id = il.item_id
          JOIN tbl_purchase_order_list po ON pi.purchase_order_id = po.purchase_order_id
          JOIN tbl_suppliers s ON po.supplier_id = s.supplier_id
          WHERE pi.quantity_ordered > pi.quantity_received
          AND po.status IN ('ordered', 'partially_received')
          ORDER BY pi.created_at DESC";
$result = $conn->query($query);
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h2 class="mb-4">Back Orders</h2>
            
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
                        <h5 class="mb-0">Pending Items</h5>
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
                                    <th>Ordered</th>
                                    <th>Received</th>
                                    <th>Remaining</th>
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
                                        <td><?php echo $item['quantity_ordered']; ?></td>
                                        <td><?php echo $item['quantity_received']; ?></td>
                                        <td><?php echo $item['remaining_quantity']; ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-warning create-back-order" 
                                                    data-id="<?php echo $item['purchase_item_id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($item['ingredient_name']); ?>"
                                                    data-remaining="<?php echo $item['remaining_quantity']; ?>">
                                                <i class="bi bi-clock-history"></i> Create Back Order
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

<!-- Create Back Order Modal -->
<div class="modal fade" id="createBackOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Back Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="createBackOrderForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_back_order">
                    <input type="hidden" name="purchase_item_id" id="purchase_item_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Item</label>
                        <input type="text" class="form-control" id="item_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Remaining Quantity</label>
                        <input type="number" class="form-control" id="remaining_quantity" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quantity to Back Order</label>
                        <input type="number" class="form-control" name="quantity_back_ordered" 
                               id="quantity_back_ordered" required min="1">
                        <small class="text-muted">Maximum available: <span id="max_quantity"></span></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Expected Delivery Date</label>
                        <input type="date" class="form-control" name="expected_delivery_date" 
                               required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Create Back Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle create back order button click
    document.querySelectorAll('.create-back-order').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.dataset.id;
            const itemName = this.dataset.name;
            const remaining = this.dataset.remaining;
            
            document.getElementById('purchase_item_id').value = itemId;
            document.getElementById('item_name').value = itemName;
            document.getElementById('remaining_quantity').value = remaining;
            document.getElementById('max_quantity').textContent = remaining;
            document.getElementById('quantity_back_ordered').max = remaining;
            
            new bootstrap.Modal(document.getElementById('createBackOrderModal')).show();
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?> 