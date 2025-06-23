<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class PHPMailerEmailService {
    private $smtp_host = 'smtppro.zoho.com';
    private $smtp_port = 465;
    private $smtp_user = 'victor@pixfilmes.com';
    private $smtp_pass = 'alucardAS12*';
    private $from_email = 'victor@pixfilmes.com';
    private $from_name = 'Victor Pix Filmes';
    private $admin_email = 'victor@pixfilmes.com';

    public function sendUploadCompleteEmail($user_email, $title, $session_token, $expires_at, $file_count, $total_size) {
        // Criar URL curta
        require_once __DIR__ . '/../models/ShortUrl.php';
        $shortUrl = new ShortUrl();
        $short_code = $shortUrl->create($session_token, $expires_at);
        
        // Email para o usuário
        $user_subject = "Upload Concluído - " . htmlspecialchars($title);
        $download_link = $short_code ? "http://localhost:3131/s/" . $short_code : "http://localhost:3131/download/" . $session_token;
        
        $user_message = $this->getEmailTemplate(
            "Upload Concluído!",
            "Seu upload foi processado com sucesso.",
            [
                "Título" => htmlspecialchars($title),
                "Arquivos" => $file_count,
                "Tamanho" => $this->formatBytes($total_size),
                "Expira em" => date('d/m/Y H:i', strtotime($expires_at))
            ],
            $download_link,
            "Acessar Arquivos"
        );

        $result1 = $this->sendEmail($user_email, $user_subject, $user_message);

        // Email para admin
        $admin_subject = "Notificacao de Upload Pix Transfer";
        $admin_message = $this->getEmailTemplate(
            "Novo Upload no Sistema",
            "Um novo upload foi realizado.",
            [
                "Usuário" => $user_email,
                "Título" => htmlspecialchars($title),
                "Arquivos" => $file_count,
                "Tamanho" => $this->formatBytes($total_size),
                "Data" => date('d/m/Y H:i:s')
            ],
            $download_link,
            "Visualizar Upload"
        );

        $result2 = $this->sendEmail($this->admin_email, $admin_subject, $admin_message);

        return $result1 || $result2;
    }

    public function sendDownloadNotificationEmail($downloader_ip, $owner_email, $session_token, $title) {
        $subject = "Notificacao de Download Pix Transfer";
        $download_link = "http://localhost:3131/download/" . $session_token;
        
        $message = $this->getEmailTemplate(
            "Arquivo Baixado",
            "Seus arquivos foram baixados por alguém.",
            [
                "Arquivo" => htmlspecialchars($title ?? 'Sem título'),
                "IP" => $downloader_ip,
                "Data/Hora" => date('d/m/Y H:i:s')
            ],
            $download_link,
            "Ver Upload"
        );
        
        $result1 = $this->sendEmail($owner_email, $subject, $message);
        $result2 = $this->sendEmail($this->admin_email, $subject, $message);
        
        return $result1 || $result2;
    }

    public function sendDownloadLinkToRecipient($recipient_email, $sender_email, $title, $session_token, $expires_at, $file_count, $total_size) {
        require_once __DIR__ . '/../models/ShortUrl.php';
        $shortUrl = new ShortUrl();
        $short_code = $shortUrl->create($session_token, $expires_at);
        
        $subject = "Você recebeu arquivos de " . $sender_email;
        $download_link = $short_code ? "http://localhost:3131/s/" . $short_code : "http://localhost:3131/download/" . $session_token;

        $message = $this->getEmailTemplate(
            "Você recebeu arquivos!",
            $sender_email . " compartilhou arquivos com você.",
            [
                "Título" => htmlspecialchars($title),
                "Remetente" => $sender_email,
                "Arquivos" => $file_count,
                "Tamanho" => $this->formatBytes($total_size),
                "Expira em" => date('d/m/Y H:i', strtotime($expires_at))
            ],
            $download_link,
            "Baixar Arquivos"
        );

        return $this->sendEmail($recipient_email, $subject, $message);
    }

    private function sendEmail($to, $subject, $message) {
        error_log("Tentando enviar email via PHPMailer para: $to");
        
        $mail = new PHPMailer(true);

        try {
            // Configurações do servidor
            $mail->isSMTP();
            $mail->Host       = $this->smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->smtp_user;
            $mail->Password   = $this->smtp_pass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = $this->smtp_port;
            
            // Configurar charset
            $mail->CharSet = 'UTF-8';
            
            // Remetente
            $mail->setFrom($this->from_email, $this->from_name);
            
            // Destinatário
            $mail->addAddress($to);
            
            // Conteúdo
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            
            // Enviar
            $result = $mail->send();
            
            if ($result) {
                error_log("Email enviado com sucesso via PHPMailer para: $to");
                return true;
            } else {
                error_log("PHPMailer falhou para: $to");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Erro PHPMailer: " . $mail->ErrorInfo);
            error_log("Exception: " . $e->getMessage());
            return false;
        }
    }

    private function getEmailTemplate($title, $subtitle, $info, $link, $button_text) {
        $info_html = '';
        foreach ($info as $label => $value) {
            $info_html .= "<p><strong>$label:</strong> $value</p>";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .button { display: inline-block; background: linear-gradient(135deg, #4CAF50, #45a049); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
                .info { background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .footer { text-align: center; padding: 15px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>$title</h1>
                </div>
                <div class='content'>
                    <p>$subtitle</p>
                    <div class='info'>
                        $info_html
                    </div>
                    <p style='text-align: center;'>
                        <a href='$link' class='button'>$button_text</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>Pix Transfer - Victor Pix Filmes © " . date('Y') . "</p>
                </div>
            </div>
        </body>
        </html>";
    }

    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
?>