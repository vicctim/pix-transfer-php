<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

require_once 'models/UploadSession.php';
require_once 'models/User.php';
require_once 'config/email.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? '';

    if (empty($token)) {
        throw new Exception('Token não fornecido');
    }

    $uploadSession = new UploadSession();
    $session_data = $uploadSession->getByToken($token);

    if (!$session_data || $session_data['user_id'] != $_SESSION['user_id']) {
        throw new Exception('Sessão não encontrada ou não autorizada');
    }

    $fileModel = new File();
    $files = $fileModel->getBySessionId($session_data['id']);
    $total_size = $fileModel->getTotalSizeBySession($session_data['id']);

    $user = new User();
    $userData = $user->getById($_SESSION['user_id']);

    $emailService = new EmailService();
    
    // Enviar email para o usuário
    $result = $emailService->sendUploadCompleteEmail(
        $userData['email'],
        $token,
        $session_data['expires_at'],
        count($files),
        $total_size
    );

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Email enviado com sucesso']);
    } else {
        throw new Exception('Erro ao enviar email');
    }

} catch (Exception $e) {
    error_log("Email error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 