<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is Cashier
if (!isset($_SESSION['user_id']) || $_SESSION['job_name'] !== 'Cashier') {
    header("Location: ../../auth/login.php");
    exit();
}

// Get date range from parameters or use default (last 30 days)
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Sales by Payment Method
$payment_query = "SELECT 
    p.payment_method,
    COUNT(*) as transaction_count,
    COALESCE(SUM(o.order_total_amount), 0) as total_amount
FROM tbl_pos_orders o
JOIN tbl_payments p ON o.pos_order_id = p.pos_order_id
WHERE o.employee_id = ?
AND DATE(o.order_date) BETWEEN ? AND ?
AND o.order_status = 'completed'
GROUP BY p.payment_method
ORDER BY total_amount DESC";

$stmt = $conn->prepare($payment_query);
$stmt->bind_param("iss", $_SESSION['employee_id'], $start_date, $end_date);
$stmt->execute();
$payment_stats = $stmt->get_result();

// Daily Sales Trend
$daily_trend_query = "SELECT 
    DATE(order_date) as sale_date,
    COUNT(*) as order_count,
    COALESCE(SUM(order_total_amount), 0) as daily_total
FROM tbl_pos_orders
WHERE employee_id = ?
AND DATE(order_date) BETWEEN ? AND ?
AND order_status = 'completed'
GROUP BY DATE(order_date)
ORDER BY sale_date";

$stmt = $conn->prepare($daily_trend_query);
$stmt->bind_param("iss", $_SESSION['employee_id'], $start_date, $end_date);
$stmt->execute();
$daily_trend = $stmt->get_result();

// Customer Type Analysis
$customer_query = "SELECT 
    c.customer_type,
    COUNT(*) as order_count,
    COALESCE(SUM(o.order_total_amount), 0) as total_amount,
    COUNT(DISTINCT c.cust_id) as unique_customers
FROM tbl_pos_orders o
JOIN tbl_customer c ON o.cust_id = c.cust_id
WHERE o.employee_id = ?
AND DATE(o.order_date) BETWEEN ? AND ?
AND o.order_status = 'completed'
GROUP BY c.customer_type";

$stmt = $conn->prepare($customer_query);
$stmt->bind_param("iss", $_SESSION['employee_id'], $start_date, $end_date);
$stmt->execute();
$customer_stats = $stmt->get_result();

// Order Type Distribution
$order_type_query = "SELECT 
    order_type,
    COUNT(*) as type_count,
    COALESCE(SUM(order_total_amount), 0) as total_amount
FROM tbl_pos_orders
WHERE employee_id = ?
AND DATE(order_date) BETWEEN ? AND ?
AND order_status = 'completed'
GROUP BY order_type";

$stmt = $conn->prepare($order_type_query);
$stmt->bind_param("iss", $_SESSION['employee_id'], $start_date, $end_date);
$stmt->execute();
$order_type_stats = $stmt->get_result();

// Performance Metrics
$performance_query = "SELECT 
    COUNT(*) as total_orders,
    COUNT(CASE WHEN order_status = 'completed' THEN 1 END) as completed_orders,
    COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) as cancelled_orders,
    COALESCE(SUM(CASE WHEN order_status = 'completed' THEN order_total_amount END), 0) as total_sales,
    COALESCE(AVG(CASE WHEN order_status = 'completed' THEN order_total_amount END), 0) as avg_order_value
FROM tbl_pos_orders
WHERE employee_id = ?
AND DATE(order_date) BETWEEN ? AND ?";

$stmt = $conn->prepare($performance_query);
$stmt->bind_param("iss", $_SESSION['employee_id'], $start_date, $end_date);
$stmt->execute();
$performance = $stmt->get_result()->fetch_assoc();
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid py-0">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">Analytics Report</h1>
                <form class="d-flex gap-2" method="GET">
                    <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                    <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                    <button type="submit" class="btn btn-primary">Update</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Performance Overview -->
    <div class="row mb-4">
        <div class="col-12">
            <h5 class="mb-3">Performance Overview</h5>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card border-primary h-100">
                <div class="card-body">
                    <h6 class="text-uppercase mb-1">Total Sales</h6>
                    <h3 class="mb-0">₱<?php echo number_format($performance['total_sales'], 2); ?></h3>
                    <small class="text-muted"><?php echo $performance['completed_orders']; ?> completed orders</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card border-success h-100">
                <div class="card-body">
                    <h6 class="text-uppercase mb-1">Average Order Value</h6>
                    <h3 class="mb-0">₱<?php echo number_format($performance['avg_order_value'], 2); ?></h3>
                    <small class="text-muted">Per completed order</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card border-info h-100">
                <div class="card-body">
                    <h6 class="text-uppercase mb-1">Completion Rate</h6>
                    <h3 class="mb-0">
                        <?php 
                        $completion_rate = $performance['total_orders'] > 0 
                            ? round(($performance['completed_orders'] / $performance['total_orders']) * 100, 1)
                            : 0;
                        echo $completion_rate . '%';
                        ?>
                    </h3>
                    <small class="text-muted">Of total orders</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card border-warning h-100">
                <div class="card-body">
                    <h6 class="text-uppercase mb-1">Cancellation Rate</h6>
                    <h3 class="mb-0">
                        <?php 
                        $cancellation_rate = $performance['total_orders'] > 0 
                            ? round(($performance['cancelled_orders'] / $performance['total_orders']) * 100, 1)
                            : 0;
                        echo $cancellation_rate . '%';
                        ?>
                    </h3>
                    <small class="text-muted">Of total orders</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Daily Sales Trend Chart -->
        <div class="col-md-8 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Daily Sales Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyTrendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Payment Methods</h5>
                </div>
                <div class="card-body">
                    <canvas id="paymentMethodChart"></canvas>
                    <div class="table-responsive mt-3">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th class="text-end">Count</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                mysqli_data_seek($payment_stats, 0);
                                while ($payment = $payment_stats->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                        <td class="text-end"><?php echo $payment['transaction_count']; ?></td>
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

    <div class="row">
        <!-- Customer Analysis -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Customer Analysis</h5>
                </div>
                <div class="card-body">
                    <canvas id="customerChart"></canvas>
                    <div class="table-responsive mt-3">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th class="text-end">Orders</th>
                                    <th class="text-end">Customers</th>
                                    <th class="text-end">Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($customer = $customer_stats->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo ucfirst($customer['customer_type']); ?></td>
                                        <td class="text-end"><?php echo $customer['order_count']; ?></td>
                                        <td class="text-end"><?php echo $customer['unique_customers']; ?></td>
                                        <td class="text-end">₱<?php echo number_format($customer['total_amount'], 2); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Type Distribution -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Order Type Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="orderTypeChart"></canvas>
                    <div class="table-responsive mt-3">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th class="text-end">Count</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($type = $order_type_stats->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo ucfirst($type['order_type']); ?></td>
                                        <td class="text-end"><?php echo $type['type_count']; ?></td>
                                        <td class="text-end">₱<?php echo number_format($type['total_amount'], 2); ?></td>
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
// Prepare data for charts
<?php
// Reset result pointers
mysqli_data_seek($payment_stats, 0);
mysqli_data_seek($daily_trend, 0);
mysqli_data_seek($customer_stats, 0);
mysqli_data_seek($order_type_stats, 0);
?>

// Daily Trend Chart
const dailyTrendData = {
    labels: [<?php 
        $dates = [];
        $amounts = [];
        $counts = [];
        while ($day = $daily_trend->fetch_assoc()) {
            $dates[] = "'" . date('M d', strtotime($day['sale_date'])) . "'";
            $amounts[] = $day['daily_total'];
            $counts[] = $day['order_count'];
        }
        echo implode(',', $dates);
    ?>],
    amounts: [<?php echo implode(',', $amounts); ?>],
    counts: [<?php echo implode(',', $counts); ?>]
};

new Chart(document.getElementById('dailyTrendChart'), {
    type: 'line',
    data: {
        labels: dailyTrendData.labels,
        datasets: [{
            label: 'Sales Amount',
            data: dailyTrendData.amounts,
            borderColor: 'rgb(75, 192, 192)',
            yAxisID: 'y',
            tension: 0.1
        }, {
            label: 'Order Count',
            data: dailyTrendData.counts,
            borderColor: 'rgb(255, 99, 132)',
            yAxisID: 'y1',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Sales Amount (₱)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Order Count'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});

// Payment Methods Chart
const paymentData = {
    labels: [<?php 
        $methods = [];
        $amounts = [];
        while ($payment = $payment_stats->fetch_assoc()) {
            $methods[] = "'" . ucfirst($payment['payment_method']) . "'";
            $amounts[] = $payment['total_amount'];
        }
        echo implode(',', $methods);
    ?>],
    amounts: [<?php echo implode(',', $amounts); ?>]
};

new Chart(document.getElementById('paymentMethodChart'), {
    type: 'doughnut',
    data: {
        labels: paymentData.labels,
        datasets: [{
            data: paymentData.amounts,
            backgroundColor: [
                'rgb(255, 99, 132)',
                'rgb(54, 162, 235)',
                'rgb(255, 205, 86)',
                'rgb(75, 192, 192)',
                'rgb(153, 102, 255)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Customer Analysis Chart
const customerData = {
    labels: [<?php 
        $types = [];
        $amounts = [];
        while ($customer = $customer_stats->fetch_assoc()) {
            $types[] = "'" . ucfirst($customer['customer_type']) . "'";
            $amounts[] = $customer['total_amount'];
        }
        echo implode(',', $types);
    ?>],
    amounts: [<?php echo implode(',', $amounts); ?>]
};

new Chart(document.getElementById('customerChart'), {
    type: 'pie',
    data: {
        labels: customerData.labels,
        datasets: [{
            data: customerData.amounts,
            backgroundColor: [
                'rgb(255, 99, 132)',
                'rgb(54, 162, 235)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Order Type Distribution Chart
const orderTypeData = {
    labels: [<?php 
        $types = [];
        $counts = [];
        while ($type = $order_type_stats->fetch_assoc()) {
            $types[] = "'" . ucfirst($type['order_type']) . "'";
            $counts[] = $type['type_count'];
        }
        echo implode(',', $types);
    ?>],
    counts: [<?php echo implode(',', $counts); ?>]
};

new Chart(document.getElementById('orderTypeChart'), {
    type: 'bar',
    data: {
        labels: orderTypeData.labels,
        datasets: [{
            label: 'Number of Orders',
            data: orderTypeData.counts,
            backgroundColor: 'rgb(75, 192, 192)'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Number of Orders'
                }
            }
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?> 