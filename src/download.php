<?php
ob_start(); // Inicia o buffer de saída
session_start(); // Inicia a sessão para controle de senha

require_once 'models/UploadSession.php';
require_once 'models/File.php';
require_once 'models/User.php';
require_once 'config/email_phpmailer.php'; // Adicionar include para o serviço de email

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
function sendDownloadNotification($token, $userData, $session_data) {
    if (!isset($_SESSION['download_notified'])) {
        $_SESSION['download_notified'] = [];
    }
    
    if (!in_array($token, $_SESSION['download_notified'])) {
        try {
            $emailService = new PHPMailerEmailService();
            $emailService->sendDownloadNotificationEmail(
                $_SERVER['REMOTE_ADDR'],
                $userData['email'],
                $token,
                $session_data['title']
            );
            $_SESSION['download_notified'][] = $token; // Marca como notificado
            error_log("Notificação de download enviada para: " . $userData['email']);
        } catch (Exception $e) {
            error_log('Falha ao enviar e-mail de notificação de download: ' . $e->getMessage());
        }
    }
}

// Download individual
if ($action === 'download' && $file_id) {
    $file = $fileModel->getById($file_id);
    if ($file && $file['session_id'] == $session_data['id'] && file_exists($file['file_path'])) {
        // Enviar notificação de download
        sendDownloadNotification($token, $userData, $session_data);
        
        ob_end_clean(); // Limpa o buffer antes de enviar os headers
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . htmlspecialchars($file['original_name']) . '"');
        header('Content-Length: ' . $file['file_size']);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        readfile($file['file_path']);
        exit();
    }
}

// Download ZIP
if ($action === 'download_zip') {
    if (empty($files)) {
        die('Nenhum arquivo encontrado');
    }
    
    // Enviar notificação de download
    sendDownloadNotification($token, $userData, $session_data);
    
    $zip_name = 'upload_' . $token . '.zip';
    $zip_path = sys_get_temp_dir() . '/' . $zip_name;
    
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
        foreach ($files as $file) {
            if (file_exists($file['file_path'])) {
                $zip->addFile($file['file_path'], $file['original_name']);
            }
        }
        $zip->close();
        
        ob_end_clean(); // Limpa o buffer antes de enviar os headers
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . htmlspecialchars($zip_name) . '"');
        header('Content-Length: ' . filesize($zip_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        readfile($zip_path);
        unlink($zip_path); // Limpar arquivo temporário
        exit();
    }
}

// Calcular tamanho total
$total_size = 0;
foreach ($files as $file) {
    $total_size += $file['file_size'];
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

if ($password_required && !$is_authorized) {
    ob_end_clean(); 
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Download Protegido</title>
        <style>
            @import url('https://fonts.googleapis.com/css?family=Titillium+Web:400,600,700');
            body { 
                font-family: 'Titillium Web', sans-serif; 
                background-color: #f7fcf5; 
                display: flex; 
                justify-content: center; 
                align-items: center; 
                min-height: 100vh; 
            }
            /* ... (demais estilos do formulário de senha) ... */
        </style>
    </head>
    <!-- ... (HTML do formulário de senha) ... -->
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download - Upload System</title>
    <link rel="icon" type="image/png" href="src/img/favicon.png">
    <style>
        @import url('https://fonts.googleapis.com/css?family=Titillium+Web:400,600,700');
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Titillium Web', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f7fcf5;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: #fff;
            color: #333;
            padding: 30px;
            text-align: center;
            border-bottom: 1px solid #e0e0e0;
        }

        .header h1 {
            color: #7cb342;
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .content {
            padding: 30px;
        }

        .upload-info {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .info-value {
            font-weight: 600;
            color: #333;
        }

        .download-all {
            background: #7cb342;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .download-all:hover {
            background: #689f38;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }

        .files-section {
            margin-top: 30px;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }

        .file-item {
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .file-item:hover {
            border-color: #c8e6c9;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            word-break: break-all;
        }

        .file-details {
            display: flex;
            gap: 20px;
            font-size: 0.9rem;
            color: #666;
        }

        .download-btn {
            background: #7cb342;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .download-btn:hover {
            background: #689f38;
            transform: translateY(-1px);
        }

        .expired-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 15px;
            }

            .header {
                padding: 20px;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .content {
                padding: 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .file-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .file-details {
                flex-direction: column;
                gap: 5px;
            }

            .download-btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="src/img/logo.png" alt="Logo" style="height: 70px; width: auto; margin: 0 0 15px 0; display: block;">
            <h1>Download de Arquivos</h1>
            <p>Arquivos compartilhados por <?php echo htmlspecialchars($userData['username']); ?></p>
        </div>

        <div class="content">
            <?php if (strtotime($session_data['expires_at']) < time()): ?>
                <div class="expired-warning">
                    ⚠️ Este link expirou em <?php echo date('d/m/Y H:i', strtotime($session_data['expires_at'])); ?>
                </div>
            <?php endif; ?>

            <div class="upload-info">
                <?php if ($session_data['title']): ?>
                    <h3 style="margin-bottom: 15px; color: #333;"><?php echo htmlspecialchars($session_data['title']); ?></h3>
                <?php endif; ?>
                
                <?php if (isset($session_data['description']) && $session_data['description']): ?>
                    <p style="margin-bottom: 20px; color: #666;"><?php echo htmlspecialchars($session_data['description']); ?></p>
                <?php endif; ?>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Arquivos</div>
                        <div class="info-value"><?php echo count($files); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Tamanho Total</div>
                        <div class="info-value"><?php echo formatBytes($total_size); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Data de Upload</div>
                        <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($session_data['created_at'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Expira em</div>
                        <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($session_data['expires_at'])); ?></div>
                    </div>
                </div>

                <?php if (!empty($files)): ?>
                    <a href="?token=<?php echo $token; ?>&action=download_zip" class="download-all">
                        📦 Baixar Todos os Arquivos (ZIP)
                    </a>
                <?php endif; ?>
            </div>

            <div class="files-section">
                <h3 class="section-title">Arquivos Individuais</h3>
                
                <?php if (empty($files)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📁</div>
                        <h3>Nenhum arquivo encontrado</h3>
                        <p>Os arquivos podem ter sido removidos ou o link expirou</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($files as $file): ?>
                        <div class="file-item">
                            <div class="file-info">
                                <div class="file-name"><?php echo htmlspecialchars($file['original_name']); ?></div>
                                <div class="file-details">
                                    <span>📏 <?php echo formatBytes($file['file_size']); ?></span>
                                    <span>📅 <?php echo date('d/m/Y H:i', strtotime($file['uploaded_at'])); ?></span>
                                </div>
                            </div>
                            <a href="?token=<?php echo $token; ?>&action=download&file_id=<?php echo $file['id']; ?>" 
                               class="download-btn">
                                📥 Baixar
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 