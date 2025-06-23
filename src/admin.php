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
            height: 70px;
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
            font-family: inherit;
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
                <img src="img/logo.png" alt="Logo">
                <h1>Upload System</h1>
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
                    <select name="user_id" id="user_select" class="form-select" onchange="this.form.submit()">
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

            <?php if ($selected_user_id && !empty($user_uploads)): ?>
                <h3 style="margin-top: 25px; color: #4CAF50;">
                    📤 Uploads de <?php echo htmlspecialchars($user_uploads[0]['username'] ?? ''); ?>
                </h3>
                <table class="uploads-table">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Arquivos</th>
                            <th>Data de Upload</th>
                            <th>Expira em</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_uploads as $upload): 
                            $files = $fileModel->getBySessionId($upload['id']);
                            $fileCount = count($files);
                            $expiresAt = new DateTime($upload['expires_at']);
                            $now = new DateTime();
                            $isExpired = $expiresAt <= $now;
                            $isExpiringSoon = !$isExpired && $expiresAt <= (new DateTime())->add(new DateInterval('P1D'));
                        ?>
                            <tr>
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
                                    <a href="download.php?token=<?php echo $upload['token']; ?>" target="_blank" class="btn btn-secondary" style="margin-right: 10px;">
                                        👁️ Ver
                                    </a>
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
            <?php elseif ($selected_user_id): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>Nenhum upload encontrado</h3>
                    <p>Este usuário ainda não fez nenhum upload.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>