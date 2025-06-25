<?php
session_start();

require_once 'models/SystemSettings.php';

$settings = new SystemSettings();

// Check if setup is complete
if (!$settings->isSetupComplete()) {
    header('Location: setup.php');
    exit();
}

// Set timezone
date_default_timezone_set($settings->getTimezone());

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'models/User.php';
require_once 'models/UploadSession.php';
require_once 'models/File.php';
require_once 'models/DownloadLog.php';
require_once 'models/ShortUrl.php';

$userModel = new User();
$uploadSessionModel = new UploadSession();
$fileModel = new File();
$downloadLog = new DownloadLog();

// Obter dados do usuário
$user = $userModel->getById($_SESSION['user_id']);
if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Obter uploads do usuário
$user_uploads = $uploadSessionModel->getByUserId($_SESSION['user_id']);

$siteName = $settings->get('site_name', 'Pix Transfer');
$availableExpirationDays = $settings->get('available_expiration_days', [1, 3, 7, 14, 30]);

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($siteName); ?></title>
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
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 20px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .header .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-logo {
            max-width: 50px;
            height: auto;
        }
        
        .header h1 {
            font-size: 1.8em;
            font-weight: 700;
        }
        
        .header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 60% 40%;
            gap: 30px;
            align-items: start;
        }
        
        .upload-section {
            background: white;
            border-radius: 15px;
            border: 1px solid #e0e0e0;
            padding: 30px;
        }
        
        .upload-section h2 {
            margin-bottom: 20px;
            color: #495057;
            font-weight: 600;
        }
        
        .upload-form {
            display: grid;
            gap: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 124, 89, 0.1);
        }
        
        .file-upload-area {
            border: 3px dashed #dee2e6;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #f8f9fa;
            user-select: none;
        }
        
        .file-upload-area:hover,
        .file-upload-area.dragover {
            border-color: var(--primary-color);
            background: #f0f8f4;
        }
        
        .file-upload-area .icon {
            font-size: 3em;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .file-upload-area h3 {
            margin-bottom: 10px;
            color: #495057;
        }
        
        .file-upload-area p {
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .file-list {
            margin-top: 20px;
            display: none;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .file-item .icon {
            color: var(--primary-color);
            margin-right: 10px;
        }
        
        .file-item .info {
            flex: 1;
        }
        
        .file-item .remove {
            color: #dc3545;
            cursor: pointer;
            padding: 5px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .mode-selection {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            background: #f8f9fa;
        }
        
        .mode-buttons {
            display: flex;
            gap: 10px;
        }
        
        .mode-btn {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: #666;
        }
        
        .mode-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .mode-btn.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .email-fields {
            display: none;
        }
        
        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .mode-buttons {
                flex-direction: column;
            }
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
        
        .btn-large {
            background: var(--primary-color) !important;
        }
        
        .btn:hover {
            filter: brightness(0.9);
            opacity: 0.9;
        }
        
        .btn-success {
            background: var(--success-color);
        }
        
        .btn-success:hover {
            filter: brightness(0.9);
        }
        
        .btn-danger {
            background: var(--error-color);
        }
        
        .btn-danger:hover {
            filter: brightness(0.9);
        }
        
        .btn-large {
            font-size: 16px;
            padding: 15px 30px;
        }
        
        .uploads-section {
            background: white;
            border-radius: 15px;
            border: 1px solid #e0e0e0;
            padding: 30px;
        }
        
        .uploads-section h2 {
            margin-bottom: 20px;
            color: #495057;
            font-weight: 600;
        }
        
        .upload-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .upload-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .upload-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .upload-title {
            font-weight: 600;
            color: #333;
            font-size: 1.1em;
        }
        
        .upload-meta {
            font-size: 0.9em;
            color: #6c757d;
        }
        
        .upload-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .stat {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .stat .number {
            font-weight: 600;
            color: #333;
        }
        
        .stat .label {
            font-size: 0.8em;
            color: #6c757d;
        }
        
        .upload-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .upload-actions .btn {
            padding: 8px 16px;
            font-size: 12px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }
        
        .alert-success {
            background: color-mix(in srgb, var(--success-color) 20%, white);
            color: color-mix(in srgb, var(--success-color) 80%, black);
            border-color: color-mix(in srgb, var(--success-color) 40%, white);
        }
        
        .alert-error {
            background: color-mix(in srgb, var(--error-color) 20%, white);
            color: color-mix(in srgb, var(--error-color) 80%, black);
            border-color: color-mix(in srgb, var(--error-color) 40%, white);
        }
        
        .progress-container {
            margin-top: 20px;
            display: none;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .progress-text {
            text-align: center;
            margin-top: 10px;
            font-weight: 600;
            color: #495057;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state .icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .header .container {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-left {
                flex-direction: column;
                gap: 10px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .upload-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .upload-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="header-left">
                <?php 
                $siteLogo = $settings->get('site_logo', 'src/img/logo.png');
                if ($siteLogo && file_exists($siteLogo)): 
                ?>
                    <img src="<?php echo htmlspecialchars($siteLogo); ?>" alt="<?php echo htmlspecialchars($siteName); ?>" class="header-logo">
                <?php endif; ?>
                <h1><i class="fas fa-cloud-upload-alt"></i> <?php echo htmlspecialchars($siteName); ?></h1>
            </div>
            <div class="user-info">
                <span>Olá, <strong><?php echo htmlspecialchars($user['username']); ?></strong></span>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="/admin" class="btn">
                        <i class="fas fa-cog"></i> Admin
                    </a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="main-content">
            <!-- Upload Section -->
            <div class="upload-section">
            <h2><i class="fas fa-upload"></i> Enviar Arquivos</h2>
            
            <!-- Mode Selection -->
            <div class="mode-selection" style="margin-bottom: 20px;">
                <div class="mode-buttons">
                    <button type="button" id="linkModeBtn" class="mode-btn active">
                        <i class="fas fa-link"></i> Apenas Link
                    </button>
                    <button type="button" id="emailModeBtn" class="mode-btn">
                        <i class="fas fa-envelope"></i> Enviar por Email
                    </button>
                </div>
            </div>
            
            <div id="uploadAlert" class="alert" style="display: none;"></div>
            
            <form id="uploadForm" class="upload-form">
                <div class="file-upload-area" id="fileUploadArea">
                    <div class="icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <h3>Arraste arquivos aqui ou clique para selecionar</h3>
                    <p>Suporte a arquivos de até <?php echo formatFileSize($settings->get('max_file_size', 10737418240)); ?></p>
                    <input type="file" id="fileInput" multiple style="display: none;">
                    <button type="button" class="btn" onclick="document.getElementById('fileInput').click();">
                        <i class="fas fa-file-plus"></i>
                        Selecionar Arquivos
                    </button>
                </div>
                
                <div id="fileList" class="file-list"></div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Título do Compartilhamento</label>
                        <input type="text" id="title" name="title" placeholder="Ex: Relatórios Mensais">
                    </div>
                    
                    <div class="form-group">
                        <label for="expires_in">Expira em</label>
                        <select id="expires_in" name="expires_in">
                            <?php foreach ($availableExpirationDays as $days): ?>
                                <option value="<?php echo $days; ?>" <?php echo $days == $settings->get('default_expiration_days', 7) ? 'selected' : ''; ?>>
                                    <?php echo $days; ?> dia<?php echo $days > 1 ? 's' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="email-fields" id="emailFields">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="recipient_email">Email do Destinatário</label>
                            <input type="email" id="recipient_email" name="recipient_email" placeholder="destinatario@exemplo.com">
                        </div>
                        
                        <div class="form-group">
                            <label for="additional_emails">Emails Adicionais (separados por vírgula)</label>
                            <input type="text" id="additional_emails" name="additional_emails" placeholder="email1@exemplo.com, email2@exemplo.com">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="custom_message">Mensagem Personalizada</label>
                        <textarea id="custom_message" name="custom_message" rows="3" placeholder="Adicione uma mensagem personalizada para os destinatários..."></textarea>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="notify_sender" name="notify_sender">
                        <label for="notify_sender">Receber notificação quando alguém baixar os arquivos</label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-large" id="uploadButton">
                    <i class="fas fa-upload"></i>
                    Enviar Arquivos
                </button>
                
                <div class="progress-container" id="progressContainer">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="progress-text" id="progressText">Enviando...</div>
                </div>
            </form>
            </div>

            <!-- User Uploads Section -->
        <div class="uploads-section">
            <h2><i class="fas fa-history"></i> Meus Compartilhamentos</h2>
            
            <?php if (empty($user_uploads)): ?>
                <div class="empty-state">
                    <div class="icon">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <h3>Nenhum compartilhamento ainda</h3>
                    <p>Seus arquivos enviados aparecerão aqui</p>
                </div>
            <?php else: ?>
                <?php foreach ($user_uploads as $upload): ?>
                    <?php 
                    $files = $fileModel->getBySessionId($upload['id']);
                    $totalSize = $fileModel->getTotalSizeBySession($upload['id']);
                    $downloadStats = $downloadLog->getDownloadStats($upload['id']);
                    $isExpired = strtotime($upload['expires_at']) < time();
                    ?>
                    <div class="upload-card <?php echo $isExpired ? 'expired' : ''; ?>">
                        <div class="upload-header">
                            <div>
                                <div class="upload-title"><?php echo htmlspecialchars($upload['title']); ?></div>
                                <div class="upload-meta">
                                    Criado em <?php echo date('d/m/Y H:i', strtotime($upload['created_at'])); ?>
                                    <?php if ($isExpired): ?>
                                        <span style="color: #dc3545; font-weight: 600;"> • EXPIRADO</span>
                                    <?php else: ?>
                                        • Expira em <?php echo date('d/m/Y H:i', strtotime($upload['expires_at'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="upload-stats">
                            <div class="stat">
                                <div class="number"><?php echo count($files); ?></div>
                                <div class="label">Arquivo(s)</div>
                            </div>
                            <div class="stat">
                                <div class="number"><?php echo formatFileSize($totalSize); ?></div>
                                <div class="label">Tamanho</div>
                            </div>
                            <div class="stat">
                                <div class="number"><?php echo $downloadStats['total_downloads']; ?></div>
                                <div class="label">Downloads</div>
                            </div>
                            <div class="stat">
                                <div class="number"><?php echo $downloadStats['unique_downloads']; ?></div>
                                <div class="label">Únicos</div>
                            </div>
                        </div>
                        
                        <div class="upload-actions">
                            <?php if (!$isExpired): ?>
                                <a href="../download/<?php echo urlencode($upload['token']); ?>" class="btn" target="_blank">
                                    <i class="fas fa-eye"></i> Visualizar
                                </a>
                                <?php 
                                $shortUrl = new ShortUrl();
                                $shortCode = $shortUrl->getShortCodeByToken($upload['token']);
                                $shortLink = $shortCode ? $settings->getSiteUrl() . '/s/' . $shortCode : $settings->getSiteUrl() . '/download/' . urlencode($upload['token']);
                                ?>
                                <button class="btn" onclick="copyToClipboard('<?php echo $shortLink; ?>')">
                                    <i class="fas fa-copy"></i> Copiar Link
                                </button>
                            <?php endif; ?>
                            <button class="btn" onclick="changeExpiration(<?php echo $upload['id']; ?>, '<?php echo $upload['expires_at']; ?>')">
                                <i class="fas fa-clock"></i> Alterar Expiração
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Mode selection functionality
        const linkModeBtn = document.getElementById('linkModeBtn');
        const emailModeBtn = document.getElementById('emailModeBtn');
        const emailFields = document.getElementById('emailFields');
        
        linkModeBtn.addEventListener('click', () => {
            linkModeBtn.classList.add('active');
            emailModeBtn.classList.remove('active');
            emailFields.style.display = 'none';
        });
        
        emailModeBtn.addEventListener('click', () => {
            emailModeBtn.classList.add('active');
            linkModeBtn.classList.remove('active');
            emailFields.style.display = 'block';
        });
        
        // File upload functionality
        const fileInput = document.getElementById('fileInput');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileList = document.getElementById('fileList');
        const uploadForm = document.getElementById('uploadForm');
        const uploadButton = document.getElementById('uploadButton');
        const progressContainer = document.getElementById('progressContainer');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        const uploadAlert = document.getElementById('uploadAlert');

        let selectedFiles = [];

        // Drag and drop events
        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        // Make entire upload area clickable
        fileUploadArea.addEventListener('click', (e) => {
            // Don't trigger if clicking on the button itself to avoid double trigger
            if (e.target.tagName !== 'BUTTON' && !e.target.closest('button')) {
                fileInput.click();
            }
        });

        function handleFiles(files) {
            selectedFiles = Array.from(files);
            displayFileList();
        }

        function displayFileList() {
            if (selectedFiles.length === 0) {
                fileList.style.display = 'none';
                return;
            }

            fileList.style.display = 'block';
            fileList.innerHTML = '';

            selectedFiles.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <i class="fas fa-file icon"></i>
                    <div class="info">
                        <strong>${file.name}</strong>
                        <br>
                        <small>${formatFileSize(file.size)}</small>
                    </div>
                    <i class="fas fa-times remove" onclick="removeFile(${index})"></i>
                `;
                fileList.appendChild(fileItem);
            });
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            displayFileList();
        }

        function formatFileSize(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0;
            while (bytes >= 1024 && i < units.length - 1) {
                bytes /= 1024;
                i++;
            }
            return Math.round(bytes * 100) / 100 + ' ' + units[i];
        }

        function showAlert(message, type = 'success') {
            uploadAlert.className = `alert alert-${type}`;
            uploadAlert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            uploadAlert.style.display = 'block';
            
            setTimeout(() => {
                uploadAlert.style.display = 'none';
            }, 5000);
        }

        // Form submission
        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (selectedFiles.length === 0) {
                showAlert('Selecione pelo menos um arquivo para enviar.', 'error');
                return;
            }

            const formData = new FormData();
            
            // Add files
            selectedFiles.forEach(file => {
                formData.append('files[]', file);
            });

            // Add form data
            formData.append('title', document.getElementById('title').value);
            formData.append('recipient_email', document.getElementById('recipient_email').value);
            formData.append('additional_emails', document.getElementById('additional_emails').value);
            formData.append('expires_in', document.getElementById('expires_in').value);
            formData.append('custom_message', document.getElementById('custom_message').value);
            formData.append('notify_sender', document.getElementById('notify_sender').checked ? '1' : '0');

            // Show progress
            uploadButton.disabled = true;
            progressContainer.style.display = 'block';
            progressFill.style.width = '0%';
            progressText.textContent = 'Enviando...';

            try {
                const xhr = new XMLHttpRequest();
                
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressFill.style.width = percentComplete + '%';
                        progressText.textContent = `Enviando... ${Math.round(percentComplete)}%`;
                    }
                });

                xhr.onload = function() {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            showAlert(`Upload realizado com sucesso! ${response.data.file_count} arquivo(s) enviado(s).`);
                            
                            // Reset form
                            uploadForm.reset();
                            selectedFiles = [];
                            displayFileList();
                            
                            // Reload page after delay
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            showAlert(response.message, 'error');
                        }
                    } else {
                        showAlert('Erro no servidor. Tente novamente.', 'error');
                    }
                    
                    uploadButton.disabled = false;
                    progressContainer.style.display = 'none';
                };

                xhr.onerror = function() {
                    showAlert('Erro de conexão. Verifique sua internet.', 'error');
                    uploadButton.disabled = false;
                    progressContainer.style.display = 'none';
                };

                xhr.open('POST', 'upload_handler.php');
                xhr.send(formData);

            } catch (error) {
                showAlert('Erro inesperado: ' + error.message, 'error');
                uploadButton.disabled = false;
                progressContainer.style.display = 'none';
            }
        });

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showAlert('Link copiado para a área de transferência!');
            });
        }

        function changeExpiration(uploadId, currentExpiration) {
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0,0,0,0.5); z-index: 1000; display: flex;
                align-items: center; justify-content: center;
            `;
            
            const currentDate = new Date(currentExpiration);
            const formattedDate = currentDate.toISOString().slice(0, 16);
            
            modal.innerHTML = `
                <div style="background: white; padding: 30px; border-radius: 15px; width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                    <h3 style="margin-bottom: 20px; color: var(--text-color);">
                        <i class="fas fa-clock"></i> Alterar Expiração
                    </h3>
                    <form id="expirationForm">
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-color);">
                                Nova Data e Hora de Expiração
                            </label>
                            <input type="datetime-local" id="newExpiration" value="${formattedDate}" 
                                   style="width: 100%; padding: 12px; border: 2px solid #dee2e6; border-radius: 8px; font-size: 14px;"
                                   min="${new Date().toISOString().slice(0, 16)}">
                        </div>
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button type="button" onclick="closeModal()" 
                                    style="padding: 12px 20px; background: #6c757d; color: white; border: none; border-radius: 8px; cursor: pointer;">
                                Cancelar
                            </button>
                            <button type="submit" 
                                    style="padding: 12px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; cursor: pointer;">
                                <i class="fas fa-save"></i> Salvar
                            </button>
                        </div>
                    </form>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            window.closeModal = function() {
                document.body.removeChild(modal);
                delete window.closeModal;
            };
            
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeModal();
            });
            
            document.getElementById('expirationForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const newExpiration = document.getElementById('newExpiration').value;
                
                fetch('update_expiration.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        upload_id: uploadId,
                        new_expiration: newExpiration
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Expiração atualizada com sucesso!');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert(data.message, 'error');
                    }
                    closeModal();
                })
                .catch(error => {
                    showAlert('Erro ao atualizar expiração', 'error');
                    closeModal();
                });
            });
        }
    </script>
</body>
</html>