<?php
session_start();
require_once 'models/User.php';
require_once 'models/UploadSession.php';
require_once 'models/File.php';

// Proteção da página: Apenas usuários logados e com role 'admin'
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$userModel = new User();
if (!$userModel->isAdmin($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$uploadSessionModel = new UploadSession();
$fileModel = new File();
$allUsers = $userModel->getAllUsers();
$allUploads = $uploadSessionModel->getAll();
$selected_user_id = $_GET['user_id'] ?? null;
$user_uploads = [];

if ($selected_user_id) {
    $user_uploads = $uploadSessionModel->getByUserId($selected_user_id);
}

// Estatísticas do sistema
$totalUsers = count($allUsers);
$totalUploads = count($allUploads);
$totalFiles = 0;
$totalSize = 0;

foreach ($allUploads as $upload) {
    $files = $fileModel->getBySessionId($upload['id']);
    $totalFiles += count($files);
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - Upload System</title>
    <link rel="icon" type="image/png" href="img/favicon.png">
    <style>
        @import url('https://fonts.googleapis.com/css?family=Titillium+Web:400,600,700');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Titillium Web', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f7fcf5;
            color: #333;
            min-height: 100vh;
        }

        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1600px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo img {
            height: 50px;
            width: auto;
        }

        .logo h1 {
            color: #4CAF50;
            font-size: 24px;
            font-weight: 700;
        }

        .admin-badge {
            background: linear-gradient(135deg, #FF9800, #F57C00);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-info span {
            color: #666;
            font-weight: 600;
        }

        .user-info a {
            background: linear-gradient(135deg, #FFCDD2, #EF9A9A);
            color: #C62828;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid #FFCDD2;
            margin-left: 8px;
        }

        .user-info a:hover {
            background: linear-gradient(135deg, #EF9A9A, #E57373);
            transform: translateY(-1px);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .page-title {
            color: #333;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-title::before {
            content: "🛠️";
            font-size: 36px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 5px solid;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.users {
            border-left-color: #4CAF50;
        }

        .stat-card.uploads {
            border-left-color: #2196F3;
        }

        .stat-card.files {
            border-left-color: #FF9800;
        }

        .stat-card.storage {
            border-left-color: #9C27B0;
        }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            font-weight: 600;
        }

        .admin-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .section-title {
            color: #333;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            font-family: 'Titillium Web', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #45a049, #3d8b40);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #FFCDD2, #EF9A9A);
            color: #C62828;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #EF9A9A, #E57373);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 154, 154, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #78909C, #607D8B);
            color: white;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #607D8B, #546E7A);
            transform: translateY(-2px);
        }

        .uploads-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .uploads-table th,
        .uploads-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .uploads-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .uploads-table tr:hover {
            background: #f8f9fa;
        }

        .upload-title {
            font-weight: 600;
            color: #333;
        }

        .upload-date {
            color: #666;
            font-size: 14px;
        }

        .upload-expires {
            font-size: 14px;
        }

        .expires-soon {
            color: #FF9800;
            font-weight: 600;
        }

        .expired {
            color: #EF5350;
            font-weight: 600;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }

        .status-expired {
            background: rgba(239, 83, 80, 0.1);
            color: #EF5350;
        }

        .status-admin {
            background: rgba(255, 152, 0, 0.1);
            color: #FF9800;
        }

        .status-user {
            background: rgba(33, 150, 243, 0.1);
            color: #2196F3;
        }

        /* Modal de uploads do usuário */
        .user-uploads-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .user-uploads-modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-uploads-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 1000px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            transform: scale(0.7);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .user-uploads-modal.show .user-uploads-content {
            transform: scale(1);
            opacity: 1;
        }

        .user-uploads-header {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 25px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-uploads-title {
            font-size: 22px;
            font-weight: 700;
            margin: 0;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 24px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .user-uploads-body {
            padding: 25px;
        }

        .upload-item-modal {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .upload-item-modal:hover {
            border-color: #4CAF50;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.1);
        }

        .upload-header-modal {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .upload-info-modal {
            flex: 1;
        }

        .upload-title-modal {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .upload-meta-modal {
            font-size: 14px;
            color: #666;
            margin-bottom: 3px;
        }

        .upload-status-modal {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .upload-actions-modal {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-modal {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-copy {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
        }

        .btn-copy:hover {
            background: linear-gradient(135deg, #1976D2, #1565C0);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.3);
        }

        .btn-expiry {
            background: linear-gradient(135deg, #FF9800, #F57C00);
            color: white;
        }

        .btn-expiry:hover {
            background: linear-gradient(135deg, #F57C00, #EF6C00);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
        }

        .btn-view {
            background: linear-gradient(135deg, #9C27B0, #7B1FA2);
            color: white;
        }

        .btn-view:hover {
            background: linear-gradient(135deg, #7B1FA2, #6A1B9A);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(156, 39, 176, 0.3);
        }

        .btn-delete-modal {
            background: linear-gradient(135deg, #FFCDD2, #EF9A9A);
            color: #C62828;
            border: 1px solid #FFCDD2;
        }

        .btn-delete-modal:hover {
            background: linear-gradient(135deg, #EF9A9A, #E57373);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 154, 154, 0.3);
        }

        .empty-uploads {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-uploads-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.6;
        }

        @media (max-width: 768px) {
            .user-uploads-content {
                width: 95%;
                margin: 20px;
            }

            .upload-header-modal {
                flex-direction: column;
                gap: 15px;
            }

            .upload-actions-modal {
                width: 100%;
                justify-content: center;
            }

            .btn-modal {
                flex: 1;
                justify-content: center;
            }
        }

        /* Modal styles from dashboard */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            transform: scale(0.8);
            transition: all 0.3s ease;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-overlay.show .modal-content {
            transform: scale(1);
        }

        .modal-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-icon {
            font-size: 32px;
            margin-right: 15px;
            color: #ffc107;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 25px;
        }

        .modal-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 120px;
        }

        .modal-btn-primary {
            background: #4CAF50;
            color: white;
        }

        .modal-btn-primary:hover {
            background: #45a049;
            transform: translateY(-2px);
        }

        .modal-btn-secondary {
            background: #6c757d;
            color: white;
        }

        .modal-btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .current-expiration {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            color: #666;
            font-weight: 500;
        }

        /* Confirm modal styles */
        .confirm-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1500;
            backdrop-filter: blur(5px);
        }

        .confirm-modal.show {
            display: flex;
        }

        .confirm-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            transform: scale(0.9);
            transition: all 0.3s ease;
        }

        .confirm-modal.show .confirm-content {
            transform: scale(1);
        }

        .confirm-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .confirm-icon {
            font-size: 32px;
            margin-right: 15px;
            color: #ff9800;
        }

        .confirm-title {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .confirm-message {
            color: #666;
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        .confirm-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        .confirm-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 100px;
        }

        .confirm-btn-danger {
            background: #EF5350;
            color: white;
        }

        .confirm-btn-danger:hover {
            background: #E53935;
            transform: translateY(-2px);
        }

        .confirm-btn-cancel {
            background: #6c757d;
            color: white;
        }

        .confirm-btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .nav-links {
            display: flex;
            gap: 10px;
            margin-left: auto;
            margin-right: 20px;
        }

        .nav-link {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .nav-link.dashboard {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }

        .nav-link.dashboard:hover {
            background: rgba(76, 175, 80, 0.2);
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .container {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .uploads-table {
                font-size: 14px;
            }

            .uploads-table th,
            .uploads-table td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <img src="/src/img/logo.png" alt="Logo">
                <h1>Pix Transfer</h1>
            </div>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link dashboard">📊 Dashboard</a>
            </div>
            <div class="user-info">
                <span>
                    <?php echo htmlspecialchars($_SESSION['username']); ?>
                    <span class="admin-badge">ADMIN</span>
                </span>
                <a href="logout.php">Sair</a>
            </div>
        </div>
    </div>

    <div class="container">
        <h1 class="page-title">Painel Administrativo</h1>

        <!-- Estatísticas do Sistema -->
        <div class="stats-grid">
            <div class="stat-card users">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo $totalUsers; ?></div>
                <div class="stat-label">Usuários Registrados</div>
            </div>
            <div class="stat-card uploads">
                <div class="stat-icon">📤</div>
                <div class="stat-value"><?php echo $totalUploads; ?></div>
                <div class="stat-label">Sessões de Upload</div>
            </div>
            <div class="stat-card files">
                <div class="stat-icon">📁</div>
                <div class="stat-value"><?php echo $totalFiles; ?></div>
                <div class="stat-label">Arquivos Totais</div>
            </div>
            <div class="stat-card storage">
                <div class="stat-icon">💾</div>
                <div class="stat-value"><?php echo formatBytes($totalSize); ?></div>
                <div class="stat-label">Armazenamento Usado</div>
            </div>
        </div>

        <!-- Seção de Adicionar Usuário -->
        <div class="admin-section">
            <h2 class="section-title">👤 Adicionar Novo Usuário</h2>
            <form action="admin_handler.php" method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="username">Nome de Usuário</label>
                        <input type="text" id="username" name="username" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-input" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="password">Senha</label>
                        <input type="password" id="password" name="password" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="role">Função</label>
                        <select id="role" name="role" class="form-select" required>
                            <option value="user">Usuário</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    ➕ Adicionar Usuário
                </button>
            </form>
        </div>

        <!-- Seção de Gerenciar Usuários -->
        <div class="admin-section">
            <h2 class="section-title">👥 Gerenciar Usuários</h2>
            
            <?php if (!empty($allUsers)): ?>
                <table class="uploads-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Função</th>
                            <th>Data de Criação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUsers as $user): ?>
                            <tr>
                                <td><strong><?php echo $user['id']; ?></strong></td>
                                <td>
                                    <div class="upload-title">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $user['role'] === 'admin' ? 'status-admin' : 'status-user'; ?>">
                                        <?php echo $user['role'] === 'admin' ? 'ADMIN' : 'USUÁRIO'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="upload-date">
                                        <?php echo isset($user['created_at']) ? date('d/m/Y H:i', strtotime($user['created_at'])) : 'N/A'; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $protectedEmails = ['victor@pixfilmes.com'];
                                    $isProtectedUser = in_array($user['email'], $protectedEmails);
                                    $isSelf = $user['id'] == $_SESSION['user_id'];
                                    ?>
                                    
                                    <?php if (!$isProtectedUser): ?>
                                        <button class="btn btn-secondary" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo $user['role']; ?>')" style="margin-right: 5px;">
                                            ✏️ Editar
                                        </button>
                                    <?php else: ?>
                                        <span class="status-badge status-admin" style="margin-right: 5px;">🔒 PROTEGIDO</span>
                                    <?php endif; ?>
                                    
                                    <?php if (!$isSelf && !$isProtectedUser): ?>
                                        <button class="btn btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                            🗑️ Excluir
                                        </button>
                                    <?php elseif ($isSelf): ?>
                                        <span class="status-badge status-user">👤 VOCÊ</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">👤</div>
                    <h3>Nenhum usuário encontrado</h3>
                    <p>Ainda não há usuários no sistema.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Seção de Todos os Uploads -->
        <div class="admin-section">
            <h2 class="section-title">📋 Todos os Uploads do Sistema</h2>
            
            <?php if (!empty($allUploads)): ?>
                <table class="uploads-table">
                    <thead>
                        <tr>
                            <th>Usuário</th>
                            <th>Título</th>
                            <th>Arquivos</th>
                            <th>Data de Upload</th>
                            <th>Expira em</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUploads as $upload): 
                            $files = $fileModel->getBySessionId($upload['id']);
                            $fileCount = count($files);
                            $expiresAt = new DateTime($upload['expires_at']);
                            $now = new DateTime();
                            $isExpired = $expiresAt <= $now;
                            $isExpiringSoon = !$isExpired && $expiresAt <= (new DateTime())->add(new DateInterval('P1D'));
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($upload['username']); ?></strong>
                                </td>
                                <td>
                                    <div class="upload-title">
                                        <?php echo htmlspecialchars($upload['title'] ?: 'Sem título'); ?>
                                    </div>
                                    <?php if ($upload['recipient_email']): ?>
                                        <div style="font-size: 12px; color: #666;">
                                            Para: <?php echo htmlspecialchars($upload['recipient_email']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo $fileCount; ?></strong> arquivo<?php echo $fileCount != 1 ? 's' : ''; ?>
                                </td>
                                <td>
                                    <div class="upload-date">
                                        <?php echo date('d/m/Y H:i', strtotime($upload['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="upload-expires <?php echo $isExpired ? 'expired' : ($isExpiringSoon ? 'expires-soon' : ''); ?>">
                                        <?php echo date('d/m/Y H:i', strtotime($upload['expires_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $isExpired ? 'status-expired' : 'status-active'; ?>">
                                        <?php echo $isExpired ? 'Expirado' : 'Ativo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <form action="admin_handler.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_upload">
                                        <input type="hidden" name="upload_id" value="<?php echo $upload['id']; ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir este upload?')">
                                            🗑️ Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>Nenhum upload encontrado</h3>
                    <p>Ainda não há uploads no sistema.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Seção de Gerenciar Uploads por Usuário -->
        <div class="admin-section">
            <h2 class="section-title">🔍 Uploads por Usuário</h2>
            <form method="GET" action="admin.php">
                <div class="form-group">
                    <label class="form-label" for="user_select">Selecionar Usuário</label>
                    <select name="user_id" id="user_select" class="form-select" onchange="showUserUploads(this.value)">
                        <option value="">Selecione um usuário para ver seus uploads</option>
                        <?php foreach ($allUsers as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo ($selected_user_id == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                <?php if ($user['role'] === 'admin'): ?> - ADMIN<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Uploads do Usuário -->
    <div id="userUploadsModal" class="user-uploads-modal">
        <div class="user-uploads-content">
            <div class="user-uploads-header">
                <h2 class="user-uploads-title" id="modalTitle">📤 Uploads do Usuário</h2>
                <button class="modal-close" onclick="closeUserUploadsModal()">&times;</button>
            </div>
            <div class="user-uploads-body" id="modalBody">
                <!-- Conteúdo será carregado dinamicamente -->
            </div>
        </div>
    </div>

    <!-- Modal de Alteração de Expiração -->
    <div id="expirationModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">⏰</div>
                <h2 class="modal-title">Alterar Data de Expiração</h2>
            </div>
            
            <div class="form-row">
                <label class="form-label">Data de expiração atual:</label>
                <div id="currentExpiration" class="current-expiration"></div>
            </div>
            
            <div class="form-row">
                <label for="newExpiration" class="form-label">Nova data de expiração:</label>
                <input type="datetime-local" id="newExpiration" class="form-input" required>
                <div class="form-hint">Selecione uma data e hora no futuro</div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeExpirationModal()">
                    Cancelar
                </button>
                <button type="button" class="modal-btn modal-btn-primary" onclick="confirmExpirationChange()">
                    Alterar Data
                </button>
            </div>
        </div>
    </div>

    <script>
        // Função para mostrar uploads do usuário
        async function showUserUploads(userId) {
            if (!userId || userId === '' || userId === null || userId === undefined) {
                return;
            }

            const modal = document.getElementById('userUploadsModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');

            // Buscar dados do usuário
            try {
                const url = `./get_user_uploads.php?user_id=${encodeURIComponent(userId)}`;
                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    modalTitle.textContent = `📤 Uploads de ${data.user.username}`;
                    
                    if (data.uploads.length === 0) {
                        modalBody.innerHTML = `
                            <div class="empty-uploads">
                                <div class="empty-uploads-icon">📭</div>
                                <h3>Nenhum upload encontrado</h3>
                                <p>Este usuário ainda não fez nenhum upload.</p>
                            </div>
                        `;
                    } else {
                        let html = '';
                        data.uploads.forEach(upload => {
                            const isExpired = new Date(upload.expires_at) <= new Date();
                            const statusClass = isExpired ? 'status-expired' : 'status-active';
                            const statusText = isExpired ? 'Expirado' : 'Ativo';
                            
                            html += `
                                <div class="upload-item-modal">
                                    <div class="upload-header-modal">
                                        <div class="upload-info-modal">
                                            <div class="upload-title-modal">${upload.title || 'Sem título'}</div>
                                            <div class="upload-meta-modal">📁 ${upload.file_count} arquivo(s)</div>
                                            <div class="upload-meta-modal">📅 Criado em: ${formatDate(upload.created_at)}</div>
                                            <div class="upload-meta-modal">⏰ Expira em: ${formatDate(upload.expires_at)}</div>
                                            ${upload.recipient_email ? `<div class="upload-meta-modal">📧 Para: ${upload.recipient_email}</div>` : ''}
                                            <div class="upload-status-modal">
                                                <span class="status-badge ${statusClass}">${statusText}</span>
                                            </div>
                                        </div>
                                        <div class="upload-actions-modal">
                                            <button class="btn-modal btn-copy" onclick="copyLink('${upload.short_code || upload.token}', ${upload.short_code ? 'true' : 'false'})">
                                                📋 Copiar Link
                                            </button>
                                            <button class="btn-modal btn-expiry" onclick="changeUploadExpiration(${upload.id}, '${upload.expires_at}')">
                                                ⏰ Alterar Exp.
                                            </button>
                                            <a href="download?token=${upload.token}" target="_blank" class="btn-modal btn-view">
                                                👁️ Ver
                                            </a>
                                            <button class="btn-modal btn-delete-modal" onclick="deleteUploadFromModal(${upload.id}, '${upload.title || 'upload'}')">
                                                🗑️ Excluir
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        modalBody.innerHTML = html;
                    }

                    // Mostrar modal
                    modal.classList.add('show');
                } else {
                    alert('Erro ao carregar uploads: ' + data.message);
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao carregar uploads do usuário');
            }
        }

        // Função para fechar o modal
        function closeUserUploadsModal() {
            const modal = document.getElementById('userUploadsModal');
            modal.classList.remove('show');
        }

        // Função para copiar link
        async function copyLink(token, isShort) {
            const baseUrl = window.location.origin;
            const link = isShort === 'true' ? `${baseUrl}/s/${token}` : `${baseUrl}/download/${token}`;
            
            try {
                await navigator.clipboard.writeText(link);
                showToast('Link copiado para a área de transferência!', 'success');
            } catch (err) {
                // Fallback para navegadores mais antigos
                const textArea = document.createElement('textarea');
                textArea.value = link;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showToast('Link copiado para a área de transferência!', 'success');
            }
        }

        // Variável global para armazenar o ID do upload atual
        let currentUploadId = null;

        // Função para alterar expiração do upload - usando modal do dashboard
        function changeUploadExpiration(uploadId, currentExpiration) {
            currentUploadId = uploadId;
            
            // Convert current date to datetime-local input format
            const currentDate = new Date(currentExpiration);
            const formattedDate = currentDate.toISOString().slice(0, 16);
            
            // Format current date for display
            const displayDate = currentDate.toLocaleString('pt-BR', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            // Fill modal
            document.getElementById('currentExpiration').textContent = displayDate;
            document.getElementById('newExpiration').value = formattedDate;
            
            // Show modal
            const modal = document.getElementById('expirationModal');
            modal.classList.add('show');
            
            // Focus on input
            setTimeout(() => {
                document.getElementById('newExpiration').focus();
            }, 300);
        }

        // Função para fechar modal de expiração
        function closeExpirationModal() {
            const modal = document.getElementById('expirationModal');
            modal.classList.remove('show');
            currentUploadId = null;
        }

        // Função para confirmar alteração de expiração
        async function confirmExpirationChange() {
            const newExpiration = document.getElementById('newExpiration').value;
            if (!newExpiration || !currentUploadId) {
                showToast('Dados inválidos para alteração', 'error');
                return;
            }

            try {
                const response = await fetch('./update_expiration.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        upload_id: currentUploadId,
                        expires_at: newExpiration
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    showToast('Data de expiração alterada com sucesso!', 'success');
                    closeExpirationModal();
                    // Recarregar dados do modal
                    const select = document.getElementById('user_select');
                    showUserUploads(select.value);
                } else {
                    showToast('Erro ao alterar data: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showToast('Erro ao alterar data de expiração', 'error');
            }
        }

        // Função para excluir upload do modal - usando modal de confirmação do dashboard
        function deleteUploadFromModal(uploadId, uploadTitle) {
            showConfirmModal(
                'Confirmar Exclusão',
                `Tem certeza que deseja excluir o upload "${uploadTitle}"? Esta ação não pode ser desfeita.`,
                'Excluir',
                'Cancelar',
                async () => {
                    try {
                        const response = await fetch('./admin_handler.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=delete_upload&upload_id=${uploadId}`
                        });

                        if (response.ok) {
                            showToast('Upload excluído com sucesso!', 'success');
                            // Recarregar dados do modal
                            const select = document.getElementById('user_select');
                            showUserUploads(select.value);
                        } else {
                            showToast('Erro ao excluir upload', 'error');
                        }
                    } catch (error) {
                        console.error('Erro:', error);
                        showToast('Erro ao excluir upload', 'error');
                    }
                }
            );
        }

        // Função para formatar data
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('pt-BR', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Sistema de modal de confirmação do dashboard
        function showConfirmModal(title, message, confirmText = 'Confirmar', cancelText = 'Cancelar', onConfirm = null) {
            // Remove existing modal if any
            const existingModal = document.querySelector('.confirm-modal');
            if (existingModal) {
                existingModal.remove();
            }

            const modal = document.createElement('div');
            modal.className = 'confirm-modal';
            modal.innerHTML = `
                <div class="confirm-content">
                    <div class="confirm-header">
                        <div class="confirm-icon">⚠️</div>
                        <h3 class="confirm-title">${title}</h3>
                    </div>
                    <div class="confirm-message">${message}</div>
                    <div class="confirm-actions">
                        <button class="confirm-btn confirm-btn-cancel" onclick="closeConfirmModal()">${cancelText}</button>
                        <button class="confirm-btn confirm-btn-danger" onclick="confirmAction()">${confirmText}</button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            
            // Store the callback function
            modal._onConfirm = onConfirm;
            
            // Show modal with animation
            setTimeout(() => {
                modal.classList.add('show');
                // Focus on the confirm button for better accessibility
                const confirmBtn = modal.querySelector('.confirm-btn-danger');
                if (confirmBtn) {
                    confirmBtn.focus();
                }
            }, 50);
            
            // Close on background click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeConfirmModal();
                }
            });
            
            // Keyboard support
            const handleKeyDown = (e) => {
                if (e.key === 'Escape') {
                    closeConfirmModal();
                } else if (e.key === 'Enter') {
                    confirmAction();
                }
            };
            
            document.addEventListener('keydown', handleKeyDown);
            modal._keyHandler = handleKeyDown;
        }

        function closeConfirmModal() {
            const modal = document.querySelector('.confirm-modal');
            if (modal) {
                // Remove keyboard event listener
                if (modal._keyHandler) {
                    document.removeEventListener('keydown', modal._keyHandler);
                }
                
                modal.classList.remove('show');
                setTimeout(() => {
                    if (modal.parentNode) {
                        modal.parentNode.removeChild(modal);
                    }
                }, 300);
            }
        }

        function confirmAction() {
            const modal = document.querySelector('.confirm-modal');
            if (modal && modal._onConfirm) {
                modal._onConfirm();
            }
            closeConfirmModal();
        }

        // Função para mostrar toast (reutilizada do dashboard)
        function showToast(message, type = 'info', duration = 3000) {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                z-index: 10000;
                font-weight: 600;
                transform: translateX(100%);
                transition: transform 0.3s ease;
            `;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => toast.style.transform = 'translateX(0)', 100);
            setTimeout(() => {
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => document.body.removeChild(toast), 300);
            }, duration);
        }

        // Fechar modais ao clicar fora e suporte ao teclado
        document.addEventListener('click', function(event) {
            const userModal = document.getElementById('userUploadsModal');
            const expirationModal = document.getElementById('expirationModal');
            
            if (event.target === userModal) {
                closeUserUploadsModal();
            }
            if (event.target === expirationModal) {
                closeExpirationModal();
            }
        });

        // Suporte ao teclado para todos os modais
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const userModal = document.getElementById('userUploadsModal');
                const expirationModal = document.getElementById('expirationModal');
                
                if (userModal.classList.contains('show')) {
                    closeUserUploadsModal();
                }
                if (expirationModal.classList.contains('show')) {
                    closeExpirationModal();
                }
            }
        });

        function editUser(id, username, email, role) {
            const newUsername = prompt('Nome do usuário:', username);
            if (newUsername === null || newUsername.trim() === '') return;

            const newEmail = prompt('Email do usuário:', email);
            if (newEmail === null || newEmail.trim() === '') return;

            const newRole = prompt('Função do usuário (user/admin):', role);
            if (newRole === null || (newRole !== 'user' && newRole !== 'admin')) {
                alert('Função deve ser "user" ou "admin"');
                return;
            }

            if (confirm(`Confirma a edição do usuário ${username}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin_handler.php';

                const fields = {
                    action: 'edit_user',
                    user_id: id,
                    username: newUsername.trim(),
                    email: newEmail.trim(),
                    role: newRole
                };

                for (const [key, value] of Object.entries(fields)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }

                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteUser(id, username) {
            if (confirm(`Tem certeza que deseja excluir o usuário "${username}"? Esta ação não pode ser desfeita.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin_handler.php';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_user';
                form.appendChild(actionInput);

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'user_id';
                idInput.value = id;
                form.appendChild(idInput);

                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>