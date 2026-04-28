<?php

/**
 * DEMO..
 * auth.php
 * ระบบ Authentication & Authorization
 * Roles: admin, tenant, guest (ไม่ได้ login)
 */

session_start();

// CORS HEADERS — อนุญาตให้ Frontend เรียก API ได้
// ปรับ Allow-Origin ให้ตรงกับ port ของ Frontend
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Pre-flight request จาก browser → ตอบ 200 แล้วจบ
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


// CONFIG: เชื่อมต่อฐานข้อมูล (ปรับค่าให้ตรงกับของคุณ)
define('DB_HOST',    'localhost');
define('DB_NAME',    'your_database');  // ← เปลี่ยนเป็นชื่อ DB จริง
define('DB_USER',    'root');
define('DB_PASS',    '');               // ← ใส่รหัสผ่านถ้ามี
define('DB_CHARSET', 'utf8mb4');

// Rate Limit: จำนวนครั้งสูงสุดและช่วงเวลา (วินาที)
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION',   900); // 15 นาที


// DATABASE: Singleton PDO Connection
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


// HELPER: ส่ง JSON Response พร้อม HTTP Status Code
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


// AUTH HELPERS: ดึงข้อมูล / ตรวจสถานะ Session
function getCurrentRole(): string {
    return $_SESSION['role'] ?? 'guest';
}

function getCurrentUser(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['name'],
        'email' => $_SESSION['email'],
        'role'  => $_SESSION['role'],
    ];
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}


// AUTHORIZATION: ตรวจสอบสิทธิ์การเข้าถึง
//
//   admin  → เข้าถึงได้ทุกส่วน
//   tenant → เข้าถึงได้เฉพาะส่วนผู้เช่า
//   guest  → เข้าถึงได้เฉพาะ public
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


// CSRF PROTECTION
/**
 * สร้าง CSRF Token และเก็บไว้ใน Session
 * เรียกหลัง login สำเร็จเพื่อให้ Frontend นำ token ไปแนบทุก request
 */
function generateCsrfToken(): string {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}
/**
 * ตรวจ CSRF Token จาก Header X-CSRF-Token
 * เรียกใช้ใน endpoint ที่เปลี่ยนแปลงข้อมูล (POST ที่ sensitive)
 */
function verifyCsrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        jsonResponse(403, 'CSRF token ไม่ถูกต้องหรือหมดอายุ');
    }
}


// RATE LIMITING: ป้องกัน Brute Force Login
// นับจำนวนครั้งที่ login ผิดใน Session
/**
 * ตรวจว่าถูกล็อกอยู่หรือไม่
 * ถ้าครบ MAX_LOGIN_ATTEMPTS และยังอยู่ใน LOCKOUT_DURATION → บล็อก
 */
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

        // พ้นช่วง lockout แล้ว → รีเซ็ตตัวนับ
        $_SESSION['login_attempts']      = 0;
        $_SESSION['login_last_attempt']  = 0;
    }
}

/** เพิ่มตัวนับเมื่อ login ผิด */
function incrementLoginFailure(): void {
    $_SESSION['login_attempts']     = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_last_attempt'] = time();
}

/** รีเซ็ตตัวนับเมื่อ login สำเร็จ */
function resetLoginAttempts(): void {
    $_SESSION['login_attempts']     = 0;
    $_SESSION['login_last_attempt'] = 0;
}


// LOGIN
// POST /auth.php?action=login
// Body (JSON): { "email": "...", "password": "..." }
function handleLogin(): void {

    // 1. ตรวจ Rate Limit ก่อนทำอะไร
    checkLoginRateLimit();

    // 2. รับและ validate input
    $input = json_decode(file_get_contents('php://input'), true);

    $email    = trim($input['email']    ?? '');
    $password = trim($input['password'] ?? '');

    if (!$email || !$password) {
        jsonResponse(400, 'กรุณากรอก email และ password');
    }

    // ตรวจรูปแบบ email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(400, 'รูปแบบ email ไม่ถูกต้อง');
    }

    // ตรวจความยาว password เบื้องต้น
    if (strlen($password) < 6) {
        jsonResponse(400, 'password ต้องมีอย่างน้อย 6 ตัวอักษร');
    }

    // 3. ดึงข้อมูลจาก DB
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // 4. ตรวจ password (ใช้ข้อความ error เดียวกันเพื่อไม่ให้รู้ว่า email มีหรือไม่)
    if (!$user || !password_verify($password, $user['password'])) {
        incrementLoginFailure(); // เพิ่ม counter เมื่อผิด
        jsonResponse(401, 'email หรือ password ไม่ถูกต้อง');
    }

    // 5. Login สำเร็จ → สร้าง Session ใหม่เพื่อป้องกัน Session Fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['email']   = $user['email'];
    $_SESSION['role']    = $user['role'];

    // รีเซ็ต rate limit และสร้าง CSRF Token
    resetLoginAttempts();
    $csrfToken = generateCsrfToken();

    jsonResponse(200, 'เข้าสู่ระบบสำเร็จ', [
        'user' => [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ],
        'csrf_token' => $csrfToken, // ส่ง token กลับให้ Frontend เก็บไว้ใช้
    ]);
}


// LOGOUT
// POST /auth.php?action=logout
function handleLogout(): void {
    session_unset();
    session_destroy();
    jsonResponse(200, 'ออกจากระบบสำเร็จ');
}


// ดึงข้อมูลผู้ใช้ปัจจุบัน
// GET /auth.php?action=me
function handleMe(): void {
    requireLogin();
    jsonResponse(200, 'ดึงข้อมูลสำเร็จ', ['user' => getCurrentUser()]);
}


// ROUTER
// Guard: รัน Router เฉพาะเมื่อเรียก auth.php โดยตรง
// ป้องกัน Router ทำงานซ้ำเมื่อไฟล์อื่น require_once ไฟล์นี้
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

