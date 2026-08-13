<?php
header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "ether_mobility");

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database Connection Failed"]);
    exit();
}

// 1. Vehicle Statistics
$v_total      = $conn->query("SELECT COUNT(*) AS total FROM vehicles")->fetch_assoc()['total'] ?? 0;
$v_assigned   = $conn->query("SELECT COUNT(*) AS total FROM vehicles WHERE status = 'Assigned'")->fetch_assoc()['total'] ?? 0;
$v_unassigned = $conn->query("SELECT COUNT(*) AS total FROM vehicles WHERE status = 'Unassigned'")->fetch_assoc()['total'] ?? 0;

// 2. Active Driver Count
$d_active     = $conn->query("SELECT COUNT(*) AS total FROM drivers WHERE status = 'Active'")->fetch_assoc()['total'] ?? 0;

// 3. Weekly Collection Summary
$monday = date('Y-m-d', strtotime('monday this week'));
$c_res = $conn->query("SELECT SUM(total_due) AS target, SUM(amount_paid) AS collected, SUM(balance_due) AS pending FROM weekly_collections WHERE week_start_date = '$monday'");
$c_data = $c_res->fetch_assoc();

// 4. Recent Active Drivers Table List
$recent_drivers = $conn->query("SELECT d.reg_code, d.full_name, d.phone, v.vehicle_number, d.registered_at 
                                FROM drivers d 
                                LEFT JOIN vehicles v ON d.vehicle_id = v.vehicle_id 
                                WHERE d.status = 'Active' 
                                ORDER BY d.driver_id DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    "status" => "success",
    "stats" => [
        "total_vehicles"   => $v_total,
        "assigned_vehicles" => $v_assigned,
        "free_vehicles"    => $v_unassigned,
        "active_drivers"   => $d_active,
        "weekly_target"    => floatval($c_data['target'] ?? 0),
        "weekly_collected" => floatval($c_data['collected'] ?? 0),
        "weekly_pending"   => floatval($c_data['pending'] ?? 0)
    ],
    "recent_drivers" => $recent_drivers
]);

$conn->close();
?>