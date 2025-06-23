-- Criar banco de dados
CREATE DATABASE IF NOT EXISTS upload_system;
USE upload_system;

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de sessões de upload
CREATE TABLE IF NOT EXISTS upload_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    title VARCHAR(255) DEFAULT NULL,
    recipient_email VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela de arquivos
CREATE TABLE files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    mime_type VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES upload_sessions(id) ON DELETE CASCADE
);

-- Tabela de logs de email
CREATE TABLE email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    recipient_email VARCHAR(100) NOT NULL,
    email_type ENUM('upload_complete', 'download_link') NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('sent', 'failed') NOT NULL,
    error_message TEXT,
    FOREIGN KEY (session_id) REFERENCES upload_sessions(id) ON DELETE CASCADE
);

-- Inserir usuário administrador padrão
INSERT INTO users (username, email, password_hash, role) VALUES ('admin', 'admin@transfer.com', '$2y$10$If6LIue4eXvrr23a/T9qFeTz9iAPlI72mJ.y5a1UQdxyQEh5Pz5a.', 'admin')
ON DUPLICATE KEY UPDATE email = 'admin@transfer.com', password_hash = '$2y$10$If6LIue4eXvrr23a/T9qFeTz9iAPlI72mJ.y5a1UQdxyQEh5Pz5a.', role = 'admin';

-- Inserir usuário de demonstração
INSERT INTO users (username, email, password_hash, role) VALUES ('demo', 'demo@transfer.com', '$2y$10$DgeI4V6q..QYw1vJ3O/2P.f6h./W8I5u2V5o.Yy9j.x3F.w4g.8y.', 'user')
ON DUPLICATE KEY UPDATE email = 'demo@transfer.com', password_hash = '$2y$10$DgeI4V6q..QYw1vJ3O/2P.f6h./W8I5u2V5o.Yy9j.x3F.w4g.8y.', role = 'user';

-- Criar índices para performance
CREATE INDEX idx_upload_sessions_user_id ON upload_sessions(user_id);
CREATE INDEX idx_upload_sessions_token ON upload_sessions(token);
CREATE INDEX idx_upload_sessions_expires_at ON upload_sessions(expires_at);
CREATE INDEX idx_files_session_id ON files(session_id);
CREATE INDEX idx_email_logs_session_id ON email_logs(session_id); 