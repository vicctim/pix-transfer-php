<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

// Ler dados JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['upload_id']) || !isset($input['expires_at'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit();
}

$upload_id = (int)$input['upload_id'];
$expires_at = $input['expires_at'];

// Validar formato da data
$date = DateTime::createFromFormat('Y-m-d H:i', $expires_at);
if (!$date) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Formato de data inválido']);
    exit();
}

// Converter para formato MySQL
$mysql_date = $date->format('Y-m-d H:i:s');

// Verificar se a nova data não é no passado
if ($date <= new DateTime()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A data de expiração deve ser no futuro']);
    exit();
}

try {
    require_once 'models/UploadSession.php';
    require_once 'models/User.php';
    
    $uploadSession = new UploadSession();
    $user = new User();
    
    // Verificar se o upload pertence ao usuário atual
    $uploads = $uploadSession->getByUserId($_SESSION['user_id']);
    $upload_belongs_to_user = false;
    
    foreach ($uploads as $upload) {
        if ($upload['id'] == $upload_id) {
            $upload_belongs_to_user = true;
            break;
        }
    }
    
    if (!$upload_belongs_to_user) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado']);
        exit();
    }
    
    // Atualizar data de expiração
    $result = $uploadSession->updateExpiration($upload_id, $mysql_date);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Data de expiração atualizada com sucesso']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar data de expiração']);
    }
    
} catch (Exception $e) {
    error_log("Error updating expiration: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>