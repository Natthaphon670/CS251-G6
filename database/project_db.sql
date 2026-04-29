-- ========================================================
-- สร้างฐานข้อมูล
-- ========================================================
CREATE DATABASE IF NOT EXISTS DepartmentStoreDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE DepartmentStoreDB;

-- ========================================================
-- 1. ตารางหลัก (Master Tables) ที่ไม่มี Foreign Key
-- ========================================================

-- ตารางพนักงาน (Employee)
CREATE TABLE Employee (
    EmployeeID VARCHAR(6) PRIMARY KEY,
    EmployeeName VARCHAR(100) NOT NULL,
    Position VARCHAR(50),
    EmTelephone VARCHAR(12)
);

-- ตารางบัญชีผู้ใช้งาน (UserAccount)
CREATE TABLE UserAccount (
    AccountID VARCHAR(6) PRIMARY KEY,
    Username VARCHAR(50) NOT NULL UNIQUE,
    Password VARCHAR(255) NOT NULL,
    Role VARCHAR(20) NOT NULL
);

-- ตารางพื้นที่เช่า (RentalSpace)
CREATE TABLE RentalSpace (
    SpaceID VARCHAR(6) PRIMARY KEY,
    Floor INT,
    Location VARCHAR(100),
    Size DECIMAL(8,2)
);

-- ตารางสินค้า (Product)
CREATE TABLE Product (
    ProductID VARCHAR(6) PRIMARY KEY,
    ProductName VARCHAR(100) NOT NULL,
    ProductPrice DECIMAL(10,2) NOT NULL,
    ProductDescription VARCHAR(255)
);

-- ตารางหมวดหมู่สินค้า (Category)
CREATE TABLE Category (
    CategoryID VARCHAR(6) PRIMARY KEY,
    CategoryName VARCHAR(100) NOT NULL
);

-- ตารางผู้จัดจำหน่าย (Supplier)
CREATE TABLE Supplier (
    SupplierID VARCHAR(6) PRIMARY KEY,
    SupplierName VARCHAR(100) NOT NULL,
    SupplierContactInfo VARCHAR(255)
);

-- ตารางคลังสินค้า (Warehouse)
CREATE TABLE Warehouse (
    WarehouseID VARCHAR(6) PRIMARY KEY,
    WarehouseQuantity INT NOT NULL DEFAULT 0,
    LastUpdate DATETIME
);

-- ========================================================
-- 2. ตารางที่มีการเชื่อมโยง Foreign Key (Dependent Tables)
-- ========================================================

-- ตารางผู้เช่าร้านค้า (Tenant)
CREATE TABLE Tenant (
    TenantID VARCHAR(6) PRIMARY KEY,
    TenantName VARCHAR(100) NOT NULL,
    TenantCategory VARCHAR(50),
    TenantContactInfo VARCHAR(255),
    AccountID VARCHAR(6),
    FOREIGN KEY (AccountID) REFERENCES UserAccount(AccountID) ON DELETE SET NULL
);

-- ตารางสัญญาเช่า (LeaseContract)
CREATE TABLE LeaseContract (
    ContractID VARCHAR(6) PRIMARY KEY,
    StartDateLease DATE NOT NULL,
    EndDateLease DATE NOT NULL,
    TenantID VARCHAR(6),
    SpaceID VARCHAR(6),
    FOREIGN KEY (TenantID) REFERENCES Tenant(TenantID) ON DELETE CASCADE,
    FOREIGN KEY (SpaceID) REFERENCES RentalSpace(SpaceID) ON DELETE CASCADE
);

-- ตารางใบแจ้งหนี้ (Invoice)
CREATE TABLE Invoice (
    InvoiceID VARCHAR(8) PRIMARY KEY,
    InvoiceDate DATE NOT NULL,
    Amount DECIMAL(10,2) NOT NULL,
    Status VARCHAR(20),
    ContractID VARCHAR(6),
    FOREIGN KEY (ContractID) REFERENCES LeaseContract(ContractID) ON DELETE CASCADE
);

-- ตารางโปรโมชัน (Promotion)
CREATE TABLE Promotion (
    PromotionID VARCHAR(6) PRIMARY KEY,
    PromotionName VARCHAR(100) NOT NULL,
    Discount DECIMAL(5,2),
    StartDatePromotion DATE,
    EndDatePromotion DATE,
    TenantID VARCHAR(6),
    FOREIGN KEY (TenantID) REFERENCES Tenant(TenantID) ON DELETE CASCADE
);

-- ตารางการขาย (Sale)
CREATE TABLE Sale (
    SalesID VARCHAR(6) PRIMARY KEY,
    SalesDate DATETIME NOT NULL,
    Quantity INT NOT NULL,
    TenantID VARCHAR(6),
    ProductID VARCHAR(6),
    FOREIGN KEY (TenantID) REFERENCES Tenant(TenantID) ON DELETE CASCADE,
    FOREIGN KEY (ProductID) REFERENCES Product(ProductID) ON DELETE CASCADE
);

-- ตารางรายงาน (Report)
CREATE TABLE Report (
    ReportID VARCHAR(6) PRIMARY KEY,
    ReportName VARCHAR(100) NOT NULL,
    ReportDate DATE NOT NULL,
    EmployeeID VARCHAR(6),
    FOREIGN KEY (EmployeeID) REFERENCES Employee(EmployeeID) ON DELETE SET NULL
);

-- ========================================================
-- 3. ตารางความสัมพันธ์ (Junction Tables จากความสัมพันธ์ Many-to-Many)
-- ========================================================

-- ตารางจัดหมวดหมู่สินค้า (Categorize)
CREATE TABLE Categorize (
    ProductID VARCHAR(6),
    CategoryID VARCHAR(6),
    PRIMARY KEY (ProductID, CategoryID),
    FOREIGN KEY (ProductID) REFERENCES Product(ProductID) ON DELETE CASCADE,
    FOREIGN KEY (CategoryID) REFERENCES Category(CategoryID) ON DELETE CASCADE
);

-- ตารางแหล่งที่มาสินค้า (Supply)
CREATE TABLE Supply (
    ProductID VARCHAR(6),
    SupplierID VARCHAR(6),
    PRIMARY KEY (ProductID, SupplierID),
    FOREIGN KEY (ProductID) REFERENCES Product(ProductID) ON DELETE CASCADE,
    FOREIGN KEY (SupplierID) REFERENCES Supplier(SupplierID) ON DELETE CASCADE
);

-- ตารางจัดเก็บสินค้าในคลัง (Store)
CREATE TABLE Store (
    ProductID VARCHAR(6),
    WarehouseID VARCHAR(6),
    PRIMARY KEY (ProductID, WarehouseID),
    FOREIGN KEY (ProductID) REFERENCES Product(ProductID) ON DELETE CASCADE,
    FOREIGN KEY (WarehouseID) REFERENCES Warehouse(WarehouseID) ON DELETE CASCADE
);

-- ตารางสินค้าร่วมโปรโมชัน (Cheapen)
CREATE TABLE Cheapen (
    ProductID VARCHAR(6),
    PromotionID VARCHAR(6),
    PRIMARY KEY (ProductID, PromotionID),
    FOREIGN KEY (ProductID) REFERENCES Product(ProductID) ON DELETE CASCADE,
    FOREIGN KEY (PromotionID) REFERENCES Promotion(PromotionID) ON DELETE CASCADE
);


