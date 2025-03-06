<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is Cashier
if (!isset($_SESSION['user_id']) || $_SESSION['job_name'] !== 'Cashier') {
    header("Location: ../../auth/login.php");
    exit();
}

// Fetch orders with related information
$orders_query = "SELECT 
    o.pos_order_id,
    o.order_date,
    o.order_total_amount,
    o.order_status,
    o.order_type,
    c.customer_name,
    c.customer_type,
    p.payment_status,
    p.payment_method,
    r.receipt_status
FROM tbl_pos_orders o
LEFT JOIN tbl_customer c ON o.cust_id = c.cust_id
LEFT JOIN tbl_payments p ON o.pos_order_id = p.pos_order_id
LEFT JOIN tbl_receipts r ON o.pos_order_id = r.pos_order_id
ORDER BY o.order_date DESC";

$orders_result = $conn->query($orders_query);
?>

<div class="container-fluid py-0">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Orders Management</h5>
                        <div class="d-flex gap-2">
                            <input type="text" class="form-control" id="searchOrder" placeholder="Search orders...">
                            <select class="form-select" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Receipt</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($order = $orders_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $order['pos_order_id']; ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($order['customer_name']); ?>
                                            <small class="text-muted d-block"><?php echo ucfirst($order['customer_type']); ?></small>
                                        </td>
                                        <td><span class="badge bg-info"><?php echo ucfirst($order['order_type']); ?></span></td>
                                        <td>₱<?php echo number_format($order['order_total_amount'], 2); ?></td>
                                        <td>
                                            <?php
                                            $status_class = [
                                                'pending' => 'bg-warning',
                                                'completed' => 'bg-success',
                                                'cancelled' => 'bg-danger'
                                            ][$order['order_status']];
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo ucfirst($order['order_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $payment_class = [
                                                'pending' => 'bg-warning',
                                                'completed' => 'bg-success',
                                                'failed' => 'bg-danger'
                                            ][$order['payment_status']];
                                            ?>
                                            <span class="badge <?php echo $payment_class; ?>">
                                                <?php echo ucfirst($order['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $receipt_class = [
                                                'unpaid' => 'bg-warning',
                                                'paid' => 'bg-success',
                                                'refunded' => 'bg-danger'
                                            ][$order['receipt_status']];
                                            ?>
                                            <span class="badge <?php echo $receipt_class; ?>">
                                                <?php echo ucfirst($order['receipt_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        onclick="viewDetails(<?php echo $order['pos_order_id']; ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php if ($order['order_status'] === 'pending'): ?>
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            onclick="completeOrder(<?php echo $order['pos_order_id']; ?>)">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="showCancelModal(<?php echo $order['pos_order_id']; ?>)">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($order['payment_status'] === 'pending'): ?>
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                            onclick="showPaymentModal(<?php echo $order['pos_order_id']; ?>, <?php echo $order['order_total_amount']; ?>)">
                                                        <i class="bi bi-cash"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($order['receipt_status'] !== 'refunded'): ?>
                                                    <button type="button" class="btn btn-sm btn-secondary" 
                                                            onclick="printReceipt(<?php echo $order['pos_order_id']; ?>)">
                                                        <i class="bi bi-printer"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
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

<!-- View Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="orderDetails">
                <!-- Details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Cancel Order Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="cancelForm">
                    <input type="hidden" id="cancelOrderId" name="order_id">
                    <div class="mb-3">
                        <label class="form-label">Reason for Cancellation</label>
                        <textarea class="form-control" name="cancel_reason" required></textarea>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Cancel Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Process Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" id="paymentOrderId" name="order_id">
                    <div class="mb-3">
                        <label class="form-label">Total Amount</label>
                        <input type="text" class="form-control" id="totalAmount" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="credit">Credit Card</option>
                            <option value="debit">Debit Card</option>
                            <option value="gcash">GCash</option>
                            <option value="online">Online Payment</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount Received</label>
                        <input type="number" class="form-control" name="amount_received" step="0.01" required>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Process Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize modals
const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
const cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));

// View order details
async function viewDetails(orderId) {
    try {
        const response = await fetch(`get_order_details.php?order_id=${orderId}`);
        if (!response.ok) throw new Error('Failed to fetch order details');
        
        const details = await response.json();
        const detailsHtml = `
            <div class="row mb-3">
                <div class="col-md-6">
                    <h6>Order Information</h6>
                    <p class="mb-1">Order ID: #${details.order.pos_order_id}</p>
                    <p class="mb-1">Date: ${new Date(details.order.order_date).toLocaleString()}</p>
                    <p class="mb-1">Status: ${details.order.order_status}</p>
                    <p class="mb-1">Type: ${details.order.order_type}</p>
                </div>
                <div class="col-md-6">
                    <h6>Customer Information</h6>
                    <p class="mb-1">Name: ${details.customer.customer_name}</p>
                    <p class="mb-1">Type: ${details.customer.customer_type}</p>
                </div>
            </div>
            <h6>Order Items</h6>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${details.items.map(item => `
                            <tr>
                                <td>${item.product_name}</td>
                                <td>${item.quantity_sold}</td>
                                <td>₱${parseFloat(item.item_price).toFixed(2)}</td>
                                <td>₱${parseFloat(item.item_total_amount).toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-end">Total:</th>
                            <th>₱${parseFloat(details.order.order_total_amount).toFixed(2)}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        `;
        
        document.getElementById('orderDetails').innerHTML = detailsHtml;
        detailsModal.show();
    } catch (error) {
        Swal.fire('Error', 'Failed to load order details', 'error');
    }
}

// Complete order
async function completeOrder(orderId) {
    try {
        const result = await Swal.fire({
            title: 'Complete Order',
            text: 'Are you sure you want to mark this order as completed?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, complete it',
            cancelButtonText: 'No, cancel'
        });

        if (result.isConfirmed) {
            const response = await fetch('update_order_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: orderId,
                    status: 'completed'
                })
            });

            if (!response.ok) throw new Error('Failed to complete order');

            Swal.fire('Success', 'Order has been completed', 'success')
                .then(() => location.reload());
        }
    } catch (error) {
        Swal.fire('Error', 'Failed to complete order', 'error');
    }
}

// Show cancel modal
function showCancelModal(orderId) {
    document.getElementById('cancelOrderId').value = orderId;
    cancelModal.show();
}

// Handle cancel form submission
document.getElementById('cancelForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    try {
        const formData = new FormData(e.target);
        const response = await fetch('update_order_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_id: formData.get('order_id'),
                status: 'cancelled',
                cancel_reason: formData.get('cancel_reason')
            })
        });

        if (!response.ok) throw new Error('Failed to cancel order');

        cancelModal.hide();
        Swal.fire('Success', 'Order has been cancelled', 'success')
            .then(() => location.reload());
    } catch (error) {
        Swal.fire('Error', 'Failed to cancel order', 'error');
    }
});

// Show payment modal
function showPaymentModal(orderId, totalAmount) {
    document.getElementById('paymentOrderId').value = orderId;
    document.getElementById('totalAmount').value = `₱${totalAmount.toFixed(2)}`;
    paymentModal.show();
}

// Handle payment form submission
document.getElementById('paymentForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    try {
        const formData = new FormData(e.target);
        const response = await fetch('update_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_id: formData.get('order_id'),
                payment_method: formData.get('payment_method'),
                amount_received: formData.get('amount_received')
            })
        });

        if (!response.ok) throw new Error('Failed to process payment');

        paymentModal.hide();
        Swal.fire('Success', 'Payment has been processed', 'success')
            .then(() => location.reload());
    } catch (error) {
        Swal.fire('Error', 'Failed to process payment', 'error');
    }
});

// Print receipt
async function printReceipt(orderId) {
    try {
        const response = await fetch(`print_receipt.php?order_id=${orderId}`);
        if (!response.ok) throw new Error('Failed to generate receipt');
        
        const receiptWindow = window.open('', '_blank');
        receiptWindow.document.write(await response.text());
        receiptWindow.document.close();
        receiptWindow.print();
    } catch (error) {
        Swal.fire('Error', 'Failed to print receipt', 'error');
    }
}

// Search and filter functionality
let searchTimeout;
const searchInput = document.getElementById('searchOrder');
const statusFilter = document.getElementById('statusFilter');

function filterOrders() {
    const searchTerm = searchInput.value.toLowerCase();
    const statusTerm = statusFilter.value.toLowerCase();
    
    document.querySelectorAll('tbody tr').forEach(row => {
        const orderId = row.cells[0].textContent.toLowerCase();
        const customer = row.cells[2].textContent.toLowerCase();
        const status = row.cells[5].textContent.toLowerCase();
        
        const matchesSearch = !searchTerm || 
                            orderId.includes(searchTerm) || 
                            customer.includes(searchTerm);
        const matchesStatus = !statusTerm || status.includes(statusTerm);
        
        row.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
}

searchInput.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(filterOrders, 300);
});

statusFilter.addEventListener('change', filterOrders);
</script>

<?php require_once '../../includes/footer.php'; ?>