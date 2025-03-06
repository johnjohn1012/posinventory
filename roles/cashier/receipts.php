<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is Cashier
if (!isset($_SESSION['user_id']) || $_SESSION['job_name'] !== 'Cashier') {
    header("Location: ../../auth/login.php");
    exit();
}

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build query for receipts
$query = "SELECT r.*, po.order_type, po.order_status,
          CONCAT(e.first_name, ' ', e.last_name) as employee_name,
          c.customer_name
          FROM tbl_receipts r
          JOIN tbl_pos_orders po ON r.pos_order_id = po.pos_order_id
          JOIN tbl_employee e ON r.employee_id = e.employee_id
          LEFT JOIN tbl_customer c ON po.cust_id = c.cust_id
          WHERE DATE(r.receipt_date) BETWEEN ? AND ?";

$params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
$types = "ss";

if ($status) {
    $query .= " AND r.receipt_status = ?";
    $params[] = $status;
    $types .= "s";
}

$query .= " ORDER BY r.receipt_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Handle mark as paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receipt_id'])) {
    try {
        $conn->begin_transaction();
        
        $receipt_id = $_POST['receipt_id'];
        $new_status = 'paid';

        // Update receipt status
        $update_query = "UPDATE tbl_receipts SET receipt_status = ? WHERE receipt_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $new_status, $receipt_id);
        $stmt->execute();

        // Update payment status
        $payment_query = "UPDATE tbl_payments p
                         JOIN tbl_receipts r ON p.pos_order_id = r.pos_order_id
                         SET p.payment_status = 'completed'
                         WHERE r.receipt_id = ?";
        $stmt = $conn->prepare($payment_query);
        $stmt->bind_param("i", $receipt_id);
        $stmt->execute();

        // Log the transaction
        $log_query = "INSERT INTO tbl_transaction_logs (employee_id, transaction_type, description) 
                     VALUES (?, 'receipt_paid', ?)";
        $description = "Marked Receipt #$receipt_id as paid";
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param("is", $_SESSION['employee_id'], $description);
        $stmt->execute();

        $conn->commit();
        header("Location: receipts.php?success=1");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error updating receipt: " . $e->getMessage();
        header("Location: receipts.php?error=1");
        exit();
    }
}
?>

<div class="container-fluid py-4">
    <h1>Receipts</h1>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
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
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="unpaid" <?php echo $status === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                        <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                    <a href="receipts.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Receipt List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Receipt List</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Receipt ID</th>
                            <th>Date & Time</th>
                            <th>Customer</th>
                            <th>Order Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Cashier</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($receipt = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $receipt['receipt_id']; ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($receipt['receipt_date'])); ?></td>
                                <td><?php echo htmlspecialchars($receipt['customer_name'] ?? 'Walk-in'); ?></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo ucfirst($receipt['order_type']); ?>
                                    </span>
                                </td>
                                <td>₱<?php echo number_format($receipt['receipt_total_amount'], 2); ?></td>
                                <td>
                                    <?php
                                    $status_class = [
                                        'unpaid' => 'bg-warning',
                                        'paid' => 'bg-success'
                                    ][$receipt['receipt_status']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($receipt['receipt_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($receipt['employee_name']); ?></td>
                                <td>
                                    <button type="button" 
                                            class="btn btn-sm btn-primary" 
                                            onclick="viewReceipt(<?php echo $receipt['receipt_id']; ?>)">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    <?php if ($receipt['receipt_status'] === 'unpaid'): ?>
                                        <button type="button" 
                                                class="btn btn-sm btn-success"
                                                onclick="markAsPaid(<?php echo $receipt['receipt_id']; ?>)">
                                            <i class="bi bi-check-circle"></i> Mark Paid
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Details Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Receipt Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="receiptDetails">
                Loading...
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printReceipt()">
                    <i class="bi bi-printer"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Mark as Paid Modal -->
<div class="modal fade" id="markPaidModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mark Receipt as Paid</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="receipt_id" id="paidReceiptId">
                    <p>Are you sure you want to mark this receipt as paid?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewReceipt(receiptId) {
    const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
    modal.show();
    
    // Load receipt details via AJAX
    fetch(`get_receipt_details.php?id=${receiptId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('receiptDetails').innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('receiptDetails').innerHTML = 'Error loading receipt details.';
        });
}

function markAsPaid(receiptId) {
    document.getElementById('paidReceiptId').value = receiptId;
    const modal = new bootstrap.Modal(document.getElementById('markPaidModal'));
    modal.show();
}

function printReceipt() {
    const receiptContent = document.getElementById('receiptDetails').innerHTML;
    const printWindow = window.open('', '', 'height=600,width=800');
    
    printWindow.document.write('<html><head><title>Print Receipt</title>');
    printWindow.document.write('<link href="../../assets/css/bootstrap.min.css" rel="stylesheet">');
    printWindow.document.write('</head><body>');
    printWindow.document.write(receiptContent);
    printWindow.document.write('</body></html>');
    
    printWindow.document.close();
    printWindow.focus();
    
    // Print after CSS loads
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}

// Show success/error messages
<?php if (isset($_GET['success'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Receipt has been marked as paid.',
        timer: 2000
    });
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: '<?php echo $_SESSION['error'] ?? "An error occurred"; ?>',
        timer: 2000
    });
<?php endif; ?>
</script>

<?php require_once '../../includes/footer.php'; ?> 