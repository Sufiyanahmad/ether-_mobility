<?php
header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "ether_mobility");

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database Connection Failed: " . $conn->connect_error]);
    exit();
}

// 1. Get Unassigned Vehicles
if (isset($_GET['get_vehicles'])) {
    $result = $conn->query("SELECT vehicle_id, vehicle_number, model_name FROM vehicles WHERE status = 'Unassigned'");
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit();
}

// 2. Process Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_id       = intval($_POST['vehicle_id'] ?? 0);
    $full_name        = trim($_POST['full_name'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $aadhaar_number   = trim($_POST['aadhaar_number'] ?? '');
    $address          = trim($_POST['address'] ?? '');
    $security_deposit = floatval($_POST['security_deposit'] ?? 10500);

    if (empty($vehicle_id) || empty($full_name) || empty($phone) || empty($aadhaar_number) || empty($address)) {
        echo json_encode(["status" => "error", "message" => "Please fill all mandatory fields!"]);
        exit();
    }

    // Generate Unique 5-Digit Reg Code
    do {
        $reg_code = strval(rand(10000, 99999));
        $check = $conn->query("SELECT driver_id FROM drivers WHERE reg_code = '$reg_code'");
    } while ($check && $check->num_rows > 0);

    // Upload Directory
    $uploadDir = "uploads/kyc_docs/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    function uploadDoc($fileKey, $dir) {
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
            $fileName = $fileKey . "_" . time() . "_" . rand(1000, 9999) . "." . $ext;
            $targetPath = $dir . $fileName;
            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetPath)) {
                return $targetPath;
            }
        }
        return null;
    }

    $aadhaar_doc    = uploadDoc('aadhaar_doc', $uploadDir);
    $agreement_doc  = uploadDoc('agreement_doc', $uploadDir);
    $live_kyc_photo = uploadDoc('live_kyc_photo', $uploadDir);

    if (!$aadhaar_doc || !$agreement_doc || !$live_kyc_photo) {
        echo json_encode(["status" => "error", "message" => "Document upload failed! Please select all 3 files."]);
        exit();
    }

    // Insert Query
    $stmt = $conn->prepare("INSERT INTO drivers (reg_code, full_name, phone, aadhaar_number, address, security_deposit, vehicle_id, aadhaar_doc, agreement_doc, live_kyc_photo, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
    $stmt->bind_param("sssssdisss", $reg_code, $full_name, $phone, $aadhaar_number, $address, $security_deposit, $vehicle_id, $aadhaar_doc, $agreement_doc, $live_kyc_photo);

    if ($stmt->execute()) {
        $conn->query("UPDATE vehicles SET status = 'Assigned' WHERE vehicle_id = $vehicle_id");
        echo json_encode([
            "status" => "success", 
            "message" => "Driver Registered Successfully!", 
            "reg_code" => $reg_code
        ]);
    } else {
        if ($conn->errno === 1062) {
            echo json_encode(["status" => "error", "message" => "Mobile or Aadhaar Number is already registered!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "DB Error: " . $stmt->error]);
        }
    }

    $stmt->close();
}

$conn->close();
?>