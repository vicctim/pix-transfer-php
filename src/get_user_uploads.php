<?php
session_start();
require_once 'models/User.php';
require_once 'models/UploadSession.php';
require_once 'models/File.php';
require_once 'models/ShortUrl.php';

header('Content-Type: application/json');

// Verificar se é admin
$userModel = new User();
if (!isset($_SESSION['user_id']) || !$userModel->isAdmin($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'ID do usuário não fornecido']);
    exit;
}

try {
    // Buscar dados do usuário
    $user = $userModel->getById($user_id);
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }

    // Buscar uploads do usuário
    $uploadSessionModel = new UploadSession();
    $fileModel = new File();
    $shortUrl = new ShortUrl();
    
    $uploads = $uploadSessionModel->getByUserId($user_id);
    
    // Adicionar informações extras para cada upload
    foreach ($uploads as &$upload) {
        // Contar arquivos
        $files = $fileModel->getBySessionId($upload['id']);
        $upload['file_count'] = count($files);
        
        // Buscar short_code se existir
        $short_code = $shortUrl->getByOriginalToken($upload['token']);
        $upload['short_code'] = $short_code;
    }
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ],
        'uploads' => $uploads
    ]);

} catch (Exception $e) {
    error_log("Erro ao buscar uploads do usuário: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>