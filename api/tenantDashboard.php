<?php
session_start();
require '../db.php'; // ตรวจสอบ path ไฟล์ db.php ให้ถูกต้องด้วยครับ

header('Content-Type: application/json; charset=utf-8');

// 1. เช็กสิทธิ์
if (!isset($_SESSION['TenantID']) || $_SESSION['Role'] !== 'Tenant') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$tenant_id = $_SESSION['TenantID'];

$response = [
    "status" => "success",
    "total_sales" => 0,
    "low_stock_count" => 0,
    "active_promo" => 0,
    "recent_sales" => []
];

try {
    // 2. ดึงยอดขายรวม
    $sql_sales = "SELECT SUM(s.Quantity * p.ProductPrice) as TotalSales 
                  FROM Sale s 
                  JOIN Product p ON s.ProductID = p.ProductID 
                  WHERE s.TenantID = :tenant_id";
    $stmt = $conn->prepare($sql_sales);
    $stmt->execute([':tenant_id' => $tenant_id]);
    $response["total_sales"] = $stmt->fetch(PDO::FETCH_ASSOC)['TotalSales'] ?? 0;

    // 3. สินค้าใกล้หมด (ใช้ w.Quantity ตามที่เคยคุยกัน หรือแก้ชื่อคอลัมน์ให้ตรง DB ล่าสุดครับ)
    $sql_low_stock = "SELECT COUNT(DISTINCT st.ProductID) as LowStock 
                      FROM Store st
                      JOIN Warehouse w ON st.WarehouseID = w.WarehouseID
                      JOIN Sale s ON st.ProductID = s.ProductID
                      WHERE s.TenantID = :tenant_id 
                      AND w.WarehouseQuantity < 51";
    $stmt_low = $conn->prepare($sql_low_stock);
    $stmt_low->execute([':tenant_id' => $tenant_id]);
    $response["low_stock_count"] = $stmt_low->fetch(PDO::FETCH_ASSOC)['LowStock'] ?? 0;

    // 4. โปรโมชันที่เปิดอยู่
    $sql_promo = "SELECT COUNT(DISTINCT p.PromotionID) as ActivePromo 
                  FROM Promotion p
                  WHERE p.TenantID = :tenant_id 
                  AND CURDATE() BETWEEN p.StartDatePromotion AND p.EndDatePromotion";
    $stmt_promo = $conn->prepare($sql_promo);
    $stmt_promo->execute([':tenant_id' => $tenant_id]);
    $response["active_promo"] = $stmt_promo->fetch(PDO::FETCH_ASSOC)['ActivePromo'] ?? 0;

    // 5. รายการขายล่าสุด
    $sql_recent = "SELECT s.SalesDate, s.ProductID, p.ProductName, s.Quantity, (s.Quantity * p.ProductPrice) as TotalPrice 
                   FROM Sale s 
                   JOIN Product p ON s.ProductID = p.ProductID 
                   WHERE s.TenantID = :tenant_id 
                   ORDER BY s.SalesDate DESC";
    $stmt_recent = $conn->prepare($sql_recent);
    $stmt_recent->execute([':tenant_id' => $tenant_id]);
    $response["recent_sales"] = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($response);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>