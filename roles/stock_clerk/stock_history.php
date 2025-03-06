<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is Stock Clerk
if (!isset($_SESSION['user_id']) || $_SESSION['job_name'] !== 'Stock Clerk') {
    header("Location: ../../auth/login.php");
    exit();
}

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$item_id = isset($_GET['item_id']) ? $_GET['item_id'] : '';
$transaction_type = isset($_GET['transaction_type']) ? $_GET['transaction_type'] : '';

// Build query for stock history
$query = "SELECT tl.*, ri.raw_ingredient_id, il.name as ingredient_name, il.unit_of_measure,
          CONCAT(e.first_name, ' ', e.last_name) as employee_name
          FROM tbl_transaction_log tl
          LEFT JOIN tbl_raw_ingredients ri ON tl.transaction_description LIKE CONCAT('%purchase item ID: ', ri.raw_ingredient_id, '%')
          LEFT JOIN tbl_item_list il ON ri.item_id = il.item_id
          LEFT JOIN tbl_employee e ON tl.transaction_description LIKE CONCAT('%by ', CONCAT(e.first_name, ' ', e.last_name))
          WHERE tl.transaction_date BETWEEN ? AND ?
          AND tl.transaction_type IN ('purchase', 'adjustment', 'refund')";

$params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
$types = "ss";

if ($item_id) {
    $query .= " AND ri.raw_ingredient_id = ?";
    $params[] = $item_id;
    $types .= "i";
}

if ($transaction_type) {
    $query .= " AND tl.transaction_type = ?";
    $params[] = $transaction_type;
    $types .= "s";
}

$query .= " ORDER BY tl.transaction_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get all items for filter dropdown
$items_query = "SELECT ri.raw_ingredient_id, il.name as ingredient_name 
                FROM tbl_raw_ingredients ri 
                JOIN tbl_item_list il ON ri.item_id = il.item_id 
                ORDER BY il.name ASC";
$items_result = $conn->query($items_query);
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h2 class="mb-4">Stock History</h2>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" 
                                   value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Item</label>
                            <select class="form-select" name="item_id">
                                <option value="">All Items</option>
                                <?php while ($item = $items_result->fetch_assoc()): ?>
                                    <option value="<?php echo $item['raw_ingredient_id']; ?>" 
                                            <?php echo $item_id == $item['raw_ingredient_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($item['ingredient_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Transaction Type</label>
                            <select class="form-select" name="transaction_type">
                                <option value="">All Types</option>
                                <option value="purchase" <?php echo $transaction_type === 'purchase' ? 'selected' : ''; ?>>Purchase</option>
                                <option value="adjustment" <?php echo $transaction_type === 'adjustment' ? 'selected' : ''; ?>>Adjustment</option>
                                <option value="refund" <?php echo $transaction_type === 'refund' ? 'selected' : ''; ?>>Refund</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="stock_history.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Transaction History</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Item</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Employee</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($transaction = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($transaction['transaction_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['ingredient_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php
                                            $type_class = [
                                                'purchase' => 'bg-success',
                                                'adjustment' => 'bg-warning',
                                                'refund' => 'bg-danger'
                                            ][$transaction['transaction_type']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $type_class; ?>">
                                                <?php echo ucfirst($transaction['transaction_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($transaction['transaction_description']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['employee_name'] ?? 'N/A'); ?></td>
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

<?php require_once '../../includes/footer.php'; ?> 