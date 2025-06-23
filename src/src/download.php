<?php
ob_start(); // Inicia o buffer de saída

require_once 'models/UploadSession.php';
require_once 'models/File.php';
require_once 'models/User.php';

$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? 'view';
$file_id = $_GET['file_id'] ?? null;

if (empty($token)) {
    http_response_code(404);
    die('Token não fornecido');
}

$uploadSession = new UploadSession();
$session_data = $uploadSession->getByToken($token);

if (!$session_data) {
    http_response_code(404);
    die('Link expirado ou inválido');
}

$fileModel = new File();
$files = $fileModel->getBySessionId($session_data['id']);
$user = new User();
$userData = $user->getById($session_data['user_id']);

// Download individual
if ($action === 'download' && $file_id) {
    $file = $fileModel->getById($file_id);
    if ($file && $file['session_id'] == $session_data['id'] && file_exists($file['file_path'])) {
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download - Upload System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
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
            background: #f8f9fa;
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-color: #667eea;
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
            background: #667eea;
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
            background: #5a6fd8;
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
            <h1>📥 Download de Arquivos</h1>
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
                
                <?php if ($session_data['description']): ?>
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