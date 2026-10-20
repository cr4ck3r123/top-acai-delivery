<?php
declare(strict_types=1);

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    session_name('pedidos_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function adminDatabase(): PDO
{
    try {
        return database();
    } catch (PDOException $exception) {
        error_log('Falha ao conectar o painel administrativo ao banco: ' . $exception->getMessage());
        respond(['error' => 'Não foi possível conectar ao banco de dados. Tente novamente em instantes.'], 503);
    }
}

function verifyAdminCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !isset($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $token)) {
        respond(['error' => 'Sua sessão expirou. Atualize a página e tente novamente.'], 403);
    }
}

function requireAdmin(PDO $pdo): array
{
    $adminId = filter_var($_SESSION['admin_id'] ?? null, FILTER_VALIDATE_INT);
    $sessionVersion = filter_var($_SESSION['admin_session_version'] ?? null, FILTER_VALIDATE_INT);
    if ($adminId === false || $sessionVersion === false) {
        respond(['error' => 'Entre novamente para continuar.'], 401);
    }

    try {
        $statement = $pdo->prepare(
            'SELECT id, username, session_version FROM admin_users
             WHERE id = ? AND is_active = 1'
        );
        $statement->execute([$adminId]);
        $admin = $statement->fetch();
    } catch (PDOException $exception) {
        error_log('Falha ao verificar a sessão administrativa: ' . $exception->getMessage());
        respond(['error' => 'Não foi possível verificar sua sessão. Tente novamente em instantes.'], 503);
    }
    if (!$admin || (int) $admin['session_version'] !== $sessionVersion) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        respond(['error' => 'Sua sessão expirou. Entre novamente.'], 401);
    }

    return ['id' => (int) $admin['id'], 'username' => $admin['username']];
}
