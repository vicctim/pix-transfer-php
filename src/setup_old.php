<?php
session_start();

require_once 'models/SystemSettings.php';
require_once 'models/User.php';
require_once 'models/EmailTemplate.php';

$settings = new SystemSettings();

// Redirect if setup is already complete
if ($settings->isSetupComplete()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$testEmailResult = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle email test
        if (isset($_POST['test_email'])) {
            if (empty($_POST['smtp_host'])) {
                throw new Exception('Preencha o servidor SMTP para testar.');
            }
            if (empty($_POST['smtp_from_email'])) {
                throw new Exception('Preencha o email remetente para testar.');
            }
            
            // Use a default test email if admin email is not filled
            $testEmail = !empty($_POST['admin_email']) ? $_POST['admin_email'] : $_POST['smtp_from_email'];
            
            // Prepare SMTP config for test
            $smtpConfig = [
                'smtp_host' => $_POST['smtp_host'],
                'smtp_port' => (int)$_POST['smtp_port'],
                'smtp_username' => $_POST['smtp_username'],
                'smtp_password' => $_POST['smtp_password'],
                'smtp_encryption' => $_POST['smtp_encryption'],
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
            
            // Stop here for email test - don't continue with setup process
            $error = '';
            $success = '';
        } else {
        
        // Validate required fields
        $required = ['site_name', 'site_url', 'admin_email', 'admin_password', 'admin_username'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Campo obrigatório: " . $field);
            }
        }
        
        // Save system settings
        $settings->set('site_name', $_POST['site_name']);
        $settings->set('site_url', rtrim($_POST['site_url'], '/'));
        $settings->set('admin_email', $_POST['admin_email']);
        $settings->set('default_expiration_days', (int)$_POST['default_expiration_days'], 'number');
        $settings->set('max_file_size', (int)$_POST['max_file_size'], 'number');
        
        // Expiration days array
        $expirationDays = array_map('intval', explode(',', $_POST['available_expiration_days']));
        $settings->set('available_expiration_days', $expirationDays, 'json');
        
        // SMTP settings
        if (!empty($_POST['smtp_host'])) {
            $settings->set('smtp_host', $_POST['smtp_host']);
            $settings->set('smtp_port', (int)$_POST['smtp_port'], 'number');
            $settings->set('smtp_username', $_POST['smtp_username']);
            $settings->set('smtp_password', $_POST['smtp_password']);
            $settings->set('smtp_encryption', $_POST['smtp_encryption']);
            $settings->set('smtp_from_email', $_POST['smtp_from_email']);
            $settings->set('smtp_from_name', $_POST['smtp_from_name']);
        }
        
        // Cloudflare tunnel settings
        if (!empty($_POST['cloudflare_tunnel_url'])) {
            $settings->set('cloudflare_tunnel_enabled', true, 'boolean');
            $settings->set('cloudflare_tunnel_url', rtrim($_POST['cloudflare_tunnel_url'], '/'));
        }
        
        // Create admin user
        $user = new User();
        $adminId = $user->create($_POST['admin_username'], $_POST['admin_email'], $_POST['admin_password'], 'admin');
        
        if (!$adminId) {
            throw new Exception("Erro ao criar usuário administrador");
        }
        
        // Mark setup as complete
        $settings->markSetupComplete();
        
        $success = "Configuração concluída com sucesso! Redirecionando...";
        header("refresh:2;url=index.php");
        
        } // End of else block for setup process
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current timezone for display
date_default_timezone_set('America/Sao_Paulo');
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
            width: 100%;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(74, 124, 89, 0.3);
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
            background: #4a7c59;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: 600;
            position: relative;
            z-index: 2;
        }
        .step-label {
            font-size: 12px;
            color: #6c757d;
            font-weight: 600;
        }
        .btn-test {
            background: #28a745;
            width: auto;
            padding: 8px 16px;
            font-size: 14px;
            margin-left: 10px;
        }
        .btn-test:hover {
            background: #218838;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        .test-email-container {
            display: flex;
            align-items: center;
            margin-top: 15px;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .help-text code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>🚀 Configuração Inicial</h1>
            <p>Configure seu sistema de compartilhamento de arquivos</p>
        </div>
        
        <div class="setup-content">
            <div class="progress-indicator">
                <div class="step">
                    <div class="step-circle">1</div>
                    <div class="step-label">Sistema</div>
                </div>
                <div class="step">
                    <div class="step-circle">2</div>
                    <div class="step-label">Admin</div>
                </div>
                <div class="step">
                    <div class="step-circle">3</div>
                    <div class="step-label">Email</div>
                </div>
                <div class="step">
                    <div class="step-circle">4</div>
                    <div class="step-label">Avançado</div>
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

            <form method="POST">
                <!-- Configurações do Sistema -->
                <div class="form-section">
                    <h3>📋 Configurações do Sistema</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="site_name">Nome do Sistema <span class="required">*</span></label>
                            <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($_POST['site_name'] ?? 'Pix Transfer'); ?>" required>
                            <div class="help-text">Nome que aparecerá no topo do sistema e nos emails</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="site_url">URL do Sistema <span class="required">*</span></label>
                            <input type="url" id="site_url" name="site_url" value="<?php echo htmlspecialchars($_POST['site_url'] ?? 'http://localhost:3131'); ?>" required>
                            <div class="help-text">URL completa onde o sistema estará acessível</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="default_expiration_days">Dias de Expiração Padrão</label>
                            <input type="number" id="default_expiration_days" name="default_expiration_days" value="<?php echo htmlspecialchars($_POST['default_expiration_days'] ?? '7'); ?>" min="1" max="365">
                        </div>
                        
                        <div class="form-group">
                            <label for="max_file_size">Tamanho Máximo por Arquivo (bytes)</label>
                            <input type="number" id="max_file_size" name="max_file_size" value="<?php echo htmlspecialchars($_POST['max_file_size'] ?? '10737418240'); ?>">
                            <div class="help-text">10737418240 = 10GB</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="available_expiration_days">Opções de Expiração (separadas por vírgula)</label>
                        <input type="text" id="available_expiration_days" name="available_expiration_days" value="<?php echo htmlspecialchars($_POST['available_expiration_days'] ?? '1,3,7,14,30'); ?>">
                        <div class="help-text">Dias disponíveis no dropdown de expiração</div>
                    </div>
                </div>

                <!-- Configurações do Administrador -->
                <div class="form-section">
                    <h3>👤 Usuário Administrador</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="admin_username">Nome de Usuário <span class="required">*</span></label>
                            <input type="text" id="admin_username" name="admin_username" value="<?php echo htmlspecialchars($_POST['admin_username'] ?? 'admin'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_email">Email do Administrador <span class="required">*</span></label>
                            <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_password">Senha do Administrador <span class="required">*</span></label>
                        <input type="password" id="admin_password" name="admin_password" required>
                        <div class="help-text">Mínimo 6 caracteres</div>
                    </div>
                </div>

                <!-- Configurações de Email -->
                <div class="form-section">
                    <h3>📧 Configurações de Email (SMTP)</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_host">Servidor SMTP</label>
                            <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($_POST['smtp_host'] ?? ''); ?>">
                            <div class="help-text">Deixe vazio para usar o mail() do PHP</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_port">Porta SMTP</label>
                            <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($_POST['smtp_port'] ?? '587'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_username">Usuário SMTP</label>
                            <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($_POST['smtp_username'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_password">Senha SMTP</label>
                            <input type="password" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($_POST['smtp_password'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_encryption">Criptografia</label>
                            <select id="smtp_encryption" name="smtp_encryption">
                                <option value="tls" <?php echo ($_POST['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo ($_POST['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="" <?php echo ($_POST['smtp_encryption'] ?? '') === '' ? 'selected' : ''; ?>>Nenhuma</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_from_email">Email Remetente</label>
                            <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($_POST['smtp_from_email'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_from_name">Nome do Remetente</label>
                        <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($_POST['smtp_from_name'] ?? 'Pix Transfer'); ?>">
                    </div>
                    
                    <div class="test-email-container">
                        <div class="help-text">
                            <strong>Para testar:</strong> Preencha Servidor SMTP e Email Remetente. O teste será enviado para o email remetente.
                        </div>
                        <button type="submit" name="test_email" class="btn btn-test">📧 Testar Email</button>
                    </div>
                </div>

                <!-- Configurações Avançadas -->
                <div class="form-section">
                    <h3>🔧 Configurações Avançadas</h3>
                    
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
                </div>

                <button type="submit" class="btn">🚀 Finalizar Configuração</button>
            </form>
        </div>
    </div>

    <script>
        // Simple form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            // Check if it's an email test
            if (e.submitter && e.submitter.name === 'test_email') {
                const emailRequired = ['smtp_host', 'smtp_from_email'];
                let hasError = false;
                let missingFields = [];
                
                emailRequired.forEach(field => {
                    const input = document.getElementById(field);
                    if (!input.value.trim()) {
                        input.style.borderColor = '#dc3545';
                        hasError = true;
                        missingFields.push(input.previousElementSibling.textContent.replace(' *', ''));
                    } else {
                        input.style.borderColor = '#dee2e6';
                    }
                });
                
                if (hasError) {
                    e.preventDefault();
                    alert('Para testar email, preencha: ' + missingFields.join(', '));
                }
                return;
            }
            
            // Regular setup validation
            const required = ['site_name', 'site_url', 'admin_email', 'admin_password', 'admin_username'];
            let hasError = false;
            
            required.forEach(field => {
                const input = document.getElementById(field);
                if (!input.value.trim()) {
                    input.style.borderColor = '#dc3545';
                    hasError = true;
                } else {
                    input.style.borderColor = '#dee2e6';
                }
            });
            
            if (hasError) {
                e.preventDefault();
                alert('Por favor, preencha todos os campos obrigatórios.');
            }
        });
    </script>
</body>
</html>