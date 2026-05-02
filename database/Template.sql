-- ========================================================
-- 1. ข้อมูลตารางหลักที่ไม่มี Foreign Key (หรือต้องสร้างก่อน)
-- ========================================================

-- เพิ่มข้อมูลบัญชีผู้ใช้งาน (8 บัญชี ครบทุก Role)
INSERT INTO UserAccount (AccountID, Username, Password, Role) VALUES
('A00001', 'admin_somchai', '123456', 'Admin'),
('A00002', 'emp_somsri', '123456', 'Employee'),
('A00003', 'tenant_fashion', '123456', 'Tenant'),
('A00004', 'tenant_gadget', '123456', 'Tenant'),
('A00005', 'admin_mana', '123456', 'Admin'),
('A00006', 'emp_wipa', '123456', 'Employee'),
('A00007', 'tenant_sport', '123456', 'Tenant'),
('A00008', 'tenant_pcmaster', '123456', 'Tenant');

-- เพิ่มข้อมูลพนักงาน (4 คน อ้างอิง AccountID A00001, A00002, A00005, A00006)
INSERT INTO Employee (EmployeeID, EmployeeName, Position, EmTelephone, AccountID) VALUES
('E00001', 'สมชาย ใจดี', 'Store Manager', '0811111111', 'A00001'),
('E00002', 'สมศรี รักงาน', 'Sales Coordinator', '0822222222', 'A00002'),
('E00003', 'มานะ อดทน', 'HR Manager', '0899999999', 'A00005'),
('E00004', 'วิภาวดี สีใส', 'Accountant', '0888888888', 'A00006');

-- เพิ่มข้อมูลพื้นที่เช่า (4 พื้นที่)
INSERT INTO RentalSpace (SpaceID, Floor, Location, Size) VALUES
('S00001', 1, 'Zone A - หน้าประตูทางเข้า', 50.00),
('S00002', 2, 'Zone B - โซนไอที', 30.50),
('S00003', 3, 'Zone C - โซนกีฬาและสุขภาพ', 80.00),
('S00004', 1, 'Zone D - ลานโปรโมชัน', 40.00);

-- เพิ่มข้อมูลสินค้า (8 รายการ)
INSERT INTO Product (ProductID, ProductName, ProductPrice, ProductDescription) VALUES
('P00001', 'เสื้อยืด Cotton 100%', 250.00, 'เสื้อยืดสีพื้นสวมใส่สบาย'),
('P00002', 'กางเกงยีนส์แฟชั่น', 890.00, 'กางเกงยีนส์ทรงกระบอก'),
('P00003', 'หูฟังไร้สาย Bluetooth', 1290.00, 'หูฟังตัดเสียงรบกวน'),
('P00004', 'สายชาร์จ Fast Charge', 150.00, 'สายชาร์จยาว 2 เมตร'),
('P00005', 'รองเท้าผ้าใบสำหรับวิ่ง', 2500.00, 'รองเท้าวิ่งน้ำหนักเบา ซับแรงกระแทก'),
('P00006', 'กระเป๋าสะพายข้างสตรี', 590.00, 'กระเป๋าหนังเทียม แฟชั่นเกาหลี'),
('P00007', 'คีย์บอร์ดไร้สาย (Mechanical)', 1890.00, 'คีย์บอร์ดบลูทูธ พิมพ์สนุก'),
('P00008', 'เมาส์เกมมิ่ง RGB', 850.00, 'เมาส์เล่นเกม ปรับความเร็วได้');

-- เพิ่มข้อมูลหมวดหมู่สินค้า (4 หมวดหมู่)
INSERT INTO Category (CategoryID, CategoryName) VALUES
('C00001', 'เครื่องแต่งกาย'),
('C00002', 'อุปกรณ์อิเล็กทรอนิกส์'),
('C00003', 'รองเท้าและกระเป๋า'),
('C00004', 'อุปกรณ์คอมพิวเตอร์');

-- เพิ่มข้อมูลผู้จัดจำหน่าย (4 บริษัท)
INSERT INTO Supplier (SupplierID, SupplierName, SupplierContactInfo) VALUES
('SP0001', 'บริษัท เสื้อผ้าไทย จำกัด', 'โทร: 02-111-1111, Email: contact@thaiclothes.com'),
('SP0002', 'บริษัท ไอทีพรีเมียม จำกัด', 'โทร: 02-222-2222, Email: sales@itpremium.com'),
('SP0003', 'บริษัท สปอร์ตแอนด์แบ็ก จำกัด', 'โทร: 02-333-3333, Email: info@sportbag.com'),
('SP0004', 'บริษัท เทคแกดเจ็ตเวิลด์ จำกัด', 'โทร: 02-444-4444, Email: support@techgadget.com');

-- เพิ่มข้อมูลคลังสินค้า (4 คลัง)
INSERT INTO Warehouse (WarehouseID, WarehouseQuantity, LastUpdate) VALUES
('W00001', 1000, CURRENT_TIMESTAMP),
('W00002', 500, CURRENT_TIMESTAMP),
('W00003', 800, CURRENT_TIMESTAMP),
('W00004', 350, CURRENT_TIMESTAMP);

-- ========================================================
-- 2. ข้อมูลตารางที่มี Foreign Key อ้างอิง
-- ========================================================

-- เพิ่มข้อมูลผู้เช่าร้านค้า (4 ร้าน อ้างอิง AccountID A00003, A00004, A00007, A00008)
INSERT INTO Tenant (TenantID, TenantName, TenantCategory, TenantContactInfo, AccountID) VALUES
('T00001', 'Fashion Hub', 'เสื้อผ้าและแฟชั่น', '0833333333', 'A00003'),
('T00002', 'Gadget Zone', 'สินค้าไอที', '0844444444', 'A00004'),
('T00003', 'Sport Center', 'อุปกรณ์กีฬาและไลฟ์สไตล์', '0855555555', 'A00007'),
('T00004', 'PC Master', 'คอมพิวเตอร์และอุปกรณ์', '0866666666', 'A00008');

-- เพิ่มข้อมูลสัญญาเช่า (4 สัญญา)
INSERT INTO LeaseContract (ContractID, StartDateLease, EndDateLease, TenantID, SpaceID) VALUES
('LC0001', '2026-01-01', '2026-12-31', 'T00001', 'S00001'),
('LC0002', '2026-02-01', '2026-01-31', 'T00002', 'S00002'),
('LC0003', '2025-03-01', '2026-02-28', 'T00003', 'S00003'),
('LC0004', '2025-04-01', '2026-03-31', 'T00004', 'S00004');

-- เพิ่มข้อมูลใบแจ้งหนี้ (4 ใบ)
INSERT INTO Invoice (InvoiceID, InvoiceDate, Amount, Status, ContractID) VALUES
('INV00001', '2024-01-05', 15000.00, 'Paid', 'LC0001'),
('INV00002', '2024-02-05', 12000.00, 'Unpaid', 'LC0002'),
('INV00003', '2024-03-05', 25000.00, 'Paid', 'LC0003'),
('INV00004', '2024-04-05', 18000.00, 'Pending', 'LC0004');

-- เพิ่มข้อมูลโปรโมชันของร้านค้า (4 โปรโมชัน)
INSERT INTO Promotion (PromotionID, PromotionName, Discount, StartDatePromotion, EndDatePromotion, TenantID) VALUES
('PR0001', 'Summer Sale ลดดับร้อน', 15.00, '2026-04-01', '2026-05-30', 'T00001'),
('PR0002', 'Gadget Payday', 10.00, '2026-04-25', '2026-05-05', 'T00002'),
('PR0003', 'Back to School', 20.00, '2026-05-01', '2026-05-31', 'T00003'),
('PR0004', 'Clearance Sale ลดล้างสต็อก', 30.00, '2026-06-01', '2026-06-15', 'T00004');

-- เพิ่มข้อมูลการขาย (7 รายการ)
INSERT INTO Sale (SalesID, SalesDate, Quantity, TenantID, ProductID) VALUES
('SL0001', '2026-04-10 14:30:00', 2, 'T00001', 'P00001'),
('SL0002', '2026-04-10 15:45:00', 1, 'T00001', 'P00002'),
('SL0003', '2026-04-11 10:15:00', 1, 'T00002', 'P00003'),
('SL0004', '2026-05-12 11:00:00', 1, 'T00003', 'P00005'),
('SL0005', '2026-05-13 13:20:00', 3, 'T00001', 'P00006'),
('SL0006', '2026-05-14 16:45:00', 2, 'T00004', 'P00007'),
('SL0007', '2026-05-15 18:30:00', 1, 'T00004', 'P00008');

-- เพิ่มข้อมูลรายงาน (4 รายงาน)
INSERT INTO Report (ReportID, ReportName, ReportDate, EmployeeID) VALUES
('R00001', 'รายงานยอดขายประจำเดือน มีนาคม', '2024-03-31', 'E00001'),
('R00002', 'รายงานสถานะการเช่าพื้นที่', '2024-04-01', 'E00002'),
('R00003', 'รายงานสต็อกสินค้าคงเหลือ', '2024-05-01', 'E00003'),
('R00004', 'สรุปยอดรายรับค่าเช่า Q1', '2024-05-05', 'E00004');

-- ========================================================
-- 3. ข้อมูลตารางความสัมพันธ์ (Many-to-Many)
-- ========================================================

-- จัดหมวดหมู่สินค้า
INSERT INTO Categorize (ProductID, CategoryID) VALUES
('P00001', 'C00001'),
('P00002', 'C00001'),
('P00003', 'C00002'),
('P00004', 'C00002'),
('P00005', 'C00003'),
('P00006', 'C00003'),
('P00007', 'C00004'),
('P00008', 'C00004');

-- แหล่งที่มาสินค้า
INSERT INTO Supply (ProductID, SupplierID) VALUES
('P00001', 'SP0001'),
('P00002', 'SP0001'),
('P00003', 'SP0002'),
('P00004', 'SP0002'),
('P00005', 'SP0003'),
('P00006', 'SP0003'),
('P00007', 'SP0004'),
('P00008', 'SP0004');

-- จัดเก็บสินค้าในคลัง
INSERT INTO Store (ProductID, WarehouseID) VALUES
('P00001', 'W00001'),
('P00002', 'W00001'),
('P00003', 'W00002'),
('P00004', 'W00002'),
('P00005', 'W00003'),
('P00006', 'W00003'),
('P00007', 'W00004'),
('P00008', 'W00004');

-- สินค้าร่วมโปรโมชัน
INSERT INTO Cheapen (ProductID, PromotionID) VALUES
('P00001', 'PR0001'),
('P00002', 'PR0001'),
('P00003', 'PR0002'),
('P00005', 'PR0003'),
('P00006', 'PR0003'),
('P00007', 'PR0004'),
('P00008', 'PR0004');

INSERT INTO HaveProduct (TenantID, ProductID) VALUES
-- ร้าน T00001: Fashion Hub (ขายเสื้อผ้าและแฟชั่น)
('T00001', 'P00001'), -- เสื้อยืด Cotton 100%
('T00001', 'P00002'), -- กางเกงยีนส์แฟชั่น
('T00001', 'P00006'), -- กระเป๋าสะพายข้างสตรี

-- ร้าน T00002: Gadget Zone (ขายสินค้าไอที)
('T00002', 'P00003'), -- หูฟังไร้สาย Bluetooth
('T00002', 'P00004'), -- สายชาร์จ Fast Charge

-- ร้าน T00003: Sport Center (ขายอุปกรณ์กีฬา)
('T00003', 'P00005'), -- รองเท้าผ้าใบสำหรับวิ่ง

-- ร้าน T00004: PC Master (ขายคอมพิวเตอร์และอุปกรณ์)
('T00004', 'P00007'), -- คีย์บอร์ดไร้สาย (Mechanical)
('T00004', 'P00008'); -- เมาส์เกมมิ่ง RGB

--Update set random quantity in store table
UPDATE Store SET Quantity = FLOOR(10 + (RAND() * 91));
--Update WarehouseQuantity (warehouse table)
UPDATE Warehouse w
LEFT JOIN (
    SELECT WarehouseID, SUM(Quantity) as TotalQuantity
    FROM Store
    GROUP BY WarehouseID
) s ON w.WarehouseID = s.WarehouseID
SET w.WarehouseQuantity = COALESCE(s.TotalQuantity, 0);