<style>
.sidebar {
    width: 250px;
    background: var(--topbar-bg);
    box-shadow: 2px 0 5px var(--border-color);
    position: fixed;
    top: 0;
    left: 0;
    height: 100%;
    z-index: 1000;
    transition: transform 0.3s ease, background-color 0.3s ease;
    overflow-y: auto;
}
.sidebar-header {
    height: 60px;
    padding: 0 1rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    position: sticky;
    top: 0;
    background: var(--topbar-bg);
    z-index: 1;
}
.sidebar-header a {
    color: var(--text-color) !important;
}
.sidebar .nav-link {
    color: var(--text-color);
    padding: 0.7rem 1rem;
    display: flex;
    align-items: center;
    transition: all 0.3s;
}
.sidebar .nav-link:hover {
    background-color: var(--body-bg);
    color: #0d6efd;
}
.sidebar .nav-link.active {
    background-color: var(--body-bg);
    color: #0d6efd;
    font-weight: 500;
}
.sidebar .nav-link i {
    width: 1.5rem;
    margin-right: 0.5rem;
    font-size: 1.1rem;
}
.submenu {
    margin-left: 2.5rem;
    border-left: 1px solid var(--border-color);
    padding-left: 0.5rem;
}
.submenu .nav-link {
    padding: 0.5rem 1rem;
}
@media (max-width: 992px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.active {
        transform: translateX(0);
    }
}
</style>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <a class="text-decoration-none" href="<?php echo $role_path; ?>/dashboard.php">
            <h4 class="mb-0 d-flex align-items-center">
                <i class="bi bi-box-seam me-2"></i>
                <span class="fs-6">SIS Harah Rubina Del Dios Farm</span>
            </h4>
        </a>
    </div>

    <div class="nav flex-column mt-3">
        <?php if ($_SESSION['job_name'] !== 'Stock Clerk'): ?>
        <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" 
           href="<?php echo $role_path; ?>/dashboard.php">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        <?php endif; ?>
        
        <?php if (in_array($_SESSION['job_name'], ['Main Admin', 'Admin'])): ?>
        <a class="nav-link <?php echo $current_page === 'inventory.php' ? 'active' : ''; ?>" 
           href="<?php echo $role_path; ?>/inventory.php">
            <i class="bi bi-box-seam"></i>
            <span>Inventory</span>
        </a>
        <?php endif; ?>
        
        <?php if (in_array($_SESSION['job_name'], ['Main Admin', 'Admin', 'Cashier'])): ?>
        <a class="nav-link <?php echo $current_page === 'pos.php' ? 'active' : ''; ?>" 
           href="<?php echo $role_path; ?>/pos.php">
            <i class="bi bi-cart3"></i>
            <span>Point of Sale</span>
        </a>
        <?php endif; ?>
        
        <?php if (in_array($_SESSION['job_name'], ['Main Admin', 'Admin'])): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo in_array($current_page, ['employees.php', 'create_admin.php', 'create_user.php', 'reports.php']) ? 'active' : ''; ?>" 
               data-bs-toggle="collapse" 
               href="#managementSubmenu" 
               role="button">
                <i class="bi bi-gear"></i>
                <span>Management</span>
                <i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <div class="collapse <?php echo in_array($current_page, ['employees.php', 'create_admin.php', 'create_user.php', 'reports.php', 'categories.php']) ? 'show' : ''; ?>" 
                 id="managementSubmenu">
                <div class="submenu">
                    <a class="nav-link <?php echo $current_page === 'employees.php' ? 'active' : ''; ?>" 
                       href="<?php echo $role_path; ?>/employees.php">
                        <i class="bi bi-people"></i>
                        <span>Employees</span>
                    </a>
                    <a class="nav-link <?php echo $current_page === 'users.php' ? 'active' : ''; ?>" 
                       href="<?php echo $role_path; ?>/users.php">
                        <i class="bi bi-person-badge"></i>
                        <span>Users</span>
                    </a>
                    <a class="nav-link <?php echo $current_page === 'categories.php' ? 'active' : ''; ?>" 
                       href="<?php echo $role_path; ?>/categories.php">
                        <i class="bi bi-tags"></i>
                        <span>Categories</span>
                    </a>
                    <a class="nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>" 
                       href="<?php echo $role_path; ?>/reports.php">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Reports</span>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($_SESSION['job_name'] === 'Stock Clerk'): ?>
            <!-- Dashboard -->
            <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" 
               href="../../roles/stock_clerk/dashboard.php">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            <!-- Inventory Management Section -->
            <div class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['inventory.php', 'raw_ingredients.php', 'item_list.php', 'products.php', 'categories.php']) ? 'active' : ''; ?>" 
                   data-bs-toggle="collapse" 
                   href="#inventorySubmenu" 
                   role="button">
                    <i class="bi bi-box-seam"></i>
                    <span>Inventory Management</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo in_array($current_page, ['inventory.php', 'raw_ingredients.php', 'item_list.php', 'products.php', 'categories.php']) ? 'show' : ''; ?>" 
                     id="inventorySubmenu">
                    <div class="submenu">
                        <a class="nav-link <?php echo $current_page === 'inventory.php' ? 'active' : ''; ?>" 
                           href="../../roles/stock_clerk/inventory.php">
                            <i class="bi bi-boxes"></i>
                            <span>Inventory</span>
                        </a>
                        <a class="nav-link <?php echo $current_page === 'raw_ingredients.php' ? 'active' : ''; ?>" 
                           href="../../roles/stock_clerk/raw_ingredients.php">
                            <i class="bi bi-egg"></i>
                            <span>Raw Ingredients</span>
                        </a>
                        <a class="nav-link <?php echo $current_page === 'item_list.php' ? 'active' : ''; ?>" 
                           href="../../roles/stock_clerk/item_list.php">
                            <i class="bi bi-list"></i>
                            <span>Item List</span>
                        </a>
                        <a class="nav-link <?php echo $current_page === 'products.php' ? 'active' : ''; ?>" 
                           href="../../roles/stock_clerk/products.php">
                            <i class="bi bi-box"></i>
                            <span>Products</span>
                        </a>
                        <a class="nav-link <?php echo $current_page === 'categories.php' ? 'active' : ''; ?>" 
                           href="../../roles/stock_clerk/categories.php">
                            <i class="bi bi-tags"></i>
                            <span>Categories</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Purchase Management Section -->
            <div class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['purchase_orders.php', 'receive_orders.php', 'returns.php', 'back_orders.php']) ? 'active' : ''; ?>" 
                   data-bs-toggle="collapse" 
                   href="#purchaseSubmenu" 
                   role="button">
                    <i class="bi bi-cart"></i>
                    <span>Purchase Management</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo in_array($current_page, ['purchase_orders.php', 'receive_orders.php', 'returns.php', 'back_orders.php']) ? 'show' : ''; ?>" 
                     id="purchaseSubmenu">
                    <div class="submenu">
                        <a class="nav-link <?php echo $current_page === 'purchase_orders.php' ? 'active' : ''; ?>" 
                           href="../../roles/stock_clerk/purchase_orders.php">
                            <i class="bi bi-cart-plus"></i>
                            <span>Purchase Orders</span>
                        </a>
                        <a class="nav-link <?php echo $current_page === 'receive_orders.php' ? 'active' : ''; ?>" 
                           href="../../roles/stock_clerk/receive_orders.php">
                            <i class="bi bi-box-seam"></i>
                            <span>Receive Orders</span>
                        </a>
                        <a class="nav-link <?php echo $current_page === 'returns.php' ? 'active' : ''; ?>" 
                           href="../../roles/stock_clerk/returns.php">
                            <i class="bi bi-arrow-return-left"></i>
                            <span>Returns</span>
                        </a>
                        <a class="nav-link <?php echo $current_page === 'back_orders.php' ? 'active' : ''; ?>" 
                           href="../../roles/stock_clerk/back_orders.php">
                            <i class="bi bi-clock-history"></i>
                            <span>Back Orders</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Stock Management Section -->
            <div class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['stock_alerts.php', 'stock_history.php', 'stock_adjustments.php', 'suppliers.php']) ? 'active' : ''; ?>" 
                   data-bs-toggle="collapse" 
                   href="#stockSubmenu" 
                   role="button">
                    <i class="bi bi-boxes"></i>
                    <span>Stock Management</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo in_array($current_page, ['stock_alerts.php', 'stock_history.php', 'stock_adjustments.php', 'suppliers.php']) ? 'show' : ''; ?>" 
                     id="stockSubmenu">
                    <div class="submenu">
                        <a class="nav-link <?php echo $current_page === 'stock_alerts.php' ? 'active' : ''; ?>" 
                           href="../../roles/stock_clerk/stock_alerts.php">
                            <i class="bi bi-exclamation-triangle"></i>
                            <span>Stock Alerts</span>
                        </a>
                        <a class="nav-link <?php echo $current_page === 'stock_history.php' ? 'active' : ''; ?>" 
                           href="../../roles/stock_clerk/stock_history.php">
                            <i class="bi bi-clock-history"></i>
                            <span>Stock History</span>
                        </a>
                        <a class="nav-link <?php echo $current_page === 'stock_adjustments.php' ? 'active' : ''; ?>" 
                           href="../../roles/stock_clerk/stock_adjustments.php">
                            <i class="bi bi-sliders"></i>
                            <span>Stock Adjustments</span>
                        </a>
                        <a class="nav-link <?php echo $current_page === 'suppliers.php' ? 'active' : ''; ?>" 
                           href="../../roles/stock_clerk/suppliers.php">
                            <i class="bi bi-truck"></i>
                            <span>Suppliers</span>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($_SESSION['job_name'] === 'Cashier'): ?>
            <!-- Dashboard -->
       
            <!-- Sales Management Section -->
            <div class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['receipts.php', 'daily_sales.php', 'analytics.php']) ? 'active' : ''; ?>" 
                   data-bs-toggle="collapse" 
                   href="#salesSubmenu" 
                   role="button">
                    <i class="bi bi-cart"></i>
                    <span>Sales Management</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo in_array($current_page, ['orders.php', 'receipts.php', 'daily_sales.php', 'analytics.php']) ? 'show' : ''; ?>" 
                     id="salesSubmenu">
                    <div class="submenu">
                        <a class="nav-link <?php echo $current_page === 'orders.php' ? 'active' : ''; ?>" 
                           href="../../roles/cashier/orders.php">
                            <i class="bi bi-cart-plus"></i>
                            <span>Orders</span>
                        </a>
                        <a class="nav-link <?php echo $current_page === 'receipts.php' ? 'active' : ''; ?>" 
                           href="../../roles/cashier/receipts.php">
                            <i class="bi bi-receipt"></i>
                            <span>Receipts</span>
                        </a>
                        <a class="nav-link <?php echo $current_page === 'daily_sales.php' ? 'active' : ''; ?>" 
                           href="../../roles/cashier/daily_sales.php">
                            <i class="bi bi-graph-up"></i>
                            <span>Daily Sales</span>
                        </a>
                        <a class="nav-link <?php echo $current_page === 'analytics.php' ? 'active' : ''; ?>" 
                           href="../../roles/cashier/analytics.php">
                            <i class="bi bi-bar-chart-line"></i>
                            <span>Analytics Report</span>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div> 