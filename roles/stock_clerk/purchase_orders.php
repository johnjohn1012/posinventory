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

// Handle form submission for creating purchase order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_po') {
        $supplier_id = $_POST['supplier_id'];
        $expected_delivery_date = $_POST['expected_delivery_date'];
        $items = $_POST['items']; // Array of raw_ingredient_id and quantities
        
        $conn->begin_transaction();
        
        try {
            // Create purchase order
            $stmt = $conn->prepare("INSERT INTO tbl_purchase_order_list (supplier_id, purchase_expected_delivery_date, status, employee_id) VALUES (?, ?, 'ordered', ?)");
            $stmt->bind_param("isi", $supplier_id, $expected_delivery_date, $_SESSION['employee_id']);
            $stmt->execute();
            $purchase_order_id = $conn->insert_id;
            
            // Insert purchase items
            $stmt = $conn->prepare("INSERT INTO tbl_purchase_items (purchase_order_id, raw_ingredient_id, quantity_ordered, employee_id) VALUES (?, ?, ?, ?)");
            
            foreach ($items as $item) {
                if (!empty($item['raw_ingredient_id']) && !empty($item['quantity'])) {
                    $stmt->bind_param("iiii", $purchase_order_id, $item['raw_ingredient_id'], $item['quantity'], $_SESSION['employee_id']);
                    $stmt->execute();
                }
            }
            
            // Log transaction
            $description = "Purchase Order created for supplier ID: {$supplier_id} by " . $_SESSION['full_name'];
            $stmt = $conn->prepare("INSERT INTO tbl_transaction_log (transaction_type, transaction_description) VALUES ('purchase', ?)");
            $stmt->bind_param("s", $description);
            $stmt->execute();
            
            $conn->commit();
            $message = "Purchase Order created successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Get all purchase orders
$query = "SELECT po.*, s.supplier_name, 
          CONCAT(e.first_name, ' ', e.last_name) as employee_name,
          COUNT(pi.purchase_item_id) as total_items,
          SUM(pi.quantity_ordered) as total_quantity
          FROM tbl_purchase_order_list po 
          LEFT JOIN tbl_suppliers s ON po.supplier_id = s.supplier_id
          LEFT JOIN tbl_employee e ON po.employee_id = e.employee_id
          LEFT JOIN tbl_purchase_items pi ON po.purchase_order_id = pi.purchase_order_id
          GROUP BY po.purchase_order_id
          ORDER BY po.purchase_created_at DESC";
$result = $conn->query($query);

// Get suppliers for dropdown
$suppliers_query = "SELECT * FROM tbl_suppliers ORDER BY supplier_name";
$suppliers_result = $conn->query($suppliers_query);

// Get raw ingredients for dropdown
$ingredients_query = "SELECT ri.*, il.name as ingredient_name, il.unit_of_measure 
                     FROM tbl_raw_ingredients ri 
                     JOIN tbl_item_list il ON ri.item_id = il.item_id 
                     ORDER BY il.name";
$ingredients_result = $conn->query($ingredients_query);
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h2 class="mb-4">Purchase Orders</h2>
            
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
                        <h5 class="mb-0">All Purchase Orders</h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPOModal">
                            <i class="bi bi-plus-circle"></i> Create Purchase Order
                        </button>
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
                                    <th>Total Items</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
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
                                                'received' => 'bg-success',
                                                'partially_received' => 'bg-warning',
                                                'back_ordered' => 'bg-danger'
                                            ][$po['status']];
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $po['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($po['purchase_expected_delivery_date'])); ?></td>
                                        <td><?php echo $po['total_items']; ?> (<?php echo $po['total_quantity']; ?> units)</td>
                                        <td><?php echo htmlspecialchars($po['employee_name']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($po['purchase_created_at'])); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info view-po" data-id="<?php echo $po['purchase_order_id']; ?>">
                                                <i class="bi bi-eye"></i> View
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

<!-- Create Purchase Order Modal -->
<div class="modal fade" id="createPOModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="createPOForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_po">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Supplier</label>
                            <select class="form-select" name="supplier_id" required>
                                <option value="">Select Supplier</option>
                                <?php while ($supplier = $suppliers_result->fetch_assoc()): ?>
                                    <option value="<?php echo $supplier['supplier_id']; ?>">
                                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expected Delivery Date</label>
                            <input type="date" class="form-control" name="expected_delivery_date" required 
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div id="itemsContainer">
                        <div class="row mb-3 item-row">
                            <div class="col-md-6">
                                <label class="form-label">Raw Ingredient</label>
                                <select class="form-select" name="items[0][raw_ingredient_id]" required>
                                    <option value="">Select Ingredient</option>
                                    <?php 
                                    $ingredients_result->data_seek(0);
                                    while ($ingredient = $ingredients_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $ingredient['raw_ingredient_id']; ?>">
                                            <?php echo htmlspecialchars($ingredient['ingredient_name']); ?> 
                                            (<?php echo htmlspecialchars($ingredient['unit_of_measure']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" name="items[0][quantity]" required min="1">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-danger d-block remove-item">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-success" id="addItem">
                        <i class="bi bi-plus-circle"></i> Add Item
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Purchase Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Purchase Order Modal -->
<div class="modal fade" id="viewPOModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Purchase Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="poDetails">
                    <!-- Details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let itemCount = 1;
    
    // Add new item row
    document.getElementById('addItem').addEventListener('click', function() {
        const container = document.getElementById('itemsContainer');
        const template = container.querySelector('.item-row').cloneNode(true);
        
        // Update names
        template.querySelector('select').name = `items[${itemCount}][raw_ingredient_id]`;
        template.querySelector('input[type="number"]').name = `items[${itemCount}][quantity]`;
        
        // Clear values
        template.querySelector('select').value = '';
        template.querySelector('input[type="number"]').value = '';
        
        container.appendChild(template);
        itemCount++;
    });
    
    // Remove item row
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-item') || e.target.closest('.remove-item')) {
            const row = e.target.closest('.item-row');
            if (document.querySelectorAll('.item-row').length > 1) {
                row.remove();
            }
        }
    });

    // View Purchase Order
    document.querySelectorAll('.view-po').forEach(button => {
        button.addEventListener('click', function() {
            const poId = this.dataset.id;
            fetch(`get_po_details.php?id=${poId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('poDetails').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('viewPOModal')).show();
                })
                .catch(error => console.error('Error:', error));
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?> 