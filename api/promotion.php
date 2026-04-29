<?php
session_start();
require '../db.php'; 

header('Content-Type: application/json; charset=utf-8');

// เช็กสิทธิ์
if (!isset($_SESSION['TenantID']) || $_SESSION['Role'] !== 'Tenant') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$tenant_id = $_SESSION['TenantID'];

// 1. กรณีสร้างโปรโมชันใหม่ (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $p_name = $data['p_name'] ?? '';
    $s_date = $data['s_date'] ?? '';
    $e_date = $data['e_date'] ?? '';
    $discount = $data['discount'] ?? 0;
    
    if(empty($p_name) || empty($s_date) || empty($e_date) || empty($discount)) {
        echo json_encode(["status" => "error", "message" => "กรุณากรอกข้อมูลให้ครบถ้วน"]);
        exit();
    }

    $p_id = "PROMO" . rand(100, 999);

    try {
        $sql = "INSERT INTO Promotion (PromotionID, PromotionName, StartDatePromotion, EndDatePromotion, Discount, TenantID) 
                VALUES (:id, :name, :s_date, :e_date, :discount, :t_id)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $p_id,
            ':name' => $p_name,
            ':s_date' => $s_date,
            ':e_date' => $e_date,
            ':discount' => $discount,
            ':t_id' => $tenant_id
        ]);
        echo json_encode(["status" => "success", "message" => "สร้างโปรโมชันสำเร็จ!"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
    }
    exit(); // จบการทำงานสำหรับ POST
}

// 2. กรณีดึงรายการโปรโมชันทั้งหมด (GET)
try {
    $sql_list = "SELECT *, 
                 CASE 
                    WHEN CURDATE() BETWEEN StartDatePromotion AND EndDatePromotion THEN 'กำลังใช้งาน'
                    WHEN CURDATE() < StartDatePromotion THEN 'รอดำเนินการ'
                    ELSE 'หมดอายุ'
                 END as Status
                 FROM Promotion 
                 WHERE TenantID = :t_id
                 ORDER BY StartDatePromotion DESC";
    $stmt_list = $conn->prepare($sql_list);
    $stmt_list->execute([':t_id' => $tenant_id]);
    $promotions = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "data" => $promotions]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>