<?php
header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "ether_mobility");

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database Connection Failed"]);
    exit();
}

$action = $_GET['action'] ?? 'get_monthly_history';

// 1. Get Monthly Earnings & Net Profit History
if ($action === 'get_monthly_history') {

    // Fetch Total Active/Registered Vehicles Count for Maintenance Calculation
    $v_res = $conn->query("SELECT COUNT(*) AS total_v FROM vehicles");
    $total_vehicles = intval($v_res->fetch_assoc()['total_v'] ?? 0);
    $maint_per_vehicle = 1500.00; // Fixed ₹1,500 / month / vehicle

    // Fetch All Monthly Gross Collections from weekly_collections table
    $sql = "SELECT 
                DATE_FORMAT(week_start_date, '%Y-%m') AS month_key,
                DATE_FORMAT(week_start_date, '%M %Y') AS month_label,
                SUM(amount_paid) AS gross_earnings
            FROM weekly_collections 
            GROUP BY month_key
            ORDER BY month_key DESC";

    $res = $conn->query($sql);
    $monthly_list = [];

    $grand_gross = 0;
    $grand_maint = 0;
    $grand_net = 0;

    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $month_key = $row['month_key'];
            $month_label = $row['month_label'];
            $gross = floatval($row['gross_earnings']);

            // Auto Maintenance Deduction = Vehicles Count * 1500
            $maintenance = $total_vehicles * $maint_per_vehicle;
            
            // Net Profit Calculation
            $net_profit = $gross - $maintenance;

            // Sync or Update into monthly_earnings database table
            $stmt = $conn->prepare("INSERT INTO monthly_earnings (month_year, gross_collected, total_vehicles_active, maintenance_deduction, net_profit) 
                                    VALUES (?, ?, ?, ?, ?) 
                                    ON DUPLICATE KEY UPDATE 
                                    gross_collected = VALUES(gross_collected),
                                    total_vehicles_active = VALUES(total_vehicles_active),
                                    maintenance_deduction = VALUES(maintenance_deduction),
                                    net_profit = VALUES(net_profit)");
            $stmt->bind_param("sdddd", $month_key, $gross, $total_vehicles, $maintenance, $net_profit);
            $stmt->execute();
            $stmt->close();

            $grand_gross += $gross;
            $grand_maint += $maintenance;
            $grand_net += $net_profit;

            $monthly_list[] = [
                "month_key" => $month_key,
                "month_label" => $month_label,
                "vehicles_count" => $total_vehicles,
                "gross_earnings" => $gross,
                "maintenance_deduction" => $maintenance,
                "net_profit" => $net_profit
            ];
        }
    }

    echo json_encode([
        "status" => "success",
        "total_vehicles" => $total_vehicles,
        "maint_rate" => $maint_per_vehicle,
        "grand_totals" => [
            "gross" => $grand_gross,
            "maintenance" => $grand_maint,
            "net_profit" => $grand_net
        ],
        "history" => $monthly_list
    ]);
    exit();
}

$conn->close();
?>