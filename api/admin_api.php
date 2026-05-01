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
        case 'get_sales':
            // JOIN เอาชื่อสินค้าและชื่อร้านมาโชว์ด้วย
            $stmt = $conn->query("
                SELECT s.SalesID, s.SalesDate, s.Quantity, p.ProductName, t.TenantName 
                FROM Sale s 
                JOIN Product p ON s.ProductID = p.ProductID 
                JOIN Tenant t ON s.TenantID = t.TenantID 
                ORDER BY s.SalesDate DESC
            ");
            $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'add_sale':
            $salesID = $_POST['sales_id'];
            $salesDate = $_POST['sales_date']; // format: YYYY-MM-DD HH:MM:SS
            $quantity = $_POST['quantity'];
            $tenantID = $_POST['tenant_id'];
            $productID = $_POST['product_id'];

            $stmt = $conn->prepare("INSERT INTO Sale (SalesID, SalesDate, Quantity, TenantID, ProductID) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$salesID, $salesDate, $quantity, $tenantID, $productID]);
            $response = ["status" => "success", "message" => "บันทึกการขายสำเร็จ"];
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

    case 'get_warehouse':
            // JOIN หลายตารางเพื่อดึงข้อมูล Product, Warehouse, Category และ Supplier มาแสดงพร้อมกัน
            $sql = "SELECT p.ProductID, p.ProductName, w.WarehouseQuantity, c.CategoryName, s.SupplierName 
                    FROM Product p
                    JOIN Store st ON p.ProductID = st.ProductID
                    JOIN Warehouse w ON st.WarehouseID = w.WarehouseID
                    LEFT JOIN Categorize ct ON p.ProductID = ct.ProductID
                    LEFT JOIN Category c ON ct.CategoryID = c.CategoryID
                    LEFT JOIN Supply sup ON p.ProductID = sup.ProductID
                    LEFT JOIN Supplier s ON sup.SupplierID = s.SupplierID
                    ORDER BY p.ProductID ASC";
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