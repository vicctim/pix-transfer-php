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
    require_once 'config/email.php';
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

    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Criar sessão de upload
    $uploadSession = new UploadSession();
    $session_token = $uploadSession->create($_SESSION['user_id'], $title, $description);
    
    if (!$session_token) {
        throw new Exception('Erro ao criar sessão de upload');
    }

    // Obter informações da sessão
    $session_data = $uploadSession->getByToken($session_token);
    if (!$session_data) {
        throw new Exception('Sessão de upload não encontrada');
    }

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
            if ($fileModel->create($session_data['id'], $original_name, $stored_name, $file_path, $file_size, $mime_type)) {
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
        $uploadSession->delete($session_data['id']);
        throw new Exception('Nenhum arquivo foi enviado com sucesso');
    }

    // Enviar emails (opcional - não falhar se email falhar)
    try {
        $emailService = new EmailService();
        $user = new User();
        $userData = $user->getById($_SESSION['user_id']);
        
        // Email para o usuário
        $emailService->sendUploadCompleteEmail(
            $userData['email'],
            $session_token,
            $session_data['expires_at'],
            count($uploaded_files),
            $total_size
        );

        // Email para o administrador
        $emailService->sendAdminNotification(
            $session_token,
            $userData['email'],
            count($uploaded_files),
            $total_size
        );
    } catch (Exception $emailError) {
        // Log do erro de email mas não falhar o upload
        error_log("Email error: " . $emailError->getMessage());
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