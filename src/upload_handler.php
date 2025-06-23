<?php
// Desabilitar exibição de erros para não interferir no JSON
error_reporting(0);
ini_set('display_errors', 0);

// Iniciar buffer de saída para capturar qualquer output antes do JSON
ob_start();

session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

// Incluir arquivos necessários
try {
    require_once 'models/UploadSession.php';
    require_once 'models/File.php';
    require_once 'models/User.php';
    require_once 'config/email_phpmailer.php';
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
    exit();
}

// Definir headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Verificar se arquivos foram enviados
    if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
        throw new Exception('Nenhum arquivo foi enviado');
    }

    // Coletar dados do formulário
    $title = !empty($_POST['title']) ? $_POST['title'] : $_FILES['files']['name'][0];
    $expires_in = (int)($_POST['expires_in'] ?? 7);
    $transfer_mode = $_POST['transfer_mode'] ?? 'link';
    $recipient_email = ($transfer_mode === 'email') ? ($_POST['email_to'] ?? null) : null;

    if ($transfer_mode === 'email' && !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email do destinatário é inválido.');
    }

    // Criar sessão de upload
    $uploadSession = new UploadSession();
    $session_id = $uploadSession->create(
        $_SESSION['user_id'], 
        $title, 
        $recipient_email,
        $expires_in
    );
    
    if (!$session_id) {
        throw new Exception('Erro ao criar sessão de upload');
    }
    
    // Obter informações da sessão, incluindo o token gerado
    $uploadSession->getById($session_id);
    if (!$uploadSession->token) {
        throw new Exception('Sessão de upload não encontrada após a criação');
    }
    $session_token = $uploadSession->token;

    $fileModel = new File();
    $uploaded_files = [];
    $total_size = 0;
    $errors = [];

    // Processar cada arquivo
    foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['files']['error'][$key] !== UPLOAD_ERR_OK) {
            $errors[] = "Erro no upload do arquivo: " . $_FILES['files']['name'][$key];
            continue;
        }

        $original_name = $_FILES['files']['name'][$key];
        $file_size = $_FILES['files']['size'][$key];
        $mime_type = $_FILES['files']['type'][$key];

        // Validar tamanho do arquivo (10GB)
        if ($file_size > 10 * 1024 * 1024 * 1024) {
            $errors[] = "Arquivo muito grande: " . $original_name;
            continue;
        }

        // Gerar nome único para o arquivo
        $extension = pathinfo($original_name, PATHINFO_EXTENSION);
        $stored_name = uniqid() . '_' . time() . '.' . $extension;
        
        // Criar diretório se não existir
        $upload_dir = 'uploads/' . date('Y/m/d');
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                $errors[] = "Erro ao criar diretório: " . $original_name;
                continue;
            }
        }

        $file_path = $upload_dir . '/' . $stored_name;

        // Mover arquivo
        if (move_uploaded_file($tmp_name, $file_path)) {
            // Salvar no banco de dados
            if ($fileModel->create($session_id, $original_name, $stored_name, $file_path, $file_size, $mime_type)) {
                $uploaded_files[] = [
                    'name' => $original_name,
                    'size' => $file_size,
                    'path' => $file_path
                ];
                $total_size += $file_size;
            } else {
                $errors[] = "Erro ao salvar arquivo no banco: " . $original_name;
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        } else {
            $errors[] = "Erro ao mover arquivo: " . $original_name;
        }
    }

    if (empty($uploaded_files)) {
        // Se nenhum arquivo foi enviado com sucesso, deletar a sessão
        $uploadSession->delete($session_id);
        throw new Exception('Nenhum arquivo foi enviado com sucesso');
    }

    // Enviar emails
    try {
        error_log("=== INICIANDO ENVIO DE EMAILS ===");
        $emailService = new PHPMailerEmailService();
        $user = new User();
        $userData = $user->getById($_SESSION['user_id']);
        
        error_log("User data retrieved: " . json_encode($userData));
        error_log("Transfer mode: " . $transfer_mode);
        error_log("Recipient email: " . $recipient_email);
        
        // Se o modo for email, envia para o destinatário
        if ($transfer_mode === 'email' && $recipient_email) {
            error_log("Sending email to recipient: " . $recipient_email);
            $recipientResult = $emailService->sendDownloadLinkToRecipient(
                $recipient_email,
                $userData['email'], // Email do remetente
                $title,
                $session_token,
                $uploadSession->expires_at,
                count($uploaded_files),
                $total_size
            );
            error_log("Recipient email result: " . ($recipientResult ? 'SUCCESS' : 'FAILED'));
        }

        // Email de confirmação para o próprio usuário e notificação para o Admin
        error_log("Sending upload complete email to: " . $userData['email']);
        $uploadResult = $emailService->sendUploadCompleteEmail(
            $userData['email'],
            $title,
            $session_token,
            $uploadSession->expires_at,
            count($uploaded_files),
            $total_size
        );
        error_log("Upload complete email result: " . ($uploadResult ? 'SUCCESS' : 'FAILED'));
        error_log("=== FIM DO ENVIO DE EMAILS ===");

    } catch (Exception $emailError) {
        // Log do erro de email mas não falhar o upload
        error_log("Email error: " . $emailError->getMessage());
        error_log("Email error trace: " . $emailError->getTraceAsString());
    }

    // Limpar qualquer output anterior
    ob_end_clean();
    
    // Retornar resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Upload concluído com sucesso',
        'data' => [
            'session_token' => $session_token,
            'files_count' => count($uploaded_files),
            'total_size' => $total_size,
            'uploaded_files' => $uploaded_files,
            'errors' => $errors
        ]
    ]);

} catch (Exception $e) {
    // Limpar qualquer output anterior
    ob_end_clean();
    
    error_log("Upload error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 