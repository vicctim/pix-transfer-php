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
    require_once 'models/SystemSettings.php';
    require_once 'models/EmailTemplate.php';
    require_once 'models/ShortUrl.php';
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
    exit();
}

$settings = new SystemSettings();

// Set timezone
date_default_timezone_set($settings->getTimezone());

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
    $recipient_email = $_POST['recipient_email'] ?? '';
    $expires_in = (int)($_POST['expires_in'] ?? $settings->get('default_expiration_days', 7));
    $custom_message = $_POST['custom_message'] ?? '';
    $notify_sender = isset($_POST['notify_sender']) && $_POST['notify_sender'] === '1';
    
    // Additional email recipients
    $additional_emails = [];
    if (!empty($_POST['additional_emails'])) {
        $additional_emails = array_filter(array_map('trim', explode(',', $_POST['additional_emails'])));
        // Validate emails
        foreach ($additional_emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Email inválido: $email");
            }
        }
    }

    // Criar sessão de upload
    $uploadSession = new UploadSession();
    $session_id = $uploadSession->create($_SESSION['user_id'], $title, $recipient_email, $expires_in);
    
    if (!$session_id) {
        throw new Exception('Erro ao criar sessão de upload');
    }

    // Atualizar notify_sender se especificado
    if ($notify_sender) {
        $db = Database::getInstance();
        $db->query("UPDATE upload_sessions SET notify_sender = 1 WHERE id = ?", [$session_id]);
    }

    // Criar diretório de upload baseado na data (GMT-3)
    $upload_date = date('Y/m/d');
    $upload_dir = "uploads/$upload_date";
    
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Erro ao criar diretório de upload');
        }
    }

    $fileModel = new File();
    $uploaded_files = [];
    $total_size = 0;

    // Processar cada arquivo
    $file_count = count($_FILES['files']['name']);
    for ($i = 0; $i < $file_count; $i++) {
        // Verificar se houve erro no upload
        if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
            throw new Exception('Erro no upload do arquivo: ' . $_FILES['files']['name'][$i]);
        }

        $original_name = $_FILES['files']['name'][$i];
        $tmp_name = $_FILES['files']['tmp_name'][$i];
        $file_size = $_FILES['files']['size'][$i];
        $mime_type = $_FILES['files']['type'][$i] ?: 'application/octet-stream';

        // Verificar tamanho máximo
        $max_size = $settings->get('max_file_size', 10737418240); // 10GB default
        if ($file_size > $max_size) {
            throw new Exception("Arquivo '$original_name' é muito grande. Máximo permitido: " . formatBytes($max_size));
        }

        // Gerar nome único para o arquivo
        $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
        $stored_name = uniqid() . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . '/' . $stored_name;

        // Mover arquivo para destino final
        if (!move_uploaded_file($tmp_name, $file_path)) {
            throw new Exception("Erro ao salvar arquivo: $original_name");
        }

        // Salvar informações no banco
        $file_id = $fileModel->create(
            $session_id,
            $original_name,
            $stored_name,
            $file_path,
            $file_size,
            $mime_type
        );

        if (!$file_id) {
            // Remover arquivo se falhou ao salvar no banco
            unlink($file_path);
            throw new Exception("Erro ao registrar arquivo no banco: $original_name");
        }

        $uploaded_files[] = [
            'id' => $file_id,
            'name' => $original_name,
            'size' => $file_size,
            'type' => $mime_type
        ];

        $total_size += $file_size;
    }

    // Obter dados da sessão criada
    $uploadSession->getById($session_id);
    $token = $uploadSession->token;

    // Criar short URL
    $shortUrl = new ShortUrl();
    $short_code = $shortUrl->create($token, $uploadSession->expires_at);
    
    // Update session with short URL
    if ($short_code) {
        $db = Database::getInstance();
        $db->query("UPDATE upload_sessions SET short_url_id = (SELECT id FROM short_urls WHERE short_code = ?)", [$short_code]);
    }

    // Preparar URLs
    $download_url = $settings->getSiteUrl() . '/download/' . urlencode($token);
    $short_url_full = $short_code ? $settings->getSiteUrl() . '/s/' . $short_code : null;

    // Enviar emails se especificado
    $email_sent = false;
    $emailTemplate = new EmailTemplate();
    
    // SEMPRE enviar notificação para o administrador do sistema
    $adminEmail = $settings->get('admin_email');
    if ($adminEmail) {
        $emailTemplate->sendAdminUploadNotification($session_id);
    }
    
    // Enviar para usuário logado (notificação de upload completo)
    $currentUser = new User();
    $currentUserData = $currentUser->getById($_SESSION['user_id']);
    if ($currentUserData && $currentUserData['email']) {
        $emailTemplate->sendUserUploadCompleteNotification($session_id, $currentUserData['email']);
    }
    
    // Enviar para destinatários especificados
    if (!empty($recipient_email) || !empty($additional_emails)) {
        // Prepare all recipient emails
        $all_recipients = [];
        if (!empty($recipient_email)) {
            $all_recipients[] = $recipient_email;
        }
        $all_recipients = array_merge($all_recipients, $additional_emails);
        
        // Remove duplicates
        $all_recipients = array_unique($all_recipients);
        
        if (!empty($all_recipients)) {
            $email_sent = $emailTemplate->sendUploadNotification(
                $session_id,
                $all_recipients,
                $custom_message,
                $notify_sender
            );
        }
    }

    // Limpar buffer e enviar resposta de sucesso
    ob_end_clean();
    
    $response = [
        'success' => true,
        'message' => 'Upload realizado com sucesso!',
        'data' => [
            'session_id' => $session_id,
            'token' => $token,
            'title' => $title,
            'files' => $uploaded_files,
            'file_count' => count($uploaded_files),
            'total_size' => $total_size,
            'total_size_formatted' => formatBytes($total_size),
            'expires_at' => $uploadSession->expires_at,
            'expires_at_formatted' => date('d/m/Y H:i', strtotime($uploadSession->expires_at)),
            'download_url' => $download_url,
            'short_url' => $short_url_full,
            'email_sent' => $email_sent,
            'email_count' => count($all_recipients ?? [])
        ]
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    // Limpar buffer e enviar resposta de erro
    ob_end_clean();
    
    // Log do erro
    error_log("Erro no upload: " . $e->getMessage());
    
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    
    http_response_code(400);
    echo json_encode($response);
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>