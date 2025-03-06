<?php
session_start();
require_once '../../config/database.php';

// Check if user is Cashier
if (!isset($_SESSION['user_id']) || $_SESSION['job_name'] !== 'Cashier') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['customer_name']) || !isset($data['customer_type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Customer name and type are required']);
        exit();
    }

    // Validate customer type
    $valid_types = ['individual', 'business'];
    if (!in_array($data['customer_type'], $valid_types)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid customer type']);
        exit();
    }

    try {
        $conn->begin_transaction();

        // Check if customer already exists
        $check_query = "SELECT cust_id FROM tbl_customer WHERE customer_name = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("s", $data['customer_name']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('Customer already exists');
        }

        // Insert new customer
        $insert_query = "INSERT INTO tbl_customer (customer_name, customer_type) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ss", $data['customer_name'], $data['customer_type']);
        $stmt->execute();
        $customer_id = $conn->insert_id;

        // Log the action
        $log_query = "INSERT INTO tbl_transaction_logs (employee_id, transaction_type, description) 
                     VALUES (?, 'customer_added', ?)";
        $description = "Added new {$data['customer_type']} customer: {$data['customer_name']}";
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param("is", $_SESSION['employee_id'], $description);
        $stmt->execute();

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Customer added successfully',
            'data' => [
                'cust_id' => $customer_id,
                'customer_name' => $data['customer_name'],
                'customer_type' => $data['customer_type']
            ]
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// Handle GET request - return customer list
try {
    $query = "SELECT * FROM tbl_customer ORDER BY customer_name";
    $result = $conn->query($query);
    $customers = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $customers
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 