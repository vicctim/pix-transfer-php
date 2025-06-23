<?php
require_once 'TestSuite.php';
require_once 'models/User.php';
require_once 'config/database.php';

class LoginTest extends TestSuite {
    private $user;

    public function __construct() {
        $this->user = new User();
    }

    public function runAllTests() {
        echo "🔐 TESTES DE LOGIN\n";
        echo str_repeat("=", 40) . "\n";

        $this->runTest("Teste de autenticação válida", function() {
            return $this->testValidAuthentication();
        });

        $this->runTest("Teste de autenticação inválida", function() {
            return $this->testInvalidAuthentication();
        });

        $this->runTest("Teste de autenticação com usuário inexistente", function() {
            return $this->testNonExistentUser();
        });

        $this->runTest("Teste de autenticação com senha vazia", function() {
            return $this->testEmptyPassword();
        });

        $this->runTest("Teste de autenticação com usuário vazio", function() {
            return $this->testEmptyUsername();
        });

        $this->runTest("Teste de login por email", function() {
            return $this->testLoginByEmail();
        });

        $this->runTest("Teste de obtenção de usuário por ID", function() {
            return $this->testGetUserById();
        });

        $this->printResults();
    }

    private function testValidAuthentication() {
        $result = $this->user->authenticate('admin', 'password');
        $this->assertTrue($result, 'Autenticação válida deveria retornar true');
        $this->assertEquals('admin', $this->user->username, 'Username deveria ser admin');
        $this->assertEquals('victor@pixfilmes.com', $this->user->email, 'Email deveria ser victor@pixfilmes.com');
        return true;
    }

    private function testInvalidAuthentication() {
        $result = $this->user->authenticate('admin', 'wrongpassword');
        $this->assertFalse($result, 'Autenticação inválida deveria retornar false');
        return true;
    }

    private function testNonExistentUser() {
        $result = $this->user->authenticate('nonexistent', 'password');
        $this->assertFalse($result, 'Usuário inexistente deveria retornar false');
        return true;
    }

    private function testEmptyPassword() {
        $result = $this->user->authenticate('admin', '');
        $this->assertFalse($result, 'Senha vazia deveria retornar false');
        return true;
    }

    private function testEmptyUsername() {
        $result = $this->user->authenticate('', 'password');
        $this->assertFalse($result, 'Usuário vazio deveria retornar false');
        return true;
    }

    private function testLoginByEmail() {
        $result = $this->user->authenticate('victor@pixfilmes.com', 'password');
        $this->assertTrue($result, 'Login por email deveria funcionar');
        $this->assertEquals('admin', $this->user->username, 'Username deveria ser admin');
        return true;
    }

    private function testGetUserById() {
        // Primeiro autenticar para obter o ID
        $this->user->authenticate('admin', 'password');
        $userData = $this->user->getById($this->user->id);
        
        $this->assertNotEmpty($userData, 'Dados do usuário não deveriam estar vazios');
        $this->assertEquals('admin', $userData['username'], 'Username deveria ser admin');
        $this->assertEquals('victor@pixfilmes.com', $userData['email'], 'Email deveria ser victor@pixfilmes.com');
        return true;
    }
}

// Executar testes se chamado diretamente
if (php_sapi_name() === 'cli') {
    $test = new LoginTest();
    $test->runAllTests();
}
?> 