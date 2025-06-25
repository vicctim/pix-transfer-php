<?php
ob_start(); // Inicia o buffer de saída
session_start(); // Inicia a sessão para controle de senha

require_once 'models/UploadSession.php';
require_once 'models/File.php';
require_once 'models/User.php';
require_once 'models/SystemSettings.php';
require_once 'models/DownloadLog.php';
require_once 'models/EmailTemplate.php';
require_once 'models/ShortUrl.php';

$settings = new SystemSettings();
$downloadLog = new DownloadLog();
$emailTemplate = new EmailTemplate();

// Set timezone
date_default_timezone_set($settings->getTimezone());

$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? 'view';
$file_id = $_GET['file_id'] ?? null;


if (empty($token)) {
    http_response_code(404);
    die('Token não fornecido');
}

$uploadSession = new UploadSession();
$session_data = $uploadSession->getByToken($token);
$password_required = false; // Inicializar variável
$is_authorized = true; // Inicializar variável


if (!$session_data) {
    http_response_code(404);
    die('Link expirado ou inválido');
}

$fileModel = new File();
$files = $fileModel->getBySessionId($session_data['id']);
$user = new User();
$userData = $user->getById($session_data['user_id']);

// Função para enviar notificação de download
function sendDownloadNotification($token, $userData, $session_data, $location = '') {
    global $emailTemplate, $downloadLog, $settings;
    
    // Verificar se o usuário optou por receber notificações
    if (!$session_data['notify_sender'] || $session_data['notify_sender'] == 0) {
        error_log("Notificação de download não enviada - usuário não optou por receber (notify_sender = " . $session_data['notify_sender'] . ")");
        return false;
    }
    
    // Verificar se já foi enviada notificação nas últimas 2 horas para este upload
    $db = Database::getInstance();
    $checkStmt = $db->query(
        "SELECT COUNT(*) as count FROM email_logs 
         WHERE session_id = ? AND email_type = 'download_notification' 
         AND sent_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)",
        [$session_data['id']]
    );
    $recentNotification = $checkStmt->fetch();
    
    if ($recentNotification['count'] == 0) {
        try {
            // Get download count
            $downloadCount = $downloadLog->getDownloadCount($session_data['id']);
            
            // Prepare email variables
            $variables = [
                'upload_title' => $session_data['title'],
                'download_date' => date('d/m/Y H:i'),
                'download_location' => $location ?: 'Localização não disponível',
                'download_count' => $downloadCount,
                'user_name' => $userData['username']
            ];
            
            // Send notification email
            $rendered = $emailTemplate->renderTemplate('download_notification', $variables);
            if (!$rendered) {
                // Fallback template
                $subject = "Download realizado - " . $variables['upload_title'];
                $body = "Olá " . $variables['user_name'] . ",\n\n";
                $body .= "Seus arquivos foram baixados!\n\n";
                $body .= "Upload: " . $variables['upload_title'] . "\n";
                $body .= "Data do download: " . $variables['download_date'] . "\n";
                $body .= "Localização: " . $variables['download_location'] . "\n";
                $body .= "Total de downloads: " . $variables['download_count'] . "\n\n";
                $body .= "Este download foi realizado através do link que você compartilhou.";
                
                $rendered = ['subject' => $subject, 'body' => $body];
            }
            
            if ($emailTemplate->sendEmail($userData['email'], $rendered['subject'], $rendered['body'])) {
                // Log the email in database
                $emailTemplate->logEmail($session_data['id'], $userData['email'], 'download_notification', 'sent');
                error_log("Notificação de download enviada para: " . $userData['email'] . " - Upload: " . $session_data['title']);
                return true;
            } else {
                // Log failed email
                $emailTemplate->logEmail($session_data['id'], $userData['email'], 'download_notification', 'failed');
                error_log("Erro ao enviar email de notificação de download para: " . $userData['email']);
                return false;
            }
        } catch (Exception $e) {
            error_log("Erro ao enviar notificação de download: " . $e->getMessage());
            return false;
        }
    } else {
        error_log("Notificação de download já enviada nas últimas 2 horas para upload: " . $session_data['title']);
        return false;
    }
}

// Handle file download
if ($action === 'download' && $file_id) {
    // Log download and send notification BEFORE processing download
    $downloadId = $downloadLog->logDownload($session_data['id'], $file_id);
    
    if ($downloadId) {
        // Get location info for notification
        $downloads = $downloadLog->getDownloadsBySession($session_data['id']);
        $lastDownload = end($downloads);
        $location = '';
        
        if ($lastDownload) {
            $location = $downloadLog->getLocationString($lastDownload['country'], $lastDownload['city']);
        }
        
        // Send notification to uploader
        if ($userData && $userData['email']) {
            sendDownloadNotification($token, $userData, $session_data, $location);
        }
    }
    
    $file = $fileModel->getById($file_id);
    
    if (!$file || $file['session_id'] != $session_data['id']) {
        http_response_code(404);
        die('Arquivo não encontrado');
    }
    
    $filepath = $file['file_path'];
    
    if (!file_exists($filepath)) {
        http_response_code(404);
        die('Arquivo não existe no servidor');
    }
    
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Output file
    readfile($filepath);
    exit();
}

// Handle ZIP download of all files
if ($action === 'download_all') {
    // Log download and send notification BEFORE processing download
    $downloadId = $downloadLog->logDownload($session_data['id'], null); // null for all files
    
    if ($downloadId) {
        // Get location info for notification
        $downloads = $downloadLog->getDownloadsBySession($session_data['id']);
        $lastDownload = end($downloads);
        $location = '';
        
        if ($lastDownload) {
            $location = $downloadLog->getLocationString($lastDownload['country'], $lastDownload['city']);
        }
        
        // Send notification to uploader
        if ($userData && $userData['email']) {
            sendDownloadNotification($token, $userData, $session_data, $location);
        }
    }
    
    if (empty($files)) {
        die('Nenhum arquivo encontrado para download');
    }
    
    // Create temporary ZIP file
    $zip = new ZipArchive();
    $zipFilename = sys_get_temp_dir() . '/download_' . uniqid() . '.zip';
    
    if ($zip->open($zipFilename, ZipArchive::CREATE) !== TRUE) {
        die('Não foi possível criar o arquivo ZIP');
    }
    
    foreach ($files as $file) {
        if (file_exists($file['file_path'])) {
            $zip->addFile($file['file_path'], $file['original_name']);
        }
    }
    
    $zip->close();
    
    if (!file_exists($zipFilename)) {
        die('Erro ao criar arquivo ZIP');
    }
    
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers for ZIP download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $session_data['title'] . '.zip"');
    header('Content-Length: ' . filesize($zipFilename));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Output ZIP file
    readfile($zipFilename);
    
    // Clean up temporary file
    unlink($zipFilename);
    exit();
}

// Calculate total size
$totalSize = 0;
foreach ($files as $file) {
    $totalSize += $file['file_size'];
}

// Format file size
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Get download statistics
$downloadStats = $downloadLog->getDownloadStats($session_data['id']);

// Get short URL if exists
$shortUrl = new ShortUrl();
$shortCode = $shortUrl->getByOriginalToken($token);
if (!$shortCode) {
    // Create short URL
    $shortCode = $shortUrl->create($token, $session_data['expires_at']);
}
$shortUrlFull = $shortCode ? $settings->getSiteUrl() . '/s/' . $shortCode : null;

ob_end_flush(); // Libera o buffer de saída
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($session_data['title']); ?> - <?php echo htmlspecialchars($settings->get('site_name', 'Pix Transfer')); ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($settings->get('site_favicon', 'src/img/favicon.png')); ?>">
    <style>
        @import url('https://fonts.googleapis.com/css?family=Titillium+Web:400,600,700');
        @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
        
        :root {
            --primary-color: <?php echo $settings->get('primary_color', '#4a7c59'); ?>;
            --secondary-color: <?php echo $settings->get('secondary_color', '#6a9ba5'); ?>;
            --background-color: <?php echo $settings->get('background_color', '#f7fcf5'); ?>;
            --text-color: <?php echo $settings->get('text_color', '#333333'); ?>;
            --success-color: <?php echo $settings->get('success_color', '#28a745'); ?>;
            --error-color: <?php echo $settings->get('error_color', '#dc3545'); ?>;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Titillium Web', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--background-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--text-color);
        }
        
        .download-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }
        
        .download-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .logo-section {
            margin-bottom: 20px;
        }
        
        .logo {
            max-width: 80px;
            height: auto;
            margin-bottom: 10px;
        }
        
        .site-name {
            font-size: 1.2em;
            font-weight: 600;
            opacity: 0.9;
            margin-bottom: 15px;
        }
        
        .download-header h1 {
            font-size: 2em;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .download-header .meta {
            opacity: 0.9;
            font-size: 0.9em;
        }
        
        .download-content {
            padding: 30px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .info-card .icon {
            font-size: 2em;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .info-card .number {
            font-size: 1.5em;
            font-weight: 700;
            color: var(--text-color);
        }
        
        .info-card .label {
            color: #6c757d;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        .files-section {
            margin-bottom: 30px;
        }
        
        .files-section h3 {
            margin-bottom: 20px;
            color: #495057;
            font-weight: 600;
        }
        
        .file-list {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            transition: background-color 0.3s ease;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-item:hover {
            background: #f8f9fa;
        }
        
        .file-icon {
            font-size: 1.5em;
            color: var(--primary-color);
            margin-right: 15px;
            width: 30px;
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-name {
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 5px;
        }
        
        .file-size {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .file-download {
            margin-left: 15px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .btn:hover {
            filter: brightness(0.9);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .btn-success {
            background: var(--success-color);
        }
        
        .btn-success:hover {
            filter: brightness(0.9);
        }
        
        .btn-large {
            font-size: 16px;
            padding: 15px 30px;
        }
        
        .download-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .stats-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .stats-section h4 {
            margin-bottom: 15px;
            color: #495057;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 5px;
        }
        
        .short-url-section {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .short-url-section h4 {
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .short-url {
            background: white;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            word-break: break-all;
            border: 1px solid #bbdefb;
        }
        
        .copy-btn {
            margin-left: 10px;
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .expiry-warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
        }
        
        .expiry-warning .icon {
            margin-right: 10px;
        }
        
        @media (max-width: 768px) {
            .download-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            @media (max-width: 480px) {
                .info-grid {
                    grid-template-columns: 1fr;
                }
            }
        }
    </style>
</head>
<body>
    <div class="download-container">
        <div class="download-header">
            <div class="logo-section">
                <?php 
                $siteLogo = $settings->get('site_logo', 'src/img/logo.png');
                $siteName = $settings->get('site_name', 'Pix Transfer');
                if ($siteLogo && file_exists($siteLogo)): 
                ?>
                    <img src="../<?php echo htmlspecialchars($siteLogo); ?>" alt="<?php echo htmlspecialchars($siteName); ?>" class="logo">
                <?php endif; ?>
                <div class="site-name"><?php echo htmlspecialchars($siteName); ?></div>
            </div>
            <h1><?php echo htmlspecialchars($session_data['title']); ?></h1>
            <div class="meta">
                Enviado por <strong><?php echo htmlspecialchars($userData['username']); ?></strong>
                em <?php echo date('d/m/Y H:i', strtotime($session_data['created_at'])); ?>
            </div>
        </div>
        
        <div class="download-content">
            <!-- Expiry Warning -->
            <?php 
            $now = new DateTime();
            $expiry = new DateTime($session_data['expires_at']);
            $diff = $now->diff($expiry);
            
            if ($diff->days <= 1 && $expiry > $now): 
            ?>
                <div class="expiry-warning">
                    <i class="fas fa-exclamation-triangle icon"></i>
                    <strong>Atenção:</strong> Este link expira em <?php echo $expiry->format('d/m/Y H:i'); ?>
                </div>
            <?php endif; ?>
            
            <!-- Info Cards -->
            <div class="info-grid">
                <div class="info-card">
                    <div class="icon"><i class="fas fa-file"></i></div>
                    <div class="number"><?php echo count($files); ?></div>
                    <div class="label">Arquivo(s)</div>
                </div>
                
                <div class="info-card">
                    <div class="icon"><i class="fas fa-hdd"></i></div>
                    <div class="number"><?php echo formatFileSize($totalSize); ?></div>
                    <div class="label">Tamanho Total</div>
                </div>
                
                <div class="info-card">
                    <div class="icon"><i class="fas fa-download"></i></div>
                    <div class="number"><?php echo $downloadStats['total_downloads']; ?></div>
                    <div class="label">Downloads</div>
                </div>
                
                <div class="info-card">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <div class="number"><?php echo $downloadStats['unique_downloads']; ?></div>
                    <div class="label">Usuários Únicos</div>
                </div>
            </div>
            
            <!-- Download Actions -->
            <div class="download-actions">
                <a href="?token=<?php echo urlencode($token); ?>&action=download_all" class="btn btn-success btn-large">
                    <i class="fas fa-download"></i>
                    Baixar Todos os Arquivos
                </a>
            </div>
            
            <!-- Short URL -->
            <?php if ($shortUrlFull): ?>
                <div class="short-url-section">
                    <h4><i class="fas fa-link"></i> Link Curto</h4>
                    <div class="short-url">
                        <?php echo htmlspecialchars($shortUrlFull); ?>
                        <button class="btn copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($shortUrlFull); ?>')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Files List -->
            <div class="files-section">
                <h3><i class="fas fa-folder-open"></i> Arquivos Disponíveis</h3>
                <div class="file-list">
                    <?php foreach ($files as $file): ?>
                        <div class="file-item">
                            <div class="file-icon">
                                <i class="<?php 
                                    $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                                    echo match($ext) {
                                        'pdf' => 'fas fa-file-pdf',
                                        'doc', 'docx' => 'fas fa-file-word',
                                        'xls', 'xlsx' => 'fas fa-file-excel',
                                        'ppt', 'pptx' => 'fas fa-file-powerpoint',
                                        'jpg', 'jpeg', 'png', 'gif', 'bmp' => 'fas fa-file-image',
                                        'mp4', 'avi', 'mov', 'wmv' => 'fas fa-file-video',
                                        'mp3', 'wav', 'flac' => 'fas fa-file-audio',
                                        'zip', 'rar', '7z' => 'fas fa-file-archive',
                                        default => 'fas fa-file'
                                    };
                                ?>"></i>
                            </div>
                            <div class="file-info">
                                <div class="file-name"><?php echo htmlspecialchars($file['original_name']); ?></div>
                                <div class="file-size"><?php echo formatFileSize($file['file_size']); ?></div>
                            </div>
                            <div class="file-download">
                                <a href="?token=<?php echo urlencode($token); ?>&action=download&file_id=<?php echo $file['id']; ?>" class="btn">
                                    <i class="fas fa-download"></i>
                                    Baixar
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Download Statistics -->
            <?php if (!empty($downloadStats['locations'])): ?>
                <div class="stats-section">
                    <h4><i class="fas fa-chart-bar"></i> Estatísticas de Download</h4>
                    <div class="stats-grid">
                        <?php foreach (array_slice($downloadStats['locations'], 0, 4) as $location): ?>
                            <div class="stat-item">
                                <div><strong><?php echo $location['location_count']; ?></strong></div>
                                <div style="font-size: 0.8em; color: #6c757d;">
                                    <?php echo $downloadLog->getLocationString($location['country'], $location['city']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Create temporary success message
                const btn = event.target.closest('.copy-btn');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
                btn.style.background = '#28a745';
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.background = '';
                }, 2000);
            });
        }
    </script>
</body>
</html>