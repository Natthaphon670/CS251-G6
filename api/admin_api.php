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
            // ดึงข้อมูลยอดขายรวม (ดึงจำนวนจาก Sale * ราคาจาก Product)
            $stmtSales = $conn->query("SELECT SUM(s.Quantity * p.ProductPrice) as TotalSales FROM Sale s JOIN Product p ON s.ProductID = p.ProductID");
            $totalSales = $stmtSales->fetch(PDO::FETCH_ASSOC)['TotalSales'] ?? 0;

            // ดึงจำนวนร้านค้าทั้งหมด
            $stmtTenants = $conn->query("SELECT COUNT(*) as TotalTenants FROM Tenant");
            $totalTenants = $stmtTenants->fetch(PDO::FETCH_ASSOC)['TotalTenants'] ?? 0;

            // ดึงจำนวนสินค้าทั้งหมดในระบบ
            $stmtProducts = $conn->query("SELECT COUNT(*) as TotalProducts FROM Product");
            $totalProducts = $stmtProducts->fetch(PDO::FETCH_ASSOC)['TotalProducts'] ?? 0;

            $response = [
                "status" => "success",
                "data" => [
                    "total_sales" => number_format($totalSales, 2),
                    "total_tenants" => $totalTenants,
                    "total_products" => $totalProducts
                ]
            ];
            break;

        // ==========================================
        // ระบบที่ 2: Employee Management (จัดการพนักงาน)
        // ==========================================
        case 'get_employees':
            $stmt = $conn->query("SELECT * FROM Employee ORDER BY EmployeeID ASC");
            $response = ["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'add_employee':
            $empID = $_POST['employee_id']; 
            $empName = $_POST['employee_name'];
            $position = $_POST['position'];
            $tel = $_POST['telephone'];

            $stmt = $conn->prepare("INSERT INTO Employee (EmployeeID, EmployeeName, Position, EmTelephone) VALUES (?, ?, ?, ?)");
            $stmt->execute([$empID, $empName, $position, $tel]);
            
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
            $stmt = $conn->query("SELECT * FROM RentalSpace ORDER BY SpaceID ASC");
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
            $stmt = $conn->query("SELECT * FROM Invoice ORDER BY InvoiceDate DESC");
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