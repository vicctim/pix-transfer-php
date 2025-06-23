<?php
// Redirecionador de URLs curtas
require_once 'models/ShortUrl.php';

// Obter código curto da URL
$short_code = $_GET['c'] ?? '';

if (empty($short_code)) {
    http_response_code(404);
    header('Location: /');
    exit();
}

$shortUrl = new ShortUrl();

// Buscar token original
$original_token = $shortUrl->getOriginalToken($short_code);

if (!$original_token) {
    http_response_code(404);
    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Link Expirado - Pix Transfer</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; }
            .error { color: #e74c3c; }
            .container { max-width: 500px; margin: 0 auto; padding: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1 class='error'>❌ Link Expirado</h1>
            <p>Este link de download expirou ou não existe.</p>
            <p>Entre em contato com quem enviou o arquivo para obter um novo link.</p>
            <a href='/'>← Página Inicial</a>
        </div>
    </body>
    </html>";
    exit();
}

// Registrar acesso
$shortUrl->logAccess($short_code, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '');

// Redirecionar para a URL original
$redirect_url = "/download/" . urlencode($original_token);
header("Location: $redirect_url");
exit();
?>