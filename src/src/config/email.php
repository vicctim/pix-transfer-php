<?php
class EmailService {
    private $smtp_host;
    private $smtp_port;
    private $admin_email;

    public function __construct() {
        $this->smtp_host = $_ENV['SMTP_HOST'] ?? 'mailhog';
        $this->smtp_port = $_ENV['SMTP_PORT'] ?? 1025;
        $this->admin_email = $_ENV['ADMIN_EMAIL'] ?? 'victor@pixfilmes.com';
    }

    public function sendUploadCompleteEmail($user_email, $session_token, $expires_at, $file_count, $total_size) {
        $subject = "Upload Concluído - Seus arquivos estão prontos";
        $download_link = "http://localhost:3131/download.php?token=" . $session_token;
        
        $message = "
        <html>
        <head>
            <title>Upload Concluído</title>
        </head>
        <body>
            <h2>Upload Concluído com Sucesso!</h2>
            <p>Seu upload foi finalizado com sucesso.</p>
            
            <h3>Detalhes do Upload:</h3>
            <ul>
                <li><strong>Número de arquivos:</strong> {$file_count}</li>
                <li><strong>Tamanho total:</strong> " . $this->formatBytes($total_size) . "</li>
                <li><strong>Data de expiração:</strong> " . date('d/m/Y H:i', strtotime($expires_at)) . "</li>
            </ul>
            
            <h3>Link para Download:</h3>
            <p><a href='{$download_link}'>{$download_link}</a></p>
            
            <p><strong>Importante:</strong> Este link expira em " . date('d/m/Y H:i', strtotime($expires_at)) . "</p>
            
            <hr>
            <p><small>Este é um email automático do sistema de upload.</small></p>
        </body>
        </html>";

        return $this->sendEmail($user_email, $subject, $message);
    }

    public function sendAdminNotification($session_token, $user_email, $file_count, $total_size) {
        $subject = "Novo Upload Realizado - Notificação Administrativa";
        $download_link = "http://localhost:3131/download.php?token=" . $session_token;
        
        $message = "
        <html>
        <head>
            <title>Novo Upload</title>
        </head>
        <body>
            <h2>Novo Upload Realizado</h2>
            
            <h3>Detalhes:</h3>
            <ul>
                <li><strong>Usuário:</strong> {$user_email}</li>
                <li><strong>Número de arquivos:</strong> {$file_count}</li>
                <li><strong>Tamanho total:</strong> " . $this->formatBytes($total_size) . "</li>
                <li><strong>Data do upload:</strong> " . date('d/m/Y H:i') . "</li>
            </ul>
            
            <h3>Link para Download:</h3>
            <p><a href='{$download_link}'>{$download_link}</a></p>
        </body>
        </html>";

        return $this->sendEmail($this->admin_email, $subject, $message);
    }

    private function sendEmail($to, $subject, $message) {
        $headers = array(
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: upload-system@localhost',
            'Reply-To: no-reply@localhost'
        );

        try {
            $result = mail($to, $subject, $message, implode("\r\n", $headers));
            return $result;
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return false;
        }
    }

    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
} 