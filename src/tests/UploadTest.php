<?php
require_once 'TestSuite.php';
require_once 'models/UploadSession.php';
require_once 'models/File.php';
require_once 'models/User.php';
require_once 'config/database.php';

class UploadTest extends TestSuite {
    private $uploadSession;
    private $fileModel;
    private $user;
    private $testUserId = 6;

    public function __construct() {
        $this->uploadSession = new UploadSession();
        $this->fileModel = new File();
        $this->user = new User();
    }

    public function runAllTests() {
        echo "📤 TESTES DE UPLOAD\n";
        echo str_repeat("=", 40) . "\n";

        $this->runTest("Teste de criação de sessão de upload", function() {
            return $this->testCreateUploadSession();
        });

        $this->runTest("Teste de obtenção de sessão por token", function() {
            return $this->testGetSessionByToken();
        });

        $this->runTest("Teste de obtenção de sessões por usuário", function() {
            return $this->testGetSessionsByUser();
        });

        $this->runTest("Teste de criação de arquivo", function() {
            return $this->testCreateFile();
        });

        $this->runTest("Teste de obtenção de arquivos por sessão", function() {
            return $this->testGetFilesBySession();
        });

        $this->runTest("Teste de cálculo de tamanho total", function() {
            return $this->testTotalSizeCalculation();
        });

        $this->runTest("Teste de contagem de arquivos", function() {
            return $this->testFileCount();
        });

        $this->runTest("Teste de validação de token", function() {
            return $this->testTokenValidation();
        });

        $this->runTest("Teste de limpeza de sessões expiradas", function() {
            return $this->testCleanupExpired();
        });

        $this->printResults();
    }

    private function testCreateUploadSession() {
        $token = $this->uploadSession->create($this->testUserId, 'Test Upload', 'test@example.com', 7);
        
        $this->assertNotEmpty($token, 'Token não deveria estar vazio');
        $this->assertTrue(strlen($token) >= 32, 'Token deveria ter pelo menos 32 caracteres');
        
        return true;
    }

    private function testGetSessionByToken() {
        // Criar uma sessão primeiro
        $token = $this->uploadSession->create($this->testUserId, 'Test Session', 'test@example.com', 7);
        $session = $this->uploadSession->getByToken($token);
        
        $this->assertNotEmpty($session, 'Sessão deveria ser encontrada');
        $this->assertEquals($this->testUserId, $session['user_id'], 'User ID deveria corresponder');
        $this->assertEquals('Test Session', $session['title'], 'Título deveria corresponder');
        $this->assertEquals('test@example.com', $session['recipient_email'], 'Descrição deveria corresponder');
        
        return true;
    }

    private function testGetSessionsByUser() {
        $sessions = $this->uploadSession->getByUserId($this->testUserId);
        
        $this->assertTrue(is_array($sessions), 'Sessões deveriam ser um array');
        $this->assertTrue(count($sessions) > 0, 'Deveria haver pelo menos uma sessão');
        
        return true;
    }

    private function testCreateFile() {
        // Criar uma sessão primeiro
        $token = $this->uploadSession->create($this->testUserId, 'Test File Session', 'test@example.com', 7);
        $session = $this->uploadSession->getByToken($token);
        
        // Criar um arquivo de teste
        $testFilePath = 'uploads/test_file.txt';
        file_put_contents($testFilePath, 'Test content');
        
        $result = $this->fileModel->create(
            $session['id'],
            'test_file.txt',
            'test_stored_name.txt',
            $testFilePath,
            1024,
            'text/plain'
        );
        
        $this->assertTrue($result, 'Arquivo deveria ser criado com sucesso');
        
        // Limpar arquivo de teste
        unlink($testFilePath);
        
        return true;
    }

    private function testGetFilesBySession() {
        // Criar uma sessão e arquivo
        $token = $this->uploadSession->create($this->testUserId, 'Test Files Session', 'test@example.com', 7);
        $session = $this->uploadSession->getByToken($token);
        
        $testFilePath = 'uploads/test_file2.txt';
        file_put_contents($testFilePath, 'Test content 2');
        
        $this->fileModel->create(
            $session['id'],
            'test_file2.txt',
            'test_stored_name2.txt',
            $testFilePath,
            2048,
            'text/plain'
        );
        
        $files = $this->fileModel->getBySessionId($session['id']);
        
        $this->assertTrue(is_array($files), 'Arquivos deveriam ser um array');
        $this->assertTrue(count($files) > 0, 'Deveria haver pelo menos um arquivo');
        
        // Limpar arquivo de teste
        unlink($testFilePath);
        
        return true;
    }

    private function testTotalSizeCalculation() {
        // Criar uma sessão e arquivos
        $token = $this->uploadSession->create($this->testUserId, 'Test Size Session', 'test@example.com', 7);
        $session = $this->uploadSession->getByToken($token);
        
        $testFilePath1 = 'uploads/test_size1.txt';
        $testFilePath2 = 'uploads/test_size2.txt';
        
        file_put_contents($testFilePath1, 'Test content 1');
        file_put_contents($testFilePath2, 'Test content 2');
        
        $this->fileModel->create($session['id'], 'test1.txt', 'stored1.txt', $testFilePath1, 100, 'text/plain');
        $this->fileModel->create($session['id'], 'test2.txt', 'stored2.txt', $testFilePath2, 200, 'text/plain');
        
        $totalSize = $this->fileModel->getTotalSizeBySession($session['id']);
        
        $this->assertEquals(300, $totalSize, 'Tamanho total deveria ser 300 bytes');
        
        // Limpar arquivos de teste
        unlink($testFilePath1);
        unlink($testFilePath2);
        
        return true;
    }

    private function testFileCount() {
        // Criar uma sessão e arquivos
        $token = $this->uploadSession->create($this->testUserId, 'Test Count Session', 'test@example.com', 7);
        $session = $this->uploadSession->getByToken($token);
        
        $testFilePath1 = 'uploads/test_count1.txt';
        $testFilePath2 = 'uploads/test_count2.txt';
        $testFilePath3 = 'uploads/test_count3.txt';
        
        file_put_contents($testFilePath1, 'Test content 1');
        file_put_contents($testFilePath2, 'Test content 2');
        file_put_contents($testFilePath3, 'Test content 3');
        
        $this->fileModel->create($session['id'], 'test1.txt', 'stored1.txt', $testFilePath1, 100, 'text/plain');
        $this->fileModel->create($session['id'], 'test2.txt', 'stored2.txt', $testFilePath2, 100, 'text/plain');
        $this->fileModel->create($session['id'], 'test3.txt', 'stored3.txt', $testFilePath3, 100, 'text/plain');
        
        $fileCount = $this->fileModel->getCountBySession($session['id']);
        
        $this->assertEquals(3, $fileCount, 'Contagem de arquivos deveria ser 3');
        
        // Limpar arquivos de teste
        unlink($testFilePath1);
        unlink($testFilePath2);
        unlink($testFilePath3);
        
        return true;
    }

    private function testTokenValidation() {
        // Testar token válido
        $token = $this->uploadSession->create($this->testUserId, 'Test Token Session', 'test@example.com', 7);
        $session = $this->uploadSession->getByToken($token);
        $this->assertNotEmpty($session, 'Token válido deveria retornar sessão');
        
        // Testar token inválido
        $invalidSession = $this->uploadSession->getByToken('invalid_token_12345');
        $this->assertFalse($invalidSession, 'Token inválido deveria retornar false');
        
        return true;
    }

    private function testCleanupExpired() {
        // Este teste verifica se a função de limpeza existe e pode ser executada
        $result = $this->uploadSession->cleanupExpired();
        $this->assertTrue($result !== null, 'Limpeza de sessões expiradas deveria retornar um valor');
        
        return true;
    }
}

// Executar testes se chamado diretamente
if (php_sapi_name() === 'cli') {
    $test = new UploadTest();
    $test->runAllTests();
}
?> 