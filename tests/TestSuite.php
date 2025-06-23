<?php
/**
 * TestSuite - Classe base para testes unitários
 */
class TestSuite {
    protected $testResults = [];
    protected $passed = 0;
    protected $failed = 0;
    protected $total = 0;

    public function runTest($testName, $testFunction) {
        $this->total++;
        echo "🧪 Executando: $testName... ";
        
        try {
            $result = $testFunction();
            if ($result === true) {
                echo "✅ PASSOU\n";
                $this->passed++;
                $this->testResults[$testName] = ['status' => 'PASSED', 'message' => 'Teste executado com sucesso'];
            } else {
                echo "❌ FALHOU\n";
                $this->failed++;
                $this->testResults[$testName] = ['status' => 'FAILED', 'message' => $result];
            }
        } catch (Exception $e) {
            echo "❌ ERRO\n";
            $this->failed++;
            $this->testResults[$testName] = ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    public function assertTrue($condition, $message = '') {
        if (!$condition) {
            throw new Exception($message ?: 'Assertion failed: expected true');
        }
        return true;
    }

    public function assertFalse($condition, $message = '') {
        if ($condition) {
            throw new Exception($message ?: 'Assertion failed: expected false');
        }
        return true;
    }

    public function assertEquals($expected, $actual, $message = '') {
        if ($expected !== $actual) {
            throw new Exception($message ?: "Assertion failed: expected '$expected', got '$actual'");
        }
        return true;
    }

    public function assertNotEmpty($value, $message = '') {
        if (empty($value)) {
            throw new Exception($message ?: 'Assertion failed: expected non-empty value');
        }
        return true;
    }

    public function assertFileExists($filePath, $message = '') {
        if (!file_exists($filePath)) {
            throw new Exception($message ?: "File does not exist: $filePath");
        }
        return true;
    }

    public function printResults() {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 RESULTADOS DOS TESTES\n";
        echo str_repeat("=", 60) . "\n";
        echo "Total de testes: {$this->total}\n";
        echo "✅ Passou: {$this->passed}\n";
        echo "❌ Falhou: {$this->failed}\n";
        echo "📈 Taxa de sucesso: " . round(($this->passed / $this->total) * 100, 2) . "%\n\n";

        if ($this->failed > 0) {
            echo "🔍 DETALHES DOS FALHOS:\n";
            echo str_repeat("-", 40) . "\n";
            foreach ($this->testResults as $testName => $result) {
                if ($result['status'] !== 'PASSED') {
                    echo "❌ $testName: {$result['message']}\n";
                }
            }
        }

        echo "\n" . str_repeat("=", 60) . "\n";
    }
}
?> 