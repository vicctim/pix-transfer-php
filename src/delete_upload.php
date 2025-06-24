<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

require_once 'models/UploadSession.php';
require_once 'models/File.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Log para debug
    error_log("Delete upload - Input recebido: " . json_encode($input));
    
    $session_id = $input['id'] ?? 0;

    if (empty($session_id)) {
        error_log("Delete upload - ID vazio ou não fornecido. Input: " . json_encode($input));
        throw new Exception('ID da sessão não fornecido');
    }

    $uploadSession = new UploadSession();
    $session_data = $uploadSession->getByUserId($_SESSION['user_id']);
    
    // Verificar se a sessão pertence ao usuário
    $session_belongs_to_user = false;
    foreach ($session_data as $session) {
        if ($session['id'] == $session_id) {
            $session_belongs_to_user = true;
            break;
        }
    }

    if (!$session_belongs_to_user) {
        throw new Exception('Sessão não encontrada ou não autorizada');
    }

    $fileModel = new File();
    $files = $fileModel->getBySessionId($session_id);

    // Deletar arquivos físicos
    foreach ($files as $file) {
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
    }

    // Deletar sessão (arquivos serão deletados automaticamente por CASCADE)
    $result = $uploadSession->delete($session_id);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Upload excluído com sucesso']);
    } else {
        throw new Exception('Erro ao excluir upload');
    }

} catch (Exception $e) {
    error_log("Delete error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 