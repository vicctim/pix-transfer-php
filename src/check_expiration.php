<?php
// Script para verificar uploads próximos da expiração
// Este script deve ser executado via cron job diariamente

require_once 'config/database.php';
require_once 'models/UploadSession.php';
require_once 'models/User.php';
require_once 'models/EmailTemplate.php';
require_once 'models/SystemSettings.php';

$settings = new SystemSettings();
date_default_timezone_set($settings->getTimezone());

$db = Database::getInstance();
$emailTemplate = new EmailTemplate();
$userModel = new User();

// Buscar uploads que expiram em 24 horas
$stmt = $db->query("
    SELECT us.*, u.email, u.username 
    FROM upload_sessions us 
    JOIN users u ON us.user_id = u.id 
    WHERE us.expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
    AND us.expiration_notified = 0
");

$expiringUploads = $stmt->fetchAll();

foreach ($expiringUploads as $upload) {
    // Preparar variáveis para o template
    $variables = [
        'upload_title' => $upload['title'],
        'expiration_date' => date('d/m/Y H:i', strtotime($upload['expires_at'])),
        'download_url' => $settings->getSiteUrl() . '/download/' . $upload['token'],
        'user_name' => $upload['username']
    ];
    
    // Renderizar template ou usar fallback
    $rendered = $emailTemplate->renderTemplate('expiration_warning', $variables);
    if (!$rendered) {
        // Template fallback
        $subject = "Aviso: Seu upload expira em breve - " . $variables['upload_title'];
        $body = "Olá " . $variables['user_name'] . ",\n\n";
        $body .= "Seu upload '" . $variables['upload_title'] . "' expirará em breve.\n\n";
        $body .= "Data de expiração: " . $variables['expiration_date'] . "\n";
        $body .= "Link para download: " . $variables['download_url'] . "\n\n";
        $body .= "Se você precisar manter este arquivo disponível por mais tempo, faça login no sistema e altere a data de expiração.";
        
        $rendered = ['subject' => $subject, 'body' => $body];
    }
    
    // Enviar email para o usuário
    if ($emailTemplate->sendEmail($upload['email'], $rendered['subject'], $rendered['body'])) {
        // Marcar como notificado
        $db->query("UPDATE upload_sessions SET expiration_notified = 1 WHERE id = ?", [$upload['id']]);
        echo "Notificação de expiração enviada para: " . $upload['email'] . " - Upload: " . $upload['title'] . "\n";
    } else {
        echo "Erro ao enviar notificação para: " . $upload['email'] . " - Upload: " . $upload['title'] . "\n";
    }
}

echo "Verificação de expiração concluída. Total de notificações enviadas: " . count($expiringUploads) . "\n";
?>