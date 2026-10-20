<?php
declare(strict_types=1);

$requestPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if (preg_match('~(?:^|/)\.env(?:\.|/|$)~i', str_replace('\\', '/', $requestPath))) {
    http_response_code(404);
    exit;
}

return false;
