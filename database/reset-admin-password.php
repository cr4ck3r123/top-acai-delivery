<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Execute este script pela linha de comando.\n");
    exit(1);
}

require __DIR__ . '/../api/bootstrap.php';

$username = $argv[1] ?? '';
if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
    fwrite(STDERR, "Uso: php database/reset-admin-password.php usuario\n");
    fwrite(STDERR, "Informe o usuário da conta administrativa que deseja redefinir.\n");
    exit(1);
}

function readHiddenPassword(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    $isInteractive = function_exists('stream_isatty') && stream_isatty(STDIN);
    if ($isInteractive) {
        exec('stty -echo');
    }
    try {
        $password = rtrim((string) fgets(STDIN), "\r\n");
    } finally {
        if ($isInteractive) {
            exec('stty echo');
            fwrite(STDOUT, PHP_EOL);
        }
    }
    return $password;
}

$password = readHiddenPassword('Nova senha (mínimo de 12 caracteres): ');
$confirmation = readHiddenPassword('Confirme a nova senha: ');
if (strlen($password) < 12 || strlen($password) > 200) {
    fwrite(STDERR, "A senha deve ter entre 12 e 200 caracteres.\n");
    exit(1);
}
if (!hash_equals($password, $confirmation)) {
    fwrite(STDERR, "As senhas não conferem.\n");
    exit(1);
}

try {
    $pdo = database();
    $statement = $pdo->prepare(
        'UPDATE admin_users SET password_hash = ?, session_version = session_version + 1
         WHERE username = ?'
    );
    $statement->execute([
        password_hash($password, PASSWORD_DEFAULT),
        strtolower($username)
    ]);
    if ($statement->rowCount() !== 1) {
        fwrite(STDERR, "A conta não foi encontrada ou a senha já estava atualizada.\n");
        exit(1);
    }
    fwrite(STDOUT, "Senha redefinida. Entre novamente em /admin/.\n");
} catch (PDOException $exception) {
    error_log('Falha ao redefinir senha administrativa: ' . $exception->getMessage());
    fwrite(STDERR, "Não foi possível redefinir a senha. Verifique a conexão com o banco.\n");
    exit(1);
}
