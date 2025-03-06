<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is Cashier
if (!isset($_SESSION['user_id']) || $_SESSION['job_name'] !== 'Cashier') {
    header("Location: ../../auth/login.php");
    exit();
}

// Get today's statistics
$today_query = "SELECT 
    COUNT(*) as total_orders,
    COUNT(CASE WHEN order_status = 'completed' THEN 1 END) as completed_orders,
    COUNT(CASE WHEN order_status = 'pending' THEN 1 END) as pending_orders,
    COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) as cancelled_orders,
    COALESCE(SUM(CASE WHEN order_status = 'completed' THEN order_total_amount ELSE 0 END), 0) as total_sales
FROM tbl_pos_orders 
WHERE DATE(order_date) = CURDATE() 
AND employee_id = ?";

$stmt = $conn->prepare($today_query);
$stmt->bind_param("i", $_SESSION['employee_id']);
$stmt->execute();
$today_stats = $stmt->get_result()->fetch_assoc();

// Get total all-time statistics
$total_query = "SELECT 
    COUNT(*) as total_orders,
    COUNT(CASE WHEN order_status = 'completed' THEN 1 END) as completed_orders,
    COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) as cancelled_orders,
    COALESCE(SUM(CASE WHEN order_status = 'completed' THEN order_total_amount ELSE 0 END), 0) as total_sales
FROM tbl_pos_orders 
WHERE employee_id = ?";

$stmt = $conn->prepare($total_query);
$stmt->bind_param("i", $_SESSION['employee_id']);
$stmt->execute();
$total_stats = $stmt->get_result()->fetch_assoc();

// Get payment method statistics for today
$payment_query = "SELECT 
    p.payment_method,
    COUNT(*) as count,
    COALESCE(SUM(o.order_total_amount), 0) as total_amount
FROM tbl_pos_orders o
JOIN tbl_payments p ON o.pos_order_id = p.pos_order_id
WHERE DATE(o.order_date) = CURDATE() 
AND o.employee_id = ?
AND o.order_status = 'completed'
GROUP BY p.payment_method";

$stmt = $conn->prepare($payment_query);
$stmt->bind_param("i", $_SESSION['employee_id']);
$stmt->execute();
$payment_stats = $stmt->get_result();

// Get recent orders with more details
$recent_query = "SELECT po.*, 
                c.customer_name, c.customer_type,
                p.payment_method, p.payment_status,
                r.receipt_status
                FROM tbl_pos_orders po
                LEFT JOIN tbl_customer c ON po.cust_id = c.cust_id
                LEFT JOIN tbl_payments p ON po.pos_order_id = p.pos_order_id
                LEFT JOIN tbl_receipts r ON po.pos_order_id = r.pos_order_id
                WHERE po.employee_id = ?
                ORDER BY po.order_date DESC 
                LIMIT 10";

$stmt = $conn->prepare($recent_query);
$stmt->bind_param("i", $_SESSION['employee_id']);
$stmt->execute();
$recent_orders = $stmt->get_result();
?>

<div class="container-fluid py-o">

    <!-- Today's Statistics -->
    <div class="row mb-4">
        <div class="col-12">
            <h5 class="mb-3">Today's Overview</h5>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Today's Sales</h6>
                            <h3 class="display-6 mb-0">₱<?php echo number_format($today_stats['total_sales'], 2); ?></h3>
                        </div>
                        <i class="bi bi-currency-dollar fs-1"></i>
                    </div>
                    <small><?php echo $today_stats['completed_orders']; ?> completed orders</small>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Pending Orders</h6>
                            <h3 class="display-6 mb-0"><?php echo $today_stats['pending_orders']; ?></h3>
                        </div>
                        <i class="bi bi-clock-history fs-1"></i>
                    </div>
                    <small>Need attention</small>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Completed Today</h6>
                            <h3 class="display-6 mb-0"><?php echo $today_stats['completed_orders']; ?></h3>
                        </div>
                        <i class="bi bi-check-circle fs-1"></i>
                    </div>
                    <small>Successfully processed</small>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Cancelled Today</h6>
                            <h3 class="display-6 mb-0"><?php echo $today_stats['cancelled_orders']; ?></h3>
                        </div>
                        <i class="bi bi-x-circle fs-1"></i>
                    </div>
                    <small>Orders cancelled</small>
                </div>
            </div>
        </div>
    </div>

    <!-- All-Time Statistics -->
    <div class="row mb-4">
        <div class="col-12">
            <h5 class="mb-3">All-Time Statistics</h5>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card border-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Total Sales</h6>
                            <h3 class="mb-0">₱<?php echo number_format($total_stats['total_sales'], 2); ?></h3>
                        </div>
                        <i class="bi bi-cash-stack text-primary fs-1"></i>
                    </div>
                    <small class="text-muted"><?php echo $total_stats['completed_orders']; ?> completed orders</small>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card border-success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Success Rate</h6>
                            <h3 class="mb-0">
                                <?php 
                                $success_rate = $total_stats['total_orders'] > 0 
                                    ? round(($total_stats['completed_orders'] / $total_stats['total_orders']) * 100, 1)
                                    : 0;
                                echo $success_rate . '%';
                                ?>
                            </h3>
                        </div>
                        <i class="bi bi-graph-up text-success fs-1"></i>
                    </div>
                    <small class="text-muted">Based on total orders</small>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card border-info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Total Orders</h6>
                            <h3 class="mb-0"><?php echo $total_stats['total_orders']; ?></h3>
                        </div>
                        <i class="bi bi-cart-check text-info fs-1"></i>
                    </div>
                    <small class="text-muted">All time</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Payment Methods Today -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Payment Methods Today</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th class="text-end">Count</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($payment = $payment_stats->fetch_assoc()): ?>
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

        <!-- Quick Actions -->
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="pos.php" class="btn btn-primary">
                            <i class="bi bi-cart-plus"></i> New Sale
                        </a>
                        <a href="orders.php?status=pending" class="btn btn-warning">
                            <i class="bi bi-clock"></i> View Pending Orders
                        </a>
                        <a href="daily_sales.php" class="btn btn-info">
                            <i class="bi bi-graph-up"></i> View Sales Report
                        </a>
                        <a href="receipts.php" class="btn btn-secondary">
                            <i class="bi bi-receipt"></i> Manage Receipts
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="col-md-5 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Orders</h5>
                    <a href="orders.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php while ($order = $recent_orders->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <h6 class="mb-1">
                                        Order #<?php echo $order['pos_order_id']; ?>
                                        <small class="text-muted">(<?php echo ucfirst($order['order_type']); ?>)</small>
                                    </h6>
                                    <?php
                                    $status_class = [
                                        'completed' => 'text-success',
                                        'pending' => 'text-warning',
                                        'cancelled' => 'text-danger'
                                    ][$order['order_status']] ?? 'text-secondary';
                                    ?>
                                    <span class="badge <?php echo str_replace('text', 'bg', $status_class); ?>">
                                        <?php echo ucfirst($order['order_status']); ?>
                                    </span>
                                </div>
                                <p class="mb-1">
                                    <strong>₱<?php echo number_format($order['order_total_amount'], 2); ?></strong>
                                    <?php if ($order['payment_method']): ?>
                                        <small class="text-muted">
                                            via <?php echo ucfirst($order['payment_method']); ?>
                                        </small>
                                    <?php endif; ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($order['customer_name'] ?? 'Walk-in'); ?>
                                        <span class="mx-1">•</span>
                                        <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?>
                                    </small>
                                    <div>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="viewDetails(<?php echo $order['pos_order_id']; ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewDetails(orderId) {
    window.location.href = `orders.php?view=${orderId}`;
}
</script>

<?php require_once '../../includes/footer.php'; ?> 