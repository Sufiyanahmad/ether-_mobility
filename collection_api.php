<?php
header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "ether_mobility");

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database Connection Failed"]);
    exit();
}

$action = $_GET['action'] ?? '';

// Current Monday to Sunday Week Dates Calculation
$monday = date('Y-m-d', strtotime('monday this week'));
$sunday = date('Y-m-d', strtotime('sunday this week'));

// 1. Fetch Active Drivers & Sync Weekly Due (With Automatic Previous Carry Forward)
if ($action === 'get_weekly_sheet') {
    $active_drivers = $conn->query("SELECT driver_id, reg_code, full_name, phone, vehicle_id FROM drivers WHERE status = 'Active'");
    
    while ($d = $active_drivers->fetch_assoc()) {
        $driver_id = $d['driver_id'];

        // Check if record exists for this current week
        $check = $conn->query("SELECT collection_id FROM weekly_collections WHERE driver_id = $driver_id AND week_start_date = '$monday'");

        if ($check->num_rows === 0) {
            // Find last week's balance due (Previous Arrears)
            $last_week = $conn->query("SELECT balance_due FROM weekly_collections WHERE driver_id = $driver_id ORDER BY collection_id DESC LIMIT 1");
            $prev_arrears = 0.00;
            if ($row = $last_week->fetch_assoc()) {
                $prev_arrears = floatval($row['balance_due']);
            }

            $weekly_rent = 3500.00; // ₹500/day x 7
            $total_due = $weekly_rent + $prev_arrears;
            $balance_due = $total_due;

            // Insert new current week record
            $ins = $conn->prepare("INSERT INTO weekly_collections (driver_id, week_start_date, week_end_date, weekly_rent_due, previous_arrears, total_due, amount_paid, balance_due, payment_status) VALUES (?, ?, ?, ?, ?, ?, 0.00, ?, 'Unpaid')");
            $ins->bind_param("issdddd", $driver_id, $monday, $sunday, $weekly_rent, $prev_arrears, $total_due, $balance_due);
            $ins->execute();
            $ins->close();
        }
    }

    // Fetch Full Weekly List with Vehicle Details
    $sql = "SELECT c.*, d.reg_code, d.full_name, d.phone, v.vehicle_number 
            FROM weekly_collections c
            JOIN drivers d ON c.driver_id = d.driver_id
            LEFT JOIN vehicles v ON d.vehicle_id = v.vehicle_id
            WHERE c.week_start_date = '$monday' AND d.status = 'Active'
            ORDER BY c.payment_status DESC, c.collection_id DESC";
    
    $res = $conn->query($sql);
    $data = $res->fetch_all(MYSQLI_ASSOC);

    // Calculate Dashboard Totals
    $total_target = 0.00;
    $total_collected = 0.00;
    $total_pending = 0.00;

    foreach ($data as $item) {
        $total_target += floatval($item['total_due']);
        $total_collected += floatval($item['amount_paid']);
        $total_pending += floatval($item['balance_due']);
    }

    echo json_encode([
        "status" => "success",
        "week_period" => date("d M", strtotime($monday)) . " - " . date("d M Y", strtotime($sunday)),
        "totals" => [
            "target" => $total_target,
            "collected" => $total_collected,
            "pending" => $total_pending
        ],
        "list" => $data
    ]);
    exit();
}

// 2. Submit / Record Payment Entry
if ($action === 'collect_payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $collection_id = intval($_POST['collection_id'] ?? 0);
    $amount_paid   = floatval($_POST['amount_paid'] ?? 0);
    $payment_mode  = trim($_POST['payment_mode'] ?? 'Cash');

    // Fetch current record
    $res = $conn->query("SELECT total_due FROM weekly_collections WHERE collection_id = $collection_id");
    $rec = $res->fetch_assoc();

    if (!$rec) {
        echo json_encode(["status" => "error", "message" => "Record not found"]);
        exit();
    }

    $total_due = floatval($rec['total_due']);
    $balance_due = $total_due - $amount_paid;
    if ($balance_due < 0) $balance_due = 0;

    $status = 'Unpaid';
    if ($amount_paid >= $total_due) {
        $status = 'Paid';
    } elseif ($amount_paid > 0) {
        $status = 'Partial';
    }

    $stmt = $conn->prepare("UPDATE weekly_collections SET amount_paid = ?, balance_due = ?, payment_status = ?, payment_mode = ?, collected_at = NOW() WHERE collection_id = ?");
    $stmt->bind_param("ddssi", $amount_paid, $balance_due, $status, $payment_mode, $collection_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Payment recorded successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update record"]);
    }
    $stmt->close();
    exit();
}

$conn->close();
?>