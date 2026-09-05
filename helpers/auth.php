<?php
// Authentication & RBAC Helper

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

// Checks if a user is currently logged in.
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Retrieves the currently authenticated user record from the database.
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }

    static $cachedUser = null;
    if ($cachedUser !== null && $cachedUser['id'] == $_SESSION['user_id']) {
        return $cachedUser;
    }

    try {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT u.*, r.name AS role_name, r.display_name AS role_display
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            $cachedUser = $user;
            return $user;
        }
    } catch (Exception $e) {
        error_log("Error fetching current user: " . $e->getMessage());
    }

    return null;
}

// Logs in a user, regenerates session ID for security, and stores identity in $_SESSION.
function login_user(array $user): void {
    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['role_name'] = $user['role_name'] ?? 'customer';
    $_SESSION['logged_in_time'] = time();
}

// Logs out the current user, wipes $_SESSION array, and clears the session cookie.
function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

// Checks if the current user has any of the specified roles.
function has_role($allowedRoles): bool {
    $user = current_user();
    if (!$user) {
        return false;
    }

    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    return in_array($user['role_name'], $allowedRoles);
}

// Enforces login. If visitor is a guest, redirects to login page and remembers return URL.
function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = "Please log in to access this botanical portal.";
        header("Location: " . BASE_URL . "/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

// Enforces Role-Based Access Control (RBAC).
function require_role($allowedRoles): void {
    require_login();

    if (!has_role($allowedRoles)) {
        $_SESSION['flash_error'] = "Access denied: You do not have permission to view that section.";
        header("Location: " . BASE_URL . "/index.php");
        exit;
    }
}

// Generates a cryptographic CSRF token and stores it in the user's session.
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validates a submitted CSRF token using timing-safe comparison.
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Helper that returns a hidden HTML input containing the CSRF token.
function csrf_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

