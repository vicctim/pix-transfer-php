<?php
// Script de debug para testar upload
session_start();

// Simular login
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['email'] = 'victor@pixfilmes.com';

echo "🔍 DEBUG: Testando upload...\n";
echo "Session user_id: " . $_SESSION['user_id'] . "\n";

// Verificar se os arquivos necessários existem
$files_to_check = [
    'models/UploadSession.php',
    'models/File.php', 
    'models/User.php',
    'config/email.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✅ $file existe\n";
    } else {
        echo "❌ $file não existe\n";
    }
}

// Verificar diretório de uploads
$upload_dir = 'uploads/' . date('Y/m/d');
echo "📁 Diretório de upload: $upload_dir\n";

if (!is_dir($upload_dir)) {
    echo "📁 Criando diretório...\n";
    if (mkdir($upload_dir, 0755, true)) {
        echo "✅ Diretório criado com sucesso\n";
    } else {
        echo "❌ Erro ao criar diretório\n";
    }
} else {
    echo "✅ Diretório já existe\n";
}

// Verificar permissões
echo "🔐 Permissões do diretório: " . substr(sprintf('%o', fileperms($upload_dir)), -4) . "\n";

// Testar conexão com banco
try {
    require_once 'config/database.php';
    $database = new Database();
    $conn = $database->getConnection();
    if ($conn) {
        echo "✅ Conexão com banco OK\n";
    } else {
        echo "❌ Erro na conexão com banco\n";
    }
} catch (Exception $e) {
    echo "❌ Erro no banco: " . $e->getMessage() . "\n";
}

echo "🎯 Debug concluído!\n";
?> 