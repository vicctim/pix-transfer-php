<?php
require_once 'TestSuite.php';
require_once 'models/UploadSession.php';
require_once 'models/File.php';
require_once 'models/User.php';
require_once 'config/database.php';

class DownloadTest extends TestSuite {
    private $uploadSession;
    private $fileModel;
    private $user;
    private $testUserId = 1;

    public function __construct() {
        $this->uploadSession = new UploadSession();
        $this->fileModel = new File();
        $this->user = new User();
    }

    public function runAllTests() {
        echo "📥 TESTES DE DOWNLOAD\n";
        echo str_repeat("=", 40) . "\n";

        $this->runTest("Teste de validação de token de download", function() {
            return $this->testDownloadTokenValidation();
        });

        $this->runTest("Teste de verificação de expiração", function() {
            return $this->testExpirationCheck();
        });

        $this->runTest("Teste de obtenção de arquivos para download", function() {
            return $this->testGetFilesForDownload();
        });

        $this->runTest("Teste de cálculo de tamanho total para download", function() {
            return $this->testTotalSizeForDownload();
        });

        $this->runTest("Teste de verificação de existência de arquivo", function() {
            return $this->testFileExistenceCheck();
        });

        $this->runTest("Teste de obtenção de arquivo por ID", function() {
            return $this->testGetFileById();
        });

        $this->runTest("Teste de validação de acesso ao arquivo", function() {
            return $this->testFileAccessValidation();
        });

        $this->runTest("Teste de formatação de bytes", function() {
            return $this->testBytesFormatting();
        });

        $this->printResults();
    }

    private function testDownloadTokenValidation() {
        // Criar uma sessão válida
        $token = $this->uploadSession->create($this->testUserId, 'Test Download Session', 'Test Description');
        $session = $this->uploadSession->getByToken($token);
        
        $this->assertNotEmpty($session, 'Sessão válida deveria ser encontrada');
        $this->assertEquals($this->testUserId, $session['user_id'], 'User ID deveria corresponder');
        
        // Testar token inválido
        $invalidSession = $this->uploadSession->getByToken('invalid_download_token_12345');
        $this->assertFalse($invalidSession, 'Token inválido deveria retornar false');
        
        return true;
    }

    private function testExpirationCheck() {
        // Criar uma sessão válida (expira em 7 dias por padrão)
        $token = $this->uploadSession->create($this->testUserId, 'Test Expiration Session', 'Test Description');
        $session = $this->uploadSession->getByToken($token);
        
        $this->assertNotEmpty($session, 'Sessão deveria ser encontrada');
        
        // Verificar se a data de expiração está no futuro
        $expiresAt = strtotime($session['expires_at']);
        $now = time();
        
        $this->assertTrue($expiresAt > $now, 'Data de expiração deveria estar no futuro');
        
        return true;
    }

    private function testGetFilesForDownload() {
        // Criar uma sessão e arquivos
        $token = $this->uploadSession->create($this->testUserId, 'Test Files Download Session', 'Test Description');
        $session = $this->uploadSession->getByToken($token);
        
        // Criar arquivos de teste
        $testFilePath1 = '../uploads/test_download1.txt';
        $testFilePath2 = '../uploads/test_download2.txt';
        
        file_put_contents($testFilePath1, 'Test download content 1');
        file_put_contents($testFilePath2, 'Test download content 2');
        
        $this->fileModel->create($session['id'], 'download1.txt', 'stored_download1.txt', $testFilePath1, 500, 'text/plain');
        $this->fileModel->create($session['id'], 'download2.txt', 'stored_download2.txt', $testFilePath2, 750, 'text/plain');
        
        $files = $this->fileModel->getBySessionId($session['id']);
        
        $this->assertTrue(is_array($files), 'Arquivos deveriam ser um array');
        $this->assertTrue(count($files) >= 2, 'Deveria haver pelo menos 2 arquivos');
        
        // Verificar se os arquivos têm as propriedades corretas
        foreach ($files as $file) {
            $this->assertNotEmpty($file['original_name'], 'Nome original não deveria estar vazio');
            $this->assertNotEmpty($file['stored_name'], 'Nome armazenado não deveria estar vazio');
            $this->assertNotEmpty($file['file_path'], 'Caminho do arquivo não deveria estar vazio');
            $this->assertTrue($file['file_size'] > 0, 'Tamanho do arquivo deveria ser maior que 0');
        }
        
        // Limpar arquivos de teste
        unlink($testFilePath1);
        unlink($testFilePath2);
        
        return true;
    }

    private function testTotalSizeForDownload() {
        // Criar uma sessão e arquivos
        $token = $this->uploadSession->create($this->testUserId, 'Test Size Download Session', 'Test Description');
        $session = $this->uploadSession->getByToken($token);
        
        // Criar arquivos de teste
        $testFilePath1 = '../uploads/test_size_download1.txt';
        $testFilePath2 = '../uploads/test_size_download2.txt';
        $testFilePath3 = '../uploads/test_size_download3.txt';
        
        file_put_contents($testFilePath1, 'Test content 1');
        file_put_contents($testFilePath2, 'Test content 2');
        file_put_contents($testFilePath3, 'Test content 3');
        
        $this->fileModel->create($session['id'], 'size1.txt', 'stored_size1.txt', $testFilePath1, 100, 'text/plain');
        $this->fileModel->create($session['id'], 'size2.txt', 'stored_size2.txt', $testFilePath2, 200, 'text/plain');
        $this->fileModel->create($session['id'], 'size3.txt', 'stored_size3.txt', $testFilePath3, 300, 'text/plain');
        
        $totalSize = $this->fileModel->getTotalSizeBySession($session['id']);
        
        $this->assertEquals(600, $totalSize, 'Tamanho total deveria ser 600 bytes');
        
        // Limpar arquivos de teste
        unlink($testFilePath1);
        unlink($testFilePath2);
        unlink($testFilePath3);
        
        return true;
    }

    private function testFileExistenceCheck() {
        // Criar uma sessão e arquivo
        $token = $this->uploadSession->create($this->testUserId, 'Test Existence Session', 'Test Description');
        $session = $this->uploadSession->getByToken($token);
        
        $testFilePath = '../uploads/test_existence.txt';
        file_put_contents($testFilePath, 'Test existence content');
        
        $fileId = $this->fileModel->create(
            $session['id'],
            'existence.txt',
            'stored_existence.txt',
            $testFilePath,
            1024,
            'text/plain'
        );
        
        // Verificar se o arquivo existe fisicamente
        $this->assertFileExists($testFilePath, 'Arquivo deveria existir fisicamente');
        
        // Limpar arquivo de teste
        unlink($testFilePath);
        
        return true;
    }

    private function testGetFileById() {
        // Criar uma sessão e arquivo
        $token = $this->uploadSession->create($this->testUserId, 'Test Get File Session', 'Test Description');
        $session = $this->uploadSession->getByToken($token);
        
        $testFilePath = '../uploads/test_get_file.txt';
        file_put_contents($testFilePath, 'Test get file content');
        
        $this->fileModel->create(
            $session['id'],
            'get_file.txt',
            'stored_get_file.txt',
            $testFilePath,
            2048,
            'text/plain'
        );
        
        // Obter arquivos da sessão para pegar o ID
        $files = $this->fileModel->getBySessionId($session['id']);
        $file = $files[0]; // Pegar o primeiro arquivo
        
        // Obter arquivo por ID
        $retrievedFile = $this->fileModel->getById($file['id']);
        
        $this->assertNotEmpty($retrievedFile, 'Arquivo deveria ser encontrado por ID');
        $this->assertEquals($file['original_name'], $retrievedFile['original_name'], 'Nome original deveria corresponder');
        $this->assertEquals($file['file_size'], $retrievedFile['file_size'], 'Tamanho deveria corresponder');
        
        // Limpar arquivo de teste
        unlink($testFilePath);
        
        return true;
    }

    private function testFileAccessValidation() {
        // Criar uma sessão e arquivo
        $token = $this->uploadSession->create($this->testUserId, 'Test Access Session', 'Test Description');
        $session = $this->uploadSession->getByToken($token);
        
        $testFilePath = '../uploads/test_access.txt';
        file_put_contents($testFilePath, 'Test access content');
        
        $this->fileModel->create(
            $session['id'],
            'access.txt',
            'stored_access.txt',
            $testFilePath,
            1024,
            'text/plain'
        );
        
        // Obter arquivos da sessão
        $files = $this->fileModel->getBySessionId($session['id']);
        $file = $files[0];
        
        // Verificar se o arquivo pertence à sessão correta
        $this->assertEquals($session['id'], $file['session_id'], 'Arquivo deveria pertencer à sessão correta');
        
        // Limpar arquivo de teste
        unlink($testFilePath);
        
        return true;
    }

    private function testBytesFormatting() {
        // Testar função de formatação de bytes
        $this->assertEquals('1.00 B', $this->formatBytes(1), '1 byte deveria ser formatado como 1.00 B');
        $this->assertEquals('1.00 KB', $this->formatBytes(1024), '1024 bytes deveria ser formatado como 1.00 KB');
        $this->assertEquals('1.00 MB', $this->formatBytes(1024 * 1024), '1MB deveria ser formatado como 1.00 MB');
        $this->assertEquals('1.00 GB', $this->formatBytes(1024 * 1024 * 1024), '1GB deveria ser formatado como 1.00 GB');
        
        return true;
    }

    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// Executar testes se chamado diretamente
if (php_sapi_name() === 'cli') {
    $test = new DownloadTest();
    $test->runAllTests();
}
?> 