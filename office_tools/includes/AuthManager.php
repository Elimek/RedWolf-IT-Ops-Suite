<?php
/**
 * AuthManager - Session-based authentication with CSRF protection
 * RedWolf IT Ops Suite
 */

declare(strict_types=1);

namespace RedWolf\OfficeTools;

class AuthManager
{
    private const CSRF_TOKEN_KEY = 'csrf_token';
    private const AUTH_KEY = 'authenticated';
    private const USER_KEY = 'auth_user';

    /**
     * Start session and initialize CSRF token if needed
     */
    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION[self::CSRF_TOKEN_KEY])) {
            $_SESSION[self::CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Authenticate user against environment variables
     */
    public static function login(string $username, string $password): bool
    {
        self::init();

        $adminUser = getenv('ADMIN_USER') ?: 'admin';
        $adminPass = getenv('ADMIN_PASS') ?: '';

        if ($username === $adminUser && $password === $adminPass) {
            session_regenerate_id(true);
            $_SESSION[self::AUTH_KEY] = true;
            $_SESSION[self::USER_KEY] = $username;
            self::logAudit('login', "User '$username' logged in successfully");
            return true;
        }

        self::logAudit('login_failed', "Failed login attempt for user '$username'");
        return false;
    }

    /**
     * Destroy session and log out
     */
    public static function logout(): void
    {
        self::init();
        $user = $_SESSION[self::USER_KEY] ?? 'unknown';
        session_regenerate_id(true);
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
        self::logAudit('logout', "User '$user' logged out");
    }

    /**
     * Check if current session is authenticated
     */
    public static function checkAuth(): bool
    {
        self::init();
        return !empty($_SESSION[self::AUTH_KEY]);
    }

    /**
     * Require authentication or redirect to login page
     */
    public static function requireAuth(): void
    {
        self::init();
        if (!self::checkAuth()) {
            if (php_sapi_name() === 'cli') {
                echo "Error: Authentication required.\n";
                exit(1);
            }
            $redirect = $_SERVER['SCRIPT_NAME'] ?? '/office_tools/index.php';
            header("Location: /office_tools/login.php?redirect=" . urlencode($redirect));
            exit;
        }
    }

    /**
     * Generate CSRF token for forms
     */
    public static function csrfToken(): string
    {
        self::init();
        return $_SESSION[self::CSRF_TOKEN_KEY];
    }

    /**
     * Generate CSRF hidden input field HTML
     */
    public static function csrfField(): string
    {
        $token = htmlspecialchars(self::csrfToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /**
     * Validate submitted CSRF token
     */
    public static function validateCsrf(string $token): bool
    {
        self::init();
        if (empty($_SESSION[self::CSRF_TOKEN_KEY])) {
            return false;
        }
        return hash_equals($_SESSION[self::CSRF_TOKEN_KEY], $token);
    }

    /**
     * Validate CSRF from POST data, exit on failure
     */
    public static function requireCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!self::validateCsrf($token)) {
            http_response_code(403);
            die('CSRF token validation failed.');
        }
    }

    /**
     * Get the currently authenticated username
     */
    public static function currentUser(): ?string
    {
        self::init();
        return $_SESSION[self::USER_KEY] ?? null;
    }

    /**
     * Write audit log entry to database or file
     */
    private static function logAudit(string $action, string $details): void
    {
        try {
            $dbPath = dirname(__DIR__, 2) . '/sql/redwolf.db';
            if (file_exists($dbPath)) {
                $db = new \SQLite3($dbPath);
                $stmt = $db->prepare(
                    'INSERT INTO audit_log (timestamp, user, action, details, ip_address) 
                     VALUES (datetime("now"), ?, ?, ?, ?)'
                );
                $stmt->bindValue(1, $_SESSION[self::USER_KEY] ?? 'system', SQLITE3_TEXT);
                $stmt->bindValue(2, $action, SQLITE3_TEXT);
                $stmt->bindValue(3, $details, SQLITE3_TEXT);
                $stmt->bindValue(4, $_SERVER['REMOTE_ADDR'] ?? 'cli', SQLITE3_TEXT);
                $stmt->execute();
                $db->close();
            }
        } catch (\Throwable $e) {
            // Fallback: append to log file
            $logFile = dirname(__DIR__) . '/logs/audit.log';
            $dir = dirname($logFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $line = sprintf(
                "[%s] [%s] [%s] %s (IP: %s)\n",
                date('Y-m-d H:i:s'),
                $_SESSION[self::USER_KEY] ?? 'system',
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? 'cli'
            );
            @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Render a simple login form (used by protected pages)
     */
    public static function renderLoginPage(string $redirect = ''): void
    {
        if (self::checkAuth()) {
            header('Location: ' . ($redirect ?: '/office_tools/'));
            exit;
        }
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrfToken = $_POST['csrf_token'] ?? '';
            if (self::validateCsrf($csrfToken)) {
                $user = $_POST['username'] ?? '';
                $pass = $_POST['password'] ?? '';
                if (self::login($user, $pass)) {
                    header('Location: ' . ($redirect ?: '/office_tools/'));
                    exit;
                }
                $error = 'Invalid credentials.';
            } else {
                $error = 'Security token expired. Please try again.';
            }
        }
        self::init();
        $csrf = self::csrfField();
        $redirectField = $redirect ? '<input type="hidden" name="redirect" value="' . htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') . '">' : '';
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RedWolf IT Ops</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">RedWolf IT Ops</h3>
                        ' . ($error ? '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>' : '') . '
                        <form method="POST" action="">
                            ' . $csrf . $redirectField . '
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-danger w-100">Sign In</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
        exit;
    }
}
