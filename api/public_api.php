<?php
// ========================================================
// api/public_api.php — Public API (ไม่ต้อง Login)
// ให้บริการข้อมูลร้านค้า, สินค้า, โปรโมชัน แก่ลูกค้าทั่วไป
// ========================================================

require_once __DIR__ . '/db.php';

// รับ action จาก query string เช่น ?action=get_tenants
$action = $_GET['action'] ?? '';

switch ($action) {

    // ─────────────────────────────────────────────
    // 1. ดึงรายชื่อร้านค้าทั้งหมด (Tenant Directory)
    //    GET /api/public_api.php?action=get_tenants
    // ─────────────────────────────────────────────
    case 'get_tenants':
        getTenants();
        break;

    // ─────────────────────────────────────────────
    // 2. ดึงสินค้าทั้งหมด (พร้อม category, ราคา, สต็อก)
    //    GET /api/public_api.php?action=get_products
    //    optional: &category_id=C001  &tenant_id=T001
    //              &search=ชื่อสินค้า  &page=1  &limit=20
    // ─────────────────────────────────────────────
    case 'get_products':
        getProducts();
        break;

    // ─────────────────────────────────────────────
    // 3. ดึงรายละเอียดสินค้าชิ้นเดียว + สต็อก
    //    GET /api/public_api.php?action=get_product_detail&product_id=P001
    // ─────────────────────────────────────────────
    case 'get_product_detail':
        getProductDetail();
        break;

    // ─────────────────────────────────────────────
    // 4. ดึงโปรโมชันที่กำลังใช้งานอยู่ (Active Promotions)
    //    GET /api/public_api.php?action=get_promotions
    //    optional: &tenant_id=T001
    // ─────────────────────────────────────────────
    case 'get_promotions':
        getPromotions();
        break;

    // ─────────────────────────────────────────────
    // 5. ดึงหมวดหมู่สินค้าทั้งหมด (Categories)
    //    GET /api/public_api.php?action=get_categories
    // ─────────────────────────────────────────────
    case 'get_categories':
        getCategories();
        break;

    // ─────────────────────────────────────────────
    // 6. ค้นหาสินค้า + ร้านค้าแบบ Unified Search
    //    GET /api/public_api.php?action=search&q=ชื่อ
    // ─────────────────────────────────────────────
    case 'search':
        searchAll();
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
}


// ════════════════════════════════════════════════
//  ฟังก์ชันหลัก
// ════════════════════════════════════════════════

/**
 * 1. ดึงรายชื่อร้านค้าทั้งหมด พร้อมข้อมูลพื้นที่เช่า
 */
function getTenants(): void {
    $pdo = getDB();

    $sql = "
        SELECT
            t.TenantID,
            t.TenantName,
            t.TenantCategory,
            t.TenantContactInfo,
            rs.Floor,
            rs.Location,
            rs.Size
        FROM Tenant t
        LEFT JOIN LeaseContract lc ON t.TenantID = lc.TenantID
            AND lc.EndDateLease >= CURDATE()        -- สัญญายังไม่หมด
        LEFT JOIN RentalSpace rs ON lc.SpaceID = rs.SpaceID
        ORDER BY rs.Floor, t.TenantName
    ";

    $stmt = $pdo->query($sql);
    $tenants = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'count'   => count($tenants),
        'data'    => $tenants,
    ]);
}


/**
 * 2. ดึงรายการสินค้า (รองรับ filter + pagination)
 */
function getProducts(): void {
    $pdo = getDB();

    // รับ query params
    $categoryId = $_GET['category_id'] ?? null;
    $tenantId   = $_GET['tenant_id']   ?? null;
    $search     = $_GET['search']      ?? null;
    $page       = max(1, (int)($_GET['page']  ?? 1));
    $limit      = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset     = ($page - 1) * $limit;

    // ─── สร้าง query แบบ dynamic ───
    $conditions = [];
    $params     = [];

    if ($categoryId) {
        $conditions[] = "ca.CategoryID = :category_id";
        $params[':category_id'] = $categoryId;
    }

    if ($tenantId) {
        $conditions[] = "s.TenantID = :tenant_id";
        $params[':tenant_id'] = $tenantId;
    }

    if ($search) {
        $conditions[] = "(p.ProductName LIKE :search OR p.ProductDescription LIKE :search2)";
        $params[':search']  = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
    }

    $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // ─── นับจำนวนรวม (สำหรับ pagination) ───
    $countSql = "
        SELECT COUNT(DISTINCT p.ProductID) AS total
        FROM Product p
        LEFT JOIN Categorize ca ON p.ProductID = ca.ProductID
        LEFT JOIN Sale s ON p.ProductID = s.ProductID
        $whereClause
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // ─── ดึงข้อมูลสินค้า ───
    $sql = "
        SELECT
            p.ProductID,
            p.ProductName,
            p.ProductPrice,
            p.ProductDescription,
            GROUP_CONCAT(DISTINCT ca.CategoryID ORDER BY ca.CategoryID) AS CategoryIDs,
            GROUP_CONCAT(DISTINCT cat.CategoryName ORDER BY cat.CategoryName SEPARATOR ', ') AS Categories,
            COALESCE(SUM(DISTINCT w.WarehouseQuantity), 0) AS TotalStock
        FROM Product p
        LEFT JOIN Categorize ca   ON p.ProductID = ca.ProductID
        LEFT JOIN Category cat    ON ca.CategoryID = cat.CategoryID
        LEFT JOIN Store st        ON p.ProductID = st.ProductID
        LEFT JOIN Warehouse w     ON st.WarehouseID = w.WarehouseID
        LEFT JOIN Sale s          ON p.ProductID = s.ProductID
        $whereClause
        GROUP BY p.ProductID, p.ProductName, p.ProductPrice, p.ProductDescription
        ORDER BY p.ProductName
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    // Bind params ทีละตัวเพราะ LIMIT/OFFSET ต้องเป็น int
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();

    // แปลง TotalStock เป็น int และ ProductPrice เป็น float
    foreach ($products as &$p) {
        $p['TotalStock']    = (int)$p['TotalStock'];
        $p['ProductPrice']  = (float)$p['ProductPrice'];
        $p['InStock']       = $p['TotalStock'] > 0;
    }
    unset($p);

    jsonResponse([
        'success'    => true,
        'total'      => $total,
        'page'       => $page,
        'limit'      => $limit,
        'totalPages' => (int)ceil($total / $limit),
        'data'       => $products,
    ]);
}


/**
 * 3. ดึงรายละเอียดสินค้าชิ้นเดียว
 */
function getProductDetail(): void {
    $productId = $_GET['product_id'] ?? null;

    if (!$productId) {
        jsonResponse(['success' => false, 'message' => 'product_id is required.'], 400);
    }

    $pdo = getDB();

    // ข้อมูลสินค้าหลัก
    $sql = "
        SELECT
            p.ProductID,
            p.ProductName,
            p.ProductPrice,
            p.ProductDescription,
            GROUP_CONCAT(DISTINCT cat.CategoryName SEPARATOR ', ') AS Categories,
            COALESCE(SUM(DISTINCT w.WarehouseQuantity), 0) AS TotalStock
        FROM Product p
        LEFT JOIN Categorize ca ON p.ProductID = ca.ProductID
        LEFT JOIN Category cat  ON ca.CategoryID = cat.CategoryID
        LEFT JOIN Store st      ON p.ProductID = st.ProductID
        LEFT JOIN Warehouse w   ON st.WarehouseID = w.WarehouseID
        WHERE p.ProductID = :product_id
        GROUP BY p.ProductID, p.ProductName, p.ProductPrice, p.ProductDescription
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':product_id' => $productId]);
    $product = $stmt->fetch();

    if (!$product) {
        jsonResponse(['success' => false, 'message' => 'Product not found.'], 404);
    }

    $product['TotalStock']   = (int)$product['TotalStock'];
    $product['ProductPrice'] = (float)$product['ProductPrice'];
    $product['InStock']      = $product['TotalStock'] > 0;

    // ดึงชื่อผู้จัดจำหน่าย
    $supSql = "
        SELECT s.SupplierID, s.SupplierName, s.SupplierContactInfo
        FROM Supplier s
        JOIN Supply sp ON s.SupplierID = sp.SupplierID
        WHERE sp.ProductID = :product_id
    ";
    $supStmt = $pdo->prepare($supSql);
    $supStmt->execute([':product_id' => $productId]);
    $product['Suppliers'] = $supStmt->fetchAll();

    // ดึงโปรโมชันที่สินค้านี้เข้าร่วม (ที่ยังไม่หมดอายุ)
    $promoSql = "
        SELECT
            pr.PromotionID,
            pr.PromotionName,
            pr.Discount,
            pr.StartDatePromotion,
            pr.EndDatePromotion,
            t.TenantName
        FROM Promotion pr
        JOIN Cheapen ch  ON pr.PromotionID = ch.PromotionID
        JOIN Tenant t    ON pr.TenantID = t.TenantID
        WHERE ch.ProductID = :product_id
          AND pr.EndDatePromotion >= CURDATE()
          AND pr.StartDatePromotion <= CURDATE()
        ORDER BY pr.Discount DESC
    ";
    $promoStmt = $pdo->prepare($promoSql);
    $promoStmt->execute([':product_id' => $productId]);
    $promotions = $promoStmt->fetchAll();

    foreach ($promotions as &$promo) {
        $promo['Discount'] = (float)$promo['Discount'];
        // คำนวณราคาหลังลด
        $promo['PriceAfterDiscount'] = round(
            $product['ProductPrice'] * (1 - $promo['Discount'] / 100),
            2
        );
    }
    unset($promo);

    $product['ActivePromotions'] = $promotions;

    jsonResponse(['success' => true, 'data' => $product]);
}


/**
 * 4. ดึงโปรโมชันที่กำลัง active อยู่
 */
function getPromotions(): void {
    $pdo = getDB();

    $tenantId = $_GET['tenant_id'] ?? null;

    $conditions = [
        "pr.StartDatePromotion <= CURDATE()",
        "pr.EndDatePromotion >= CURDATE()",
    ];
    $params = [];

    if ($tenantId) {
        $conditions[] = "pr.TenantID = :tenant_id";
        $params[':tenant_id'] = $tenantId;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $conditions);

    $sql = "
        SELECT
            pr.PromotionID,
            pr.PromotionName,
            pr.Discount,
            pr.StartDatePromotion,
            pr.EndDatePromotion,
            t.TenantID,
            t.TenantName,
            t.TenantCategory,
            GROUP_CONCAT(DISTINCT p.ProductID ORDER BY p.ProductID) AS ProductIDs,
            GROUP_CONCAT(DISTINCT p.ProductName ORDER BY p.ProductName SEPARATOR ' | ') AS ProductNames
        FROM Promotion pr
        JOIN Tenant t   ON pr.TenantID = t.TenantID
        LEFT JOIN Cheapen ch ON pr.PromotionID = ch.PromotionID
        LEFT JOIN Product p  ON ch.ProductID = p.ProductID
        $whereClause
        GROUP BY
            pr.PromotionID, pr.PromotionName, pr.Discount,
            pr.StartDatePromotion, pr.EndDatePromotion,
            t.TenantID, t.TenantName, t.TenantCategory
        ORDER BY pr.Discount DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $promotions = $stmt->fetchAll();

    foreach ($promotions as &$promo) {
        $promo['Discount'] = (float)$promo['Discount'];
    }
    unset($promo);

    jsonResponse([
        'success' => true,
        'count'   => count($promotions),
        'data'    => $promotions,
    ]);
}


/**
 * 5. ดึงหมวดหมู่สินค้าทั้งหมด
 */
function getCategories(): void {
    $pdo = getDB();

    // นับด้วยว่าแต่ละหมวดมีสินค้ากี่รายการ
    $sql = "
        SELECT
            cat.CategoryID,
            cat.CategoryName,
            COUNT(ca.ProductID) AS ProductCount
        FROM Category cat
        LEFT JOIN Categorize ca ON cat.CategoryID = ca.CategoryID
        GROUP BY cat.CategoryID, cat.CategoryName
        ORDER BY cat.CategoryName
    ";

    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll();

    foreach ($categories as &$cat) {
        $cat['ProductCount'] = (int)$cat['ProductCount'];
    }
    unset($cat);

    jsonResponse([
        'success' => true,
        'count'   => count($categories),
        'data'    => $categories,
    ]);
}


/**
 * 6. ค้นหาแบบ Unified — คืนทั้งสินค้าและร้านค้าที่ตรงกับ keyword
 */
function searchAll(): void {
    $q = trim($_GET['q'] ?? '');

    if (strlen($q) < 1) {
        jsonResponse(['success' => false, 'message' => 'Query parameter "q" is required.'], 400);
    }

    $pdo   = getDB();
    $like  = '%' . $q . '%';

    // ─── ค้นหาสินค้า ───
    $productSql = "
        SELECT
            p.ProductID,
            p.ProductName,
            p.ProductPrice,
            p.ProductDescription,
            GROUP_CONCAT(DISTINCT cat.CategoryName SEPARATOR ', ') AS Categories,
            COALESCE(SUM(DISTINCT w.WarehouseQuantity), 0) AS TotalStock
        FROM Product p
        LEFT JOIN Categorize ca ON p.ProductID = ca.ProductID
        LEFT JOIN Category cat  ON ca.CategoryID = cat.CategoryID
        LEFT JOIN Store st      ON p.ProductID = st.ProductID
        LEFT JOIN Warehouse w   ON st.WarehouseID = w.WarehouseID
        WHERE p.ProductName LIKE :q OR p.ProductDescription LIKE :q2
        GROUP BY p.ProductID, p.ProductName, p.ProductPrice, p.ProductDescription
        LIMIT 20
    ";
    $pStmt = $pdo->prepare($productSql);
    $pStmt->execute([':q' => $like, ':q2' => $like]);
    $products = $pStmt->fetchAll();

    foreach ($products as &$p) {
        $p['TotalStock']   = (int)$p['TotalStock'];
        $p['ProductPrice'] = (float)$p['ProductPrice'];
        $p['InStock']      = $p['TotalStock'] > 0;
    }
    unset($p);

    // ─── ค้นหาร้านค้า ───
    $tenantSql = "
        SELECT
            t.TenantID,
            t.TenantName,
            t.TenantCategory,
            t.TenantContactInfo,
            rs.Floor,
            rs.Location
        FROM Tenant t
        LEFT JOIN LeaseContract lc ON t.TenantID = lc.TenantID
            AND lc.EndDateLease >= CURDATE()
        LEFT JOIN RentalSpace rs ON lc.SpaceID = rs.SpaceID
        WHERE t.TenantName LIKE :q OR t.TenantCategory LIKE :q2
        GROUP BY t.TenantID
        LIMIT 10
    ";
    $tStmt = $pdo->prepare($tenantSql);
    $tStmt->execute([':q' => $like, ':q2' => $like]);
    $tenants = $tStmt->fetchAll();

    jsonResponse([
        'success'  => true,
        'query'    => $q,
        'products' => $products,
        'tenants'  => $tenants,
    ]);
}