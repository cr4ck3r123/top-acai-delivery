<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/admin-session.php';
startAdminSession();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(['error' => 'Método não permitido.'], 405);
}
verifyAdminCsrf();
$pdo = adminDatabase();
requireAdmin($pdo);
$file = $_FILES['image'] ?? null;
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    respond(['error' => 'Selecione uma imagem válida para enviar.'], 422);
}
if (($file['size'] ?? 0) < 1 || $file['size'] > 5 * 1024 * 1024) {
    respond(['error' => 'A imagem deve ter até 5 MB.'], 413);
}
$tmp = $file['tmp_name'] ?? '';
if (!is_uploaded_file($tmp)) {
    respond(['error' => 'O arquivo enviado não é válido.'], 422);
}
$info = @getimagesize($tmp);
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if ($info === false || !isset($extensions[$mime])) {
    respond(['error' => 'Formato não aceito. Envie JPG, PNG ou WebP.'], 422);
}
$directory = dirname(__DIR__) . '/uploads/products';
if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
    respond(['error' => 'Não foi possível preparar o armazenamento de imagens.'], 500);
}
$name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
if (!move_uploaded_file($tmp, $directory . '/' . $name)) {
    respond(['error' => 'Não foi possível salvar a imagem no servidor.'], 500);
}
respond(['image_url' => '/uploads/products/' . $name]);
