USE DepartmentStoreDB;

-- ========================================================
-- 1. เพิ่มข้อมูลในตารางหลัก (Master Tables)
-- ========================================================

-- ข้อมูลพนักงาน (Employee)
INSERT INTO Employee (EmployeeID, EmployeeName, Position, EmTelephone) VALUES
('EMP001', 'สมชาย ใจดี', 'Manager', '0812345678'),
('EMP002', 'สมหญิง รักงาน', 'Staff', '0898765432'),
('EMP003', 'มานะ อดทน', 'Staff', '0811112222'),
('EMP004', 'ปิติ ยินดี', 'Technician', '0822223333'),
('EMP005', 'ชูใจ ร่าเริง', 'Accountant', '0833334444'),
('EMP006', 'วิไล สวยงาม', 'Staff', '0844445555'),
('EMP007', 'ธนา ร่ำรวย', 'Manager', '0855556666'),
('EMP008', 'สุชาติ แข็งขัน', 'Security', '0866667777'),
('EMP009', 'อรุณ แสงทอง', 'Cleaner', '0877778888'),
('EMP010', 'นารี มีสุข', 'HR', '0888889999');

-- ข้อมูลบัญชีผู้ใช้งาน (UserAccount)
INSERT INTO UserAccount (AccountID, Username, Password, Role) VALUES
('ACC001', 'tenant_a', 'hash_pw_1', 'Tenant'),
('ACC002', 'tenant_b', 'hash_pw_2', 'Tenant'),
('ACC003', 'tenant_c', 'hash_pw_3', 'Tenant'),
('ACC004', 'tenant_d', 'hash_pw_4', 'Tenant'),
('ACC005', 'tenant_e', 'hash_pw_5', 'Tenant'),
('ACC006', 'tenant_f', 'hash_pw_6', 'Tenant'),
('ACC007', 'admin_somchai', 'hash_pw_7', 'Admin'),
('ACC008', 'staff_ying', 'hash_pw_8', 'Staff'),
('ACC009', 'tenant_g', 'hash_pw_9', 'Tenant'),
('ACC010', 'tenant_h', 'hash_pw_10', 'Tenant');

-- ข้อมูลพื้นที่เช่า (RentalSpace)
INSERT INTO RentalSpace (SpaceID, Floor, Location, Size) VALUES
('SPC001', 1, 'Zone A - Front', 50.00),
('SPC002', 1, 'Zone A - Center', 45.50),
('SPC003', 1, 'Zone B', 60.00),
('SPC004', 2, 'Food Court A', 20.00),
('SPC005', 2, 'Food Court B', 25.00),
('SPC006', 3, 'IT Zone', 100.00),
('SPC007', 3, 'IT Zone Mini', 30.00),
('SPC008', 4, 'Cinema Front', 80.00),
('SPC009', 1, 'Event Hall', 150.00),
('SPC010', 5, 'Rooftop Bar', 200.00);

-- ข้อมูลสินค้า (Product)
INSERT INTO Product (ProductID, ProductName, ProductPrice, ProductDescription) VALUES
('PRD001', 'เสื้อยืดคอกลม', 199.00, 'เสื้อยืดคอตตอน 100%'),
('PRD002', 'กางเกงยีนส์', 890.00, 'กางเกงยีนส์ทรงกระบอก'),
('PRD003', 'สมาร์ทโฟน X1', 15900.00, 'มือถือรุ่นใหม่ล่าสุด'),
('PRD004', 'หูฟังไร้สาย', 1290.00, 'หูฟังบลูทูธ 5.0'),
('PRD005', 'ชาไข่มุก', 50.00, 'ชาไต้หวันต้นตำรับ'),
('PRD006', 'กาแฟอเมริกาโน่', 60.00, 'กาแฟคั่วกลาง'),
('PRD007', 'กระเป๋าหนัง', 2500.00, 'กระเป๋าหนังแท้ 100%'),
('PRD008', 'รองเท้าผ้าใบ', 1500.00, 'รองเท้าใส่วิ่ง'),
('PRD009', 'ครีมกันแดด', 350.00, 'SPF 50 PA+++'),
('PRD010', 'ตุ๊กตาหมี', 450.00, 'ตุ๊กตานุ่มนิ่ม ขนาด 50cm');

-- ข้อมูลหมวดหมู่สินค้า (Category)
INSERT INTO Category (CategoryID, CategoryName) VALUES
('CAT001', 'เสื้อผ้า (Clothing)'),
('CAT002', 'กางเกง (Pants)'),
('CAT003', 'อิเล็กทรอนิกส์ (Electronics)'),
('CAT004', 'อุปกรณ์เสริม (Accessories)'),
('CAT005', 'เครื่องดื่ม (Beverages)'),
('CAT006', 'กระเป๋า (Bags)'),
('CAT007', 'รองเท้า (Shoes)'),
('CAT008', 'ความงาม (Beauty)'),
('CAT009', 'ของเล่น (Toys)'),
('CAT010', 'อาหาร (Food)');

-- ข้อมูลผู้จัดจำหน่าย (Supplier)
INSERT INTO Supplier (SupplierID, SupplierName, SupplierContactInfo) VALUES
('SUP001', 'Thai Garment Co.', 'garment@email.com, 021112222'),
('SUP002', 'Tech Asia', 'techasia@email.com, 023334444'),
('SUP003', 'Fresh Tea Farm', 'freshtea@email.com, 025556666'),
('SUP004', 'Leather World', 'leather@email.com, 027778888'),
('SUP005', 'Sneaker Hub', 'sneaker@email.com, 029990000'),
('SUP006', 'Beauty Care Co.', 'beauty@email.com, 021231234'),
('SUP007', 'Toy Maker Ltd.', 'toymaker@email.com, 024564567'),
('SUP008', 'Coffee Beans', 'coffee@email.com, 027897890'),
('SUP009', 'Denim Studio', 'denim@email.com, 023213210'),
('SUP010', 'Gadget Pro', 'gadget@email.com, 026546543');

-- ข้อมูลคลังสินค้า (Warehouse)
INSERT INTO Warehouse (WarehouseID, WarehouseQuantity, LastUpdate) VALUES
('WH0001', 500, '2023-10-01 10:00:00'),
('WH0002', 200, '2023-10-02 11:30:00'),
('WH0003', 1000, '2023-10-03 09:15:00'),
('WH0004', 150, '2023-10-04 14:20:00'),
('WH0005', 800, '2023-10-05 16:45:00'),
('WH0006', 300, '2023-10-06 08:00:00'),
('WH0007', 450, '2023-10-07 13:10:00'),
('WH0008', 600, '2023-10-08 15:25:00'),
('WH0009', 250, '2023-10-09 17:50:00'),
('WH0010', 900, '2023-10-10 12:00:00');


-- ========================================================
-- 2. เพิ่มข้อมูลในตารางที่มี Foreign Key (Dependent Tables)
-- ========================================================

-- ข้อมูลผู้เช่าร้านค้า (Tenant)
INSERT INTO Tenant (TenantID, TenantName, TenantCategory, TenantContactInfo, AccountID) VALUES
('TEN001', 'ร้านเสื้อผ้าสวย', 'Fashion', '0810001111', 'ACC001'),
('TEN002', 'Tech Shop', 'IT', '0810002222', 'ACC002'),
('TEN003', 'ชาไข่มุกฟินๆ', 'F&B', '0810003333', 'ACC003'),
('TEN004', 'กระเป๋าพรีเมียม', 'Fashion', '0810004444', 'ACC004'),
('TEN005', 'รองเท้าสุดเท่', 'Fashion', '0810005555', 'ACC005'),
('TEN006', 'กาแฟหอมกรุ่น', 'F&B', '0810006666', 'ACC006'),
('TEN007', 'เครื่องสำอางเกาหลี', 'Beauty', '0810007777', 'ACC009'),
('TEN008', 'เมืองของเล่น', 'Kids', '0810008888', 'ACC010'),
('TEN009', 'กางเกงยีนส์วินเทจ', 'Fashion', '0810009999', NULL),
('TEN010', 'สมาร์ทโฟนเซ็นเตอร์', 'IT', '0820000000', NULL);

-- ข้อมูลสัญญาเช่า (LeaseContract)
INSERT INTO LeaseContract (ContractID, StartDateLease, EndDateLease, TenantID, SpaceID) VALUES
('CTR001', '2023-01-01', '2024-01-01', 'TEN001', 'SPC001'),
('CTR002', '2023-02-01', '2024-02-01', 'TEN002', 'SPC006'),
('CTR003', '2023-03-01', '2024-03-01', 'TEN003', 'SPC004'),
('CTR004', '2023-04-01', '2024-04-01', 'TEN004', 'SPC002'),
('CTR005', '2023-05-01', '2024-05-01', 'TEN005', 'SPC003'),
('CTR006', '2023-06-01', '2024-06-01', 'TEN006', 'SPC005'),
('CTR007', '2023-07-01', '2024-07-01', 'TEN007', 'SPC008'),
('CTR008', '2023-08-01', '2024-08-01', 'TEN008', 'SPC007'),
('CTR009', '2023-09-01', '2024-09-01', 'TEN009', 'SPC009'),
('CTR010', '2023-10-01', '2024-10-01', 'TEN010', 'SPC010');

-- ข้อมูลใบแจ้งหนี้ (Invoice) -- InvoiceID เป็น VARCHAR(8)
INSERT INTO Invoice (InvoiceID, InvoiceDate, Amount, Status, ContractID) VALUES
('INV00001', '2023-01-05', 15000.00, 'Paid', 'CTR001'),
('INV00002', '2023-02-05', 30000.00, 'Paid', 'CTR002'),
('INV00003', '2023-03-05', 8000.00, 'Paid', 'CTR003'),
('INV00004', '2023-04-05', 12000.00, 'Unpaid', 'CTR004'),
('INV00005', '2023-05-05', 18000.00, 'Paid', 'CTR005'),
('INV00006', '2023-06-05', 9000.00, 'Unpaid', 'CTR006'),
('INV00007', '2023-07-05', 25000.00, 'Paid', 'CTR007'),
('INV00008', '2023-08-05', 10000.00, 'Paid', 'CTR008'),
('INV00009', '2023-09-05', 40000.00, 'Unpaid', 'CTR009'),
('INV00010', '2023-10-05', 50000.00, 'Pending', 'CTR010');

-- ข้อมูลโปรโมชัน (Promotion)
INSERT INTO Promotion (PromotionID, PromotionName, Discount, StartDatePromotion, EndDatePromotion, TenantID) VALUES
('PRM001', 'ลดรับปีใหม่', 10.00, '2023-12-25', '2024-01-05', 'TEN001'),
('PRM002', 'Tech Sale', 5.00, '2023-11-01', '2023-11-15', 'TEN002'),
('PRM003', 'ซื้อ 1 แถม 1', 50.00, '2023-10-01', '2023-10-31', 'TEN003'),
('PRM004', 'Mid Year Sale', 20.00, '2023-06-01', '2023-06-30', 'TEN004'),
('PRM005', 'ลดล้างสต็อก', 30.00, '2023-09-01', '2023-09-15', 'TEN005'),
('PRM006', 'สมาชิกรับส่วนลด', 15.00, '2023-01-01', '2023-12-31', 'TEN006'),
('PRM007', 'Beauty Week', 10.00, '2023-08-01', '2023-08-07', 'TEN007'),
('PRM008', 'วันเด็ก', 25.00, '2023-01-10', '2023-01-15', 'TEN008'),
('PRM009', 'ยีนส์แฟร์', 15.00, '2023-05-01', '2023-05-10', 'TEN009'),
('PRM010', 'เปิดร้านใหม่', 10.00, '2023-10-01', '2023-10-15', 'TEN010');

-- ข้อมูลการขาย (Sale)
INSERT INTO Sale (SalesID, SalesDate, Quantity, TenantID, ProductID) VALUES
('SAL001', '2023-10-25 10:30:00', 2, 'TEN001', 'PRD001'),
('SAL002', '2023-10-25 11:15:00', 1, 'TEN002', 'PRD003'),
('SAL003', '2023-10-25 12:00:00', 5, 'TEN003', 'PRD005'),
('SAL004', '2023-10-25 13:45:00', 1, 'TEN004', 'PRD007'),
('SAL005', '2023-10-25 14:20:00', 1, 'TEN005', 'PRD008'),
('SAL006', '2023-10-25 15:10:00', 3, 'TEN006', 'PRD006'),
('SAL007', '2023-10-25 16:30:00', 2, 'TEN007', 'PRD009'),
('SAL008', '2023-10-25 17:05:00', 1, 'TEN008', 'PRD010'),
('SAL009', '2023-10-25 18:20:00', 2, 'TEN009', 'PRD002'),
('SAL010', '2023-10-25 19:00:00', 1, 'TEN010', 'PRD004');

-- ข้อมูลรายงาน (Report)
INSERT INTO Report (ReportID, ReportName, ReportDate, EmployeeID) VALUES
('REP001', 'รายงานยอดขายรายวัน', '2023-10-25', 'EMP001'),
('REP002', 'รายงานผู้เช่าค้างชำระ', '2023-10-01', 'EMP005'),
('REP003', 'รายงานสรุปพื้นที่เช่า', '2023-09-30', 'EMP001'),
('REP004', 'รายงานซ่อมบำรุง', '2023-10-15', 'EMP004'),
('REP005', 'รายงานการเข้ากะ', '2023-10-20', 'EMP010'),
('REP006', 'รายงานสต็อกคงเหลือ', '2023-10-25', 'EMP007'),
('REP007', 'รายงานโปรโมชัน', '2023-10-10', 'EMP001'),
('REP008', 'รายงานความปลอดภัย', '2023-10-05', 'EMP008'),
('REP009', 'รายงานทำความสะอาด', '2023-10-25', 'EMP009'),
('REP010', 'รายงานยอดขายประจำเดือน', '2023-09-30', 'EMP007');


-- ========================================================
-- 3. เพิ่มข้อมูลในตารางความสัมพันธ์ (Junction Tables)
-- ========================================================

-- ตารางจัดหมวดหมู่สินค้า (Categorize)
INSERT INTO Categorize (ProductID, CategoryID) VALUES
('PRD001', 'CAT001'),
('PRD002', 'CAT001'),
('PRD002', 'CAT002'),
('PRD003', 'CAT003'),
('PRD004', 'CAT003'),
('PRD004', 'CAT004'),
('PRD005', 'CAT005'),
('PRD005', 'CAT010'),
('PRD006', 'CAT005'),
('PRD007', 'CAT006');

-- ตารางแหล่งที่มาสินค้า (Supply)
INSERT INTO Supply (ProductID, SupplierID) VALUES
('PRD001', 'SUP001'),
('PRD002', 'SUP009'),
('PRD003', 'SUP002'),
('PRD004', 'SUP010'),
('PRD005', 'SUP003'),
('PRD006', 'SUP008'),
('PRD007', 'SUP004'),
('PRD008', 'SUP005'),
('PRD009', 'SUP006'),
('PRD010', 'SUP007');

-- ตารางจัดเก็บสินค้าในคลัง (Store)
INSERT INTO Store (ProductID, WarehouseID) VALUES
('PRD001', 'WH0001'),
('PRD002', 'WH0002'),
('PRD003', 'WH0003'),
('PRD004', 'WH0004'),
('PRD005', 'WH0005'),
('PRD006', 'WH0006'),
('PRD007', 'WH0007'),
('PRD008', 'WH0008'),
('PRD009', 'WH0009'),
('PRD010', 'WH0010');

-- ตารางสินค้าร่วมโปรโมชัน (Cheapen)
INSERT INTO Cheapen (ProductID, PromotionID) VALUES
('PRD001', 'PRM001'),
('PRD003', 'PRM002'),
('PRD005', 'PRM003'),
('PRD007', 'PRM004'),
('PRD008', 'PRM005'),
('PRD006', 'PRM006'),
('PRD009', 'PRM007'),
('PRD010', 'PRM008'),
('PRD002', 'PRM009'),
('PRD004', 'PRM010');