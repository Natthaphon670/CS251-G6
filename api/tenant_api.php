<?php
ob_start();
session_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php'; 

$action = isset($_GET['action']) ? $_GET['action'] : '';
$data = json_decode(file_get_contents("php://input"), true);
$tenant_id = $data['tenant_id'] ?? $_SESSION['TenantID'] ?? '';

if (empty($tenant_id) && $action !== '') {
    echo json_encode(["status" => "error", "message" => "ไม่อนุญาต (ไม่มี TenantID)"]);
    exit;
}

$response = ["status" => "error", "message" => "Invalid Action"];

try {
    switch ($action) {
        
        case 'get_dashboard':
            $stmt = $conn->prepare("SELECT SUM(s.Quantity * p.ProductPrice) as today_sales FROM Sale s JOIN Product p ON s.ProductID = p.ProductID WHERE s.TenantID = :tenant_id AND DATE(s.SalesDate) = CURDATE()");
            $stmt->execute(['tenant_id' => $tenant_id]);
            $sales = $stmt->fetch(PDO::FETCH_ASSOC)['today_sales'] ?? 0;

            $stmt = $conn->prepare("SELECT COUNT(DISTINCT st.ProductID) as low_stock FROM Store st JOIN Warehouse w ON st.WarehouseID = w.WarehouseID JOIN Sale s ON st.ProductID = s.ProductID WHERE s.TenantID = :tenant_id AND w.WarehouseQuantity < 51");
            $stmt->execute(['tenant_id' => $tenant_id]);
            $low_stock = $stmt->fetch(PDO::FETCH_ASSOC)['low_stock'] ?? 0;

            $stmt = $conn->prepare("SELECT COUNT(DISTINCT p.PromotionID) as active_promos FROM Promotion p WHERE p.TenantID = :tenant_id AND CURDATE() BETWEEN p.StartDatePromotion AND p.EndDatePromotion");
            $stmt->execute(['tenant_id' => $tenant_id]);
            $active_promos = $stmt->fetch(PDO::FETCH_ASSOC)['active_promos'] ?? 0;

            $stmt = $conn->prepare("SELECT DATE_FORMAT(s.SalesDate, '%H:%i') as SaleTime, s.ProductID, p.ProductName, s.Quantity, (s.Quantity * p.ProductPrice) as TotalAmount FROM Sale s JOIN Product p ON s.ProductID = p.ProductID WHERE s.TenantID = :tenant_id ORDER BY s.SalesDate DESC LIMIT 5");
            $stmt->execute(['tenant_id' => $tenant_id]);
            $recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = ["status" => "success", "data" => ["today_sales" => $sales, "low_stock_count" => $low_stock, "active_promotions" => $active_promos, "recent_sales" => $recent_sales]];
            break;

        case 'get_products':
            // ดึงเฉพาะสินค้าที่มีในตาราง HaveProduct ของร้านนี้ พร้อมดึงชื่อหมวดหมู่และรหัส Supplier
            $stmt = $conn->prepare("
                SELECT p.ProductID, p.ProductName, p.ProductPrice, p.ProductDescription, 
                       IFNULL(c.CategoryName, 'ทั่วไป') as CategoryName,
                       IFNULL(s.SupplierID, 'ไม่ระบุ') as SupplierID
                FROM Product p
                JOIN HaveProduct hp ON p.ProductID = hp.ProductID
                LEFT JOIN Categorize cat ON p.ProductID = cat.ProductID
                LEFT JOIN Category c ON cat.CategoryID = c.CategoryID
                LEFT JOIN Supply s ON p.ProductID = s.ProductID
                WHERE hp.TenantID = :tenant_id
                GROUP BY p.ProductID
            ");
            $stmt->execute(['tenant_id' => $tenant_id]);
            $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'get_suppliers':
            // ดึงเฉพาะ Supplier ที่จัดจำหน่ายสินค้าให้กับร้านค้านี้
            $stmt = $conn->prepare("
                SELECT DISTINCT sp.SupplierID, sp.SupplierName, sp.SupplierContactInfo as ContactInfo
                FROM Supplier sp
                JOIN Supply s ON sp.SupplierID = s.SupplierID
                JOIN HaveProduct hp ON s.ProductID = hp.ProductID
                WHERE hp.TenantID = :tenant_id
            ");
            $stmt->execute(['tenant_id' => $tenant_id]);
            $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'get_warehouse':
            $stmt = $conn->prepare("SELECT p.ProductID, p.ProductName, w.WarehouseQuantity as Quantity FROM Product p JOIN Store st ON p.ProductID = st.ProductID JOIN Warehouse w ON st.WarehouseID = w.WarehouseID JOIN Sale s ON p.ProductID = s.ProductID WHERE s.TenantID = :tenant_id GROUP BY p.ProductID");
            $stmt->execute(['tenant_id' => $tenant_id]);
            $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'get_sales_history':
            $stmt = $conn->prepare("SELECT s.SalesID as ReceiptID, DATE_FORMAT(s.SalesDate, '%d/%m/%Y %H:%i') as SaleTime, s.ProductID, p.ProductName, s.Quantity, (s.Quantity * p.ProductPrice) as TotalAmount FROM Sale s JOIN Product p ON s.ProductID = p.ProductID WHERE s.TenantID = :tenant_id AND DATE(s.SalesDate) BETWEEN :start_date AND :end_date ORDER BY s.SalesDate DESC");
            $stmt->execute(['tenant_id' => $tenant_id, 'start_date' => $data['start_date'], 'end_date' => $data['end_date']]);
            $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'get_contract_invoices':
            $stmtContract = $conn->prepare("SELECT lc.ContractID, rs.Location as Zone, rs.Floor FROM LeaseContract lc JOIN RentalSpace rs ON lc.SpaceID = rs.SpaceID WHERE lc.TenantID = :tenant_id LIMIT 1");
            $stmtContract->execute(['tenant_id' => $tenant_id]);
            $contract = $stmtContract->fetch(PDO::FETCH_ASSOC);
            if ($contract) $contract['Status'] = 'Active';

            $stmtInvoice = $conn->prepare("SELECT i.InvoiceID, DATE_FORMAT(i.InvoiceDate, '%M %Y') as BillingMonth, i.Amount as TotalAmount, i.Status FROM Invoice i JOIN LeaseContract lc ON i.ContractID = lc.ContractID WHERE lc.TenantID = :tenant_id ORDER BY i.InvoiceDate DESC");
            $stmtInvoice->execute(['tenant_id' => $tenant_id]);
            $invoices = $stmtInvoice->fetchAll(PDO::FETCH_ASSOC);

            $response = ["status" => "success", "data" => ["contract" => $contract, "invoices" => $invoices]];
            break;
            
        // ============ ส่วนของการเพิ่มข้อมูลลง Database ============
        case 'add_supplier':
            try {
                // 1. สร้างรหัส SupplierID ใหม่ (เช่น ดึง SP0004 มาบวก 1 เป็น SP0005)
                $stmtId = $conn->query("SELECT SupplierID FROM Supplier ORDER BY SupplierID DESC LIMIT 1");
                $lastId = $stmtId->fetchColumn();
                $newId = $lastId ? 'SP' . str_pad(intval(substr($lastId, 2)) + 1, 4, '0', STR_PAD_LEFT) : 'SP0001';

                // 2. บันทึกลงตาราง Supplier
                $stmt = $conn->prepare("INSERT INTO Supplier (SupplierID, SupplierName, SupplierContactInfo) VALUES (:sid, :sname, :contact)");
                $stmt->execute([
                    'sid' => $newId,
                    'sname' => $data['supplier_name'], 
                    'contact' => $data['contact_info']
                ]);
                $response = ["status" => "success", "supplier_id" => $newId];
            } catch (PDOException $e) {
                $response = ["status" => "error", "message" => "SQL Error: " . $e->getMessage()];
            }
            break;
            
        case 'add_product':
            try {
                // 1. สร้างรหัส ProductID ใหม่ให้รันต่อจากของเดิม
                $stmtId = $conn->query("SELECT ProductID FROM Product ORDER BY ProductID DESC LIMIT 1");
                $lastId = $stmtId->fetchColumn();
                $newId = $lastId ? 'P' . str_pad(intval(substr($lastId, 1)) + 1, 5, '0', STR_PAD_LEFT) : 'P00001';

                // 2. บันทึกลงตาราง Product หลัก
                $stmt1 = $conn->prepare("INSERT INTO Product (ProductID, ProductName, ProductPrice, ProductDescription) VALUES (:pid, :pname, :price, :desc)");
                $stmt1->execute([
                    'pid' => $newId,
                    'pname' => $data['product_name'], 
                    'price' => $data['product_price'], 
                    'desc' => $data['description']
                ]);

                // 3. บันทึกลงตาราง Supply เพื่อจับคู่สินค้ากับ Supplier
                if (!empty($data['supplier_id'])) {
                    $stmt2 = $conn->prepare("INSERT INTO Supply (ProductID, SupplierID) VALUES (:pid, :supp)");
                    $stmt2->execute([
                        'pid' => $newId,
                        'supp' => $data['supplier_id']
                    ]);
                }

                // 4. บันทึกลงตาราง HaveProduct เพื่อผูกสินค้าเข้ากับร้านค้านี้โดยเฉพาะ
                $stmt3 = $conn->prepare("INSERT INTO HaveProduct (TenantID, ProductID) VALUES (:tenant_id, :pid)");
                $stmt3->execute([
                    'tenant_id' => $tenant_id,
                    'pid' => $newId
                ]);

                // 5. บันทึกลงตาราง Store เพื่อนำสินค้าเข้าคลัง (กำหนดให้ลง W00001 เป็นค่าเริ่มต้น)
                $stmt4 = $conn->prepare("INSERT INTO Store (ProductID, WarehouseID) VALUES (:pid, 'W00001')");
                $stmt4->execute(['pid' => $newId]);

                $response = ["status" => "success", "product_id" => $newId];
            } catch (PDOException $e) {
                $response = ["status" => "error", "message" => "SQL Error: " . $e->getMessage()];
            }
            break;
            
        case 'add_promotion':
            try {
                // 1. สร้างรหัส PromotionID ใหม่ (เช่น ดึง PR0004 มาบวก 1 เป็น PR0005)
                $stmtId = $conn->query("SELECT PromotionID FROM Promotion ORDER BY PromotionID DESC LIMIT 1");
                $lastId = $stmtId->fetchColumn();
                $newId = $lastId ? 'PR' . str_pad(intval(substr($lastId, 2)) + 1, 4, '0', STR_PAD_LEFT) : 'PR0001';

                // 2. บันทึกลงตาราง Promotion และผูกกับ TenantID ของร้านค้านี้โดยเฉพาะ
                $stmt = $conn->prepare("INSERT INTO Promotion (PromotionID, PromotionName, Discount, StartDatePromotion, EndDatePromotion, TenantID) VALUES (:pid, :pname, :discount, :start, :end, :tenant_id)");
                $stmt->execute([
                    'pid' => $newId,
                    'pname' => $data['promo_name'], 
                    'discount' => $data['discount'], 
                    'start' => $data['start_date'], 
                    'end' => $data['end_date'], 
                    'tenant_id' => $tenant_id
                ]);
                $response = ["status" => "success", "promotion_id" => $newId];
            } catch (PDOException $e) {
                $response = ["status" => "error", "message" => "SQL Error: " . $e->getMessage()];
            }
            break;
            
        case 'restock_product':
            $stmt = $conn->prepare("UPDATE Warehouse w JOIN Store st ON w.WarehouseID = st.WarehouseID SET w.WarehouseQuantity = w.WarehouseQuantity + :add_qty, w.LastUpdate = NOW() WHERE st.TenantID = :tenant_id AND st.ProductID = :product_id");
            $stmt->execute(['add_qty' => $data['add_qty'], 'tenant_id' => $tenant_id, 'product_id' => $data['product_id']]);
            $response = ["status" => "success", "message" => "อัปเดตจำนวนสินค้าสำเร็จ"];
            break;
    }
} catch (PDOException $e) {
    $response = ["status" => "error", "message" => "SQL Error: " . $e->getMessage()];
}

ob_clean();
echo json_encode($response);
?>