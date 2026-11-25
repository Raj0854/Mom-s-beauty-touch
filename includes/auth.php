<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Admin Authentication & Session Management
 * PHP 8.3+ | XAMPP Compatible
 */

// Prevent direct HTTP access
if (php_sapi_name() !== 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

require_once __DIR__ . '/functions.php';

/**
 * Checks whether an admin user is currently authenticated with an active session.
 * Enforces a 60-minute inactivity session expiration.
 *
 * @return bool
 */
function is_admin_logged_in(): bool
{
    init_session();

    if (!isset($_SESSION['admin_id'], $_SESSION['admin_username'])) {
        return false;
    }

    // Inactivity timeout: 3600 seconds (1 hour)
    $maxInactivity = 3600;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $maxInactivity)) {
        admin_logout();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Returns the currently authenticated administrator profile data.
 *
 * @return array<string, mixed>|null
 */
function current_admin(): ?array
{
    if (!is_admin_logged_in()) {
        return null;
    }

    return [
        'id'       => (int)($_SESSION['admin_id'] ?? 0),
        'username' => (string)($_SESSION['admin_username'] ?? ''),
        'email'    => (string)($_SESSION['admin_email'] ?? ''),
    ];
}

/**
 * Guard: Enforces admin authentication on protected pages.
 * Redirects unauthenticated visitors to the login page.
 */
function require_admin(): void
{
    if (!is_admin_logged_in()) {
        set_flash('error', 'Please log in to access the administration panel.');
        safe_redirect('login.php');
    }
}

/**
 * Verifies admin credentials against the database.
 *
 * @param string $identity Username or email
 * @param string $password Plaintext password
 * @return array<string, mixed>|null Admin row if valid, null otherwise
 */
function verify_admin_credentials(string $identity, string $password): ?array
{
    try {
        $db = get_db();
        $stmt = $db->prepare('SELECT id, username, email, password_hash FROM admins WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$identity, $identity]);
        $admin = $stmt->fetch();

        if ($admin && is_array($admin) && password_verify($password, (string)$admin['password_hash'])) {
            return $admin;
        }
    } catch (Throwable $e) {
        error_log('[Admin Auth Error] ' . $e->getMessage());
    }

    return null;
}

/**
 * Authenticates an admin session.
 * Supports:
 *   admin_login(string $identity, string $password): ?array (verifies credentials and establishes session)
 *   admin_login(int $adminId, string $username, string $email): void (establishes session for pre-verified user)
 */
function admin_login(mixed $arg1, mixed $arg2, ?string $arg3 = null): mixed
{
    init_session();

    // Overload 1: admin_login(string $identity, string $password)
    if (is_string($arg1) && is_string($arg2) && $arg3 === null) {
        $admin = verify_admin_credentials($arg1, $arg2);
        if ($admin !== null) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            $_SESSION['admin_id']       = (int)$admin['id'];
            $_SESSION['admin_username'] = (string)$admin['username'];
            $_SESSION['admin_email']    = (string)$admin['email'];
            $_SESSION['last_activity']  = time();
            return $admin;
        }
        return null;
    }

    // Overload 2: admin_login(int $adminId, string $username, string $email)
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['admin_id']       = (int)$arg1;
    $_SESSION['admin_username'] = (string)$arg2;
    $_SESSION['admin_email']    = (string)$arg3;
    $_SESSION['last_activity']  = time();
    return true;
}

/**
 * Destroys the admin session completely and purges authentication cookies.
 */
function admin_logout(): void
{
    init_session();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Checks whether an IP address is rate-limited (max 5 failed attempts in 15 minutes).
 *
 * @param string|null $ipAddress
 * @return bool True if rate-limited (blocked), False if allowed
 */
function is_ip_rate_limited(?string $ipAddress = null): bool
{
    $ip = $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

    try {
        $db = get_db();
        $cutoff = date('Y-m-d H:i:s', time() - 900); // 15 minutes ago
        $stmt = $db->prepare('SELECT COUNT(*) AS total FROM login_attempts WHERE ip_address = ? AND attempt_time > ?');
        $stmt->execute([$ip, $cutoff]);
        $attempts = (int)($stmt->fetch()['total'] ?? 0);
        return $attempts >= 5;
    } catch (Throwable $e) {
        error_log('[Rate Limit Warning] ' . $e->getMessage());
        return false;
    }
}

/**
 * Records a failed login attempt for the given IP address.
 *
 * @param string|null $ipAddress
 * @param string $userAgent
 */
function record_login_attempt(?string $ipAddress = null, string $userAgent = ''): void
{
    $ip = $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $ua = $userAgent ?: ($_SERVER['HTTP_USER_AGENT'] ?? '');

    try {
        $db = get_db();
        $stmt = $db->prepare('INSERT INTO login_attempts (ip_address, attempt_time, user_agent) VALUES (?, NOW(), ?)');
        $stmt->execute([$ip, mb_substr($ua, 0, 255)]);
    } catch (Throwable $e) {
        error_log('[Login Attempt Record Warning] ' . $e->getMessage());
    }
}

/**
 * Clears failed login attempts for an IP address upon successful login.
 *
 * @param string|null $ipAddress
 */
function clear_login_attempts(?string $ipAddress = null): void
{
    $ip = $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

    try {
        $db = get_db();
        $stmt = $db->prepare('DELETE FROM login_attempts WHERE ip_address = ?');
        $stmt->execute([$ip]);
    } catch (Throwable $e) {
        error_log('[Clear Attempts Warning] ' . $e->getMessage());
    }
}

/**
 * Returns true if the admins table currently has zero records (initial setup mode).
 *
 * @return bool
 */
function is_first_time_setup(): bool
{
    try {
        $db = get_db();
        $stmt = $db->query('SELECT COUNT(*) AS total FROM admins');
        $count = (int)($stmt->fetch()['total'] ?? 0);
        return $count === 0;
    } catch (Throwable $e) {
        error_log('[Setup Check Error] ' . $e->getMessage());
        return false;
    }
}
