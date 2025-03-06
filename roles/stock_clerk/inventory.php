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

// Pagination variables
$limit = isset($_GET['entries']) ? (int)$_GET['entries'] : 10; // Default show 10 entries
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$search_condition = '';
if ($search) {
    $search_condition = " WHERE p.product_name LIKE '%$search%' 
                         OR c.category_name LIKE '%$search%'";
}

// Get total records for pagination
$total_records_query = "SELECT COUNT(*) as count FROM tbl_products p 
                       LEFT JOIN tbl_categories c ON p.category_id = c.category_id" . $search_condition;
$total_records_result = $conn->query($total_records_query);
$total_records = $total_records_result->fetch_assoc()['count'];
$total_pages = ceil($total_records / $limit);

// Handle stock movement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    $type = $_POST['type']; // 'in' or 'out'
    
    $conn->begin_transaction();
    
    try {
        // Get current product details
        $stmt = $conn->prepare("SELECT product_name, product_quantity, product_restock_qty FROM tbl_products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        
        // Calculate new quantity
        $new_quantity = $type === 'in' ?    
            $product['product_quantity'] + $quantity : 
            $product['product_quantity'] - $quantity;
        
        // Check if stock out would make quantity negative
        if ($type === 'out' && $new_quantity < 0) {
            throw new Exception("Insufficient stock for this operation");
        }
        
        // Update product quantity
        $stmt = $conn->prepare("UPDATE tbl_products SET product_quantity = ? WHERE product_id = ?");
        $stmt->bind_param("ii", $new_quantity, $product_id);
        $stmt->execute();
        
        // Log transaction
        $description = "Stock {$type} for {$product['product_name']}: {$quantity} units (by " . $_SESSION['full_name'] . ")";
        $stmt = $conn->prepare("INSERT INTO tbl_transaction_log (transaction_type, transaction_description) VALUES ('Stock Update', ?)");
        $stmt->bind_param("s", $description);
        $stmt->execute();
        
        $conn->commit();
        $message = "Stock updated successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// Get products with pagination and search
$query = "SELECT p.*, c.category_name, 
          CONCAT(e.first_name, ' ', e.last_name) as employee_name
          FROM tbl_products p 
          LEFT JOIN tbl_categories c ON p.category_id = c.category_id 
          LEFT JOIN tbl_employee e ON p.employee_id = e.employee_id
          $search_condition
          ORDER BY p.product_name
          LIMIT $offset, $limit";
$result = $conn->query($query);
?>

<div class="container-fluid py-0">
    <div class="row mb-4">
        <div class="col">
            
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
                        <h5 class="mb-0">All Products</h5>
                        <button class="btn btn-primary btn-sm" onclick="printTable()">
                            <i class="fas fa-print me-1"></i> Print
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6 d-flex align-items-center">
                            <label class="me-2">Show</label>
                            <select class="form-select form-select-sm w-auto" id="entriesSelect">
                                <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                            <label class="ms-2">entries</label>
                        </div>
                        <div class="col-md-4 ms-auto">
                            <div class="input-group">
                                <input type="text" class="form-control form-control-sm" id="searchInput" 
                                       placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-secondary btn-sm" type="button" id="searchButton">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover" id="productsTable">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Current Stock</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($product = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                        <td><?php echo $product['product_quantity']; ?></td>
                                        <td>
                                            <?php
                                            $status_class = $product['product_quantity'] == 0 ? 'bg-danger' : 
                                                ($product['product_quantity'] <= $product['product_restock_qty'] ? 'bg-warning' : 'bg-success');
                                            $status_text = $product['product_quantity'] == 0 ? 'Out of Stock' : 
                                                ($product['product_quantity'] <= $product['product_restock_qty'] ? 'Low Stock' : 'In Stock');
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <p>Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> entries</p>
                        </div>
                        <div class="col-md-6">
                            <nav aria-label="Page navigation" class="float-end">
                                <ul class="pagination mb-0">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page-1; ?>&entries=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                    </li>
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&entries=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?>&entries=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Show entries functionality
        const entriesSelect = document.getElementById('entriesSelect');
        entriesSelect.addEventListener('change', function() {
            window.location.href = updateQueryStringParameter(window.location.href, 'entries', this.value);
        });

        // Dynamic Search functionality
        const searchInput = document.getElementById('searchInput');
        const searchButton = document.getElementById('searchButton');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch();
            }, 500); // Wait 500ms after user stops typing
        });

        searchButton.addEventListener('click', performSearch);
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        function performSearch() {
            const searchTerm = searchInput.value.trim();
            window.location.href = updateQueryStringParameter(window.location.href, 'search', searchTerm);
        }

        // Helper function to update URL parameters
        function updateQueryStringParameter(uri, key, value) {
            const re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
            const separator = uri.indexOf('?') !== -1 ? "&" : "?";
            
            if (uri.match(re)) {
                return uri.replace(re, '$1' + key + "=" + value + '$2');
            } else {
                return uri + separator + key + "=" + value;
            }
        }
    });

    // Print functionality
    function printTable() {
        // Create a new window for printing
        const printWindow = window.open('', '_blank');
        
        // Get the current date for the report header
        const currentDate = new Date().toLocaleDateString();
        
        // Create the print content with styling
        const printContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Inventory Report</title>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .print-header { text-align: center; margin-bottom: 20px; }
                    .print-date { text-align: right; margin-bottom: 20px; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f5f5f5; }
                    .status-badge {
                        padding: 5px 10px;
                        border-radius: 4px;
                        color: white;
                        font-size: 12px;
                    }
                    .bg-danger { background-color: #dc3545; }
                    .bg-warning { background-color: #ffc107; }
                    .bg-success { background-color: #28a745; }
                    @media print {
                        .status-badge { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
                    }
                </style>
            </head>
            <body>
                <div class="print-header">
                    <h2>Inventory Report</h2>
                </div>
                <div class="print-date">
                    <strong>Date:</strong> ${currentDate}
                </div>
                ${document.getElementById('productsTable').outerHTML}
            </body>
            </html>
        `;
        
        // Write the content to the new window and print
        printWindow.document.write(printContent);
        printWindow.document.close();
        
        // Wait for images and styles to load
        printWindow.onload = function() {
            printWindow.print();
            // printWindow.close(); // Optional: close after printing
        };
    }
</script>

<?php require_once '../../includes/footer.php'; ?> 