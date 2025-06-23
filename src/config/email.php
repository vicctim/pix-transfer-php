<?php
require_once __DIR__ . '/../env.php';

class EmailConfig {
    public static function getConfig() {
        return [
            'host' => SMTP_HOST,
            'port' => SMTP_PORT,
            'username' => SMTP_USER,
            'password' => SMTP_PASS,
            'from_email' => SMTP_FROM,
            'from_name' => SMTP_FROM_NAME,
            'encryption' => 'tls'
        ];
    }
}

class EmailService {
    private $smtp_host;
    private $smtp_port;
    private $admin_email;

    public function __construct() {
        $this->smtp_host = $_ENV['SMTP_HOST'] ?? 'mailhog';
        $this->smtp_port = $_ENV['SMTP_PORT'] ?? 1025;
        $this->admin_email = 'victor@pixfilmes.com'; // Definido diretamente
    }

    public function sendDownloadLinkToRecipient($recipient_email, $sender_email, $title, $session_token, $expires_at, $file_count, $total_size) {
        $subject = "Você recebeu arquivos de {$sender_email}";
        $download_link = "http://localhost:3131/download.php?token=" . $session_token;

        $message = "<html>...
            <h2>{$sender_email} enviou-lhe alguns arquivos</h2>
            <h3>" . htmlspecialchars($title) . "</h3>
            ...</html>";

        return $this->sendEmail($recipient_email, $subject, $message);
    }

    public function sendUploadCompleteEmail($user_email, $title, $session_token, $expires_at, $file_count, $total_size) {
        // Notificação para o usuário
        $user_subject = "Seu upload '" . htmlspecialchars($title) . "' foi concluído";
        // ... (código da mensagem do usuário)
        $this->sendEmail($user_email, $user_subject, $user_message);

        // Notificação para o Admin
        $admin_subject = "Notificacao de upload pix-transfer";
        $admin_message = "<html>...
            <h2>Novo Upload Realizado: \"" . htmlspecialchars($title) . "\"</h2>
            <p><strong>Usuário:</strong> {$user_email}</p>
            ...</html>";
        $this->sendEmail($this->admin_email, $admin_subject, $admin_message);
    }

    public function sendDownloadNotificationEmail($downloader_ip, $owner_email, $session_token, $title) {
        $subject = "Notificação de Download pix-transfer";
        $message = "<html>...
            <p>O upload com o título '<strong>" . htmlspecialchars($title) . "</strong>' foi baixado.</p>
            <p><strong>IP do Downloader:</strong> {$downloader_ip}</p>
            <p><strong>Token:</strong> {$session_token}</p>
            ...</html>";
        
        // Envia para o admin e para o dono do arquivo
        $this->sendEmail($this->admin_email, $subject, $message);
        $this->sendEmail($owner_email, $subject, $message);
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