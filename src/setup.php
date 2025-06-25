<?php
session_start();

require_once 'models/SystemSettings.php';
require_once 'models/User.php';
require_once 'models/EmailTemplate.php';

// Check if setup is already complete (only if tables exist)
try {
    $settings = new SystemSettings();
    if ($settings->isSetupComplete()) {
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    // Tables don't exist yet, continue with setup
    $settings = null;
}

$error = '';
$success = '';
$testEmailResult = '';
$currentStep = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Debug - log all POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST data received: " . print_r($_POST, true));
    error_log("next_step isset: " . (isset($_POST['next_step']) ? 'YES' : 'NO'));
    error_log("test_email isset: " . (isset($_POST['test_email']) ? 'YES' : 'NO'));
    error_log("complete_setup isset: " . (isset($_POST['complete_setup']) ? 'YES' : 'NO'));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle email test (can be done from any step)
        if (isset($_POST['test_email'])) {
            if (empty($_POST['smtp_host'])) {
                throw new Exception('Preencha o servidor SMTP para testar.');
            }
            if (empty($_POST['smtp_from_email'])) {
                throw new Exception('Preencha o email remetente para testar.');
            }
            
            // Use a default test email if admin email is not filled
            $testEmail = !empty($_POST['smtp_from_email']) ? $_POST['smtp_from_email'] : 'test@example.com';
            
            // Prepare SMTP config for test
            $smtpConfig = [
                'smtp_host' => $_POST['smtp_host'],
                'smtp_port' => (int)($_POST['smtp_port'] ?: 587),
                'smtp_username' => $_POST['smtp_username'] ?? '',
                'smtp_password' => $_POST['smtp_password'] ?? '',
                'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
                'smtp_from_email' => $_POST['smtp_from_email'],
                'smtp_from_name' => $_POST['smtp_from_name'] ?: 'Sistema de Upload'
            ];
            
            $emailTemplate = new EmailTemplate();
            $testResult = $emailTemplate->testEmailSettings(
                $testEmail,
                'Teste de Configuracao SMTP',
                'Este e um email de teste para verificar se as configuracoes SMTP estao funcionando corretamente.\n\nSe voce recebeu este email, suas configuracoes estao corretas!',
                $smtpConfig
            );
            
            if ($testResult) {
                $testEmailResult = "Email de teste enviado com sucesso para: $testEmail. Verifique sua caixa de entrada.";
            } else {
                throw new Exception('Falha ao enviar email de teste. Verifique as configurações SMTP.');
            }
        }
        // Handle step navigation
        elseif (isset($_POST['next_step'])) {
            $nextStep = (int)$_POST['next_step'];
            
            // Save data from current step
            switch($currentStep) {
                case 1: // Database configuration
                    $_SESSION['setup_db_config'] = [
                        'host' => $_POST['db_host'] ?? '',
                        'name' => $_POST['db_name'] ?? '',
                        'username' => $_POST['db_username'] ?? '',
                        'password' => $_POST['db_password'] ?? ''
                    ];
                    break;
                    
                case 2: // System configuration
                    $_SESSION['setup_system_config'] = [
                        'site_name' => $_POST['site_name'] ?? '',
                        'site_url' => rtrim($_POST['site_url'] ?? '', '/'),
                        'default_expiration_days' => (int)($_POST['default_expiration_days'] ?: 7),
                        'max_file_size' => (int)($_POST['max_file_size'] ?: 10737418240),
                        'available_expiration_days' => $_POST['available_expiration_days'] ?: '1,3,7,14,30'
                    ];
                    break;
                    
                case 3: // Admin user
                    $_SESSION['setup_admin_config'] = [
                        'username' => $_POST['admin_username'] ?? '',
                        'email' => $_POST['admin_email'] ?? '',
                        'password' => $_POST['admin_password'] ?? ''
                    ];
                    break;
                    
                case 4: // Email configuration
                    $_SESSION['setup_email_config'] = [
                        'smtp_host' => $_POST['smtp_host'] ?? '',
                        'smtp_port' => (int)($_POST['smtp_port'] ?: 587),
                        'smtp_username' => $_POST['smtp_username'] ?? '',
                        'smtp_password' => $_POST['smtp_password'] ?? '',
                        'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
                        'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
                        'smtp_from_name' => $_POST['smtp_from_name'] ?? 'Sistema de Upload'
                    ];
                    break;
            }
            
            // Debug - log the next step
            error_log("Next step requested: " . $nextStep);
            
            // Redirect to next step
            header("Location: /setup?step=" . $nextStep);
            exit;
        }
        // Final setup completion
        elseif (isset($_POST['complete_setup'])) {
            // Save all configurations
            $systemConfig = $_SESSION['setup_system_config'] ?? [];
            $adminConfig = $_SESSION['setup_admin_config'] ?? [];
            $emailConfig = $_SESSION['setup_email_config'] ?? [];
            $advancedConfig = [
                'cloudflare_tunnel_enabled' => !empty($_POST['cloudflare_tunnel_url']),
                'cloudflare_tunnel_url' => $_POST['cloudflare_tunnel_url'] ?? ''
            ];
            
            // Initialize settings if not already done
            if (!$settings) {
                $settings = new SystemSettings();
            }
            
            // Save system settings
            foreach ($systemConfig as $key => $value) {
                if ($key === 'available_expiration_days') {
                    $expirationDays = array_map('intval', explode(',', $value));
                    $settings->set($key, $expirationDays, 'json');
                } elseif (in_array($key, ['default_expiration_days', 'max_file_size'])) {
                    $settings->set($key, $value, 'number');
                } else {
                    $settings->set($key, $value);
                }
            }
            
            // Save email settings
            foreach ($emailConfig as $key => $value) {
                if ($key === 'smtp_port') {
                    $settings->set($key, $value, 'number');
                } else {
                    $settings->set($key, $value);
                }
            }
            
            // Save advanced settings
            foreach ($advancedConfig as $key => $value) {
                if ($key === 'cloudflare_tunnel_enabled') {
                    $settings->set($key, $value, 'boolean');
                } else {
                    $settings->set($key, $value);
                }
            }
            
            // Create admin user
            $user = new User();
            $adminId = $user->create(
                $adminConfig['username'],
                $adminConfig['email'],
                $adminConfig['password'],
                'admin'
            );
            
            if (!$adminId) {
                throw new Exception("Erro ao criar usuário administrador");
            }
            
            // Mark setup as complete
            $settings->markSetupComplete();
            
            // Clear setup session data
            unset($_SESSION['setup_db_config']);
            unset($_SESSION['setup_system_config']);
            unset($_SESSION['setup_admin_config']);
            unset($_SESSION['setup_email_config']);
            
            $success = "Configuração concluída com sucesso! Redirecionando...";
            header("refresh:2;url=index.php");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current timezone for display
date_default_timezone_set('America/Sao_Paulo');

// Get saved data from session
$dbConfig = $_SESSION['setup_db_config'] ?? [];
$systemConfig = $_SESSION['setup_system_config'] ?? [];
$adminConfig = $_SESSION['setup_admin_config'] ?? [];
$emailConfig = $_SESSION['setup_email_config'] ?? [];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração Inicial - Sistema de Upload</title>
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
            background: linear-gradient(135deg, #a8e6cf 0%, #88d8a3 50%, #7fcdcd 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .setup-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }
        .setup-header {
            background: linear-gradient(135deg, #4a7c59 0%, #6a9ba5 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .setup-header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .setup-header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        .setup-content {
            padding: 40px;
        }
        .progress-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 0 20px;
        }
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        .step::after {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            right: -50%;
            height: 2px;
            background: #dee2e6;
            z-index: 1;
        }
        .step:last-child::after {
            display: none;
        }
        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #dee2e6;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: 600;
            position: relative;
            z-index: 2;
        }
        .step-circle.active {
            background: #4a7c59;
            color: white;
        }
        .step-circle.completed {
            background: #28a745;
            color: white;
        }
        .step-label {
            font-size: 12px;
            color: #6c757d;
            font-weight: 600;
        }
        .step-content {
            display: none;
        }
        .step-content.active {
            display: block;
        }
        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            background: #f8f9fa;
        }
        .form-section h3 {
            color: #495057;
            margin-bottom: 20px;
            font-weight: 600;
            border-bottom: 2px solid #4a7c59;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-row {
            display: flex;
            gap: 20px;
        }
        .form-row .form-group {
            flex: 1;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #495057;
            font-weight: 600;
        }
        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #4a7c59;
            box-shadow: 0 0 0 3px rgba(74, 124, 89, 0.1);
        }
        .required {
            color: #dc3545;
        }
        .help-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
        .help-text code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            border: 1px solid #dee2e6;
        }
        .btn {
            background: linear-gradient(135deg, #4a7c59 0%, #6a9ba5 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 10px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(74, 124, 89, 0.3);
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
            box-shadow: 0 10px 25px rgba(108, 117, 125, 0.3);
        }
        .btn-test {
            background: #28a745;
            padding: 8px 16px;
            font-size: 14px;
            margin-left: 10px;
        }
        .btn-test:hover {
            background: #218838;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        .btn-test:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .test-email-container {
            display: flex;
            align-items: center;
            margin-top: 15px;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .step-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        .connection-status {
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 14px;
        }
        .connection-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .connection-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .step-content {
            display: none;
        }
        .step-content.active {
            display: block;
        }
        .step-content:not(.active) input,
        .step-content:not(.active) select,
        .step-content:not(.active) textarea {
            disabled: true;
        }
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            .step-navigation {
                flex-direction: column;
                gap: 10px;
            }
            .progress-indicator {
                overflow-x: auto;
                padding: 0 10px;
            }
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>🚀 Configuração Inicial</h1>
            <p>Configure seu sistema de compartilhamento de arquivos - Etapa <?php echo $currentStep; ?> de 5</p>
        </div>
        
        <div class="setup-content">
            <div class="progress-indicator">
                <div class="step">
                    <div class="step-circle <?php echo $currentStep == 1 ? 'active' : ($currentStep > 1 ? 'completed' : ''); ?>">1</div>
                    <div class="step-label">Banco de Dados</div>
                </div>
                <div class="step">
                    <div class="step-circle <?php echo $currentStep == 2 ? 'active' : ($currentStep > 2 ? 'completed' : ''); ?>">2</div>
                    <div class="step-label">Sistema</div>
                </div>
                <div class="step">
                    <div class="step-circle <?php echo $currentStep == 3 ? 'active' : ($currentStep > 3 ? 'completed' : ''); ?>">3</div>
                    <div class="step-label">Administrador</div>
                </div>
                <div class="step">
                    <div class="step-circle <?php echo $currentStep == 4 ? 'active' : ($currentStep > 4 ? 'completed' : ''); ?>">4</div>
                    <div class="step-label">Email</div>
                </div>
                <div class="step">
                    <div class="step-circle <?php echo $currentStep == 5 ? 'active' : ''; ?>">5</div>
                    <div class="step-label">Finalizar</div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <strong>Erro:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <strong>Sucesso:</strong> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($testEmailResult): ?>
                <div class="alert alert-info">
                    <strong>Teste de Email:</strong> <?php echo htmlspecialchars($testEmailResult); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/setup?step=<?php echo $currentStep; ?>">
                <!-- Step 1: Database Configuration -->
                <div class="step-content <?php echo $currentStep == 1 ? 'active' : ''; ?>">
                    <div class="form-section">
                        <h3>🗄️ Configuração do Banco de Dados</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="db_host">Servidor MySQL <span class="required">*</span></label>
                                <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($dbConfig['host'] ?? $_POST['db_host'] ?? 'db'); ?>" <?php echo $currentStep == 1 ? 'required' : 'disabled'; ?>>
                                <div class="help-text">Geralmente localhost ou IP do servidor MySQL</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="db_name">Nome do Banco <span class="required">*</span></label>
                                <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($dbConfig['name'] ?? $_POST['db_name'] ?? 'upload_system'); ?>" <?php echo $currentStep == 1 ? 'required' : 'disabled'; ?>>
                                <div class="help-text">Nome da base de dados MySQL</div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="db_username">Usuário MySQL <span class="required">*</span></label>
                                <input type="text" id="db_username" name="db_username" value="<?php echo htmlspecialchars($dbConfig['username'] ?? $_POST['db_username'] ?? 'upload_user'); ?>" <?php echo $currentStep == 1 ? 'required' : 'disabled'; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label for="db_password">Senha MySQL</label>
                                <input type="password" id="db_password" name="db_password" value="<?php echo htmlspecialchars($dbConfig['password'] ?? $_POST['db_password'] ?? 'upload_password'); ?>" <?php echo $currentStep == 1 ? '' : 'disabled'; ?>>
                                <div class="help-text">Deixe em branco se não houver senha</div>
                            </div>
                        </div>
                        
                        <button type="button" onclick="testDatabaseConnection()" class="btn btn-test">🧪 Testar Conexão</button>
                        <div id="connectionResult" class="connection-status" style="display: none;"></div>
                        
                        <div class="help-text" style="margin-top: 15px;">
                            <strong>Como funciona:</strong><br>
                            1️⃣ Use "🧪 Testar Conexão" para testar a conexão em tempo real<br>
                            2️⃣ Se conectar com sucesso, clique em "Próximo ➡️"<br>
                            3️⃣ Se houver erro, corrija os dados e teste novamente<br><br>
                            <strong>💡 Dica:</strong> Os campos já estão preenchidos com valores padrão para desenvolvimento Docker
                        </div>
                    </div>
                </div>

                <!-- Step 2: System Configuration -->
                <div class="step-content <?php echo $currentStep == 2 ? 'active' : ''; ?>">
                    <div class="form-section">
                        <h3>📋 Configurações do Sistema</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="site_name">Nome do Sistema <span class="required">*</span></label>
                                <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($systemConfig['site_name'] ?? $_POST['site_name'] ?? 'Pix Transfer'); ?>" <?php echo $currentStep == 2 ? 'required' : 'disabled'; ?>>
                                <div class="help-text">Nome que aparecerá no topo do sistema e nos emails</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="site_url">URL do Sistema <span class="required">*</span></label>
                                <input type="url" id="site_url" name="site_url" value="<?php echo htmlspecialchars($systemConfig['site_url'] ?? $_POST['site_url'] ?? 'http://localhost:3131'); ?>" <?php echo $currentStep == 2 ? 'required' : 'disabled'; ?>>
                                <div class="help-text">URL completa onde o sistema estará acessível</div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="default_expiration_days">Dias de Expiração Padrão</label>
                                <input type="number" id="default_expiration_days" name="default_expiration_days" value="<?php echo htmlspecialchars($systemConfig['default_expiration_days'] ?? $_POST['default_expiration_days'] ?? '7'); ?>" min="1" max="365" <?php echo $currentStep == 2 ? '' : 'disabled'; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_file_size">Tamanho Máximo por Arquivo (bytes)</label>
                                <input type="number" id="max_file_size" name="max_file_size" value="<?php echo htmlspecialchars($systemConfig['max_file_size'] ?? $_POST['max_file_size'] ?? '10737418240'); ?>" <?php echo $currentStep == 2 ? '' : 'disabled'; ?>>
                                <div class="help-text">10737418240 = 10GB</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="available_expiration_days">Opções de Expiração (separadas por vírgula)</label>
                            <input type="text" id="available_expiration_days" name="available_expiration_days" value="<?php echo htmlspecialchars($systemConfig['available_expiration_days'] ?? $_POST['available_expiration_days'] ?? '1,3,7,14,30'); ?>" <?php echo $currentStep == 2 ? '' : 'disabled'; ?>>
                            <div class="help-text">Dias disponíveis no dropdown de expiração</div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Admin User -->
                <div class="step-content <?php echo $currentStep == 3 ? 'active' : ''; ?>">
                    <div class="form-section">
                        <h3>👤 Usuário Administrador</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="admin_username">Nome de Usuário <span class="required">*</span></label>
                                <input type="text" id="admin_username" name="admin_username" value="<?php echo htmlspecialchars($adminConfig['username'] ?? $_POST['admin_username'] ?? 'admin'); ?>" <?php echo $currentStep == 3 ? 'required' : 'disabled'; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label for="admin_email">Email do Administrador <span class="required">*</span></label>
                                <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($adminConfig['email'] ?? $_POST['admin_email'] ?? ''); ?>" <?php echo $currentStep == 3 ? 'required' : 'disabled'; ?>>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_password">Senha do Administrador <span class="required">*</span></label>
                            <input type="password" id="admin_password" name="admin_password" <?php echo $currentStep == 3 ? 'required' : 'disabled'; ?>>
                            <div class="help-text">Mínimo 6 caracteres</div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Email Configuration -->
                <div class="step-content <?php echo $currentStep == 4 ? 'active' : ''; ?>">
                    <div class="form-section">
                        <h3>📧 Configurações de Email (SMTP) - Opcional</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="smtp_host">Servidor SMTP</label>
                                <input type="text" id="smtp_host" name="smtp_host" value="<?php echo $currentStep == 4 ? htmlspecialchars($emailConfig['smtp_host'] ?? $_POST['smtp_host'] ?? '') : ''; ?>" <?php echo $currentStep == 4 ? '' : 'disabled'; ?>>
                                <div class="help-text">Deixe vazio para usar o mail() do PHP</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_port">Porta SMTP</label>
                                <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($emailConfig['smtp_port'] ?? $_POST['smtp_port'] ?? '587'); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="smtp_username">Usuário SMTP</label>
                                <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($emailConfig['smtp_username'] ?? $_POST['smtp_username'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_password">Senha SMTP</label>
                                <input type="password" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($emailConfig['smtp_password'] ?? $_POST['smtp_password'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="smtp_encryption">Criptografia</label>
                                <select id="smtp_encryption" name="smtp_encryption">
                                    <option value="tls" <?php echo ($emailConfig['smtp_encryption'] ?? $_POST['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo ($emailConfig['smtp_encryption'] ?? $_POST['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                    <option value="" <?php echo ($emailConfig['smtp_encryption'] ?? $_POST['smtp_encryption'] ?? '') === '' ? 'selected' : ''; ?>>Nenhuma</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_from_email">Email Remetente</label>
                                <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($emailConfig['smtp_from_email'] ?? $_POST['smtp_from_email'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_from_name">Nome do Remetente</label>
                            <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($emailConfig['smtp_from_name'] ?? $_POST['smtp_from_name'] ?? 'Sistema de Upload'); ?>">
                        </div>
                        
                        <?php if ($currentStep == 4): ?>
                        <div class="test-email-container">
                            <div class="help-text">
                                <strong>Para testar:</strong> Preencha Servidor SMTP e Email Remetente. O teste será enviado para o email remetente.
                            </div>
                            <button type="submit" name="test_email" class="btn btn-test">📧 Testar Email</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Step 5: Final Configuration -->
                <div class="step-content <?php echo $currentStep == 5 ? 'active' : ''; ?>">
                    <div class="form-section">
                        <h3>🔧 Configurações Avançadas e Finalização</h3>
                        
                        <div class="form-group">
                            <label for="cloudflare_tunnel_url">URL do Cloudflare Tunnel (opcional)</label>
                            <input type="url" id="cloudflare_tunnel_url" name="cloudflare_tunnel_url" value="<?php echo htmlspecialchars($_POST['cloudflare_tunnel_url'] ?? ''); ?>" placeholder="https://exemplo-tunnel.trycloudflare.com">
                            <div class="help-text">
                                <strong>Para usar Cloudflare Tunnel:</strong><br>
                                1. Instale o cloudflared e configure um tunnel<br>
                                2. Execute: <code>cloudflared tunnel --url http://localhost:3131</code><br>
                                3. Cole a URL fornecida (ex: https://exemplo-tunnel.trycloudflare.com)<br>
                                <em>O sistema detectará automaticamente IPs reais através dos headers do Cloudflare</em>
                            </div>
                        </div>

                        <div style="background: #e3f2fd; padding: 20px; border-radius: 10px; margin-top: 20px;">
                            <h4 style="color: #1976d2; margin-bottom: 15px;">📋 Resumo da Configuração</h4>
                            
                            <div style="display: grid; gap: 10px;">
                                <div><strong>Sistema:</strong> <?php echo htmlspecialchars($systemConfig['site_name'] ?? 'Não configurado'); ?></div>
                                <div><strong>URL:</strong> <?php echo htmlspecialchars($systemConfig['site_url'] ?? 'Não configurado'); ?></div>
                                <div><strong>Admin:</strong> <?php echo htmlspecialchars($adminConfig['username'] ?? 'Não configurado'); ?> (<?php echo htmlspecialchars($adminConfig['email'] ?? 'Não configurado'); ?>)</div>
                                <div><strong>SMTP:</strong> <?php echo !empty($emailConfig['smtp_host']) ? 'Configurado' : 'Não configurado (usará PHP mail)'; ?></div>
                                <div><strong>Banco:</strong> <?php echo !empty($dbConfig['host']) ? $dbConfig['host'] . '/' . $dbConfig['name'] : 'Não configurado'; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation buttons -->
                <div class="step-navigation">
                    <div>
                        <?php if ($currentStep > 1): ?>
                            <a href="?step=<?php echo $currentStep - 1; ?>" class="btn btn-secondary">⬅️ Anterior</a>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <?php if ($currentStep < 5): ?>
                            <button type="submit" name="next_step" value="<?php echo $currentStep + 1; ?>" class="btn">Próximo ➡️</button>
                        <?php else: ?>
                            <button type="submit" name="complete_setup" class="btn" style="background: #28a745;">🚀 Finalizar Configuração</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        async function testDatabaseConnection() {
            const host = document.getElementById('db_host').value;
            const dbname = document.getElementById('db_name').value;
            const username = document.getElementById('db_username').value;
            const password = document.getElementById('db_password').value;
            
            if (!host || !dbname || !username) {
                alert('Preencha pelo menos Servidor, Nome do Banco e Usuário');
                return;
            }
            
            const resultDiv = document.getElementById('connectionResult');
            const testButton = document.querySelector('button[onclick="testDatabaseConnection()"]');
            
            // Show loading state
            resultDiv.style.display = 'block';
            resultDiv.className = 'connection-status';
            resultDiv.innerHTML = '⏳ Testando conexão com o banco de dados...';
            testButton.disabled = true;
            testButton.innerHTML = '⏳ Testando...';
            
            try {
                const response = await fetch('test_db_connection', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        host: host,
                        dbname: dbname,
                        username: username,
                        password: password
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    resultDiv.className = 'connection-status connection-success';
                    resultDiv.innerHTML = `✅ ${result.message}`;
                    if (result.details) {
                        resultDiv.innerHTML += `<br><small>Conectado a: ${result.details.host}/${result.details.database} como ${result.details.user}</small>`;
                    }
                } else {
                    resultDiv.className = 'connection-status connection-error';
                    resultDiv.innerHTML = `❌ ${result.message}`;
                }
                
            } catch (error) {
                resultDiv.className = 'connection-status connection-error';
                if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
                    resultDiv.innerHTML = '❌ Erro de conectividade: Verifique se o servidor está acessível';
                } else {
                    resultDiv.innerHTML = '❌ Erro ao testar conexão: ' + error.message;
                }
            } finally {
                // Restore button state
                testButton.disabled = false;
                testButton.innerHTML = '🧪 Testar Conexão';
            }
        }
        
        // Add loading state to form submissions
        function showLoadingState(button, text) {
            button.disabled = true;
            button.innerHTML = '⏳ ' + text;
        }
        
        function hideLoadingState(button, originalText) {
            button.disabled = false;
            button.innerHTML = originalText;
        }

        // No form interference - let it work normally
    </script>
</body>
</html>