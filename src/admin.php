<?php
session_start();
require_once 'models/User.php';
require_once 'models/UploadSession.php';
require_once 'models/File.php';
require_once 'models/SystemSettings.php';
require_once 'models/EmailTemplate.php';
require_once 'models/DownloadLog.php';

// Proteção da página: Apenas usuários logados e com role 'admin'
if (!isset($_SESSION['user_id'])) {
    header('Location: ../');
    exit();
}

$userModel = new User();
if (!$userModel->isAdmin($_SESSION['user_id'])) {
    header('Location: ../dashboard');
    exit();
}

$settings = new SystemSettings();
$emailTemplate = new EmailTemplate();
$downloadLog = new DownloadLog();

// Handle form submissions
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_settings':
                    foreach ($_POST['settings'] as $key => $value) {
                        $type = 'string';
                        if (in_array($key, ['smtp_port', 'default_expiration_days', 'max_file_size'])) {
                            $type = 'number';
                        } elseif (in_array($key, ['cloudflare_tunnel_enabled'])) {
                            $type = 'boolean';
                        } elseif ($key === 'available_expiration_days') {
                            $value = array_map('intval', explode(',', $value));
                            $type = 'json';
                        }
                        $settings->set($key, $value, $type);
                    }
                    $message = 'Configurações atualizadas com sucesso!';
                    break;
                    
                case 'update_email_template':
                    $templateName = $_POST['template_name'];
                    $subject = $_POST['subject'];
                    $body = $_POST['body'];
                    
                    if ($emailTemplate->updateTemplate($templateName, $subject, $body)) {
                        $message = 'Template de email atualizado com sucesso!';
                    } else {
                        throw new Exception('Erro ao atualizar template');
                    }
                    break;
                    
                case 'delete_user':
                    $userId = $_POST['user_id'];
                    if ($userModel->deleteUser($userId)) {
                        $message = 'Usuário deletado com sucesso!';
                    } else {
                        throw new Exception('Erro ao deletar usuário');
                    }
                    break;
                    
                case 'update_user':
                    $userId = $_POST['user_id'];
                    $username = $_POST['username'];
                    $email = $_POST['email'];
                    $role = $_POST['role'];
                    
                    if ($userModel->updateUser($userId, $username, $email, $role)) {
                        $message = 'Usuário atualizado com sucesso!';
                    } else {
                        throw new Exception('Erro ao atualizar usuário');
                    }
                    break;
                    
                case 'update_styling':
                    // Save styling settings
                    $settings->set('primary_color', $_POST['primary_color'] ?? '#4a7c59');
                    $settings->set('secondary_color', $_POST['secondary_color'] ?? '#6a9ba5');
                    $settings->set('background_color', $_POST['background_color'] ?? '#f7fcf5');
                    $settings->set('text_color', $_POST['text_color'] ?? '#333333');
                    $settings->set('success_color', $_POST['success_color'] ?? '#28a745');
                    $settings->set('error_color', $_POST['error_color'] ?? '#dc3545');
                    $message = 'Configurações de estilo salvas com sucesso!';
                    break;
                    
                case 'add_user':
                    $username = $_POST['username'];
                    $email = $_POST['email'];
                    $password = $_POST['password'];
                    $role = $_POST['role'];
                    
                    if ($userModel->create($username, $email, $password, $role)) {
                        $message = 'Usuário criado com sucesso!';
                    } else {
                        throw new Exception('Erro ao criar usuário');
                    }
                    break;
                    
                case 'update_email_templates':
                    // Save all email templates
                    foreach ($_POST['templates'] as $templateName => $templateData) {
                        $settings->set('email_template_' . $templateName . '_subject', $templateData['subject']);
                        $settings->set('email_template_' . $templateName . '_body', $templateData['body']);
                    }
                    $message = 'Templates de email atualizados com sucesso!';
                    break;
                    
                case 'upload_logo':
                    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = 'src/img/';
                        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
                        
                        if (!in_array($_FILES['logo']['type'], $allowedTypes)) {
                            throw new Exception('Apenas arquivos PNG e JPG são permitidos para o logo.');
                        }
                        
                        if ($_FILES['logo']['size'] > 2 * 1024 * 1024) { // 2MB max
                            throw new Exception('O arquivo do logo deve ter no máximo 2MB.');
                        }
                        
                        $fileName = 'logo.' . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                        $uploadPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                            $settings->set('site_logo', $uploadPath);
                            $message = 'Logo atualizado com sucesso!';
                        } else {
                            throw new Exception('Erro ao fazer upload do logo.');
                        }
                    }
                    
                    if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = 'src/img/';
                        $allowedTypes = ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon'];
                        
                        if (!in_array($_FILES['favicon']['type'], $allowedTypes)) {
                            throw new Exception('Apenas arquivos PNG e ICO são permitidos para o favicon.');
                        }
                        
                        if ($_FILES['favicon']['size'] > 1 * 1024 * 1024) { // 1MB max
                            throw new Exception('O arquivo do favicon deve ter no máximo 1MB.');
                        }
                        
                        $fileName = 'favicon.' . pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION);
                        $uploadPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['favicon']['tmp_name'], $uploadPath)) {
                            $settings->set('site_favicon', $uploadPath);
                            $message = 'Favicon atualizado com sucesso!';
                        } else {
                            throw new Exception('Erro ao fazer upload do favicon.');
                        }
                    }
                    break;
                    
                case 'update_expiration':
                    $uploadId = $_POST['upload_id'] ?? null;
                    $newExpiration = $_POST['new_expiration'] ?? null;
                    
                    if (!$uploadId || !$newExpiration) {
                        throw new Exception('Dados necessários não fornecidos');
                    }
                    
                    // Validar formato da data
                    $date = DateTime::createFromFormat('Y-m-d\TH:i', $newExpiration);
                    if (!$date) {
                        throw new Exception('Formato de data inválido');
                    }
                    
                    // Verificar se a data não é no passado
                    if ($date <= new DateTime()) {
                        throw new Exception('A data de expiração deve ser no futuro');
                    }
                    
                    $db = Database::getInstance();
                    
                    // Verificar se o upload existe
                    $checkStmt = $db->query(
                        "SELECT id FROM upload_sessions WHERE id = ?",
                        [$uploadId]
                    );
                    
                    if (!$checkStmt->fetch()) {
                        throw new Exception('Upload não encontrado');
                    }
                    
                    // Atualizar expiração
                    $stmt = $db->query(
                        "UPDATE upload_sessions SET expires_at = ?, expiration_notified = 0 WHERE id = ?",
                        [$date->format('Y-m-d H:i:s'), $uploadId]
                    );
                    
                    if ($stmt->rowCount() > 0) {
                        $message = 'Expiração atualizada com sucesso!';
                    } else {
                        throw new Exception('Erro ao atualizar expiração no banco de dados');
                    }
                    break;
                    
                case 'delete_upload':
                    $uploadId = $_POST['upload_id'] ?? null;
                    
                    if (!$uploadId) {
                        throw new Exception('ID do upload não fornecido');
                    }
                    
                    $db = Database::getInstance();
                    
                    // Verificar se o upload existe
                    $checkStmt = $db->query(
                        "SELECT id FROM upload_sessions WHERE id = ?",
                        [$uploadId]
                    );
                    
                    if (!$checkStmt->fetch()) {
                        throw new Exception('Upload não encontrado');
                    }
                    
                    // Buscar arquivos do upload para deletar fisicamente
                    $filesStmt = $db->query(
                        "SELECT file_path FROM files WHERE session_id = ?",
                        [$uploadId]
                    );
                    $files = $filesStmt->fetchAll();
                    
                    // Deletar arquivos físicos
                    foreach ($files as $file) {
                        if (file_exists($file['file_path'])) {
                            unlink($file['file_path']);
                        }
                    }
                    
                    // Deletar do banco (cascade vai remover files e logs relacionados)
                    $stmt = $db->query(
                        "DELETE FROM upload_sessions WHERE id = ?",
                        [$uploadId]
                    );
                    
                    if ($stmt->rowCount() > 0) {
                        $message = 'Upload deletado com sucesso!';
                    } else {
                        throw new Exception('Erro ao deletar upload do banco de dados');
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Get current data
$uploadSessionModel = new UploadSession();
$fileModel = new File();
$allUsers = $userModel->getAllUsers();
$allUploads = $uploadSessionModel->getAll();
$systemSettings = $settings->getAll();

// Get tab
$activeTab = $_GET['tab'] ?? 'dashboard';

// Statistics
$totalUsers = count($allUsers);
$totalUploads = count($allUploads);
$totalFiles = 0;
$totalSize = 0;
$totalDownloads = 0;

foreach ($allUploads as $upload) {
    $files = $fileModel->getBySessionId($upload['id']);
    $totalFiles += count($files);
    $totalDownloads += $upload['download_count'] ?? 0;
    foreach ($files as $file) {
        $totalSize += $file['file_size'];
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Set timezone
date_default_timezone_set($settings->getTimezone());
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - <?php echo htmlspecialchars($settings->get('site_name', 'Pix Transfer')); ?></title>
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
        
        .admin-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .admin-header .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-header h1 {
            font-size: 1.8em;
            font-weight: 700;
        }
        
        .admin-header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .admin-nav {
            background: white;
            border-bottom: 1px solid #dee2e6;
            padding: 0;
        }
        
        .admin-nav .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .nav-tabs {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .nav-tab {
            margin-right: 30px;
        }
        
        .nav-tab a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 15px 20px;
            text-decoration: none;
            color: #6c757d;
            font-weight: 600;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }
        
        .nav-tab a:hover,
        .nav-tab.active a {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card .icon {
            font-size: 2.5em;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .stat-card .number {
            font-size: 2em;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-card .label {
            color: #6c757d;
            font-weight: 600;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            background: #f8f9fa;
        }
        
        .card-header h3 {
            margin: 0;
            color: #495057;
            font-weight: 600;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #495057;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #dee2e6;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            filter: brightness(0.9);
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: var(--error-color);
        }
        
        .btn-danger:hover {
            filter: brightness(0.9);
        }
        
        .btn-success {
            background: var(--primary-color) !important;
        }
        
        .btn-success:hover {
            filter: brightness(0.85) !important;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-success {
            background: color-mix(in srgb, var(--success-color) 20%, white);
            color: color-mix(in srgb, var(--success-color) 80%, black);
            border: 1px solid color-mix(in srgb, var(--success-color) 40%, white);
        }
        
        .alert-error {
            background: color-mix(in srgb, var(--error-color) 20%, white);
            color: color-mix(in srgb, var(--error-color) 80%, black);
            border: 1px solid color-mix(in srgb, var(--error-color) 40%, white);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .help-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .template-variables {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .template-variables h4 {
            margin-bottom: 10px;
            color: #495057;
        }
        
        .variables-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .variable-tag {
            background: var(--primary-color);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            cursor: pointer;
        }
        
        .variable-tag:hover {
            filter: brightness(0.9);
        }
        
        .upload-actions {
            display: flex;
            gap: 10px;
        }
        
        .upload-actions .btn {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .color-preset {
            transition: all 0.3s ease;
        }
        
        .color-preset:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        
        .color-preset:active {
            transform: scale(1.05);
        }
        
        @media (max-width: 768px) {
            .form-group [style*="grid-template-columns"] {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)) !important;
            }
            
            .color-preset {
                padding: 12px !important;
            }
            
            .color-preset div {
                font-size: 10px !important;
            }
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <h1><i class="fas fa-cog"></i> Painel Administrativo</h1>
            <div class="user-info">
                <span>Bem-vindo, Admin</span>
                <a href="../dashboard" class="btn">
                    <i class="fas fa-upload"></i> Upload
                </a>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </div>
    </div>

    <div class="admin-nav">
        <div class="container">
            <ul class="nav-tabs">
                <li class="nav-tab <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>">
                    <a href="?tab=dashboard"><i class="fas fa-chart-bar"></i> Dashboard</a>
                </li>
                <li class="nav-tab <?php echo $activeTab === 'settings' ? 'active' : ''; ?>">
                    <a href="?tab=settings"><i class="fas fa-cog"></i> Configurações</a>
                </li>
                <li class="nav-tab <?php echo $activeTab === 'email' ? 'active' : ''; ?>">
                    <a href="?tab=email"><i class="fas fa-envelope"></i> Templates de Email</a>
                </li>
                <li class="nav-tab <?php echo $activeTab === 'users' ? 'active' : ''; ?>">
                    <a href="?tab=users"><i class="fas fa-users"></i> Usuários</a>
                </li>
                <li class="nav-tab <?php echo $activeTab === 'uploads' ? 'active' : ''; ?>">
                    <a href="?tab=uploads"><i class="fas fa-upload"></i> Uploads</a>
                </li>
                <li class="nav-tab <?php echo $activeTab === 'styling' ? 'active' : ''; ?>">
                    <a href="?tab=styling"><i class="fas fa-palette"></i> Estilização</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Dashboard Tab -->
        <div class="tab-content <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <div class="number"><?php echo $totalUsers; ?></div>
                    <div class="label">Usuários</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-upload"></i></div>
                    <div class="number"><?php echo $totalUploads; ?></div>
                    <div class="label">Uploads</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-file"></i></div>
                    <div class="number"><?php echo $totalFiles; ?></div>
                    <div class="label">Arquivos</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-download"></i></div>
                    <div class="number"><?php echo $totalDownloads; ?></div>
                    <div class="label">Downloads</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-hdd"></i></div>
                    <div class="number"><?php echo formatBytes($totalSize); ?></div>
                    <div class="label">Espaço Usado</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Uploads Recentes</h3>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Usuário</th>
                                <th>Arquivos</th>
                                <th>Downloads</th>
                                <th>Criado em</th>
                                <th>Expira em</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($allUploads, 0, 10) as $upload): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($upload['title']); ?></td>
                                    <td><?php echo htmlspecialchars($upload['username']); ?></td>
                                    <td><?php echo count($fileModel->getBySessionId($upload['id'])); ?></td>
                                    <td><?php echo $upload['download_count'] ?? 0; ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($upload['created_at'])); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($upload['expires_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Settings Tab -->
        <div class="tab-content <?php echo $activeTab === 'settings' ? 'active' : ''; ?>">
            <form method="POST">
                <input type="hidden" name="action" value="update_settings">
                
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-globe"></i> Configurações Gerais</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="site_name">Nome do Sistema</label>
                                <input type="text" id="site_name" name="settings[site_name]" value="<?php echo htmlspecialchars($systemSettings['site_name'] ?? 'Pix Transfer'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="site_url">URL do Sistema</label>
                                <input type="url" id="site_url" name="settings[site_url]" value="<?php echo htmlspecialchars($systemSettings['site_url'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="admin_email">Email do Administrador</label>
                                <input type="email" id="admin_email" name="settings[admin_email]" value="<?php echo htmlspecialchars($systemSettings['admin_email'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="default_expiration_days">Dias de Expiração Padrão</label>
                                <input type="number" id="default_expiration_days" name="settings[default_expiration_days]" value="<?php echo htmlspecialchars($systemSettings['default_expiration_days'] ?? 7); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="available_expiration_days">Opções de Expiração (separadas por vírgula)</label>
                            <input type="text" id="available_expiration_days" name="settings[available_expiration_days]" value="<?php echo implode(',', $systemSettings['available_expiration_days'] ?? [1,3,7,14,30]); ?>">
                            <div class="help-text">Dias disponíveis no dropdown de expiração</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-envelope"></i> Configurações SMTP</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="smtp_host">Servidor SMTP</label>
                                <input type="text" id="smtp_host" name="settings[smtp_host]" value="<?php echo htmlspecialchars($systemSettings['smtp_host'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_port">Porta SMTP</label>
                                <input type="number" id="smtp_port" name="settings[smtp_port]" value="<?php echo htmlspecialchars($systemSettings['smtp_port'] ?? 587); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_username">Usuário SMTP</label>
                                <input type="text" id="smtp_username" name="settings[smtp_username]" value="<?php echo htmlspecialchars($systemSettings['smtp_username'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_password">Senha SMTP</label>
                                <input type="password" id="smtp_password" name="settings[smtp_password]" value="<?php echo htmlspecialchars($systemSettings['smtp_password'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_encryption">Criptografia</label>
                                <select id="smtp_encryption" name="settings[smtp_encryption]">
                                    <option value="tls" <?php echo ($systemSettings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo ($systemSettings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                    <option value="" <?php echo ($systemSettings['smtp_encryption'] ?? '') === '' ? 'selected' : ''; ?>>Nenhuma</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_from_email">Email Remetente</label>
                                <input type="email" id="smtp_from_email" name="settings[smtp_from_email]" value="<?php echo htmlspecialchars($systemSettings['smtp_from_email'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_from_name">Nome do Remetente</label>
                                <input type="text" id="smtp_from_name" name="settings[smtp_from_name]" value="<?php echo htmlspecialchars($systemSettings['smtp_from_name'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-cloud"></i> Configurações Avançadas</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="settings[cloudflare_tunnel_enabled]" value="1" <?php echo ($systemSettings['cloudflare_tunnel_enabled'] ?? false) ? 'checked' : ''; ?>>
                                Habilitar Cloudflare Tunnel
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label for="cloudflare_tunnel_url">URL do Cloudflare Tunnel</label>
                            <input type="url" id="cloudflare_tunnel_url" name="settings[cloudflare_tunnel_url]" value="<?php echo htmlspecialchars($systemSettings['cloudflare_tunnel_url'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Salvar Configurações
                </button>
            </form>
        </div>

        <!-- Email Templates Tab -->
        <div class="tab-content <?php echo $activeTab === 'email' ? 'active' : ''; ?>">
            <form method="POST">
                <input type="hidden" name="action" value="update_email_templates">
                
                <!-- Upload Complete Template -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-envelope-open-text"></i> Template: Upload Completo</h3>
                    </div>
                    <div class="card-body">
                        <div class="template-variables">
                            <h4>Variáveis Disponíveis:</h4>
                            <div class="variables-list">
                                <span class="variable-tag" onclick="insertVariable('upload_complete_body', '{{SITE_NAME}}')">{SITE_NAME}</span>
                                <span class="variable-tag" onclick="insertVariable('upload_complete_body', '{{SENDER_NAME}}')">{SENDER_NAME}</span>
                                <span class="variable-tag" onclick="insertVariable('upload_complete_body', '{{TITLE}}')">{TITLE}</span>
                                <span class="variable-tag" onclick="insertVariable('upload_complete_body', '{{FILE_COUNT}}')">{FILE_COUNT}</span>
                                <span class="variable-tag" onclick="insertVariable('upload_complete_body', '{{TOTAL_SIZE}}')">{TOTAL_SIZE}</span>
                                <span class="variable-tag" onclick="insertVariable('upload_complete_body', '{{DOWNLOAD_URL}}')">{DOWNLOAD_URL}</span>
                                <span class="variable-tag" onclick="insertVariable('upload_complete_body', '{{SHORT_URL}}')">{SHORT_URL}</span>
                                <span class="variable-tag" onclick="insertVariable('upload_complete_body', '{{EXPIRES_AT}}')">{EXPIRES_AT}</span>
                                <span class="variable-tag" onclick="insertVariable('upload_complete_body', '{{CUSTOM_MESSAGE}}')">{CUSTOM_MESSAGE}</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="upload_complete_subject">Assunto</label>
                            <input type="text" id="upload_complete_subject" name="templates[upload_complete][subject]" value="<?php echo htmlspecialchars($settings->get('email_template_upload_complete_subject', 'Arquivos compartilhados - {TITLE}')); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="upload_complete_body">Corpo do Email</label>
                            <textarea id="upload_complete_body" name="templates[upload_complete][body]" rows="8" required><?php echo htmlspecialchars($settings->get('email_template_upload_complete_body', 'Olá!\n\n{SENDER_NAME} compartilhou {FILE_COUNT} arquivo(s) com você através do {SITE_NAME}.\n\nTítulo: {TITLE}\nTamanho total: {TOTAL_SIZE}\nExpira em: {EXPIRES_AT}\n\n{CUSTOM_MESSAGE}\n\nPara baixar os arquivos, clique no link abaixo:\n{DOWNLOAD_URL}\n\nOu use este link curto:\n{SHORT_URL}\n\nObrigado por usar o {SITE_NAME}!')); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Download Notification Template -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-envelope-open-text"></i> Template: Notificação de Download</h3>
                    </div>
                    <div class="card-body">
                        <div class="template-variables">
                            <h4>Variáveis Disponíveis:</h4>
                            <div class="variables-list">
                                <span class="variable-tag" onclick="insertVariable('download_notification_body', '{{SITE_NAME}}')">{SITE_NAME}</span>
                                <span class="variable-tag" onclick="insertVariable('download_notification_body', '{{TITLE}}')">{TITLE}</span>
                                <span class="variable-tag" onclick="insertVariable('download_notification_body', '{{DOWNLOAD_IP}}')">{DOWNLOAD_IP}</span>
                                <span class="variable-tag" onclick="insertVariable('download_notification_body', '{{DOWNLOAD_LOCATION}}')">{DOWNLOAD_LOCATION}</span>
                                <span class="variable-tag" onclick="insertVariable('download_notification_body', '{{DOWNLOAD_TIME}}')">{DOWNLOAD_TIME}</span>
                                <span class="variable-tag" onclick="insertVariable('download_notification_body', '{{TOTAL_DOWNLOADS}}')">{TOTAL_DOWNLOADS}</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="download_notification_subject">Assunto</label>
                            <input type="text" id="download_notification_subject" name="templates[download_notification][subject]" value="<?php echo htmlspecialchars($settings->get('email_template_download_notification_subject', 'Seus arquivos foram baixados - {TITLE}')); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="download_notification_body">Corpo do Email</label>
                            <textarea id="download_notification_body" name="templates[download_notification][body]" rows="6" required><?php echo htmlspecialchars($settings->get('email_template_download_notification_body', 'Olá!\n\nSeus arquivos "{TITLE}" foram baixados.\n\nDetalhes do download:\n- IP: {DOWNLOAD_IP}\n- Localização: {DOWNLOAD_LOCATION}\n- Data/Hora: {DOWNLOAD_TIME}\n- Total de downloads: {TOTAL_DOWNLOADS}\n\nObrigado por usar o {SITE_NAME}!')); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Expiration Warning Template -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-envelope-open-text"></i> Template: Aviso de Expiração</h3>
                    </div>
                    <div class="card-body">
                        <div class="template-variables">
                            <h4>Variáveis Disponíveis:</h4>
                            <div class="variables-list">
                                <span class="variable-tag" onclick="insertVariable('expiration_warning_body', '{{SITE_NAME}}')">{SITE_NAME}</span>
                                <span class="variable-tag" onclick="insertVariable('expiration_warning_body', '{{TITLE}}')">{TITLE}</span>
                                <span class="variable-tag" onclick="insertVariable('expiration_warning_body', '{{EXPIRES_AT}}')">{EXPIRES_AT}</span>
                                <span class="variable-tag" onclick="insertVariable('expiration_warning_body', '{{DOWNLOAD_URL}}')">{DOWNLOAD_URL}</span>
                                <span class="variable-tag" onclick="insertVariable('expiration_warning_body', '{{SHORT_URL}}')">{SHORT_URL}</span>
                                <span class="variable-tag" onclick="insertVariable('expiration_warning_body', '{{HOURS_REMAINING}}')">{HOURS_REMAINING}</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="expiration_warning_subject">Assunto</label>
                            <input type="text" id="expiration_warning_subject" name="templates[expiration_warning][subject]" value="<?php echo htmlspecialchars($settings->get('email_template_expiration_warning_subject', 'Seus arquivos expiram em breve - {TITLE}')); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="expiration_warning_body">Corpo do Email</label>
                            <textarea id="expiration_warning_body" name="templates[expiration_warning][body]" rows="6" required><?php echo htmlspecialchars($settings->get('email_template_expiration_warning_body', 'Olá!\n\nSeus arquivos "{TITLE}" expirarão em {HOURS_REMAINING} horas.\n\nData de expiração: {EXPIRES_AT}\n\nSe você ainda precisa destes arquivos, baixe-os antes da expiração:\n{DOWNLOAD_URL}\n\nOu use este link curto:\n{SHORT_URL}\n\nApós a expiração, os arquivos serão removidos permanentemente.\n\nObrigado por usar o {SITE_NAME}!')); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success btn-large">
                    <i class="fas fa-save"></i> Salvar Todos os Templates
                </button>
            </form>
        </div>

        <!-- Users Tab -->
        <div class="tab-content <?php echo $activeTab === 'users' ? 'active' : ''; ?>">
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3><i class="fas fa-users"></i> Gerenciar Usuários</h3>
                    <button class="btn btn-success" onclick="showAddUserModal()">
                        <i class="fas fa-plus"></i> Adicionar Usuário
                    </button>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome de usuário</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Criado em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsers as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="upload-actions">
                                            <button class="btn" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo $user['role']; ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja deletar este usuário?')">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Uploads Tab -->
        <div class="tab-content <?php echo $activeTab === 'uploads' ? 'active' : ''; ?>">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-upload"></i> Todos os Uploads</h3>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Usuário</th>
                                <th>Arquivos</th>
                                <th>Downloads</th>
                                <th>Tamanho</th>
                                <th>Criado em</th>
                                <th>Expira em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUploads as $upload): ?>
                                <?php 
                                $files = $fileModel->getBySessionId($upload['id']);
                                $totalSize = $fileModel->getTotalSizeBySession($upload['id']);
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($upload['title']); ?></td>
                                    <td><?php echo htmlspecialchars($upload['username']); ?></td>
                                    <td><?php echo count($files); ?></td>
                                    <td>
                                        <span class="badge"><?php echo $upload['download_count'] ?? 0; ?></span>
                                    </td>
                                    <td><?php echo formatBytes($totalSize); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($upload['created_at'])); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($upload['expires_at'])); ?></td>
                                    <td>
                                        <div class="upload-actions">
                                            <a href="../download/<?php echo $upload['token']; ?>" class="btn" target="_blank" title="Visualizar">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn" onclick="changeUploadExpiration(<?php echo $upload['id']; ?>, '<?php echo $upload['expires_at']; ?>')" title="Alterar Expiração">
                                                <i class="fas fa-clock"></i>
                                            </button>
                                            <button class="btn btn-danger" onclick="deleteUpload(<?php echo $upload['id']; ?>, '<?php echo htmlspecialchars($upload['title'], ENT_QUOTES); ?>')" title="Apagar Upload">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Styling Tab -->
        <div class="tab-content <?php echo $activeTab === 'styling' ? 'active' : ''; ?>">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-palette"></i> Configurações de Estilo</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_styling">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="primary_color">Cor Primária</label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <input type="color" id="primary_color_picker" value="<?php echo $settings->get('primary_color', '#4a7c59'); ?>" style="width: 50px; height: 40px; border: 2px solid #dee2e6; border-radius: 5px; cursor: pointer;">
                                    <input type="text" id="primary_color" name="primary_color" value="<?php echo $settings->get('primary_color', '#4a7c59'); ?>" pattern="^#[0-9A-Fa-f]{6}$" style="flex: 1;">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="secondary_color">Cor Secundária</label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <input type="color" id="secondary_color_picker" value="<?php echo $settings->get('secondary_color', '#6a9ba5'); ?>" style="width: 50px; height: 40px; border: 2px solid #dee2e6; border-radius: 5px; cursor: pointer;">
                                    <input type="text" id="secondary_color" name="secondary_color" value="<?php echo $settings->get('secondary_color', '#6a9ba5'); ?>" pattern="^#[0-9A-Fa-f]{6}$" style="flex: 1;">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="background_color">Cor de Fundo</label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <input type="color" id="background_color_picker" value="<?php echo $settings->get('background_color', '#f7fcf5'); ?>" style="width: 50px; height: 40px; border: 2px solid #dee2e6; border-radius: 5px; cursor: pointer;">
                                    <input type="text" id="background_color" name="background_color" value="<?php echo $settings->get('background_color', '#f7fcf5'); ?>" pattern="^#[0-9A-Fa-f]{6}$" style="flex: 1;">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="text_color">Cor do Texto</label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <input type="color" id="text_color_picker" value="<?php echo $settings->get('text_color', '#333333'); ?>" style="width: 50px; height: 40px; border: 2px solid #dee2e6; border-radius: 5px; cursor: pointer;">
                                    <input type="text" id="text_color" name="text_color" value="<?php echo $settings->get('text_color', '#333333'); ?>" pattern="^#[0-9A-Fa-f]{6}$" style="flex: 1;">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="success_color">Cor de Sucesso</label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <input type="color" id="success_color_picker" value="<?php echo $settings->get('success_color', '#28a745'); ?>" style="width: 50px; height: 40px; border: 2px solid #dee2e6; border-radius: 5px; cursor: pointer;">
                                    <input type="text" id="success_color" name="success_color" value="<?php echo $settings->get('success_color', '#28a745'); ?>" pattern="^#[0-9A-Fa-f]{6}$" style="flex: 1;">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="error_color">Cor de Erro</label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <input type="color" id="error_color_picker" value="<?php echo $settings->get('error_color', '#dc3545'); ?>" style="width: 50px; height: 40px; border: 2px solid #dee2e6; border-radius: 5px; cursor: pointer;">
                                    <input type="text" id="error_color" name="error_color" value="<?php echo $settings->get('error_color', '#dc3545'); ?>" pattern="^#[0-9A-Fa-f]{6}$" style="flex: 1;">
                                </div>
                            </div>
                        </div>
                        
                        <div style="width: 100%; height: 60px; border-radius: 8px; margin: 20px 0; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);" id="colorPreview">
                            Pré-visualização das Cores
                        </div>
                        
                        <div class="form-group">
                            <label>Pré-definições de Cores Harmônicas</label>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-top: 15px;">
                                <!-- Verde Natural (Atual) -->
                                <div class="color-preset" onclick="applyColorPreset('#4a7c59', '#6a9ba5', '#f7fcf5', '#333333', '#28a745', '#dc3545')" style="cursor: pointer; padding: 15px; border-radius: 10px; background: linear-gradient(135deg, #4a7c59 0%, #6a9ba5 100%); color: white; text-align: center; transition: transform 0.3s ease;">
                                    <div style="font-weight: 600; margin-bottom: 5px;">Verde Natural</div>
                                    <div style="font-size: 11px; opacity: 0.9;">Calmo & Profissional</div>
                                </div>
                                
                                <!-- Azul Oceânico -->
                                <div class="color-preset" onclick="applyColorPreset('#2563eb', '#0ea5e9', '#f0f9ff', '#1e293b', '#10b981', '#ef4444')" style="cursor: pointer; padding: 15px; border-radius: 10px; background: linear-gradient(135deg, #2563eb 0%, #0ea5e9 100%); color: white; text-align: center; transition: transform 0.3s ease;">
                                    <div style="font-weight: 600; margin-bottom: 5px;">Azul Oceânico</div>
                                    <div style="font-size: 11px; opacity: 0.9;">Confiável & Moderno</div>
                                </div>
                                
                                <!-- Púrpura Elegante -->
                                <div class="color-preset" onclick="applyColorPreset('#7c3aed', '#a855f7', '#faf5ff', '#374151', '#059669', '#f87171')" style="cursor: pointer; padding: 15px; border-radius: 10px; background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); color: white; text-align: center; transition: transform 0.3s ease;">
                                    <div style="font-weight: 600; margin-bottom: 5px;">Púrpura Elegante</div>
                                    <div style="font-size: 11px; opacity: 0.9;">Criativo & Sofisticado</div>
                                </div>
                                
                                <!-- Laranja Energético -->
                                <div class="color-preset" onclick="applyColorPreset('#ea580c', '#f97316', '#fff7ed', '#292524', '#16a34a', '#dc2626')" style="cursor: pointer; padding: 15px; border-radius: 10px; background: linear-gradient(135deg, #ea580c 0%, #f97316 100%); color: white; text-align: center; transition: transform 0.3s ease;">
                                    <div style="font-weight: 600; margin-bottom: 5px;">Laranja Energético</div>
                                    <div style="font-size: 11px; opacity: 0.9;">Vibrante & Dinâmico</div>
                                </div>
                                
                                <!-- Rosa Moderno -->
                                <div class="color-preset" onclick="applyColorPreset('#db2777', '#ec4899', '#fdf2f8', '#374151', '#059669', '#ef4444')" style="cursor: pointer; padding: 15px; border-radius: 10px; background: linear-gradient(135deg, #db2777 0%, #ec4899 100%); color: white; text-align: center; transition: transform 0.3s ease;">
                                    <div style="font-weight: 600; margin-bottom: 5px;">Rosa Moderno</div>
                                    <div style="font-size: 11px; opacity: 0.9;">Criativo & Inovador</div>
                                </div>
                                
                                <!-- Cinza Executivo -->
                                <div class="color-preset" onclick="applyColorPreset('#475569', '#64748b', '#f8fafc', '#1e293b', '#22c55e', '#ef4444')" style="cursor: pointer; padding: 15px; border-radius: 10px; background: linear-gradient(135deg, #475569 0%, #64748b 100%); color: white; text-align: center; transition: transform 0.3s ease;">
                                    <div style="font-weight: 600; margin-bottom: 5px;">Cinza Executivo</div>
                                    <div style="font-size: 11px; opacity: 0.9;">Sóbrio & Corporativo</div>
                                </div>
                                
                                <!-- Turquesa Tropical -->
                                <div class="color-preset" onclick="applyColorPreset('#0891b2', '#06b6d4', '#ecfeff', '#164e63', '#10b981', '#f43f5e')" style="cursor: pointer; padding: 15px; border-radius: 10px; background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%); color: white; text-align: center; transition: transform 0.3s ease;">
                                    <div style="font-weight: 600; margin-bottom: 5px;">Turquesa Tropical</div>
                                    <div style="font-size: 11px; opacity: 0.9;">Fresco & Tropical</div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Salvar Estilo
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-image"></i> Logo e Favicon do Sistema</h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_logo">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="logo">Logo do Sistema (PNG ou JPG, máx. 2MB)</label>
                                <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/jpg" style="margin-bottom: 15px;">
                                
                                <div style="max-width: 200px; max-height: 100px; border: 2px dashed #dee2e6; border-radius: 8px; padding: 10px; text-align: center;" id="logoPreview">
                                    <?php 
                                    $currentLogo = $settings->get('site_logo', 'src/img/logo.png');
                                    if ($currentLogo && file_exists($currentLogo)): 
                                    ?>
                                        <img src="<?php echo htmlspecialchars($currentLogo); ?>" alt="Logo atual" style="max-width: 100%; max-height: 80px; object-fit: contain;">
                                        <p style="margin-top: 10px; font-size: 12px; color: #666;">Logo atual</p>
                                    <?php else: ?>
                                        <p style="color: #666;">Nenhum logo configurado</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="favicon">Favicon (PNG ou ICO, máx. 1MB)</label>
                                <input type="file" id="favicon" name="favicon" accept="image/png,image/x-icon,image/vnd.microsoft.icon" style="margin-bottom: 15px;">
                                
                                <div style="max-width: 100px; max-height: 100px; border: 2px dashed #dee2e6; border-radius: 8px; padding: 10px; text-align: center;" id="faviconPreview">
                                    <?php 
                                    $currentFavicon = $settings->get('site_favicon', 'src/img/favicon.png');
                                    if ($currentFavicon && file_exists($currentFavicon)): 
                                    ?>
                                        <img src="<?php echo htmlspecialchars($currentFavicon); ?>" alt="Favicon atual" style="max-width: 32px; max-height: 32px; object-fit: contain;">
                                        <p style="margin-top: 10px; font-size: 12px; color: #666;">Favicon atual</p>
                                    <?php else: ?>
                                        <p style="color: #666; font-size: 12px;">Nenhum favicon configurado</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Salvar Logo e Favicon
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- User Edit Modal -->
    <div id="userModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; width: 400px;">
            <h3>Editar Usuário</h3>
            <form method="POST" id="editUserForm">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="editUserId">
                
                <div class="form-group">
                    <label for="editUsername">Nome de usuário</label>
                    <input type="text" id="editUsername" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="editEmail">Email</label>
                    <input type="email" id="editEmail" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="editRole">Role</label>
                    <select id="editRole" name="role">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="closeUserModal()">Cancelar</button>
                    <button type="submit" class="btn btn-success">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; width: 400px;">
            <h3>Adicionar Usuário</h3>
            <form method="POST" id="addUserForm">
                <input type="hidden" name="action" value="add_user">
                
                <div class="form-group">
                    <label for="addUsername">Nome de usuário</label>
                    <input type="text" id="addUsername" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="addEmail">Email</label>
                    <input type="email" id="addEmail" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="addPassword">Senha</label>
                    <input type="password" id="addPassword" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="addRole">Role</label>
                    <select id="addRole" name="role">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="closeAddUserModal()">Cancelar</button>
                    <button type="submit" class="btn btn-success">Adicionar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function insertVariable(textareaId, variable) {
            const textarea = document.getElementById(textareaId);
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            
            textarea.value = text.substring(0, start) + variable + text.substring(end);
            textarea.selectionStart = textarea.selectionEnd = start + variable.length;
            textarea.focus();
        }

        function editUser(id, username, email, role) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editUsername').value = username;
            document.getElementById('editEmail').value = email;
            document.getElementById('editRole').value = role;
            document.getElementById('userModal').style.display = 'block';
        }

        function closeUserModal() {
            document.getElementById('userModal').style.display = 'none';
        }

        function showAddUserModal() {
            document.getElementById('addUserModal').style.display = 'block';
        }

        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
            document.getElementById('addUserForm').reset();
        }

        function changeUploadExpiration(uploadId, currentExpiration) {
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
                    <form id="adminExpirationForm">
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-color);">
                                Nova Data e Hora de Expiração
                            </label>
                            <input type="datetime-local" id="adminNewExpiration" value="${formattedDate}" 
                                   style="width: 100%; padding: 12px; border: 2px solid #dee2e6; border-radius: 8px; font-size: 14px;"
                                   min="${new Date().toISOString().slice(0, 16)}">
                        </div>
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button type="button" onclick="closeAdminModal()" 
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
            
            window.closeAdminModal = function() {
                document.body.removeChild(modal);
                delete window.closeAdminModal;
            };
            
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeAdminModal();
            });
            
            document.getElementById('adminExpirationForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const newExpiration = document.getElementById('adminNewExpiration').value;
                
                const formData = new FormData();
                formData.append('action', 'update_expiration');
                formData.append('upload_id', uploadId);
                formData.append('new_expiration', newExpiration);
                
                fetch('/admin', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        alert('Expiração atualizada com sucesso!');
                        closeAdminModal();
                        window.location.reload();
                    } else {
                        throw new Error('Erro na resposta do servidor');
                    }
                })
                .catch(error => {
                    console.error('Erro ao atualizar expiração:', error);
                    alert('Erro ao atualizar expiração: ' + error.message);
                    closeAdminModal();
                });
            });
        }

        function deleteUpload(uploadId, title) {
            if (confirm(`Tem certeza que deseja apagar o upload "${title}"? Esta ação não pode ser desfeita.`)) {
                const formData = new FormData();
                formData.append('action', 'delete_upload');
                formData.append('upload_id', uploadId);
                
                fetch('/admin', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        alert('Upload deletado com sucesso!');
                        window.location.reload();
                    } else {
                        throw new Error('Erro na resposta do servidor');
                    }
                })
                .catch(error => {
                    console.error('Erro ao deletar upload:', error);
                    alert('Erro ao deletar upload: ' + error.message);
                });
            }
        }

        // Close modal when clicking outside
        document.getElementById('userModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUserModal();
            }
        });

        document.getElementById('addUserModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddUserModal();
            }
        });
        
        // Color picker synchronization
        function setupColorPicker(pickerId, textId) {
            const picker = document.getElementById(pickerId);
            const text = document.getElementById(textId);
            
            if (picker && text) {
                picker.addEventListener('change', function() {
                    text.value = this.value;
                    updateColorPreview();
                });
                
                text.addEventListener('input', function() {
                    if (this.value.match(/^#[0-9A-Fa-f]{6}$/)) {
                        picker.value = this.value;
                        updateColorPreview();
                    }
                });
            }
        }
        
        // Setup all color pickers
        document.addEventListener('DOMContentLoaded', function() {
            setupColorPicker('primary_color_picker', 'primary_color');
            setupColorPicker('secondary_color_picker', 'secondary_color');
            setupColorPicker('background_color_picker', 'background_color');
            setupColorPicker('text_color_picker', 'text_color');
            setupColorPicker('success_color_picker', 'success_color');
            setupColorPicker('error_color_picker', 'error_color');
            
            // Initial color preview update
            updateColorPreview();
            
            // File input preview
            const logoInput = document.getElementById('logo');
            if (logoInput) {
                logoInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const preview = document.getElementById('logoPreview');
                            preview.innerHTML = `
                                <img src="${e.target.result}" alt="Pré-visualização do logo" style="max-width: 100%; max-height: 80px; object-fit: contain;">
                                <p style="margin-top: 10px; font-size: 12px; color: #666;">Pré-visualização</p>
                            `;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // Favicon input preview
            const faviconInput = document.getElementById('favicon');
            if (faviconInput) {
                faviconInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const preview = document.getElementById('faviconPreview');
                            preview.innerHTML = `
                                <img src="${e.target.result}" alt="Pré-visualização do favicon" style="max-width: 32px; max-height: 32px; object-fit: contain;">
                                <p style="margin-top: 10px; font-size: 12px; color: #666;">Pré-visualização</p>
                            `;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        });
        
        // Update color preview
        function updateColorPreview() {
            const preview = document.getElementById('colorPreview');
            const primaryColorInput = document.getElementById('primary_color');
            const secondaryColorInput = document.getElementById('secondary_color');
            
            if (preview && primaryColorInput && secondaryColorInput) {
                const primaryColor = primaryColorInput.value;
                const secondaryColor = secondaryColorInput.value;
                
                preview.style.background = `linear-gradient(135deg, ${primaryColor} 0%, ${secondaryColor} 100%)`;
            }
        }
        
        // Apply color preset
        function applyColorPreset(primary, secondary, background, text, success, error) {
            // Update color pickers
            document.getElementById('primary_color').value = primary;
            document.getElementById('primary_color_picker').value = primary;
            
            document.getElementById('secondary_color').value = secondary;
            document.getElementById('secondary_color_picker').value = secondary;
            
            document.getElementById('background_color').value = background;
            document.getElementById('background_color_picker').value = background;
            
            document.getElementById('text_color').value = text;
            document.getElementById('text_color_picker').value = text;
            
            document.getElementById('success_color').value = success;
            document.getElementById('success_color_picker').value = success;
            
            document.getElementById('error_color').value = error;
            document.getElementById('error_color_picker').value = error;
            
            // Update preview
            updateColorPreview();
            
            // Add visual feedback with ripple effect
            const clickedPreset = event.target.closest('.color-preset');
            
            // Reset all presets
            document.querySelectorAll('.color-preset').forEach(preset => {
                preset.style.transform = 'scale(1)';
                preset.style.boxShadow = '';
            });
            
            // Highlight selected preset
            clickedPreset.style.transform = 'scale(1.08)';
            clickedPreset.style.boxShadow = '0 12px 30px rgba(0,0,0,0.3)';
            
            // Reset after animation
            setTimeout(() => {
                clickedPreset.style.transform = 'scale(1)';
                clickedPreset.style.boxShadow = '';
            }, 300);
            
            // Show success message
            const preview = document.getElementById('colorPreview');
            const originalText = preview.textContent;
            preview.textContent = '✓ Cores aplicadas com sucesso!';
            preview.style.background = `linear-gradient(135deg, ${primary} 0%, ${secondary} 100%)`;
            
            setTimeout(() => {
                preview.textContent = originalText;
            }, 2000);
        }
    </script>
</body>
</html>