<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is Cashier
if (!isset($_SESSION['user_id']) || $_SESSION['job_name'] !== 'Cashier') {
    header("Location: ../../auth/login.php");
    exit();
}

// Get all categories
$categories_query = "SELECT * FROM tbl_categories WHERE category_name NOT LIKE 'RI_%' ORDER BY category_name";
$categories_result = $conn->query($categories_query);

// Get all active products
$products_query = "SELECT p.*, c.category_name 
                  FROM tbl_products p 
                  LEFT JOIN tbl_categories c ON p.category_id = c.category_id 
                  WHERE p.product_quantity > 0 
                  ORDER BY c.category_name, p.product_name";
$products_result = $conn->query($products_query);

// Get all customers
$customers_query = "SELECT * FROM tbl_customer ORDER BY customer_name";
$customers_result = $conn->query($customers_query);

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        // Create POS order
        $order_query = "INSERT INTO tbl_pos_orders (employee_id, cust_id, order_type, order_status) 
                       VALUES (?, ?, ?, 'pending')";
        $stmt = $conn->prepare($order_query);
        $stmt->bind_param("iis", $_SESSION['employee_id'], $_POST['customer_id'], $_POST['order_type']);
        $stmt->execute();
        $order_id = $conn->insert_id;

        // Insert order items
        $item_query = "INSERT INTO tbl_pos_order_items (pos_order_id, product_id, quantity_sold, item_price, item_total_amount) 
                      VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($item_query);

        $total_amount = 0;
        foreach ($_POST['items'] as $item) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];
            $price = $item['price'];
            $total = $quantity * $price;

            $stmt->bind_param("iiidd", $order_id, $product_id, $quantity, $price, $total);
            $stmt->execute();

            // Update product quantity
            $update_query = "UPDATE tbl_products 
                           SET product_quantity = product_quantity - ? 
                           WHERE product_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ii", $quantity, $product_id);
            $update_stmt->execute();

            $total_amount += $total;
        }

        // Update order total
        $update_order = "UPDATE tbl_pos_orders 
                        SET order_total_amount = ? 
                        WHERE pos_order_id = ?";
        $update_stmt = $conn->prepare($update_order);
        $update_stmt->bind_param("di", $total_amount, $order_id);
        $update_stmt->execute();

        // Create payment record
        $payment_query = "INSERT INTO tbl_payments (pos_order_id, payment_amount, payment_method, payment_status) 
                         VALUES (?, ?, ?, 'pending')";
        $stmt = $conn->prepare($payment_query);
        $stmt->bind_param("ids", $order_id, $total_amount, $_POST['payment_method']);
        $stmt->execute();

        // Create receipt
        $receipt_query = "INSERT INTO tbl_receipts (pos_order_id, employee_id, receipt_total_amount, receipt_status) 
                         VALUES (?, ?, ?, 'unpaid')";
        $stmt = $conn->prepare($receipt_query);
        $stmt->bind_param("iid", $order_id, $_SESSION['employee_id'], $total_amount);
        $stmt->execute();

        $conn->commit();
        $_SESSION['success'] = "Order processed successfully!";
        header("Location: pos.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error processing order: " . $e->getMessage();
    }
}
?>

<div class="container-fluid py-0">
    <div class="row">
        <!-- Products List -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">Products</h5>
                        <div class="d-flex gap-2 flex-grow-1 justify-content-end">
                            <select class="form-select" id="categoryFilter" style="width: auto;">
                                <option value="">All Categories</option>
                                <?php while ($category = $categories_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($category['category_name']); ?>">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="input-group" style="width: 300px;">
                                <input type="text" class="form-control" id="searchProduct" placeholder="Search products...">
                                <button class="btn btn-outline-secondary" type="button">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row" id="productsList">
                        <?php while ($product = $products_result->fetch_assoc()): ?>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <div class="card h-100 shadow-sm">
                                    <?php if ($product['product_image']): ?>
                                        <img src="../../uploads/products/<?php echo htmlspecialchars($product['product_image']); ?>" 
                                             class="card-img-top" alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                             style="height: 120px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                             style="height: 120px;">
                                            <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-1" style="font-size: 0.9rem;"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                                        <p class="card-text mb-1">
                                            <small class="text-muted" style="font-size: 0.8rem;"><?php echo htmlspecialchars($product['category_name']); ?></small>
                                        </p>
                                        <p class="card-text d-flex justify-content-between align-items-center mb-2">
                                            <strong style="font-size: 0.9rem;">₱<?php echo number_format($product['product_selling_price'], 2); ?></strong>
                                            <small class="text-muted" style="font-size: 0.8rem;">Stock: <?php echo $product['product_quantity']; ?></small>
                                        </p>
                                        <button type="button" 
                                                class="btn btn-primary btn-sm w-100 add-to-cart"
                                                style="font-size: 0.8rem; padding: 0.25rem 0.5rem;"
                                                data-product-id="<?php echo $product['product_id']; ?>"
                                                data-product-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                data-product-price="<?php echo $product['product_selling_price']; ?>">
                                            Add to Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <form id="orderForm" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Customer</label>
                            <select class="form-select" name="customer_id" required>
                                <option value="">Select Customer</option>
                                <?php while ($customer = $customers_result->fetch_assoc()): ?>
                                    <option value="<?php echo $customer['cust_id']; ?>">
                                        <?php echo htmlspecialchars($customer['customer_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Order Type</label>
                            <select class="form-select" name="order_type" required>
                                <option value="counter">Counter</option>
                                <option value="qr_code">QR Code</option>
                            </select>
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

                        <div class="table-responsive mb-3">
                            <table class="table" id="cartTable">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Qty</th>
                                        <th>Price</th>
                                        <th>Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-end">Total:</th>
                                        <th colspan="2">₱<span id="orderTotal">0.00</span></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <button type="submit" class="btn btn-success w-100" id="processOrder" disabled>
                            Process Order
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];

function updateCart() {
    const tbody = document.querySelector('#cartTable tbody');
    const totalSpan = document.querySelector('#orderTotal');
    const processButton = document.querySelector('#processOrder');
    
    tbody.innerHTML = '';
    let total = 0;

    cart.forEach((item, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${item.name}</td>
            <td>
                <input type="number" class="form-control form-control-sm" 
                       value="${item.quantity}" min="1" 
                       onchange="updateQuantity(${index}, this.value)">
            </td>
            <td>₱${item.price.toFixed(2)}</td>
            <td>₱${(item.quantity * item.price).toFixed(2)}</td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" 
                        onclick="removeItem(${index})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
        total += item.quantity * item.price;
    });

    totalSpan.textContent = total.toFixed(2);
    processButton.disabled = cart.length === 0;
}

function addToCart(productId, name, price) {
    const existingItem = cart.find(item => item.product_id === productId);
    
    if (existingItem) {
        existingItem.quantity++;
    } else {
        cart.push({
            product_id: productId,
            name: name,
            price: price,
            quantity: 1
        });
    }
    
    updateCart();
}

function updateQuantity(index, quantity) {
    if (quantity < 1) {
        removeItem(index);
    } else {
        cart[index].quantity = parseInt(quantity);
        updateCart();
    }
}

function removeItem(index) {
    cart.splice(index, 1);
    updateCart();
}

// Add to cart button click handler
document.querySelectorAll('.add-to-cart').forEach(button => {
    button.addEventListener('click', () => {
        const productId = button.dataset.productId;
        const name = button.dataset.productName;
        const price = parseFloat(button.dataset.productPrice);
        addToCart(productId, name, price);
    });
});

// Enhanced search and filter functionality
let searchTimeout; // Define searchTimeout variable

function filterProducts() {
    const searchTerm = document.querySelector('#searchProduct').value.toLowerCase();
    const selectedCategory = document.querySelector('#categoryFilter').value.toLowerCase();
    let hasVisibleProducts = false;
    
    document.querySelectorAll('#productsList .col-md-3').forEach(col => {
        const productName = col.querySelector('.card-title').textContent.toLowerCase();
        const categoryName = col.querySelector('.text-muted').textContent.toLowerCase();
        
        // Improved search logic to match both product name and category
        const matchesSearch = searchTerm === '' || 
                            productName.includes(searchTerm) || 
                            categoryName.includes(searchTerm);
        const matchesCategory = !selectedCategory || categoryName === selectedCategory;
        
        if (matchesSearch && matchesCategory) {
            col.style.display = '';
            col.style.opacity = '0';
            setTimeout(() => {
                col.style.transition = 'opacity 0.3s ease-in-out';
                col.style.opacity = '1';
            }, 10);
            hasVisibleProducts = true;
        } else {
            col.style.display = 'none';
        }
    });
    
    // Show/hide no results message
    const noResultsDiv = document.querySelector('#noResults');
    if (!hasVisibleProducts) {
        if (!noResultsDiv) {
            const div = document.createElement('div');
            div.id = 'noResults';
            div.className = 'col-12 text-center py-4';
            div.innerHTML = `
                <div class="text-muted">
                    <i class="bi bi-search fs-2"></i>
                    <p class="mt-2">No products found matching "${searchTerm}"</p>
                </div>
            `;
            document.querySelector('#productsList').appendChild(div);
        }
    } else if (noResultsDiv) {
        noResultsDiv.remove();
    }
}

// Add event listeners for search and category filter with debounce
document.querySelector('#searchProduct').addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(filterProducts, 300);
});

document.querySelector('#searchProduct').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        filterProducts();
    }
});

document.querySelector('#categoryFilter').addEventListener('change', filterProducts);

// Add styles for animations
const style = document.createElement('style');
style.textContent = `
    #productsList .col-md-3 {
        transition: opacity 0.3s ease-in-out;
    }
    .category-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(0,0,0,0.6);
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.8rem;
    }
`;
document.head.appendChild(style);

// Form submission
document.querySelector('#orderForm').addEventListener('submit', (e) => {
    e.preventDefault();
    
    // Add cart items to form
    cart.forEach((item, index) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `items[${index}][product_id]`;
        input.value = item.product_id;
        e.target.appendChild(input);

        const quantityInput = document.createElement('input');
        quantityInput.type = 'hidden';
        quantityInput.name = `items[${index}][quantity]`;
        quantityInput.value = item.quantity;
        e.target.appendChild(quantityInput);

        const priceInput = document.createElement('input');
        priceInput.type = 'hidden';
        priceInput.name = `items[${index}][price]`;
        priceInput.value = item.price;
        e.target.appendChild(priceInput);
    });

    e.target.submit();
});
</script>

<?php require_once '../../includes/footer.php'; ?> 