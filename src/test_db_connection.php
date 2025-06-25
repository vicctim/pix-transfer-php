<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$host = $data['host'] ?? '';
$dbname = $data['dbname'] ?? '';
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (empty($host) || empty($dbname) || empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Preencha pelo menos Servidor, Nome do Banco e Usuário']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5 // 5 second timeout
        ]
    );
    
    // Test if we can actually query the database
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    
    if ($result && $result['test'] == 1) {
        echo json_encode([
            'success' => true, 
            'message' => 'Conexão estabelecida com sucesso! Banco de dados acessível.',
            'details' => [
                'host' => $host,
                'database' => $dbname,
                'user' => $username
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Conexão estabelecida mas não foi possível executar consultas']);
    }
    
} catch (PDOException $e) {
    $errorMsg = $e->getMessage();
    
    if (strpos($errorMsg, 'Access denied') !== false) {
        echo json_encode(['success' => false, 'message' => 'Erro de autenticação: Usuário ou senha incorretos']);
    } elseif (strpos($errorMsg, 'Unknown database') !== false) {
        echo json_encode(['success' => false, 'message' => "Erro: O banco de dados '{$dbname}' não existe no servidor"]);
    } elseif (strpos($errorMsg, 'Connection refused') !== false || strpos($errorMsg, 'Connection timed out') !== false) {
        echo json_encode(['success' => false, 'message' => "Erro: Não foi possível conectar ao servidor MySQL em '{$host}'"]);
    } elseif (strpos($errorMsg, 'Name or service not known') !== false) {
        echo json_encode(['success' => false, 'message' => "Erro: Servidor '{$host}' não encontrado"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro de conexão: ' . $errorMsg]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro inesperado: ' . $e->getMessage()]);
}
?>