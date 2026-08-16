<?php
header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "ether_mobility");

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database Connection Failed"]);
    exit();
}

// Fetch all Vehicles with their assigned Drivers & GPS info
$sql = "SELECT 
            v.vehicle_id, 
            v.vehicle_number, 
            v.model_name, 
            v.status AS vehicle_status,
            v.latitude,
            v.longitude,
            v.last_ping,
            d.driver_id,
            d.reg_code,
            d.full_name AS driver_name,
            d.phone AS driver_phone
        FROM vehicles v
        LEFT JOIN drivers d ON v.vehicle_id = d.vehicle_id AND d.status = 'Active'
        ORDER BY v.vehicle_id ASC";

$result = $conn->query($sql);
$fleet = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $fleet[] = $row;
    }
    echo json_encode(["status" => "success", "fleet" => $fleet]);
} else {
    echo json_encode(["status" => "error", "message" => $conn->error]);
}

$conn->close();
?>