<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Execute este script pela linha de comando.\n");
    exit(1);
}

require __DIR__ . '/../api/bootstrap.php';

$username = $argv[1] ?? '';
if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
    fwrite(STDERR, "Uso: php database/create-admin.php usuario\n");
    fwrite(STDERR, "O usuário deve conter de 3 a 50 letras, números, ponto, hífen ou sublinhado.\n");
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

$password = readHiddenPassword('Senha (mínimo de 12 caracteres): ');
$confirmation = readHiddenPassword('Confirme a senha: ');
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
        'INSERT INTO admin_users (username, password_hash) VALUES (?, ?)'
    );
    $statement->execute([strtolower($username), password_hash($password, PASSWORD_DEFAULT)]);
    fwrite(STDOUT, "Conta administrativa criada. Acesse /admin/ para entrar.\n");
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        fwrite(STDERR, "Já existe uma conta com esse nome de usuário.\n");
        exit(1);
    }
    error_log('Falha ao criar conta administrativa: ' . $exception->getMessage());
    fwrite(STDERR, "Não foi possível criar a conta. Verifique a conexão com o banco.\n");
    exit(1);
}
