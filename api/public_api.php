<?php
// ไฟล์: api/public_api.php
require_once 'db.php'; // เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_products':
        getProducts(); // สำหรับหน้า Product Catalog และ Homepage
        break;
        
    case 'get_tenants':
        getTenants(); // สำหรับหน้า Shop Directory
        break;
        
    case 'get_product_detail':
        getProductDetail(); // สำหรับหน้า Product Detail
        break;
        
    case 'get_promotions':
        getPromotions(); // สำหรับหน้า Homepage (แบนเนอร์โปรโมชัน)
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}

// ========================================================
// 1. ฟังก์ชันดึงสินค้าทั้งหมด (หน้า Product Catalog)
// ========================================================
function getProducts() {
    global $conn;
    try {
        // ดึงข้อมูลสินค้า พร้อมเช็กจำนวนสต็อกจากคลังสินค้า
        $sql = "
            SELECT 
                p.ProductID, 
                p.ProductName, 
                p.ProductPrice,
                COALESCE(SUM(w.WarehouseQuantity), 0) AS TotalStock
            FROM Product p
            LEFT JOIN Store st ON p.ProductID = st.ProductID
            LEFT JOIN Warehouse w ON st.WarehouseID = w.WarehouseID
            GROUP BY p.ProductID, p.ProductName, p.ProductPrice
        ";
        $stmt = $conn->query($sql);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // เช็กสถานะว่ามีของหรือไม่
        foreach ($products as &$p) {
            $p['TotalStock'] = (int)$p['TotalStock'];
            $p['InStock'] = $p['TotalStock'] > 0;
        }
        unset($p);
        
        echo json_encode(['success' => true, 'data' => $products]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ========================================================
// 2. ฟังก์ชันดึงร้านค้าทั้งหมด (หน้า Shop Directory)
// ========================================================
function getTenants() {
    global $conn;
    try {
        $sql = "
            SELECT 
                t.TenantID, 
                t.TenantName, 
                t.TenantCategory,
                t.TenantContactInfo,
                rs.Floor, 
                rs.Location 
            FROM Tenant t 
            LEFT JOIN LeaseContract lc 
                ON t.TenantID = lc.TenantID 
                AND lc.EndDateLease >= CURDATE()
            LEFT JOIN RentalSpace rs ON lc.SpaceID = rs.SpaceID
            GROUP BY t.TenantID, t.TenantName, t.TenantCategory,
                     t.TenantContactInfo, rs.Floor, rs.Location
            ORDER BY rs.Floor, t.TenantName
        ";
        $stmt = $conn->query($sql);
        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $tenants]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
// ========================================================
// 3. ฟังก์ชันดึงรายละเอียดสินค้า 1 ชิ้น (หน้า Product Detail)
// ========================================================
function getProductDetail() {
    global $conn;
    $productId = $_GET['product_id'] ?? null;
    
    if (!$productId) {
        echo json_encode(['success' => false, 'message' => 'ไม่มีการระบุรหัสสินค้า (product_id)']);
        return;
    }

    try {
        // 3.1 ดึงข้อมูลสินค้าหลักและสต็อก
        $sql = "
            SELECT 
                p.ProductID, 
                p.ProductName, 
                p.ProductPrice, 
                p.ProductDescription,
                COALESCE(SUM(w.WarehouseQuantity), 0) AS TotalStock
            FROM Product p
            LEFT JOIN Store st ON p.ProductID = st.ProductID
            LEFT JOIN Warehouse w ON st.WarehouseID = w.WarehouseID
            WHERE p.ProductID = :id
            GROUP BY p.ProductID, p.ProductName, p.ProductPrice, p.ProductDescription
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลสินค้านี้ในระบบ']);
            return;
        }

        $product['TotalStock'] = (int)$product['TotalStock'];
        $product['InStock'] = $product['TotalStock'] > 0;

        // 3.2 ดึงโปรโมชันของสินค้านี้ (ที่ยังไม่หมดอายุ)
        $promoSql = "
            SELECT pr.PromotionName, pr.Discount 
            FROM Promotion pr
            JOIN Cheapen ch ON pr.PromotionID = ch.PromotionID
            WHERE ch.ProductID = :id AND pr.EndDatePromotion >= CURDATE()
        ";
        $promoStmt = $conn->prepare($promoSql);
        $promoStmt->execute([':id' => $productId]);
        $promotions = $promoStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // คำนวณราคาหลังหักส่วนลด
        foreach ($promotions as &$promo) {
            $promo['PriceAfterDiscount'] = round($product['ProductPrice'] * (1 - $promo['Discount'] / 100), 2);
        }
        unset($p);
        $product['ActivePromotions'] = $promotions;

        echo json_encode(['success' => true, 'data' => $product]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ========================================================
// 4. ฟังก์ชันดึงโปรโมชันทั้งหมด (สำหรับสไลด์หน้า Homepage)
// ========================================================
function getPromotions() {
    global $conn;
    try {
        // ดึงโปรโมชันที่กำลังใช้งานอยู่ พร้อมชื่อร้านค้าที่จัดโปรโมชัน
        $sql = "
            SELECT 
                pr.PromotionID, 
                pr.PromotionName, 
                pr.Discount, 
                pr.EndDatePromotion, 
                t.TenantName 
            FROM Promotion pr 
            JOIN Tenant t ON pr.TenantID = t.TenantID 
            WHERE pr.StartDatePromotion <= CURDATE() 
            AND pr.EndDatePromotion >= CURDATE()
            ORDER BY pr.Discount DESC
        ";
        $stmt = $conn->query($sql);
        $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $promotions]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>