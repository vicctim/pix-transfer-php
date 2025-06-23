<?php
/**
 * Script principal para executar todos os testes unitários
 */

echo "🧪 INICIANDO TESTES UNITÁRIOS\n";
echo str_repeat("=", 60) . "\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n\n";

// Verificar se estamos no diretório correto
if (!file_exists('tests/TestSuite.php')) {
    echo "❌ ERRO: Execute este script do diretório raiz do projeto\n";
    exit(1);
}

// Verificar se o banco está acessível
try {
    require_once 'config/database.php';
    $database = new Database();
    $conn = $database->getConnection();
    if (!$conn) {
        echo "❌ ERRO: Não foi possível conectar ao banco de dados\n";
        exit(1);
    }
    echo "✅ Conexão com banco de dados: OK\n";
} catch (Exception $e) {
    echo "❌ ERRO: Falha na conexão com banco: " . $e->getMessage() . "\n";
    exit(1);
}

// Criar diretório de uploads se não existir
if (!is_dir('uploads')) {
    mkdir('uploads', 0755, true);
    echo "✅ Diretório uploads criado\n";
}

// Executar testes de login
echo "\n" . str_repeat("-", 60) . "\n";
require_once 'tests/LoginTest.php';
$loginTest = new LoginTest();
$loginTest->runAllTests();

// Executar testes de upload
echo "\n" . str_repeat("-", 60) . "\n";
require_once 'tests/UploadTest.php';
$uploadTest = new UploadTest();
$uploadTest->runAllTests();

// Executar testes de download
echo "\n" . str_repeat("-", 60) . "\n";
require_once 'tests/DownloadTest.php';
$downloadTest = new DownloadTest();
$downloadTest->runAllTests();

// Resumo final
echo "\n" . str_repeat("=", 60) . "\n";
echo "🎯 RESUMO FINAL DOS TESTES\n";
echo str_repeat("=", 60) . "\n";

$totalTests = $loginTest->total + $uploadTest->total + $downloadTest->total;
$totalPassed = $loginTest->passed + $uploadTest->passed + $downloadTest->passed;
$totalFailed = $loginTest->failed + $uploadTest->failed + $downloadTest->failed;

echo "📊 ESTATÍSTICAS GERAIS:\n";
echo "   Total de testes: $totalTests\n";
echo "   ✅ Passou: $totalPassed\n";
echo "   ❌ Falhou: $totalFailed\n";
echo "   📈 Taxa de sucesso geral: " . round(($totalPassed / $totalTests) * 100, 2) . "%\n\n";

echo "📋 BREAKDOWN POR CATEGORIA:\n";
echo "   🔐 Login: {$loginTest->passed}/{$loginTest->total} (" . round(($loginTest->passed / $loginTest->total) * 100, 2) . "%)\n";
echo "   📤 Upload: {$uploadTest->passed}/{$uploadTest->total} (" . round(($uploadTest->passed / $uploadTest->total) * 100, 2) . "%)\n";
echo "   📥 Download: {$downloadTest->passed}/{$downloadTest->total} (" . round(($downloadTest->passed / $downloadTest->total) * 100, 2) . "%)\n\n";

if ($totalFailed === 0) {
    echo "🎉 TODOS OS TESTES PASSARAM! O sistema está funcionando corretamente.\n";
    exit(0);
} else {
    echo "⚠️  ALGUNS TESTES FALHARAM. Verifique os detalhes acima.\n";
    exit(1);
}
?> 