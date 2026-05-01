-- ========================================================
-- 1. สร้างฐานข้อมูล
-- ========================================================
CREATE DATABASE IF NOT EXISTS DepartmentStoreDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE DepartmentStoreDB;

-- ========================================================
-- 2. ตารางหลัก (Master Tables)
-- ========================================================

-- ตารางบัญชีผู้ใช้งาน (ศูนย์กลางการ Login สำหรับทุก Role)
CREATE TABLE UserAccount (
    AccountID VARCHAR(6) PRIMARY KEY,
    Username VARCHAR(50) NOT NULL UNIQUE,
    Password VARCHAR(255) NOT NULL,
    Role VARCHAR(20) NOT NULL -- เก็บค่าเป็น 'Admin', 'Employee', หรือ 'Tenant'
);


-- ตารางพื้นที่เช่า
CREATE TABLE RentalSpace (
    SpaceID VARCHAR(6) PRIMARY KEY,
    Floor INT,
    Location VARCHAR(100),
    Size DECIMAL(8,2)
);

-- ตารางสินค้า
CREATE TABLE Product (
    ProductID VARCHAR(6) PRIMARY KEY,
    ProductName VARCHAR(100) NOT NULL,
    ProductPrice DECIMAL(10,2) NOT NULL,
    ProductDescription VARCHAR(255)
);

-- ตารางหมวดหมู่สินค้า
CREATE TABLE Category (
    CategoryID VARCHAR(6) PRIMARY KEY,
    CategoryName VARCHAR(100) NOT NULL
);

-- ตารางผู้จัดจำหน่าย
CREATE TABLE Supplier (
    SupplierID VARCHAR(6) PRIMARY KEY,
    SupplierName VARCHAR(100) NOT NULL,
    SupplierContactInfo VARCHAR(255)
);

-- ตารางคลังสินค้า
CREATE TABLE Warehouse (
    WarehouseID VARCHAR(6) PRIMARY KEY,
    WarehouseQuantity INT NOT NULL DEFAULT 0,
    LastUpdate DATETIME
);

-- =======================================================
-- 3. ตารางที่มีการเชื่อมโยง Foreign Key (Dependent Tables)
-- =======================================================

-- ตารางพนักงาน (รวม Admin และพนักงานทั่วไป)
CREATE TABLE Employee (
    EmployeeID VARCHAR(6) PRIMARY KEY,
    EmployeeName VARCHAR(100) NOT NULL,
    Position VARCHAR(50),
    EmTelephone VARCHAR(12),
    AccountID VARCHAR(6), -- เชื่อมไปยังบัญชีผู้ใช้เพื่อ Login
    FOREIGN KEY (AccountID) REFERENCES UserAccount(AccountID) ON DELETE SET NULL
);

-- ตารางผู้เช่าร้านค้า
CREATE TABLE Tenant (
    TenantID VARCHAR(6) PRIMARY KEY,
    TenantName VARCHAR(100) NOT NULL,
    TenantCategory VARCHAR(50),
    TenantContactInfo VARCHAR(255),
    AccountID VARCHAR(6), -- เชื่อมไปยังบัญชีผู้ใช้เพื่อ Login
    FOREIGN KEY (AccountID) REFERENCES UserAccount(AccountID) ON DELETE SET NULL
);

-- ตารางสัญญาเช่า
CREATE TABLE LeaseContract (
    ContractID VARCHAR(6) PRIMARY KEY,
    StartDateLease DATE NOT NULL,
    EndDateLease DATE NOT NULL,
    TenantID VARCHAR(6),
    SpaceID VARCHAR(6),
    FOREIGN KEY (TenantID) REFERENCES Tenant(TenantID) ON DELETE CASCADE,
    FOREIGN KEY (SpaceID) REFERENCES RentalSpace(SpaceID) ON DELETE CASCADE
);

-- ตารางใบแจ้งหนี้
CREATE TABLE Invoice (
    InvoiceID VARCHAR(8) PRIMARY KEY,
    InvoiceDate DATE NOT NULL,
    Amount DECIMAL(10,2) NOT NULL,
    Status VARCHAR(20),
    ContractID VARCHAR(6),
    FOREIGN KEY (ContractID) REFERENCES LeaseContract(ContractID) ON DELETE CASCADE
);

-- ตารางโปรโมชัน
CREATE TABLE Promotion (
    PromotionID VARCHAR(6) PRIMARY KEY,
    PromotionName VARCHAR(100) NOT NULL,
    Discount DECIMAL(5,2),
    StartDatePromotion DATE,
    EndDatePromotion DATE,
    TenantID VARCHAR(6),
    FOREIGN KEY (TenantID) REFERENCES Tenant(TenantID) ON DELETE CASCADE
);

-- ตารางการขาย
CREATE TABLE Sale (
    SalesID VARCHAR(6) PRIMARY KEY,
    SalesDate DATETIME NOT NULL,
    Quantity INT NOT NULL,
    TenantID VARCHAR(6),
    ProductID VARCHAR(6),
    FOREIGN KEY (TenantID) REFERENCES Tenant(TenantID) ON DELETE CASCADE,
    FOREIGN KEY (ProductID) REFERENCES Product(ProductID) ON DELETE CASCADE
);

-- ตารางรายงาน
CREATE TABLE Report (
    ReportID VARCHAR(6) PRIMARY KEY,
    ReportName VARCHAR(100) NOT NULL,
    ReportDate DATE NOT NULL,
    EmployeeID VARCHAR(6),
    FOREIGN KEY (EmployeeID) REFERENCES Employee(EmployeeID) ON DELETE SET NULL
);

-- ========================================================
-- 4. ตารางความสัมพันธ์ (Junction Tables)
-- ========================================================

CREATE TABLE Categorize (
    ProductID VARCHAR(6),
    CategoryID VARCHAR(6),
    PRIMARY KEY (ProductID, CategoryID),
    FOREIGN KEY (ProductID) REFERENCES Product(ProductID) ON DELETE CASCADE,
    FOREIGN KEY (CategoryID) REFERENCES Category(CategoryID) ON DELETE CASCADE
);

CREATE TABLE Supply (
    ProductID VARCHAR(6),
    SupplierID VARCHAR(6),
    PRIMARY KEY (ProductID, SupplierID),
    FOREIGN KEY (ProductID) REFERENCES Product(ProductID) ON DELETE CASCADE,
    FOREIGN KEY (SupplierID) REFERENCES Supplier(SupplierID) ON DELETE CASCADE
);

CREATE TABLE Store (
    ProductID VARCHAR(6),
    WarehouseID VARCHAR(6),
    PRIMARY KEY (ProductID, WarehouseID),
    FOREIGN KEY (ProductID) REFERENCES Product(ProductID) ON DELETE CASCADE,
    FOREIGN KEY (WarehouseID) REFERENCES Warehouse(WarehouseID) ON DELETE CASCADE
);

CREATE TABLE Cheapen (
    ProductID VARCHAR(6),
    PromotionID VARCHAR(6),
    PRIMARY KEY (ProductID, PromotionID),
    FOREIGN KEY (ProductID) REFERENCES Product(ProductID) ON DELETE CASCADE,
    FOREIGN KEY (PromotionID) REFERENCES Promotion(PromotionID) ON DELETE CASCADE
);

CREATE TABLE HaveProduct (
    TenantID VARCHAR(10) NOT NULL,
    ProductID VARCHAR(10) NOT NULL,
    PRIMARY KEY (TenantID, ProductID),
    FOREIGN KEY (TenantID) REFERENCES Tenant(TenantID) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (ProductID) REFERENCES Product(ProductID) ON DELETE CASCADE ON UPDATE CASCADE
);