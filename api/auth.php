<?php

/**
 * auth.php
 * ระบบ Authentication & Authorization
 * ปรับปรุงให้สอดคล้องกับฐานข้อมูล DepartmentStoreDB
 * (รองรับการเชื่อมโยง AccountID กับ Employee และ Tenant)
 */

require_once 'db.php';

session_start();

// CORS HEADERS — อนุญาตให้ Frontend เรียก API ได้
header('Access-Control-Allow-Origin: http://localhost:3000'); 
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// CONFIG
define('DB_HOST',    'localhost');
define('DB_NAME',    'DepartmentStoreDB');  
define('DB_USER',    'root');
define('DB_PASS',    '');               
define('DB_CHARSET', 'utf8mb4');

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION',   900); 

// DATABASE
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// HELPER
function jsonResponse(int $code, string $message, array $data = []): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'status'  => $code,
        'message' => $message,
        'data'    => $data,
    ]);
    exit;
}

function getCurrentRole(): string {
    return $_SESSION['role'] ?? 'guest';
}

function getCurrentUser(): ?array {
    if (!isset($_SESSION['account_id'])) return null;
    
    // [แก้ไข] เพิ่มการ Return ข้อมูล Employee และ Tenant กลับไปให้ Frontend
    return [
        'account_id'    => $_SESSION['account_id'],
        'username'      => $_SESSION['username'],
        'role'          => $_SESSION['role'],
        'employee_id'   => $_SESSION['employee_id'] ?? null,
        'employee_name' => $_SESSION['employee_name'] ?? null,
        'tenant_id'     => $_SESSION['tenant_id'] ?? null,
        'tenant_name'   => $_SESSION['tenant_name'] ?? null,
    ];
}

function isLoggedIn(): bool {
    return isset($_SESSION['account_id']);
}

// AUTHORIZATION
function requireLogin(): void {
    if (!isLoggedIn()) {
        jsonResponse(401, 'กรุณาเข้าสู่ระบบก่อน');
    }
}

function requireRole(string ...$allowedRoles): void {
    requireLogin();
    $role = getCurrentRole();
    if (!in_array($role, $allowedRoles)) {
        jsonResponse(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้');
    }
}

function requireAdmin(): void {
    requireRole('admin');
}

function requireTenant(): void {
    requireRole('admin', 'tenant');
}

// CSRF
function generateCsrfToken(): string {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

function verifyCsrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        jsonResponse(403, 'CSRF token ไม่ถูกต้องหรือหมดอายุ');
    }
}

// RATE LIMIT
function checkLoginRateLimit(): void {
    $attempts = $_SESSION['login_attempts'] ?? 0;
    $lastTime = $_SESSION['login_last_attempt'] ?? 0;

    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $elapsed   = time() - $lastTime;
        $remaining = LOCKOUT_DURATION - $elapsed;

        if ($remaining > 0) {
            $minutes = ceil($remaining / 60);
            jsonResponse(429, "พยายาม login เกินจำนวนที่กำหนด กรุณาลองใหม่ใน {$minutes} นาที");
        }

        $_SESSION['login_attempts']      = 0;
        $_SESSION['login_last_attempt']  = 0;
    }
}

function incrementLoginFailure(): void {
    $_SESSION['login_attempts']     = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_last_attempt'] = time();
}

function resetLoginAttempts(): void {
    $_SESSION['login_attempts']     = 0;
    $_SESSION['login_last_attempt'] = 0;
}

// LOGIN
function handleLogin(): void {

    checkLoginRateLimit();

    $input = json_decode(file_get_contents('php://input'), true);

    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (!$username || !$password) {
        jsonResponse(400, 'กรุณากรอก Username และ Password');
    }

    if (strlen($password) < 6) {
        jsonResponse(400, 'Password ต้องมีอย่างน้อย 6 ตัวอักษร');
    }

    $pdo  = getDB();
    
    // [แก้ไข] เปลี่ยน Query เป็น LEFT JOIN เพื่อดึงข้อมูล Employee และ Tenant ออกมาด้วย
    // หาก AccountID นั้นเชื่อมกับ Employee ข้อมูล e.EmployeeID จะมีค่า
    // หาก AccountID นั้นเชื่อมกับ Tenant ข้อมูล t.TenantID จะมีค่า
    $sql = "
        SELECT 
            u.AccountID, u.Username, u.Password, u.Role,
            e.EmployeeID, e.EmployeeName,
            t.TenantID, t.TenantName
        FROM UserAccount u
        LEFT JOIN Employee e ON u.AccountID = e.AccountID
        LEFT JOIN Tenant t ON u.AccountID = t.AccountID
        WHERE u.Username = ? 
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['Password'])) {
        incrementLoginFailure();
        jsonResponse(401, 'Username หรือ Password ไม่ถูกต้อง');
    }

    session_regenerate_id(true);

    // เก็บ Session หลัก
    $_SESSION['account_id'] = $user['AccountID'];
    $_SESSION['username']   = $user['Username'];
    $_SESSION['role']       = $user['Role'];
    
    // [แก้ไข] เก็บ Session โปรไฟล์ (ถ้ามีข้อมูลจะเก็บค่า ถ้าไม่มีจะเป็น null)
    $_SESSION['employee_id']   = $user['EmployeeID'];
    $_SESSION['employee_name'] = $user['EmployeeName'];
    $_SESSION['tenant_id']     = $user['TenantID'];
    $_SESSION['tenant_name']   = $user['TenantName'];

    resetLoginAttempts();
    $csrfToken = generateCsrfToken();

    // [แก้ไข] ส่งข้อมูลทั้งหมดให้ Frontend แสดงผลได้ทันที
    jsonResponse(200, 'เข้าสู่ระบบสำเร็จ', [
        'user' => [
            'account_id'    => $user['AccountID'],
            'username'      => $user['Username'],
            'role'          => $user['Role'],
            'employee_id'   => $user['EmployeeID'],
            'employee_name' => $user['EmployeeName'],
            'tenant_id'     => $user['TenantID'],
            'tenant_name'   => $user['TenantName'],
        ],
        'csrf_token' => $csrfToken,
    ]);
}

// LOGOUT
function handleLogout(): void {
    session_unset();
    session_destroy();
    jsonResponse(200, 'ออกจากระบบสำเร็จ');
}

// GET PROFILE
function handleMe(): void {
    requireLogin();
    jsonResponse(200, 'ดึงข้อมูลสำเร็จ', ['user' => getCurrentUser()]);
}

// ROUTER
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {

    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    match (true) {
        $action === 'login'  && $method === 'POST' => handleLogin(),
        $action === 'logout' && $method === 'POST' => handleLogout(),
        $action === 'me'     && $method === 'GET'  => handleMe(),
        default => jsonResponse(404, 'ไม่พบ action ที่ระบุ'),
    };

}

