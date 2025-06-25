<?php
session_start();

// Simular usuário logado
$_SESSION['user_id'] = 11; // ID do admin

require_once 'src/models/SystemSettings.php';

$settings = new SystemSettings();

echo "=== DEBUG UPLOAD TEST ===\n";
echo "Usuario logado: " . ($_SESSION['user_id'] ?? 'Nao') . "\n";
echo "Max file size configurado: " . $settings->get('max_file_size', 10737418240) . "\n";
echo "PHP upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "PHP post_max_size: " . ini_get('post_max_size') . "\n";
echo "PHP max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "Upload dir exists: " . (is_dir('uploads') ? 'Sim' : 'Nao') . "\n";
echo "Upload dir writable: " . (is_writable('uploads') ? 'Sim' : 'Nao') . "\n";

// Testar configurações básicas
try {
    require_once 'src/models/UploadSession.php';
    require_once 'src/models/File.php';
    require_once 'src/models/User.php';
    echo "Modelos carregados com sucesso\n";
} catch (Exception $e) {
    echo "ERRO ao carregar modelos: " . $e->getMessage() . "\n";
}

// Testar conexão com banco
try {
    $uploadSession = new UploadSession();
    echo "UploadSession criado com sucesso\n";
} catch (Exception $e) {
    echo "ERRO ao criar UploadSession: " . $e->getMessage() . "\n";
}

// Se POST, simular upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "\n=== SIMULANDO UPLOAD ===\n";
    
    if (isset($_FILES['test_file'])) {
        echo "Arquivo recebido: " . $_FILES['test_file']['name'] . "\n";
        echo "Tamanho: " . $_FILES['test_file']['size'] . "\n";
        echo "Erro: " . $_FILES['test_file']['error'] . "\n";
        echo "Tipo: " . $_FILES['test_file']['type'] . "\n";
        echo "Tmp name: " . $_FILES['test_file']['tmp_name'] . "\n";
        
        if ($_FILES['test_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/' . date('Y/m/d');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
                echo "Diretorio criado: $uploadDir\n";
            }
            
            $fileName = 'test_' . time() . '.txt';
            $filePath = $uploadDir . '/' . $fileName;
            
            if (move_uploaded_file($_FILES['test_file']['tmp_name'], $filePath)) {
                echo "Arquivo salvo com sucesso em: $filePath\n";
            } else {
                echo "ERRO ao mover arquivo\n";
            }
        } else {
            echo "ERRO no upload: " . $_FILES['test_file']['error'] . "\n";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Debug Upload</title>
</head>
<body>
    <h1>Test Upload Debug</h1>
    
    <form method="POST" enctype="multipart/form-data">
        <p>Selecione um arquivo pequeno para testar:</p>
        <input type="file" name="test_file" required>
        <button type="submit">Testar Upload</button>
    </form>
    
    <h2>Debug Info</h2>
    <pre><?php
        echo "=== DEBUG UPLOAD TEST ===\n";
        echo "Usuario logado: " . ($_SESSION['user_id'] ?? 'Nao') . "\n";
        echo "Max file size configurado: " . $settings->get('max_file_size', 10737418240) . "\n";
        echo "PHP upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
        echo "PHP post_max_size: " . ini_get('post_max_size') . "\n";
        echo "PHP max_execution_time: " . ini_get('max_execution_time') . "\n";
        echo "Upload dir exists: " . (is_dir('uploads') ? 'Sim' : 'Nao') . "\n";
        echo "Upload dir writable: " . (is_writable('uploads') ? 'Sim' : 'Nao') . "\n";
    ?></pre>
</body>
</html>