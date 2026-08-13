<?php
header('Content-Type: application/json');

// Localhost Database Configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ether_mobility";

// Database Connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database Connection Failed: " . $conn->connect_error]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and read form inputs
    $vehicle_number   = trim($_POST['vehicle_number'] ?? '');
    $model_name       = trim($_POST['model_name'] ?? '');
    $chassis_number   = trim($_POST['chassis_number'] ?? '');
    $motor_number     = trim($_POST['motor_number'] ?? '');
    $battery_serial   = trim($_POST['battery_serial'] ?? '');
    $battery_type     = trim($_POST['battery_type'] ?? 'Lithium-ion');
    $rc_number        = trim($_POST['rc_number'] ?? '');
    $rto_reg_date     = !empty($_POST['rto_reg_date']) ? $_POST['rto_reg_date'] : NULL;
    $insurance_policy = trim($_POST['insurance_policy'] ?? '');
    $insurance_expiry = !empty($_POST['insurance_expiry']) ? $_POST['insurance_expiry'] : NULL;
    $fitness_expiry   = !empty($_POST['fitness_expiry']) ? $_POST['fitness_expiry'] : NULL;
    $permit_expiry    = !empty($_POST['permit_expiry']) ? $_POST['permit_expiry'] : NULL;

    // Validate Required Fields
    if (empty($vehicle_number) || empty($model_name) || empty($chassis_number) || empty($motor_number) || empty($battery_serial)) {
        echo json_encode(["status" => "error", "message" => "Please fill all mandatory fields marked with *"]);
        exit();
    }

    // Prepare SQL Statement
    $stmt = $conn->prepare("INSERT INTO vehicles (vehicle_number, model_name, chassis_number, motor_number, battery_serial, battery_type, rc_number, rto_reg_date, insurance_policy, insurance_expiry, fitness_expiry, permit_expiry, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Unassigned')");
    
    $stmt->bind_param(
        "ssssssssssss", 
        $vehicle_number, 
        $model_name, 
        $chassis_number, 
        $motor_number, 
        $battery_serial, 
        $battery_type, 
        $rc_number, 
        $rto_reg_date, 
        $insurance_policy, 
        $insurance_expiry, 
        $fitness_expiry, 
        $permit_expiry
    );

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "RTO Vehicle Registered Successfully in Local Database!"]);
    } else {
        if ($conn->errno === 1062) {
            echo json_encode(["status" => "error", "message" => "Vehicle Number, Chassis Number, or Motor Number already exists!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database Error: " . $stmt->error]);
        }
    }

    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Invalid Request Method"]);
}

$conn->close();
?>