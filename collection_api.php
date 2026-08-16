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

if ($action === 'get_weekly_sheet') {
    $active_drivers = $conn->query("SELECT driver_id, reg_code, full_name, phone, vehicle_id FROM drivers WHERE status = 'Active'");
    
    while ($d = $active_drivers->fetch_assoc()) {
        $driver_id = $d['driver_id'];

        $check = $conn->query("SELECT collection_id FROM weekly_collections WHERE driver_id = $driver_id AND week_start_date = '$monday'");

        if ($check->num_rows === 0) {
            $last_week = $conn->query("SELECT balance_due FROM weekly_collections WHERE driver_id = $driver_id ORDER BY collection_id DESC LIMIT 1");
            $prev_arrears = 0.00;
            if ($row = $last_week->fetch_assoc()) {
                $prev_arrears = floatval($row['balance_due']);
            }

            $weekly_rent = 3500.00;
            $total_due = $weekly_rent + $prev_arrears;
            $balance_due = $total_due;

            $ins = $conn->prepare("INSERT INTO weekly_collections (driver_id, week_start_date, week_end_date, weekly_rent_due, previous_arrears, total_due, amount_paid, balance_due, payment_status) VALUES (?, ?, ?, ?, ?, ?, 0.00, ?, 'Unpaid')");
            $ins->bind_param("issdddd", $driver_id, $monday, $sunday, $weekly_rent, $prev_arrears, $total_due, $balance_due);
            $ins->execute();
            $ins->close();
        }
    }

    $sql = "SELECT c.*, d.reg_code, d.full_name, d.phone, v.vehicle_number 
            FROM weekly_collections c
            JOIN drivers d ON c.driver_id = d.driver_id
            LEFT JOIN vehicles v ON d.vehicle_id = v.vehicle_id
            WHERE c.week_start_date = '$monday' AND d.status = 'Active'
            ORDER BY c.payment_status DESC, c.collection_id DESC";
    
    $res = $conn->query($sql);
    $data = $res->fetch_all(MYSQLI_ASSOC);

    $week_target = 0.00;
    $week_collected = 0.00;
    $week_pending = 0.00;

    foreach ($data as $item) {
        $week_target += floatval($item['total_due']);
        $week_collected += floatval($item['amount_paid']);
        $week_pending += floatval($item['balance_due']);
    }

    // --- ALL-TIME CALCULATIONS ---
    // 1. Gross Collected from Drivers
    $all_gross_res = $conn->query("SELECT SUM(amount_paid) AS all_time_gross FROM weekly_collections");
    $gross_income = floatval($all_gross_res->fetch_assoc()['all_time_gross'] ?? 0.00);

    // 2. All-Time Maintenance Expense (₹1,500/month/vehicle)
    $v_res = $conn->query("SELECT COUNT(*) AS total_v FROM vehicles");
    $total_vehicles = intval($v_res->fetch_assoc()['total_v'] ?? 0);
    
    $distinct_months_res = $conn->query("SELECT COUNT(DISTINCT DATE_FORMAT(week_start_date, '%Y-%m')) AS total_months FROM weekly_collections");
    $total_months = max(1, intval($distinct_months_res->fetch_assoc()['total_months'] ?? 1));
    
    $maintenance_expense = $total_vehicles * $total_months * 1500.00;

    // 3. Profit without minus sign (Absolute value)
    $raw_profit = $gross_income - $maintenance_expense;
    $clean_profit = abs($raw_profit); // Minus sign completely removed

    // 4. Net Earning = Expenses + Profit
    $net_earning = $maintenance_expense + $clean_profit;

    echo json_encode([
        "status" => "success",
        "week_period" => date("d M", strtotime($monday)) . " - " . date("d M Y", strtotime($sunday)),
        "totals" => [
            "target" => $week_target,
            "collected" => $week_collected,
            "pending" => $week_pending
        ],
        "all_time" => [
            "maintenance_expense" => $maintenance_expense,
            "profit" => $clean_profit,
            "net_earning" => $net_earning
        ],
        "list" => $data
    ]);
    exit();
}

if ($action === 'collect_payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $collection_id = intval($_POST['collection_id'] ?? 0);
    $amount_paid   = floatval($_POST['amount_paid'] ?? 0);
    $payment_mode  = trim($_POST['payment_mode'] ?? 'Cash');

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