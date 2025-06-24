<?php
session_start();
require_once 'models/User.php';
require_once 'models/UploadSession.php';
require_once 'models/File.php';
require_once 'config/database.php';

// Proteção: Apenas admin pode executar ações
$userModel = new User();
if (!isset($_SESSION['user_id']) || !$userModel->isAdmin($_SESSION['user_id'])) {
    // Redireciona ou mostra erro
    header('Location: dashboard.php');
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'add_user') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Validação básica
    if (!empty($username) && !empty($email) && !empty($password)) {
        $result = $userModel->create($username, $email, $password, $role);
    }
    header('Location: admin.php');
    exit();
}

if ($action === 'delete_upload') {
    $upload_id = $_POST['upload_id'];

    if (!empty($upload_id)) {
        $uploadSessionModel = new UploadSession();
        $fileModel = new File();

        // 1. Obter informações da sessão de upload primeiro
        $session_info = $uploadSessionModel->getById($upload_id);
        
        // 2. Encontrar os arquivos associados
        $files = $fileModel->getBySessionId($upload_id);
        
        // 3. Deletar os arquivos do servidor
        foreach ($files as $file) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
        }
        
        // 4. Deletar short_urls se existir token
        if ($session_info && isset($session_info['token'])) {
            try {
                $db = Database::getInstance();
                $db->query("DELETE FROM short_urls WHERE original_token = ?", [$session_info['token']]);
            } catch (Exception $e) {
                error_log("Error deleting short_urls for upload: " . $e->getMessage());
            }
        }
        
        // 5. Deletar a sessão de upload e os registros de arquivo no DB (CASCADE deve cuidar dos files)
        $uploadSessionModel->delete($upload_id);
    }

    // Redireciona de volta para a página admin
    header('Location: admin.php');
    exit();
}

if ($action === 'edit_user') {
    $user_id = $_POST['user_id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];

    // Validação básica
    if (!empty($user_id) && !empty($username) && !empty($email) && in_array($role, ['user', 'admin'])) {
        $userModel->updateUser($user_id, $username, $email, $role);
    }
    header('Location: admin.php');
    exit();
}

if ($action === 'delete_user') {
    $user_id = $_POST['user_id'];

    // Verificar se não é usuário protegido do sistema
    $protectedUsers = ['victor@pixfilmes.com'];
    $userToDelete = $userModel->getById($user_id);
    $isProtectedUser = $userToDelete && in_array($userToDelete['email'], $protectedUsers);

    // Não pode excluir a si mesmo ou usuários protegidos do sistema
    if (!empty($user_id) && $user_id != $_SESSION['user_id'] && !$isProtectedUser) {
        $uploadSessionModel = new UploadSession();
        $fileModel = new File();

        // 1. Encontrar todos os uploads do usuário
        $userUploads = $uploadSessionModel->getByUserId($user_id);
        
        // 2. Para cada upload, deletar os arquivos físicos e limpar short_urls
        foreach ($userUploads as $upload) {
            $files = $fileModel->getBySessionId($upload['id']);
            foreach ($files as $file) {
                if (file_exists($file['file_path'])) {
                    unlink($file['file_path']);
                }
            }
            
            // Deletar short_urls relacionadas a este token
            try {
                $db = Database::getInstance();
                $db->query("DELETE FROM short_urls WHERE original_token = ?", [$upload['token']]);
            } catch (Exception $e) {
                error_log("Error deleting short_urls: " . $e->getMessage());
            }
        }
        
        // 3. Deletar manualmente upload_sessions e files primeiro
        try {
            $db = Database::getInstance();
            $db->query("DELETE FROM files WHERE session_id IN (SELECT id FROM upload_sessions WHERE user_id = ?)", [$user_id]);
            $db->query("DELETE FROM email_logs WHERE session_id IN (SELECT id FROM upload_sessions WHERE user_id = ?)", [$user_id]);
            $db->query("DELETE FROM upload_sessions WHERE user_id = ?", [$user_id]);
        } catch (Exception $e) {
            error_log("Error deleting user uploads: " . $e->getMessage());
        }
        
        // 4. Deletar o usuário
        $userModel->deleteUser($user_id);
    }
    header('Location: admin.php');
    exit();
}

// Ação desconhecida, volta para o painel
header('Location: admin.php');
exit(); 