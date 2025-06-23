<?php
// Teste simples de upload
session_start();

// Simular login
$_SESSION['user_id'] = 1;

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "❌ Método não é POST\n";
    exit();
}

// Verificar se há arquivos
if (!isset($_FILES['files'])) {
    echo "❌ Nenhum arquivo enviado\n";
    exit();
}

echo "📁 Arquivos recebidos:\n";
print_r($_FILES['files']);

// Verificar cada arquivo
foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
    echo "\n📄 Processando arquivo " . ($key + 1) . ":\n";
    echo "Nome: " . $_FILES['files']['name'][$key] . "\n";
    echo "Tamanho: " . $_FILES['files']['size'][$key] . " bytes\n";
    echo "Tipo: " . $_FILES['files']['type'][$key] . "\n";
    echo "Erro: " . $_FILES['files']['error'][$key] . "\n";
    echo "Tmp: " . $tmp_name . "\n";
    
    if ($_FILES['files']['error'][$key] !== UPLOAD_ERR_OK) {
        echo "❌ Erro no upload: " . $_FILES['files']['error'][$key] . "\n";
        continue;
    }
    
    // Tentar mover o arquivo
    $upload_dir = 'uploads/' . date('Y/m/d');
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $stored_name = uniqid() . '_' . time() . '.txt';
    $file_path = $upload_dir . '/' . $stored_name;
    
    if (move_uploaded_file($tmp_name, $file_path)) {
        echo "✅ Arquivo movido com sucesso para: $file_path\n";
    } else {
        echo "❌ Erro ao mover arquivo\n";
    }
}

echo "\n🎯 Teste concluído!\n";
?> 