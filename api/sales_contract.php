<?php
session_start();
require '../db.php'; 

// 1. บอกเบราว์เซอร์ว่าเราจะส่งข้อมูลกลับไปเป็น JSON นะ
header('Content-Type: application/json; charset=utf-8');

// 2. เช็กสิทธิ์
if (!isset($_SESSION['TenantID']) || $_SESSION['Role'] !== 'Tenant') {
    // ถ้ายังไม่ล็อกอิน ให้พ่น JSON ฟ้อง Error กลับไป
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$tenant_id = $_SESSION['TenantID'];

// เตรียมกล่องใส่ข้อมูลที่จะส่งกลับไป
$response = [
    "status" => "success",
    "contract" => null,
    "invoices" => []
];

try {
    // ดึงข้อมูลสัญญา
    $sql_contract = "SELECT lc.ContractID, rs.Location, rs.Floor 
                     FROM leasecontract lc
                     JOIN rentalspace rs ON lc.SpaceID = rs.SpaceID
                     WHERE lc.TenantID = :t_id
                     LIMIT 1";
    $stmt_con = $conn->prepare($sql_contract);
    $stmt_con->execute([':t_id' => $tenant_id]);
    $response['contract'] = $stmt_con->fetch(PDO::FETCH_ASSOC);

    // ดึงข้อมูลใบแจ้งหนี้
    $sql_invoice = "SELECT i.InvoiceID, i.InvoiceDate, i.Amount, i.Status 
                    FROM invoice i
                    JOIN leasecontract lc ON i.ContractID = lc.ContractID
                    WHERE lc.TenantID = :t_id
                    ORDER BY i.InvoiceDate DESC";
    $stmt_inv = $conn->prepare($sql_invoice);
    $stmt_inv->execute([':t_id' => $tenant_id]);
    $response['invoices'] = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

    // 3. แปลง Array เป็น JSON แล้วพ่นออกไปเลย
    echo json_encode($response);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>