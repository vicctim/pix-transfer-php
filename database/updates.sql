-- Updates para o sistema
USE upload_system;

-- Tabela de configurações do sistema
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de templates de email
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL UNIQUE,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    variables TEXT COMMENT 'JSON array of available variables',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de downloads (para tracking)
CREATE TABLE IF NOT EXISTS download_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    file_id INT,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    country VARCHAR(100),
    city VARCHAR(100),
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES upload_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE SET NULL
);

-- Adicionar coluna download_count nas sessões
ALTER TABLE upload_sessions 
ADD COLUMN IF NOT EXISTS download_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS notify_sender BOOLEAN DEFAULT FALSE;

-- Adicionar coluna short_url_id na tabela upload_sessions
ALTER TABLE upload_sessions 
ADD COLUMN IF NOT EXISTS short_url_id INT DEFAULT NULL,
ADD FOREIGN KEY (short_url_id) REFERENCES short_urls(id) ON DELETE SET NULL;

-- Configurações padrão do sistema
INSERT INTO system_settings (setting_key, setting_value, setting_type) VALUES
('site_name', 'Pix Transfer', 'string'),
('site_url', 'http://localhost:3131', 'string'),
('site_logo', '/src/img/logo.png', 'string'),
('admin_email', 'admin@transfer.com', 'string'),
('default_expiration_days', '7', 'number'),
('max_file_size', '10737418240', 'number'),
('smtp_host', '', 'string'),
('smtp_port', '587', 'number'),
('smtp_username', '', 'string'),
('smtp_password', '', 'string'),
('smtp_encryption', 'tls', 'string'),
('smtp_from_email', '', 'string'),
('smtp_from_name', 'Pix Transfer', 'string'),
('system_timezone', 'America/Sao_Paulo', 'string'),
('is_setup_complete', 'false', 'boolean'),
('available_expiration_days', '[1,3,7,14,30]', 'json'),
('cloudflare_tunnel_enabled', 'false', 'boolean'),
('cloudflare_tunnel_url', '', 'string')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Templates de email padrão
INSERT INTO email_templates (template_name, subject, body, variables) VALUES
('upload_notification', 'Seus arquivos foram enviados - {{site_name}}', 
'<html>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
        <h2 style="color: #333; text-align: center;">{{site_name}}</h2>
        <h3 style="color: #28a745;">Seus arquivos foram enviados com sucesso!</h3>
        
        <p>Olá,</p>
        <p>{{custom_message}}</p>
        
        <div style="background: white; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h4>Detalhes do envio:</h4>
            <p><strong>Título:</strong> {{upload_title}}</p>
            <p><strong>Arquivos:</strong> {{file_count}} arquivo(s)</p>
            <p><strong>Tamanho total:</strong> {{total_size}}</p>
            <p><strong>Expira em:</strong> {{expiration_date}}</p>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{download_url}}" style="background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">
                Download dos Arquivos
            </a>
        </div>
        
        <p style="font-size: 12px; color: #666; text-align: center;">
            Este link expira em {{expiration_date}}
        </p>
    </div>
</body>
</html>', 
'["site_name", "custom_message", "upload_title", "file_count", "total_size", "expiration_date", "download_url"]'),

('download_notification', 'Download realizado - {{site_name}}',
'<html>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
        <h2 style="color: #333; text-align: center;">{{site_name}}</h2>
        <h3 style="color: #007bff;">Download realizado!</h3>
        
        <p>Olá,</p>
        <p>Seus arquivos foram baixados por alguém.</p>
        
        <div style="background: white; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h4>Detalhes do download:</h4>
            <p><strong>Título:</strong> {{upload_title}}</p>
            <p><strong>Download em:</strong> {{download_date}}</p>
            <p><strong>Local:</strong> {{download_location}}</p>
            <p><strong>Total de downloads:</strong> {{download_count}}</p>
        </div>
    </div>
</body>
</html>',
'["site_name", "upload_title", "download_date", "download_location", "download_count"]')
ON DUPLICATE KEY UPDATE 
    subject = VALUES(subject),
    body = VALUES(body),
    variables = VALUES(variables);