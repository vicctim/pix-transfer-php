<?php
session_start();

// Se já estiver logado, redireciona para dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adicionar log para a tentativa de login
    file_put_contents('/var/www/html/logs/app.log', "[LOGIN_ATTEMPT] Tentativa de login recebida para o usuário: " . $_POST['username'] . "\n", FILE_APPEND);

    require_once 'models/User.php';
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $user = new User();
        if ($user->authenticate($username, $password)) {
            $_SESSION['user_id'] = $user->id;
            $_SESSION['username'] = $user->username;
            $_SESSION['email'] = $user->email;
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Usuário ou senha incorretos';
        }
    } else {
        $error = 'Por favor, preencha todos os campos';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload System - Login</title>
    <link rel="icon" type="image/png" href="src/img/favicon.png">
    <style>
        @import url('https://fonts.googleapis.com/css?family=Titillium+Web:400,600,700');
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Titillium Web', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f7fcf5;
            color: #333;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #616161;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        .header h1 {
            font-size: 2rem;
            margin-bottom: 5px;
        }
        .header h1 span {
            color: #7cb342;
        }
        .header p {
            color: #757575;
            font-size: 1.1rem;
        }
        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            padding: 40px;
            width: 100%;
            max-width: 420px;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
        }
        .btn {
            width: 100%;
            padding: 15px;
            background: #7cb342;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }
        .error {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            border: 1px solid #fcc;
        }
        .demo-info {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 8px;
            padding: 15px;
            margin-top: 25px;
            font-size: 0.9rem;
            color: #388e3c;
            text-align: left;
        }
        .demo-info strong {
            display: block;
            margin-bottom: 5px;
        }
        .links {
            margin-top: 20px;
            font-size: 0.9rem;
            text-align: center;
        }
        .links a {
            color: #7cb342;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="src/img/logo.png" alt="Logo" style="width: 120px; height: 120px; margin-bottom: 20px;">
        <h1>Compartilhamento de arquivos <span>Pix Filmes</span></h1>
        <p>Faça login para começar a enviar arquivos</p>
    </div>

    <div class="login-container">
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Email</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" required value="">
            </div>
            <button type="submit" class="btn">Entrar</button>
        </form>
    </div>

    <div class="demo-info">
        <strong>Informações de demonstração:</strong>
        <p>Usuário: admin@transfer.com</p>
        <p>Senha: password</p>
    </div>
</body>
</html> 