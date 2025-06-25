<?php
session_start();

require_once 'models/SystemSettings.php';

$settings = new SystemSettings();

// Check if setup is complete
if (!$settings->isSetupComplete()) {
    header('Location: setup.php');
    exit();
}

// Set timezone
date_default_timezone_set($settings->getTimezone());

// Se já estiver logado, redireciona para dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard');
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
            $_SESSION['role'] = $user->role;
            header('Location: ../dashboard');
            exit();
        } else {
            $error = 'Usuário ou senha incorretos';
        }
    } else {
        $error = 'Preencha todos os campos';
    }
}

$siteName = $settings->get('site_name', 'Pix Transfer');
$siteLogo = $settings->get('site_logo', 'src/img/logo.png');
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?> - Login</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($settings->get('site_favicon', 'src/img/favicon.png')); ?>">
    <style>
        @import url('https://fonts.googleapis.com/css?family=Titillium+Web:400,600,700');
        
        :root {
            --primary-color: <?php echo $settings->get('primary_color', '#4a7c59'); ?>;
            --secondary-color: <?php echo $settings->get('secondary_color', '#6a9ba5'); ?>;
            --background-color: <?php echo $settings->get('background_color', '#f7fcf5'); ?>;
            --text-color: <?php echo $settings->get('text_color', '#333333'); ?>;
            --success-color: <?php echo $settings->get('success_color', '#28a745'); ?>;
            --error-color: <?php echo $settings->get('error_color', '#dc3545'); ?>;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Titillium Web', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--background-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
            padding: 20px;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo {
            max-width: 120px;
            height: auto;
            margin-bottom: 20px;
        }
        
        .site-name {
            color: var(--text-color);
            font-size: 2.5em;
            font-weight: 700;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            border: 1px solid #e0e0e0;
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #495057;
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 124, 89, 0.1);
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            font-size: 14px;
        }
        
        .login-button {
            width: 100%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .login-button:hover {
            opacity: 0.9;
        }
        
        
        @media (max-width: 768px) {
            .login-container {
                padding: 30px 20px;
            }
            
            .site-name {
                font-size: 2em;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="logo-container">
        <?php if ($siteLogo && file_exists($siteLogo)): ?>
            <img src="<?php echo htmlspecialchars($siteLogo); ?>" alt="<?php echo htmlspecialchars($siteName); ?>" class="logo">
        <?php endif; ?>
        <h1 class="site-name"><?php echo htmlspecialchars($siteName); ?></h1>
    </div>

    <div class="login-container">
        <div class="login-header">
            <h2>Acesso ao Sistema</h2>
            <p>Faça login para compartilhar seus arquivos</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i>
                    Usuário ou Email
                </label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i>
                    Senha
                </label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="login-button">
                <i class="fas fa-sign-in-alt"></i>
                Entrar
            </button>
        </form>
    </div>

    <script>
        // Auto-focus no primeiro campo
        document.getElementById('username').focus();
        
        // Enter key navigation
        document.getElementById('username').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('password').focus();
            }
        });
    </script>
</body>
</html>

<?php
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>