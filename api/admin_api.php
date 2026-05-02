<?php

// ตั้งค่า Header ให้รองรับ JSON และแก้ปัญหา CORS (Cross-Origin Resource Sharing)
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");

// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once 'db.php'; 

// ตรวจสอบว่ามีค่า action ส่งมาหรือไม่
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ตัวแปรตั้งต้นสำหรับส่งค่ากลับ
$response = ["status" => "error", "message" => "Invalid Action or Action not found"];

try {
    switch ($action) {

        // ==========================================
        // ระบบที่ 1: Dashboard (สรุปข้อมูลหน้าแรก)
        // ==========================================
        case 'get_dashboard_stats':
            // 1. ดึงยอดขายรวมทั้งหมด
            $stmtSales = $conn->query("SELECT SUM(s.Quantity * p.ProductPrice) as TotalSales FROM Sale s JOIN Product p ON s.ProductID = p.ProductID");
            $totalSales = $stmtSales->fetch(PDO::FETCH_ASSOC)['TotalSales'] ?? 0;

            // 1.1 ยอดขายเดือนนี้
            $stmtThisMonth = $conn->query("SELECT SUM(s.Quantity * p.ProductPrice) as ThisMonth FROM Sale s JOIN Product p ON s.ProductID = p.ProductID WHERE MONTH(s.SalesDate) = MONTH(CURRENT_DATE()) AND YEAR(s.SalesDate) = YEAR(CURRENT_DATE())");
            $thisMonth = $stmtThisMonth->fetch(PDO::FETCH_ASSOC)['ThisMonth'] ?? 0;

            // 1.2 ยอดขายเดือนที่แล้ว
            $stmtLastMonth = $conn->query("SELECT SUM(s.Quantity * p.ProductPrice) as LastMonth FROM Sale s JOIN Product p ON s.ProductID = p.ProductID WHERE MONTH(s.SalesDate) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(s.SalesDate) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)");
            $lastMonth = $stmtLastMonth->fetch(PDO::FETCH_ASSOC)['LastMonth'] ?? 0;

            // 1.3 คำนวณ % การเติบโตของยอดขาย
            $salesTrend = 0;
            if ($lastMonth > 0) {
                $salesTrend = (($thisMonth - $lastMonth) / $lastMonth) * 100;
            }

            // 2. ดึงจำนวนร้านค้าและสินค้า
            $totalTenants = $conn->query("SELECT COUNT(*) FROM Tenant")->fetchColumn();
            $totalProducts = $conn->query("SELECT COUNT(*) FROM Product")->fetchColumn();

            // 3. กล่องสรุปภาพรวม
            // 3.1 สัญญาใกล้หมดอายุ (ภายใน 30 วัน)
            $expiring = $conn->query("SELECT COUNT(*) FROM LeaseContract WHERE EndDateLease BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
            
            // 3.2 พื้นที่ว่าง (หา Space ที่ไม่มีในสัญญาช่วงวันที่ปัจจุบัน)
            $freeSpace = $conn->query("SELECT COUNT(*) FROM RentalSpace WHERE SpaceID NOT IN (SELECT SpaceID FROM LeaseContract WHERE CURDATE() BETWEEN StartDateLease AND EndDateLease)")->fetchColumn();
            
            // 3.3 ใบแจ้งหนี้ค้างชำระ (Status = Unpaid หรือ Pending)
            $unpaid = $conn->query("SELECT SUM(Amount) FROM Invoice WHERE Status IN ('Unpaid', 'Pending')")->fetchColumn();

            $response = [
                "status" => "success",
                "data" => [
                    "total_sales" => (float)$totalSales,
                    "sales_trend" => round($salesTrend, 1), // ปัดทศนิยม 1 ตำแหน่ง
                    "total_tenants" => (int)$totalTenants,
                    "total_products" => (int)$totalProducts,
                    "expiring_contracts" => (int)$expiring,
                    "free_spaces" => (int)$freeSpace,
                    "unpaid_invoices" => (float)$unpaid
                ]
            ];
            break;

        // ==========================================
        // ระบบที่ 2: Employee Management (จัดการพนักงาน)
        // ==========================================
        case 'get_employees':
            // JOIN ตาราง UserAccount เพื่อดึง Role และ Username มาแสดงผลด้วย
            $sql = "SELECT e.EmployeeID, e.EmployeeName, e.Position, e.EmTelephone, u.Role, u.Username 
                    FROM Employee e
                    LEFT JOIN UserAccount u ON e.AccountID = u.AccountID
                    ORDER BY e.EmployeeID ASC";
            $stmt = $conn->query($sql);
            $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'add_employee':
            $empID = $_POST['employee_id']; 
            $empName = $_POST['employee_name'];
            $position = $_POST['position'];
            $tel = $_POST['telephone'];
            
            $accID = $_POST['account_id'];
            $username = $_POST['username'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // หรือถ้าเพื่อนดึงพาสเวิร์ดตรงๆ ให้เอา password_hash ออก
            $role = $_POST['role'];

            // ใช้ Transaction ป้องกันการ Error กลางคัน
            $conn->beginTransaction();
            
            // 1. ต้องสร้าง UserAccount ก่อนเสมอ
            $stmt1 = $conn->prepare("INSERT INTO UserAccount (AccountID, Username, Password, Role) VALUES (?, ?, ?, ?)");
            $stmt1->execute([$accID, $username, $password, $role]);

            // 2. สร้าง Profile พนักงานแล้วผูก AccountID (สังเกตว่ามีการเพิ่ม AccountID ในคำสั่ง SQL)
            $stmt2 = $conn->prepare("INSERT INTO Employee (EmployeeID, EmployeeName, Position, EmTelephone, AccountID) VALUES (?, ?, ?, ?, ?)");
            $stmt2->execute([$empID, $empName, $position, $tel, $accID]);
            
            $conn->commit();
            $response = ["status" => "success", "message" => "เพิ่มพนักงาน $empName สำเร็จ"];
            break;

        // ==========================================
        // ระบบที่ 3: Tenant & Space (จัดการร้านค้าและพื้นที่)
        // ==========================================
        case 'get_tenants':
            $stmt = $conn->query("SELECT * FROM Tenant ORDER BY TenantID ASC");
            $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'get_spaces':
            // ดึงข้อมูลพื้นที่ และนับจำนวนสัญญาเช่าที่ "กำลัง Active อยู่ในวันนี้" (ถ้า > 0 แปลว่าไม่ว่าง)
            $sql = "SELECT s.*, 
                    (SELECT COUNT(*) FROM LeaseContract lc 
                     WHERE lc.SpaceID = s.SpaceID 
                     AND CURDATE() BETWEEN lc.StartDateLease AND lc.EndDateLease) as IsOccupied 
                    FROM RentalSpace s ORDER BY s.SpaceID ASC";
            $stmt = $conn->query($sql);
            $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'add_tenant':
            // ตัวอย่างการเพิ่มร้านค้าพร้อมสร้าง Account (ใช้ Transaction)
            $accountID = $_POST['account_id'];
            $username = $_POST['username'];
            $password = $_POST['password']; // ควรใช้ password_hash() ในการใช้งานจริง
            $role = 'Tenant';

            $tenantID = $_POST['tenant_id'];
            $tenantName = $_POST['tenant_name'];
            $category = $_POST['category'];
            $contactInfo = $_POST['contact_info'];

            $conn->beginTransaction();
            // 1. สร้าง Account ก่อน
            $stmt1 = $conn->prepare("INSERT INTO UserAccount (AccountID, Username, Password, Role) VALUES (?, ?, ?, ?)");
            $stmt1->execute([$accountID, $username, $password, $role]);

            // 2. สร้าง Profile ร้านค้าและผูก AccountID
            $stmt2 = $conn->prepare("INSERT INTO Tenant (TenantID, TenantName, TenantCategory, TenantContactInfo, AccountID) VALUES (?, ?, ?, ?, ?)");
            $stmt2->execute([$tenantID, $tenantName, $category, $contactInfo, $accountID]);

            $conn->commit();
            $response = ["status" => "success", "message" => "เพิ่มร้านค้า $tenantName สำเร็จ"];
            break;

        // ==========================================
        // ระบบที่ 4: Lease Contract (จัดการสัญญาเช่า)
        // ==========================================
        case 'get_contracts':
            // JOIN เอาชื่อร้านมาโชว์ด้วย
            $stmt = $conn->query("SELECT l.*, t.TenantName FROM LeaseContract l JOIN Tenant t ON l.TenantID = t.TenantID ORDER BY l.StartDateLease DESC");
            $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'get_expiring_contracts':
            // หาสัญญาที่จะหมดอายุในอีก 30 วัน
            $stmt = $conn->query("
                SELECT l.ContractID, t.TenantName, l.EndDateLease 
                FROM LeaseContract l 
                JOIN Tenant t ON l.TenantID = t.TenantID 
                WHERE l.EndDateLease BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ");
            $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'add_contract':
            $contractID = $_POST['contract_id'];
            $startDate = $_POST['start_date'];
            $endDate = $_POST['end_date'];
            $tenantID = $_POST['tenant_id'];
            $spaceID = $_POST['space_id'];

            $stmt = $conn->prepare("INSERT INTO LeaseContract (ContractID, StartDateLease, EndDateLease, TenantID, SpaceID) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$contractID, $startDate, $endDate, $tenantID, $spaceID]);
            $response = ["status" => "success", "message" => "บันทึกสัญญา $contractID สำเร็จ"];
            break;

       // ==========================================
        // ระบบที่ 5: Invoice Management (ใบแจ้งหนี้)
        // ==========================================
        case 'get_invoices':
            // ดึงข้อมูลบิล และ JOIN ข้ามไปดึงชื่อร้านค้า (TenantName)
            $sql = "SELECT i.InvoiceID, i.InvoiceDate, i.Amount, i.Status, t.TenantName 
                    FROM Invoice i 
                    JOIN LeaseContract lc ON i.ContractID = lc.ContractID 
                    JOIN Tenant t ON lc.TenantID = t.TenantID 
                    ORDER BY i.InvoiceDate DESC";
            $stmt = $conn->query($sql);
            $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'add_invoice':
            $invoiceID = $_POST['invoice_id']; 
            $invoiceDate = $_POST['invoice_date'];
            $amount = $_POST['amount'];
            $status = $_POST['status']; // e.g., 'Paid', 'Unpaid'
            $contractID = $_POST['contract_id'];

            $stmt = $conn->prepare("INSERT INTO Invoice (InvoiceID, InvoiceDate, Amount, Status, ContractID) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$invoiceID, $invoiceDate, $amount, $status, $contractID]);
            $response = ["status" => "success", "message" => "สร้างใบแจ้งหนี้ $invoiceID สำเร็จ"];
            break;

        case 'update_invoice_status':
            $invoiceID = $_POST['invoice_id'];
            $status = $_POST['status'];

            $stmt = $conn->prepare("UPDATE Invoice SET Status = ? WHERE InvoiceID = ?");
            $stmt->execute([$status, $invoiceID]);
            $response = ["status" => "success", "message" => "อัปเดตสถานะบิลเป็น $status สำเร็จ"];
            break;

        // ==========================================
        // ระบบที่ 6: Sales Management (บันทึกการขาย)
        // ==========================================
        // case 'get_sales':
        //     // JOIN เอาชื่อสินค้าและชื่อร้านมาโชว์ด้วย
        //     $stmt = $conn->query("
        //         SELECT s.SalesID, s.SalesDate, s.Quantity, p.ProductName, t.TenantName 
        //         FROM Sale s 
        //         JOIN Product p ON s.ProductID = p.ProductID 
        //         JOIN Tenant t ON s.TenantID = t.TenantID 
        //         ORDER BY s.SalesDate DESC
        //     ");
        //     $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        //     break;

        // case 'add_sale':
        //     $salesID = $_POST['sales_id'];
        //     $salesDate = $_POST['sales_date']; // format: YYYY-MM-DD HH:MM:SS
        //     $quantity = $_POST['quantity'];
        //     $tenantID = $_POST['tenant_id'];
        //     $productID = $_POST['product_id'];

        //     $stmt = $conn->prepare("INSERT INTO Sale (SalesID, SalesDate, Quantity, TenantID, ProductID) VALUES (?, ?, ?, ?, ?)");
        //     $stmt->execute([$salesID, $salesDate, $quantity, $tenantID, $productID]);
        //     $response = ["status" => "success", "message" => "บันทึกการขายสำเร็จ"];
        //     break;
        // 1. ดึงข้อมูลร้านค้าทั้งหมดมาใส่ Dropdown
        case 'get_tenants_for_sales':
            $stmt = $conn->query("SELECT TenantID, TenantName FROM Tenant ORDER BY TenantID ASC");
            $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        // 2. ดึงสินค้าเฉพาะของร้านที่เลือก + เช็คโปรโมชั่นอัตโนมัติ (ลบเป็น %)
        case 'get_products_by_tenant':
            $tenant_id = $_POST['tenant_id'];
            $stmt = $conn->prepare("
                SELECT p.ProductID, p.ProductName, p.ProductPrice,
                       COALESCE(
                           (SELECT pr.Discount
                            FROM Promotion pr
                            JOIN cheapen c ON pr.PromotionID = c.PromotionID
                            WHERE c.ProductID = p.ProductID
                              AND pr.TenantID = :tenant_id
                              AND CURDATE() BETWEEN pr.StartDatePromotion AND pr.EndDatePromotion
                            LIMIT 1), 0) as DiscountPercent
                FROM Product p
                JOIN HaveProduct hp ON p.ProductID = hp.ProductID
                WHERE hp.TenantID = :tenant_id
            ");
            $stmt->execute(['tenant_id' => $tenant_id]);
            $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        // 3. บันทึกการขาย (สร้าง ID ให้เอง และรองรับหลายสินค้า)
        // 3. บันทึกการขาย + ตัดสต็อกอัตโนมัติ (รองรับการหักหลายคลัง)
        case 'add_multiple_sales':
            $sales_date = $_POST['sales_date']; 
            $tenant_id = $_POST['tenant_id'];
            $items = json_decode($_POST['items'], true); 

            try {
                $conn->beginTransaction();

                // หารหัส SalesID ล่าสุดในระบบ
                $stmtId = $conn->query("SELECT SalesID FROM Sale ORDER BY SalesID DESC LIMIT 1");
                $lastId = $stmtId->fetchColumn();
                $next_num = $lastId ? intval(substr($lastId, 2)) + 1 : 1;

                $stmtInsert = $conn->prepare("INSERT INTO Sale (SalesID, SalesDate, Quantity, TenantID, ProductID) VALUES (?, ?, ?, ?, ?)");

                // วนลูปจัดการทีละสินค้าที่ถูกกดขาย
                foreach ($items as $item) {
                    $pid = $item['product_id'];
                    $qty_to_sell = $item['quantity'];

                    // ----------------------------------------------------
                    // ส่วนที่ 1: ตัดสต็อกในตาราง Store 
                    // ----------------------------------------------------
                    // ดึงข้อมูลคลังทั้งหมดที่มีสินค้านี้อยู่ และมีของ > 0 (เรียงจากคลังที่มีของเยอะสุดก่อน)
                    $stmtFindStore = $conn->prepare("SELECT WarehouseID, Quantity FROM Store WHERE ProductID = ? AND Quantity > 0 ORDER BY Quantity DESC");
                    $stmtFindStore->execute([$pid]);
                    $stores = $stmtFindStore->fetchAll(PDO::FETCH_ASSOC);

                    // เช็คก่อนว่ามีของพอขายไหม
                    $total_available = array_sum(array_column($stores, 'Quantity'));
                    if ($total_available < $qty_to_sell) {
                        throw new Exception("สินค้า $pid มีสต็อกไม่พอ! (มี $total_available ชิ้น, แต่จะขาย $qty_to_sell ชิ้น)");
                    }

                    // เริ่มหักสต็อกทีละคลัง
                    $qty_left_to_deduct = $qty_to_sell;
                    foreach ($stores as $store) {
                        if ($qty_left_to_deduct <= 0) break; // หักครบจำนวนที่ขายแล้ว ให้หยุด

                        $wid = $store['WarehouseID'];
                        $available = $store['Quantity'];

                        if ($available >= $qty_left_to_deduct) {
                            // กรณีที่คลังนี้มีของพอให้หักจบในทีเดียว
                            $conn->prepare("UPDATE Store SET Quantity = Quantity - ? WHERE ProductID = ? AND WarehouseID = ?")->execute([$qty_left_to_deduct, $pid, $wid]);
                            
                            // สั่งอัปเดตยอดรวมในตาราง Warehouse ทันที
                            $conn->prepare("UPDATE Warehouse SET WarehouseQuantity = (SELECT COALESCE(SUM(Quantity), 0) FROM Store WHERE WarehouseID = ?), LastUpdate = CURRENT_TIMESTAMP WHERE WarehouseID = ?")->execute([$wid, $wid]);
                            
                            $qty_left_to_deduct = 0; 
                        } else {
                            // กรณีที่คลังนี้ของไม่พอ ให้หักเกลี้ยงคลัง (เป็น 0) แล้วเก็บเศษไปหักคลังอื่นต่อ
                            $conn->prepare("UPDATE Store SET Quantity = 0 WHERE ProductID = ? AND WarehouseID = ?")->execute([$pid, $wid]);
                            
                            // สั่งอัปเดตยอดรวมในตาราง Warehouse ทันที
                            $conn->prepare("UPDATE Warehouse SET WarehouseQuantity = (SELECT COALESCE(SUM(Quantity), 0) FROM Store WHERE WarehouseID = ?), LastUpdate = CURRENT_TIMESTAMP WHERE WarehouseID = ?")->execute([$wid, $wid]);

                            $qty_left_to_deduct -= $available; 
                        }
                    }

                    // ----------------------------------------------------
                    // ส่วนที่ 2: บันทึกยอดขายลงตาราง Sale
                    // ----------------------------------------------------
                    $newSalesId = 'SL' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
                    $stmtInsert->execute([
                        $newSalesId,
                        $sales_date,
                        $qty_to_sell,
                        $tenant_id,
                        $pid
                    ]);
                    $next_num++; 
                }

                $conn->commit();
                $response = ["status" => "success", "message" => "บันทึกยอดขายและตัดสต็อกสำเร็จ!"];
            } catch (Exception $e) {
                $conn->rollBack();
                $response = ["status" => "error", "message" => $e->getMessage()];
            }
            break;

        // 4. อัปเดตฟังก์ชันดึงประวัติการขาย (คำนวณราคาสุทธิที่หักโปรฯ แล้วมาโชว์)
        case 'get_sales':
            $stmt = $conn->query("
                SELECT s.SalesID, s.SalesDate, s.Quantity, p.ProductName, t.TenantName,
                       (p.ProductPrice * s.Quantity * (1 - COALESCE(
                           (SELECT pr.Discount
                            FROM Promotion pr
                            JOIN cheapen c ON pr.PromotionID = c.PromotionID
                            WHERE c.ProductID = p.ProductID
                              AND pr.TenantID = s.TenantID
                              AND DATE(s.SalesDate) BETWEEN pr.StartDatePromotion AND pr.EndDatePromotion
                            LIMIT 1), 0) / 100)) as FinalAmount
                FROM Sale s 
                JOIN Product p ON s.ProductID = p.ProductID 
                JOIN Tenant t ON s.TenantID = t.TenantID 
                ORDER BY s.SalesDate DESC
            ");
            $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        // ==========================================
        //  ระบบที่ 7: Report Generation (ออกรายงาน)
        // ==========================================
        case 'get_reports':
            // JOIN เอาชื่อพนักงานที่สร้างรายงานมาโชว์
            $stmt = $conn->query("
                SELECT r.ReportID, r.ReportName, r.ReportDate, e.EmployeeName 
                FROM Report r 
                LEFT JOIN Employee e ON r.EmployeeID = e.EmployeeID 
                ORDER BY r.ReportDate DESC
            ");
            $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'add_report':
            $reportID = $_POST['report_id'];
            $reportName = $_POST['report_name'];
            $reportDate = $_POST['report_date'];
            $employeeID = $_POST['employee_id'];

            $stmt = $conn->prepare("INSERT INTO Report (ReportID, ReportName, ReportDate, EmployeeID) VALUES (?, ?, ?, ?)");
            $stmt->execute([$reportID, $reportName, $reportDate, $employeeID]);
            $response = ["status" => "success", "message" => "บันทึกประวัติการออกรายงานสำเร็จ"];
            break;

    // หน้า warehouse
        case 'get_warehouse':
            $sql = "SELECT w.WarehouseID, p.ProductID, p.ProductName, 
                           COALESCE(st.Quantity, w.WarehouseQuantity, 0) as Quantity, 
                           c.CategoryName, s.SupplierName 
                    FROM Warehouse w
                    LEFT JOIN Store st ON w.WarehouseID = st.WarehouseID
                    LEFT JOIN Product p ON st.ProductID = p.ProductID
                    LEFT JOIN Categorize ct ON p.ProductID = ct.ProductID
                    LEFT JOIN Category c ON ct.CategoryID = c.CategoryID
                    LEFT JOIN Supply sup ON p.ProductID = sup.ProductID
                    LEFT JOIN Supplier s ON sup.SupplierID = s.SupplierID
                    ORDER BY w.WarehouseID DESC"; // เรียงจากคลังใหม่ล่าสุดขึ้นก่อน
            $stmt = $conn->query($sql);
            $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

            // เพิ่มเข้าไปใน switch ($action)
        case 'add_space':
            $spaceID = $_POST['space_id'];
            $floor = $_POST['floor'];
            $location = $_POST['location'];
            $size = $_POST['size'];

            $stmt = $conn->prepare("INSERT INTO RentalSpace (SpaceID, Floor, Location, Size) VALUES (?, ?, ?, ?)");
            $stmt->execute([$spaceID, $floor, $location, $size]);
            $response = ["status" => "success", "message" => "เพิ่มพื้นที่ $spaceID สำเร็จ"];
            break;

            // ==========================================
        // ระบบจัดการคลังสินค้า (เพิ่มข้อมูลทีเดียว 5 ตาราง)
        // ==========================================
        case 'add_warehouse_stock':
            $productID = $_POST['product_id'];
            $productName = $_POST['product_name'];
            $price = $_POST['price'];
            $warehouseID = $_POST['warehouse_id'];
            $quantity = $_POST['quantity'];
            $categoryID = $_POST['category_id'];
            $supplierID = $_POST['supplier_id'];

            // ใช้ Transaction เพราะต้อง Insert หลายตาราง ถ้าพังจะ Rollback ได้
            $conn->beginTransaction();
            
            // 1. เพิ่มสินค้าใหม่ลงตาราง Product
            $stmt1 = $conn->prepare("INSERT INTO Product (ProductID, ProductName, ProductPrice) VALUES (?, ?, ?)");
            $stmt1->execute([$productID, $productName, $price]);

            // 2. เพิ่มข้อมูลล็อตคลัง/จำนวนลงตาราง Warehouse
            $stmt2 = $conn->prepare("INSERT INTO Warehouse (WarehouseID, WarehouseQuantity, LastUpdate) VALUES (?, ?, CURRENT_TIMESTAMP)");
            $stmt2->execute([$warehouseID, $quantity]);

            // 3. ผูกสินค้าเข้ากับล็อตคลัง (Store Junction)
            $stmt3 = $conn->prepare("INSERT INTO Store (ProductID, WarehouseID) VALUES (?, ?)");
            $stmt3->execute([$productID, $warehouseID]);

            // 4. ผูกหมวดหมู่สินค้า (Categorize Junction)
            $stmt4 = $conn->prepare("INSERT INTO Categorize (ProductID, CategoryID) VALUES (?, ?)");
            $stmt4->execute([$productID, $categoryID]);

            // 5. ผูกผู้จัดจำหน่าย (Supply Junction)
            $stmt5 = $conn->prepare("INSERT INTO Supply (ProductID, SupplierID) VALUES (?, ?)");
            $stmt5->execute([$productID, $supplierID]);

            $conn->commit();
            $response = ["status" => "success", "message" => "เพิ่มสินค้า $productName เข้าคลังสำเร็จ"];
            break;

            // ==========================================
        // ระบบ Update ข้อมูล Tenant และ Space
        // ==========================================
        
        // 1. ดึงข้อมูล Tenant มาแสดงในฟอร์ม
        case 'get_tenant_by_id':
            $id = $_POST['tenant_id'];
            $stmt = $conn->prepare("SELECT * FROM Tenant WHERE TenantID = ?");
            $stmt->execute([$id]);
            $response = ["status" => "success", "data" => $stmt->fetch(PDO::FETCH_ASSOC)];
            break;

        // 2. บันทึกข้อมูล Tenant ที่แก้ไขแล้ว
        case 'update_tenant':
            $id = $_POST['tenant_id'];
            $name = $_POST['tenant_name'];
            $category = $_POST['category'];
            $contact = $_POST['contact_info'];

            $stmt = $conn->prepare("UPDATE Tenant SET TenantName = ?, TenantCategory = ?, TenantContactInfo = ? WHERE TenantID = ?");
            $stmt->execute([$name, $category, $contact, $id]);
            $response = ["status" => "success", "message" => "อัปเดตข้อมูลร้านค้า $name เรียบร้อยแล้ว"];
            break;

        // 3. ดึงข้อมูล Space มาแสดงในฟอร์ม
        case 'get_space_by_id':
            $id = $_POST['space_id'];
            $stmt = $conn->prepare("SELECT * FROM RentalSpace WHERE SpaceID = ?");
            $stmt->execute([$id]);
            $response = ["status" => "success", "data" => $stmt->fetch(PDO::FETCH_ASSOC)];
            break;

        // 4. บันทึกข้อมูล Space ที่แก้ไขแล้ว
        case 'update_space':
            $id = $_POST['space_id'];
            $floor = $_POST['floor'];
            $location = $_POST['location'];
            $size = $_POST['size'];

            $stmt = $conn->prepare("UPDATE RentalSpace SET Floor = ?, Location = ?, Size = ? WHERE SpaceID = ?");
            $stmt->execute([$floor, $location, $size, $id]);
            $response = ["status" => "success", "message" => "อัปเดตข้อมูลพื้นที่รหัส $id เรียบร้อยแล้ว"];
            break;
        // 2. สร้างคลังสินค้าใหม่ (รันเลขให้อัตโนมัติใน DB)
        // 2. สร้างคลังสินค้าใหม่ (หารหัสที่ว่างอยู่ / อุดช่องโหว่)
        case 'add_empty_warehouse':
            try {
                // ดึงรหัสคลังทั้งหมดมาเรียงจากน้อยไปมาก (W00001, W00002, W00003, W00005...)
                $stmt = $conn->query("SELECT WarehouseID FROM Warehouse ORDER BY WarehouseID ASC");
                $existing_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $next_num = 1; // ตั้งเป้าว่าจะหาเลข 1 ก่อน
                
                // วนลูปเช็คทีละเลข
                foreach ($existing_ids as $id) {
                    $num = intval(substr($id, 1)); // ตัด 'W' ออกแล้วแปลงเป็นตัวเลข
                    
                    if ($num == $next_num) {
                        // ถ้าเลขตรงกับที่หาอยู่ ให้ขยับเป้าหมายไปหาเลขถัดไป
                        $next_num++; 
                    } else if ($num > $next_num) {
                        // ถ้าเลขที่ดึงมา มากกว่าเลขที่หาอยู่
                        break; 
                    }
                }
                $newId = 'W' . str_pad($next_num, 5, '0', STR_PAD_LEFT);

                // Insert แบบ Quantity = 0 ทันที
                $stmtInsert = $conn->prepare("INSERT INTO Warehouse (WarehouseID, WarehouseQuantity, LastUpdate) VALUES (?, 0, CURRENT_TIMESTAMP)");
                $stmtInsert->execute([$newId]);
                
                $response = ["status" => "success", "message" => "เปิดคลังใหม่รหัส $newId สำเร็จ!"];
            } catch (Exception $e) {
                $response = ["status" => "error", "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()];
            }
            break;

        // 3. ลบคลังสินค้า หรือ ลบสินค้า
        // 3. ลบคลังสินค้า (และเอาสินค้าออกจากคลังนั้น โดยไม่กระทบข้อมูลสินค้าหลัก)
        case 'delete_warehouse_item':
            $warehouseID = $_POST['warehouse_id'] ?? '';
            $productID = $_POST['product_id'] ?? '';

            try {
                $conn->beginTransaction();
                
                if (!empty($warehouseID) && !empty($productID)) {
                    // กรณีที่ 1: ลบสินค้าที่ผูกกับคลัง (มีทั้ง W000XX และ P000XX)
                    $stmtDel = $conn->prepare("DELETE FROM Store WHERE WarehouseID = ? AND ProductID = ?");
                    $stmtDel->execute([$warehouseID, $productID]);
                    
                    $stmtSync = $conn->prepare("
                        UPDATE Warehouse 
                        SET WarehouseQuantity = (SELECT COALESCE(SUM(Quantity), 0) FROM Store WHERE WarehouseID = ?), 
                            LastUpdate = CURRENT_TIMESTAMP 
                        WHERE WarehouseID = ?
                    ");
                    $stmtSync->execute([$warehouseID, $warehouseID]);

                    $msg = "นำสินค้า $productID ออกจากคลัง $warehouseID เรียบร้อยแล้ว";

                } else if (!empty($warehouseID) && empty($productID)) {
                    // กรณีที่ 2: ลบคลังว่าง (แถวสีเหลืองที่มีแต่ W000XX)
                    // เช็คให้ชัวร์ก่อนว่า WarehouseQuantity เป็น 0 จริงๆ
                    $stmtCheck = $conn->prepare("SELECT WarehouseQuantity FROM Warehouse WHERE WarehouseID = ?");
                    $stmtCheck->execute([$warehouseID]);
                    $wQty = $stmtCheck->fetchColumn();

                    if ($wQty > 0) {
                        throw new Exception("ไม่สามารถลบคลังได้! คลังสินค้านี้ยังมีของอยู่ ($wQty ชิ้น)");
                    }

                    // ถ้ายอดเป็น 0 และไม่มีของแล้ว ถึงจะอนุญาตให้ลบคลังทิ้งได้
                    $conn->prepare("DELETE FROM Warehouse WHERE WarehouseID = ?")->execute([$warehouseID]);
                    $msg = "ลบคลังว่าง $warehouseID สำเร็จ";

                } else {
                    throw new Exception("ข้อมูลไม่ครบถ้วน ไม่สามารถทำรายการได้");
                }
                
                $conn->commit();
                $response = ["status" => "success", "message" => $msg];
            } catch (Exception $e) {
                $conn->rollBack();
                $response = ["status" => "error", "message" => $e->getMessage()];
            }
            break;
    }
    
    
} catch (PDOException $e) {
    // ดักจับ Error จาก Database
    if ($conn->inTransaction()) {
        $conn->rollBack(); // ยกเลิกข้อมูลถ้าเกิดพังกลางคัน
    }
    $response = [
        "status" => "error", 
        "message" => "Database Error: " . $e->getMessage()
    ];
}

// แปลงผลลัพธ์ Array เป็น JSON เพื่อส่งกลับไปยัง JavaScript
echo json_encode($response);
exit();
?>