<?php
// Redirecionador de URLs curtas
require_once 'models/ShortUrl.php';
require_once 'models/SystemSettings.php';

$settings = new SystemSettings();

// Set timezone
date_default_timezone_set($settings->getTimezone());

// Obter código curto da URL
$short_code = $_GET['c'] ?? '';

if (empty($short_code)) {
    http_response_code(404);
    header('Location: ' . $settings->getSiteUrl());
    exit();
}

$shortUrl = new ShortUrl();

// Buscar token original
$original_token = $shortUrl->getOriginalToken($short_code);

if (!$original_token) {
    http_response_code(404);
    $siteName = $settings->get('site_name', 'Pix Transfer');
    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Link Expirado - {$siteName}</title>
        <style>
            body { 
                font-family: 'Titillium Web', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                text-align: center; 
                margin-top: 50px; 
                background: #f8f9fa;
                color: #333;
            }
            .error { color: #e74c3c; }
            .container { 
                max-width: 500px; 
                margin: 0 auto; 
                padding: 40px; 
                background: white;
                border-radius: 10px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            }
            .btn {
                display: inline-block;
                padding: 10px 20px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin-top: 20px;
            }
            .btn:hover {
                background: #5a67d8;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1 class='error'>❌ Link Expirado</h1>
            <p>Este link de download expirou ou não existe.</p>
            <p>Entre em contato com quem enviou o arquivo para obter um novo link.</p>
            <a href='{$settings->getSiteUrl()}' class='btn'>← Página Inicial</a>
        </div>
    </body>
    </html>";
    exit();
}

// Registrar acesso
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$shortUrl->logAccess($short_code, $ip, $_SERVER['HTTP_USER_AGENT'] ?? '');

// Redirecionar para a URL original
$redirect_url = $settings->getSiteUrl() . "/download/" . urlencode($original_token);
header("Location: $redirect_url");
exit();
?>