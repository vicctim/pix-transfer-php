<?php
session_start();
require_once 'models/User.php';
require_once 'models/UploadSession.php';

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
$allUsers = $userModel->getAllUsers();
$selected_user_id = $_GET['user_id'] ?? null;
$user_uploads = [];

if ($selected_user_id) {
    $user_uploads = $uploadSessionModel->getByUserId($selected_user_id);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Upload System</title>
    <link rel="icon" type="image/png" href="src/img/favicon.png">
    <style>
    /* Estilos copiados do dashboard.php para consistência, com alguns ajustes */
    /* Em um projeto real, isso estaria em um arquivo CSS compartilhado */
    .header .logo {
        height: 40px;
    }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="src/img/logo.png" alt="Logo" style="height: 100%;">
        </div>
        <div class="user-info">
            <span>Bem-vindo, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
            <a href="logout.php">Sair</a>
        </div>
    </div>
    <div class="container">
        <h1>Painel de Administração</h1>

        <!-- Seção de Adicionar Usuário -->
        <div class="admin-section">
            <h2>Adicionar Novo Usuário</h2>
            <form action="admin_handler.php" method="POST">
                <input type="hidden" name="action" value="add_user">
                <!-- Inputs para username, email, password, role -->
                <button type="submit">Adicionar Usuário</button>
            </form>
        </div>

        <!-- Seção de Gerenciar Uploads -->
        <div class="admin-section">
            <h2>Gerenciar Uploads de Usuário</h2>
            <form method="GET" action="admin.php">
                <select name="user_id" onchange="this.form.submit()">
                    <option value="">Selecione um usuário</option>
                    <?php foreach ($allUsers as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo ($selected_user_id == $user['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if ($selected_user_id && !empty($user_uploads)): ?>
                <h3>Uploads de <?php echo htmlspecialchars($user_uploads[0]['username'] ?? ''); ?></h3>
                <!-- Loop para exibir uploads similar ao do dashboard -->
                <!-- Cada item terá um botão de exclusão -->
                <form action="admin_handler.php" method="POST">
                    <input type="hidden" name="action" value="delete_upload">
                    <input type="hidden" name="upload_id" value="<?php echo $upload['id']; ?>">
                    <button type="submit">Excluir</button>
                </form>
            <?php elseif ($selected_user_id): ?>
                <p>Nenhum upload encontrado para este usuário.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 