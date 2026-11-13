<?php
/**
 * ==========================================
 * Tool 13: Session & Cookie Security Demo
 * Created by: Ahmed_Q
 * ==========================================
 */

declare(strict_types=1);

// Set strict response headers
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

// Detect if HTTPS is active (Local development server runs over HTTP)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

// Configure session hardening directives securely before starting the session
session_name('SECUREDEMOSESSID');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');

// Set secure cookie parameters for session ID
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);

session_start();

// Initialize CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Define mock credentials for demonstration purposes (demo / demo123)
$demoPasswordHash = getenv('DEMO_PASSWORD_HASH');
if (!is_string($demoPasswordHash) || $demoPasswordHash === '') {
    $demoPasswordHash = password_hash('demo123', PASSWORD_DEFAULT);
}

/**
 * Helper function to redirect back to the same page safely
 */
function redirectToSelf(): never {
    header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));
    exit;
}

/**
 * Helper function to escape output and prevent XSS
 */
function escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Handle incoming POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF Token
    if (!is_string($postedToken) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        http_response_code(403);
        exit('CSRF token validation failed. Please reload the page and try again.');
    }

    $action = $_POST['action'] ?? '';

    // 1. Handle Login Action
    if ($action === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (is_string($username) && $username === 'demo' && is_string($password) && password_verify($password, $demoPasswordHash)) {
            // Prevent Session Fixation attacks by regenerating session ID
            if (!session_regenerate_id(true)) {
                http_response_code(500);
                exit('Failed to regenerate session ID securely.');
            }
            $_SESSION['username'] = 'demo';
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['flash'] = 'Logged in successfully!';
        } else {
            $_SESSION['flash'] = 'Invalid login credentials. Use demo / demo123';
        }
        redirectToSelf();
    }

    // 2. Handle Setting Test Cookies Action
    if ($action === 'set_cookies') {
        $expires = time() + 3600;

        // Protected Cookie (HttpOnly = true -> JavaScript cannot access it via document.cookie)
        $protectedCookieSet = setcookie('protected_demo', bin2hex(random_bytes(16)), [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        // Readable Cookie (HttpOnly = false -> Accessible via JavaScript for educational comparison)
        $readableCookieSet = setcookie('readable_demo', bin2hex(random_bytes(16)), [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => false,
            'samesite' => 'Strict',
        ]);

        $_SESSION['flash'] = ($protectedCookieSet && $readableCookieSet) 
            ? 'Cookies issued successfully! Reload the page to inspect.' 
            : 'Failed to issue one or more cookies.';
        redirectToSelf();
    }

    // 3. Handle Logout Action
    if ($action === 'logout') {
        $_SESSION = [];
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_destroy();
        redirectToSelf();
    }

    http_response_code(400);
    exit('Unknown action requested.');
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$isLoggedIn = !empty($_SESSION['username']);
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tool 13: Session & Cookie Security</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f4f7f6; color: #333; }
        .card { background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        input { padding: 8px; margin: 5px 0; width: 100%; max-width: 300px; box-sizing: border-box; }
        button { background: #007bff; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .flash { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>

<div class="card">
    <h2>Tool 13: Session & Cookie Security Demo</h2>
    <?php if ($flash !== ''): ?>
        <div class="flash"><?= escape($flash) ?></div>
    <?php endif; ?>

    <?php if ($isLoggedIn): ?>
        <p><strong>Status:</strong> Logged in as <code><?= escape($_SESSION['username']) ?></code></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
            <button type="submit" name="action" value="set_cookies">Issue Test Cookies</button>
        </form>
        <br>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
            <button type="submit" name="action" value="logout" style="background: #dc3545;">Logout</button>
        </form>
    <?php else: ?>
        <p><strong>Status:</strong> Not logged in. (Credentials: <code>demo</code> / <code>demo123</code>)</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
            <label>Username:</label><br>
            <input type="text" name="username" required><br>
            <label>Password:</label><br>
            <input type="password" name="password" required><br>
            <button type="submit" name="action" value="login">Login</button>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Cookie Inspection Matrix</h3>
    <table>
        <tr>
            <th>Cookie Name</th>
            <th>HttpOnly</th>
            <th>Accessible via JavaScript (`document.cookie`)</th>
            <th>Received by Server</th>
        </tr>
        <tr>
            <td><code>protected_demo</code></td>
            <td>Yes (true)</td>
            <td id="res-protected" style="color: green;">Checking...</td>
            <td>Yes</td>
        </tr>
        <tr>
            <td><code>readable_demo</code></td>
            <td>No (false)</td>
            <td id="res-readable" style="color: red;">Checking...</td>
            <td>Yes</td>
        </tr>
    </table>
    <p style="margin-top: 15px; font-size: 0.9em; color: #666;">
        <em>Note:</em> Open your browser's Developer Tools (Console) to verify that <code>document.cookie</code> only reveals <code>readable_demo</code> while <code>protected_demo</code> remains completely hidden from JavaScript.
    </p>
</div>

<script>
    // JavaScript inspection to demonstrate HttpOnly defense
    const allCookies = document.cookie;
    const hasProtected = allCookies.includes('protected_demo');
    const hasReadable = allCookies.includes('readable_demo');

    document.getElementById('res-protected').textContent = hasProtected ? 'Visible (FAIL)' : 'Hidden (Protected by HttpOnly)';
    document.getElementById('res-readable').textContent = hasReadable ? 'Visible (Readable Demo Cookie)' : 'Not Found (Issue cookies first)';
</script>

</body>
</html>