<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is Cashier
if (!isset($_SESSION['user_id']) || $_SESSION['job_name'] !== 'Cashier') {
    header("Location: ../../auth/login.php");
    exit();
}

// Get date range
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get sales data
$sales_query = "SELECT 
    o.pos_order_id,
    o.order_date,
    o.order_total_amount,
    o.order_status,
    o.order_type,
    c.customer_name,
    p.payment_method,
    p.payment_status,
    CONCAT(e.first_name, ' ', e.last_name) as cashier_name
FROM tbl_pos_orders o
LEFT JOIN tbl_customer c ON o.cust_id = c.cust_id
LEFT JOIN tbl_payments p ON o.pos_order_id = p.pos_order_id
LEFT JOIN tbl_employee e ON o.employee_id = e.employee_id
WHERE DATE(o.order_date) BETWEEN ? AND ?
ORDER BY o.order_date DESC";

$stmt = $conn->prepare($sales_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$sales_result = $stmt->get_result();

// Get summary data
$summary_query = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
    SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
    SUM(CASE WHEN order_status = 'completed' THEN order_total_amount ELSE 0 END) as total_sales,
    COUNT(DISTINCT cust_id) as unique_customers
FROM tbl_pos_orders
WHERE DATE(order_date) BETWEEN ? AND ?";

$stmt = $conn->prepare($summary_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Get payment method breakdown
$payment_query = "SELECT 
    payment_method,
    COUNT(*) as count,
    SUM(payment_amount) as total_amount
FROM tbl_payments p
JOIN tbl_pos_orders o ON p.pos_order_id = o.pos_order_id
WHERE DATE(o.order_date) BETWEEN ? AND ?
AND p.payment_status = 'completed'
GROUP BY payment_method";

$stmt = $conn->prepare($payment_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$payment_result = $stmt->get_result();
?>

<div class="container-fluid py-4">
    <!-- Date Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form class="row g-3 align-items-center">
                        <div class="col-auto">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-auto">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" 
                                   value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-auto">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block">Filter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Total Orders</h6>
                    <h3 class="card-text"><?php echo $summary['total_orders']; ?></h3>
                    <small class="text-muted">
                        <?php echo $summary['completed_orders']; ?> completed, 
                        <?php echo $summary['cancelled_orders']; ?> cancelled
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Total Sales</h6>
                    <h3 class="card-text">₱<?php echo number_format($summary['total_sales'], 2); ?></h3>
                    <small class="text-muted">From completed orders</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Unique Customers</h6>
                    <h3 class="card-text"><?php echo $summary['unique_customers']; ?></h3>
                    <small class="text-muted">Customers served</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Average Order Value</h6>
                    <h3 class="card-text">
                        ₱<?php 
                        echo $summary['completed_orders'] > 0 
                            ? number_format($summary['total_sales'] / $summary['completed_orders'], 2)
                            : '0.00';
                        ?>
                    </h3>
                    <small class="text-muted">Per completed order</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Payment Methods</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th class="text-end">Count</th>
                                    <th class="text-end">Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($payment = $payment_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                        <td class="text-end"><?php echo $payment['count']; ?></td>
                                        <td class="text-end">₱<?php echo number_format($payment['total_amount'], 2); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Sales Details</h5>
                        <button class="btn btn-sm btn-secondary" onclick="exportToExcel()">
                            Export to Excel
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="salesTable">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Cashier</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($sale = $sales_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $sale['pos_order_id']; ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($sale['order_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                        <td><?php echo ucfirst($sale['order_type']); ?></td>
                                        <td>₱<?php echo number_format($sale['order_total_amount'], 2); ?></td>
                                        <td>
                                            <?php
                                            $status_class = [
                                                'completed' => 'bg-success',
                                                'cancelled' => 'bg-danger',
                                                'pending' => 'bg-warning'
                                            ][$sale['order_status']];
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo ucfirst($sale['order_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($sale['payment_method']): ?>
                                                <?php echo ucfirst($sale['payment_method']); ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo ucfirst($sale['payment_status']); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No Payment</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($sale['cashier_name']); ?></td>
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

<script>
function exportToExcel() {
    // Get the table
    let table = document.getElementById('salesTable');
    
    // Convert table to CSV
    let csv = [];
    for (let i = 0; i < table.rows.length; i++) {
        let row = [], cols = table.rows[i].cells;
        for (let j = 0; j < cols.length; j++) {
            // Get the text content and clean it
            let text = cols[j].textContent.replace(/(\r\n|\n|\r)/gm, '').trim();
            row.push('"' + text + '"');
        }
        csv.push(row.join(','));
    }
    
    // Create CSV file
    let csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
    
    // Download link
    let downloadLink = document.createElement('a');
    downloadLink.download = `sales_report_${new Date().toISOString().split('T')[0]}.csv`;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    
    // Trigger download
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>

<?php require_once '../../includes/footer.php'; ?> 