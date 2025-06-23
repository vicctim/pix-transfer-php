<?php
session_start();
require_once 'models/User.php';
require_once 'models/UploadSession.php';
require_once 'models/File.php';

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
        $userModel->create($username, $email, $password, $role);
    }
    header('Location: admin.php');
    exit();
}

if ($action === 'delete_upload') {
    $upload_id = $_POST['upload_id'];

    if (!empty($upload_id)) {
        $uploadSessionModel = new UploadSession();
        $fileModel = new File();

        // 1. Encontrar os arquivos associados
        $files = $fileModel->getBySessionId($upload_id);
        
        // 2. Deletar os arquivos do servidor
        foreach ($files as $file) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
        }
        
        // 3. Deletar a sessão de upload e os registros de arquivo no DB (CASCADE deve cuidar dos files)
        $uploadSessionModel->delete($upload_id);
    }

    // Redireciona de volta para a mesma visualização de usuário
    $user_id = $_POST['user_id_context']; // Um campo hidden no formulário de exclusão
    header('Location: admin.php?user_id=' . $user_id);
    exit();
}

// Ação desconhecida, volta para o painel
header('Location: admin.php');
exit(); 