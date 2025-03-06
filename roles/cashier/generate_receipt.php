<?php
session_start();
require_once '../../config/database.php';

// Check if user is Cashier
if (!isset($_SESSION['user_id']) || $_SESSION['job_name'] !== 'Cashier') {
    header("Location: ../../auth/login.php");
    exit();
}

// Check if receipt ID is provided
if (!isset($_GET['id'])) {
    die("Receipt ID not provided");
}

$receipt_id = $_GET['id'];

// Get receipt details
$query = "SELECT r.*, po.order_type, po.order_status,
          CONCAT(e.first_name, ' ', e.last_name) as employee_name,
          c.customer_name, c.customer_address, c.customer_phone
          FROM tbl_receipts r
          JOIN tbl_pos_orders po ON r.pos_order_id = po.pos_order_id
          JOIN tbl_employee e ON r.employee_id = e.employee_id
          LEFT JOIN tbl_customer c ON po.cust_id = c.cust_id
          WHERE r.receipt_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $receipt_id);
$stmt->execute();
$receipt = $stmt->get_result()->fetch_assoc();

if (!$receipt) {
    die("Receipt not found");
}

// Get order items
$items_query = "SELECT poi.*, p.product_name, p.product_selling_price
                FROM tbl_pos_order_items poi
                JOIN tbl_products p ON poi.product_id = p.product_id
                WHERE poi.pos_order_id = ?";
$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $receipt['pos_order_id']);
$stmt->execute();
$items_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo $receipt_id; ?></title>
    <style>
        @media print {
            body {
                font-family: 'Courier New', monospace;
                font-size: 12px;
                line-height: 1.4;
                margin: 0;
                padding: 20px;
                width: 80mm;
            }
            .no-print {
                display: none;
            }
            .receipt {
                border: 1px solid #000;
                padding: 10px;
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
            }
            .header h1 {
                font-size: 16px;
                margin: 0;
            }
            .header p {
                margin: 5px 0;
            }
            .details {
                margin-bottom: 20px;
            }
            .details p {
                margin: 5px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            th, td {
                text-align: left;
                padding: 5px;
                border-bottom: 1px dashed #000;
            }
            th {
                font-weight: bold;
            }
            .total {
                text-align: right;
                font-weight: bold;
            }
            .footer {
                text-align: center;
                margin-top: 20px;
                font-size: 10px;
            }
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        .receipt {
            background-color: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            max-width: 80mm;
            width: 100%;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 24px;
            margin: 0;
            color: #333;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .details {
            margin-bottom: 20px;
        }
        .details p {
            margin: 5px 0;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .total {
            text-align: right;
            font-weight: bold;
            font-size: 18px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 12px;
        }
        .no-print {
            margin-top: 20px;
            text-align: center;
        }
        .no-print button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h1>Your Company Name</h1>
            <p>123 Business Street, City, Country</p>
            <p>Phone: (123) 456-7890</p>
            <p>Email: info@company.com</p>
        </div>

        <div class="details">
            <p><strong>Receipt #:</strong> <?php echo $receipt_id; ?></p>
            <p><strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($receipt['receipt_date'])); ?></p>
            <p><strong>Cashier:</strong> <?php echo htmlspecialchars($receipt['employee_name']); ?></p>
            <?php if ($receipt['customer_name']): ?>
                <p><strong>Customer:</strong> <?php echo htmlspecialchars($receipt['customer_name']); ?></p>
                <?php if ($receipt['customer_phone']): ?>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($receipt['customer_phone']); ?></p>
                <?php endif; ?>
            <?php endif; ?>
            <p><strong>Order Type:</strong> <?php echo ucfirst($receipt['order_type']); ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = $items_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td><?php echo $item['quantity_sold']; ?></td>
                        <td>₱<?php echo number_format($item['item_price'], 2); ?></td>
                        <td>₱<?php echo number_format($item['item_total_amount'], 2); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="total">Total Amount:</td>
                    <td class="total">₱<?php echo number_format($receipt['receipt_total_amount'], 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="footer">
            <p>Thank you for your business!</p>
            <p>Please keep this receipt for your records</p>
        </div>
    </div>

    <div class="no-print">
        <button onclick="window.print()">Print Receipt</button>
        <button onclick="window.close()">Close</button>
    </div>

    <script>
        // Automatically trigger print dialog when page loads
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html> 