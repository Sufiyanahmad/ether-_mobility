<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

error_reporting(0);
mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli("localhost", "root", "", "ether_mobility");

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

// 1. Assign Vehicle Action
if ($action === 'assign_vehicle') {
    $data = json_decode(file_get_contents('php://input'), true);
    $driver_id = intval($data['driver_id'] ?? 0);
    $vehicle_id = intval($data['vehicle_id'] ?? 0);

    if ($driver_id <= 0 || $vehicle_id <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid ID"]);
        exit;
    }

    $conn->begin_transaction();
    try {
        $stmt1 = $conn->prepare("UPDATE drivers SET vehicle_id = ? WHERE driver_id = ?");
        $stmt1->bind_param("ii", $vehicle_id, $driver_id);
        $stmt1->execute();

        $stmt2 = $conn->prepare("UPDATE vehicles SET status = 'Assigned' WHERE vehicle_id = ?");
        $stmt2->bind_param("i", $vehicle_id);
        $stmt2->execute();

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Vehicle assigned successfully."]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    $conn->close();
    exit;
}

// 2. Available Vehicles Action
if ($action === 'get_available_vehicles') {
    $sql = "
        SELECT vehicle_id, vehicle_number, model_name 
        FROM vehicles 
        WHERE vehicle_id NOT IN (
            SELECT vehicle_id FROM drivers WHERE vehicle_id IS NOT NULL AND status = 'Active'
        ) AND status != 'Scrapped'
        ORDER BY vehicle_id ASC
    ";
    $res = $conn->query($sql);
    $vehicles = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $vehicles[] = [
                "vehicle_id" => intval($row['vehicle_id']),
                "vehicle_number" => $row['vehicle_number'],
                "model_name" => $row['model_name'] ?? 'Mayuri Deluxe Pro'
            ];
        }
    }
    echo json_encode(["status" => "success", "vehicles" => $vehicles]);
    $conn->close();
    exit;
}

// 3. Driver Profile Popup Action
if ($action === 'get_driver_profile') {
    $driver_id = intval($_GET['driver_id'] ?? 0);

    $d_res = $conn->query("
        SELECT d.*, v.vehicle_number, v.model_name 
        FROM drivers d 
        LEFT JOIN vehicles v ON d.vehicle_id = v.vehicle_id 
        WHERE d.driver_id = $driver_id
    ");

    if (!$d_res || $d_res->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Driver not found"]);
        exit;
    }

    $d = $d_res->fetch_assoc();
    $is_assigned = !empty($d['vehicle_id']);

    $col_res = $conn->query("SELECT * FROM weekly_collections WHERE driver_id = $driver_id ORDER BY 1 DESC");
    
    $total_paid = 0.0;
    $current_dues = 0.0;
    $has_records = false;
    $last_amt = 0.0;
    $last_date = null;

    if ($col_res && $col_res->num_rows > 0) {
        $has_records = true;
        $is_latest = true;

        while ($c = $col_res->fetch_assoc()) {
            $p_amt = floatval($c['paid_amount'] ?? $c['collected_amount'] ?? $c['amount_paid'] ?? 0);
            $d_amt = floatval($c['due_amount'] ?? $c['target_amount'] ?? $c['rent_amount'] ?? 3500);
            $arr_amt = floatval($c['arrears_amount'] ?? $c['balance_amount'] ?? $c['balance'] ?? 0);

            $total_paid += $p_amt;

            if ($is_latest) {
                $is_latest = false;
                if ($arr_amt > 0) {
                    $current_dues = $arr_amt;
                } else {
                    $current_dues = max(0, $d_amt - $p_amt);
                }
            }

            if ($last_amt == 0 && $p_amt > 0) {
                $last_amt = $p_amt;
                $last_date = !empty($c['payment_date']) ? $c['payment_date'] : (!empty($c['collection_date']) ? $c['collection_date'] : (!empty($c['created_at']) ? $c['created_at'] : (!empty($c['week_start_date']) ? $c['week_start_date'] : null)));
            }
        }
    }

    $final_dues = $has_records ? $current_dues : ($is_assigned ? 3500.0 : 0.0);

    echo json_encode([
        "status" => "success",
        "profile" => [
            "driver_id" => intval($d['driver_id']),
            "reg_code" => $d['reg_code'] ?? 'N/A',
            "full_name" => $d['full_name'],
            "phone" => $d['phone'],
            "aadhaar_number" => $d['aadhaar_number'] ?? 'N/A',
            "address" => $d['address'] ?? 'N/A',
            "security_deposit" => floatval($d['security_deposit'] ?? 10500),
            "vehicle_number" => $d['vehicle_number'] ?? 'Unassigned',
            "model_name" => $d['model_name'] ?? '',
            "live_kyc_photo" => $d['live_kyc_photo'] ?? '',
            "registered_at" => $d['registered_at'],
            "total_paid_alltime" => $total_paid,
            "total_dues_alltime" => $final_dues,
            "last_paid_amount" => $last_amt,
            "last_payment_date" => $last_date
        ]
    ]);
    $conn->close();
    exit;
}

// 4. Default: Live Stats & Directory Dynamic Sync
$total_v = 0; $assigned_v = 0; $free_v = 0; $active_d = 0;

$r1 = $conn->query("SELECT COUNT(*) AS c FROM vehicles WHERE status != 'Scrapped'");
if ($r1) $total_v = intval($r1->fetch_assoc()['c']);

$r2 = $conn->query("SELECT COUNT(DISTINCT vehicle_id) AS c FROM drivers WHERE vehicle_id IS NOT NULL AND status = 'Active'");
if ($r2) $assigned_v = intval($r2->fetch_assoc()['c']);

$free_v = max(0, $total_v - $assigned_v);

$r3 = $conn->query("SELECT COUNT(*) AS c FROM drivers WHERE status = 'Active'");
if ($r3) $active_d = intval($r3->fetch_assoc()['c']);

// Map latest collection balances per driver dynamically
$driver_latest_dues = [];
$tot_collected = 0.0;

$col_all = $conn->query("SELECT * FROM weekly_collections ORDER BY 1 ASC");
if ($col_all) {
    while ($cr = $col_all->fetch_assoc()) {
        $d_id = intval($cr['driver_id']);
        $p = floatval($cr['paid_amount'] ?? $cr['collected_amount'] ?? $cr['amount_paid'] ?? 0);
        $d = floatval($cr['due_amount'] ?? $cr['target_amount'] ?? $cr['rent_amount'] ?? 3500);
        $a = floatval($cr['arrears_amount'] ?? $cr['balance_amount'] ?? $cr['balance'] ?? 0);

        $tot_collected += $p;

        if ($a > 0) {
            $driver_latest_dues[$d_id] = $a;
        } else {
            $driver_latest_dues[$d_id] = max(0, $d - $p);
        }
    }
}

// Fetch all active drivers
$drivers_res = $conn->query("
    SELECT 
        d.driver_id, 
        d.reg_code, 
        d.full_name, 
        d.phone, 
        d.vehicle_id, 
        d.registered_at, 
        v.vehicle_number, 
        v.model_name
    FROM drivers d
    LEFT JOIN vehicles v ON d.vehicle_id = v.vehicle_id
    WHERE d.status = 'Active'
    ORDER BY (d.vehicle_id IS NULL) DESC, d.driver_id ASC
");

$drivers_list = [];
$total_pending_accumulated = 0.0;

if ($drivers_res) {
    while ($row = $drivers_res->fetch_assoc()) {
        $d_id = intval($row['driver_id']);
        $has_veh = !empty($row['vehicle_id']);

        if (isset($driver_latest_dues[$d_id])) {
            $row['driver_dues'] = $driver_latest_dues[$d_id];
        } else {
            $row['driver_dues'] = $has_veh ? 3500.0 : 0.0;
        }

        $total_pending_accumulated += $row['driver_dues'];
        $drivers_list[] = $row;
    }
}

// Exact Weekly Metrics (Assigned × ₹3,500)
$weekly_target = $assigned_v * 3500.0;
$weekly_collected = $tot_collected;
$weekly_pending = max(0, $weekly_target - $weekly_collected);

echo json_encode([
    "status" => "success",
    "stats" => [
        "total_vehicles" => $total_v,
        "assigned_vehicles" => $assigned_v,
        "free_vehicles" => $free_v,
        "active_drivers" => $active_d,
        "weekly_target" => $weekly_target,
        "weekly_collected" => $weekly_collected,
        "weekly_pending" => $weekly_pending
    ],
    "recent_drivers" => $drivers_list
]);

$conn->close();
?>