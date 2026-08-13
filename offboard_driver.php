<?php
header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "ether_mobility");

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database Connection Failed"]);
    exit();
}

$action = $_GET['action'] ?? '';

// 1. Search Active Driver by 5-Digit Reg Code
if ($action === 'search') {
    $reg_id = trim($_GET['reg_id'] ?? '');

    $stmt = $conn->prepare("SELECT d.driver_id, d.reg_code, d.full_name, d.phone, d.security_deposit, d.vehicle_id, v.vehicle_number, v.model_name 
                            FROM drivers d 
                            JOIN vehicles v ON d.vehicle_id = v.vehicle_id 
                            WHERE (d.reg_code = ? OR d.driver_id = ?) AND d.status = 'Active'");
    $stmt->bind_param("ss", $reg_id, $reg_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode(["status" => "success", "data" => $row]);
    } else {
        echo json_encode(["status" => "error", "message" => "No active driver found with Registration ID: $reg_id"]);
    }
    $stmt->close();
    exit();
}

// 2. Process Settlement & Release Vehicle
if ($action === 'process_settlement' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $driver_id  = intval($_POST['driver_id'] ?? 0);
    $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
    $deductions = floatval($_POST['deductions'] ?? 0);

    $base_deposit = 10500.00;
    $net_refunded = $base_deposit - $deductions;
    if ($net_refunded < 0) $net_refunded = 0;

    $conn->begin_transaction();

    try {
        $stmt1 = $conn->prepare("UPDATE drivers SET status = 'Settled', damage_deduction = ?, net_refunded = ?, vehicle_id = NULL, offboarded_at = NOW() WHERE driver_id = ?");
        $stmt1->bind_param("ddi", $deductions, $net_refunded, $driver_id);
        $stmt1->execute();

        $stmt2 = $conn->prepare("UPDATE vehicles SET status = 'Unassigned' WHERE vehicle_id = ?");
        $stmt2->bind_param("i", $vehicle_id);
        $stmt2->execute();

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Settlement Complete! Net Refund: ₹" . number_format($net_refunded, 2)]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "Settlement failed: " . $e->getMessage()]);
    }
    exit();
}

$conn->close();
?>