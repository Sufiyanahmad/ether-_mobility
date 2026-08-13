<?php
$conn = new mysqli("localhost", "root", "", "ether_mobility");

if ($conn->connect_error) {
    die("Database Connection Failed!");
}

$type = $_GET['type'] ?? 'registration';
$id   = trim($_GET['id'] ?? '');

if (empty($id)) {
    die("Invalid Receipt Request!");
}

if ($type === 'registration') {
    $stmt = $conn->prepare("SELECT d.*, v.vehicle_number, v.model_name 
                            FROM drivers d 
                            LEFT JOIN vehicles v ON d.vehicle_id = v.vehicle_id 
                            WHERE d.reg_code = ? OR d.driver_id = ?");
    $stmt->bind_param("ss", $id, $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data) die("Registration Record Not Found!");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Acknowledgement - The Ether Mobility</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; color: #1e293b; padding: 20px; }
        .receipt-card { max-width: 650px; margin: 0 auto; background: #fff; padding: 30px; border: 1px solid #cbd5e1; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .header { text-align: center; border-bottom: 2px solid #0284c7; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { color: #0284c7; margin: 0; font-size: 1.8rem; }
        .header p { color: #64748b; margin: 4px 0 0 0; font-size: 0.9rem; }
        .badge-id { display: inline-block; background: #e0f2fe; color: #0369a1; padding: 6px 16px; font-weight: 700; font-size: 1.2rem; border-radius: 6px; margin-top: 10px; border: 1px dashed #0284c7; }
        .section-title { font-weight: 700; color: #334155; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-top: 20px; font-size: 0.95rem; }
        .row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #f1f5f9; font-size: 0.9rem; }
        .label { color: #64748b; }
        .value { font-weight: 600; color: #0f172a; }
        .terms { font-size: 0.78rem; color: #64748b; margin-top: 25px; background: #f1f5f9; padding: 12px; border-radius: 6px; }
        .signatures { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 20px; font-size: 0.85rem; font-weight: 600; }
        .btn-print { display: block; width: 100%; max-width: 200px; margin: 20px auto 0 auto; padding: 10px; background: #0284c7; color: #fff; text-align: center; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        @media print { .btn-print { display: none; } body { background: #fff; padding: 0; } .receipt-card { box-shadow: none; border: none; } }
    </style>
</head>
<body>
<div class="receipt-card">
    <div class="header">
        <h1>THE ETHER MOBILITY</h1>
        <p>E-Rickshaw Driver Onboarding Acknowledgement Receipt</p>
        <div class="badge-id">REG ID: <?= htmlspecialchars($data['reg_code'] ?? $data['driver_id']); ?></div>
    </div>

    <div class="section-title">1. DRIVER & ALLOCATION DETAILS</div>
    <div class="row"><span class="label">Driver Full Name:</span><span class="value"><?= htmlspecialchars($data['full_name']); ?></span></div>
    <div class="row"><span class="label">Mobile Number:</span><span class="value"><?= htmlspecialchars($data['phone']); ?></span></div>
    <div class="row"><span class="label">Local Address:</span><span class="value"><?= htmlspecialchars($data['address']); ?></span></div>
    <div class="row"><span class="label">Assigned Rickshaw:</span><span class="value"><?= htmlspecialchars($data['vehicle_number'] ?? 'Unassigned'); ?> (<?= htmlspecialchars($data['model_name'] ?? ''); ?>)</span></div>
    <div class="row"><span class="label">Registration Date:</span><span class="value"><?= date("d M Y, h:i A", strtotime($data['registered_at'])); ?></span></div>

    <div class="section-title">2. FINANCIAL & SECURITY DEPOSIT</div>
    <div class="row"><span class="label">Advance Deposit Amount:</span><span class="value" style="color:#0284c7;">₹ 10,500.00 (3 Weeks Advance)</span></div>
    <div class="row"><span class="label">Deposit Status:</span><span class="value" style="color:#16a34a;">✅ CONFIRMED & LOCKED</span></div>

    <div class="terms">
        <strong>Terms & Conditions:</strong>
        <ul>
            <li>Security deposit of ₹10,500 is refundable upon complete vehicle surrender & clear dues.</li>
            <li>Driver agrees to operate the vehicle responsibly and pay standard daily rent.</li>
            <li>Physical copies of identity and agreement verification are held on record.</li>
        </ul>
    </div>

    <div class="signatures">
        <div>_______________________<br>Driver Signature</div>
        <div>_______________________<br>Authorized Signatory (Ether)</div>
    </div>

    <button class="btn-print" onclick="window.print()">🖨️ Print Receipt</button>
</div>
</body>
</html>
<?php
} elseif ($type === 'offboarding') {
    $stmt = $conn->prepare("SELECT * FROM drivers WHERE reg_code = ? OR driver_id = ?");
    $stmt->bind_param("ss", $id, $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data) die("Offboarding Record Not Found!");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Offboarding Settlement Receipt - The Ether Mobility</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; color: #1e293b; padding: 20px; }
        .receipt-card { max-width: 650px; margin: 0 auto; background: #fff; padding: 30px; border: 1px solid #cbd5e1; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .header { text-align: center; border-bottom: 2px solid #d97706; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { color: #d97706; margin: 0; font-size: 1.8rem; }
        .header p { color: #64748b; margin: 4px 0 0 0; font-size: 0.9rem; }
        .badge-id { display: inline-block; background: #fef3c7; color: #b45309; padding: 6px 16px; font-weight: 700; font-size: 1.2rem; border-radius: 6px; margin-top: 10px; border: 1px dashed #d97706; }
        .section-title { font-weight: 700; color: #334155; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-top: 20px; font-size: 0.95rem; }
        .row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #f1f5f9; font-size: 0.9rem; }
        .label { color: #64748b; }
        .value { font-weight: 600; color: #0f172a; }
        .settlement-box { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 15px; border-radius: 8px; text-align: center; margin-top: 20px; }
        .refund-val { font-size: 1.5rem; font-weight: 800; color: #16a34a; }
        .signatures { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 20px; font-size: 0.85rem; font-weight: 600; }
        .btn-print { display: block; width: 100%; max-width: 200px; margin: 20px auto 0 auto; padding: 10px; background: #d97706; color: #fff; text-align: center; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        @media print { .btn-print { display: none; } body { background: #fff; padding: 0; } .receipt-card { box-shadow: none; border: none; } }
    </style>
</head>
<body>
<div class="receipt-card">
    <div class="header">
        <h1>THE ETHER MOBILITY</h1>
        <p>Driver Offboarding & Final Settlement Receipt</p>
        <div class="badge-id">REG ID: <?= htmlspecialchars($data['reg_code'] ?? $data['driver_id']); ?></div>
    </div>

    <div class="section-title">1. DRIVER DETAILS</div>
    <div class="row"><span class="label">Driver Full Name:</span><span class="value"><?= htmlspecialchars($data['full_name']); ?></span></div>
    <div class="row"><span class="label">Mobile Number:</span><span class="value"><?= htmlspecialchars($data['phone']); ?></span></div>
    <div class="row"><span class="label">Offboarding Date:</span><span class="value"><?= !empty($data['offboarded_at']) ? date("d M Y, h:i A", strtotime($data['offboarded_at'])) : date("d M Y"); ?></span></div>

    <div class="section-title">2. FINAL FINANCIAL SETTLEMENT</div>
    <div class="row"><span class="label">Initial Security Deposit:</span><span class="value">₹ 10,500.00</span></div>
    <div class="row"><span class="label">Damage / Rent Deductions:</span><span class="value" style="color:#dc2626;">- ₹ <?= number_format($data['damage_deduction'] ?? 0, 2); ?></span></div>

    <div class="settlement-box">
        <div style="font-size:0.85rem; color:#166534;">NET SECURITY REFUNDED TO DRIVER</div>
        <div class="refund-val">₹ <?= number_format($data['net_refunded'] ?? 10500, 2); ?></div>
    </div>

    <div class="signatures">
        <div>_______________________<br>Driver Signature (Received)</div>
        <div>_______________________<br>Authorized Signatory (Ether)</div>
    </div>

    <button class="btn-print" onclick="window.print()">🖨️ Print Settlement Slip</button>
</div>
</body>
</html>
<?php
}
?>