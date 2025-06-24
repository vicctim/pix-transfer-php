<?php
require_once __DIR__ . '/../env.php';

class EmailService {
    // Configurações primárias (Zoho)
    private $smtp_host = 'smtppro.zoho.com';
    private $smtp_port = 465;
    private $smtp_user = 'victor@pixfilmes.com';
    private $smtp_pass = 'alucardAS12*';
    private $smtp_from = 'victor@pixfilmes.com';
    private $smtp_from_name = 'Victor Pix Filmes';
    private $admin_email = 'victor@pixfilmes.com';
    private $smtp_encryption = 'ssl';
    
    // Configurações de fallback (MailHog)
    private $fallback_host = 'mailhog';
    private $fallback_port = 1025;

    public function sendUploadCompleteEmail($user_email, $title, $session_token, $expires_at, $file_count, $total_size) {
        // Template de email para o usuário
        $user_subject = "Upload Concluído - " . htmlspecialchars($title);
        $download_link = "http://localhost:3131/download.php?token=" . $session_token;
        
        $user_message = $this->getUploadCompleteTemplate(
            htmlspecialchars($title),
            $download_link,
            $expires_at,
            $file_count,
            $this->formatBytes($total_size)
        );

        // Enviar para o usuário
        $this->sendEmail($user_email, $user_subject, $user_message);

        // Template de notificação para o admin
        $admin_subject = "Notificacao de Upload Pix Transfer - " . htmlspecialchars($title);
        $admin_message = $this->getUploadNotificationTemplate(
            $user_email,
            htmlspecialchars($title),
            $download_link,
            $expires_at,
            $file_count,
            $this->formatBytes($total_size)
        );

        // Enviar para o admin com cópia
        $this->sendEmail($this->admin_email, $admin_subject, $admin_message, $user_email);
    }

    public function sendDownloadNotificationEmail($downloader_ip, $owner_email, $session_token, $title) {
        $subject = "Notificacao de Download Pix Transfer - " . htmlspecialchars($title);
        $download_link = "http://localhost:3131/download.php?token=" . $session_token;
        
        $message = $this->getDownloadNotificationTemplate(
            htmlspecialchars($title ?? 'Sem título'),
            $downloader_ip,
            $download_link,
            date('d/m/Y H:i:s')
        );
        
        // Enviar para o dono do arquivo
        $this->sendEmail($owner_email, $subject, $message);
        
        // Enviar para o admin com cópia
        $this->sendEmail($this->admin_email, $subject, $message, $owner_email);
    }

    public function sendDownloadLinkToRecipient($recipient_email, $sender_email, $title, $session_token, $expires_at, $file_count, $total_size) {
        $subject = "Você recebeu arquivos de " . $sender_email;
        $download_link = "http://localhost:3131/download.php?token=" . $session_token;

        $message = $this->getRecipientEmailTemplate(
            $sender_email,
            htmlspecialchars($title),
            $download_link,
            $expires_at,
            $file_count,
            $this->formatBytes($total_size)
        );

        return $this->sendEmail($recipient_email, $subject, $message);
    }

    private function sendEmail($to, $subject, $message, $cc = null) {
        // Validar parâmetros obrigatórios
        if (empty($to) || empty($subject) || empty($message)) {
            error_log("Email parameters cannot be empty: to={$to}, subject={$subject}");
            return false;
        }

        // Log tentativa de envio
        error_log("Attempting to send email to: $to with subject: $subject");

        // Método 1: MailHog (desenvolvimento) - mais confiável
        try {
            $result = $this->sendMailHogEmail($to, $subject, $message, $cc);
            if ($result) {
                error_log("Email sent successfully via MailHog to: $to");
                return true;
            }
        } catch (Exception $e) {
            error_log("MailHog method failed: " . $e->getMessage());
        }

        // Método 2: Configurar mail() para usar MailHog
        try {
            $result = $this->sendViaPHPMail($to, $subject, $message, $cc);
            if ($result) {
                error_log("Email sent successfully via PHP mail() to MailHog: $to");
                return true;
            }
        } catch (Exception $e) {
            error_log("PHP mail() via MailHog failed: " . $e->getMessage());
        }

        // Método 3: Tentar SMTP Zoho (se network permitir)
        try {
            $result = $this->sendSMTPEmail($to, $subject, $message, [], $cc);
            if ($result) {
                error_log("Email sent successfully via Zoho SMTP to: $to");
                return true;
            }
        } catch (Exception $e) {
            error_log("Zoho SMTP method failed: " . $e->getMessage());
        }

        // Método 3: Usar mail() com headers customizados
        try {
            $headers = array();
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/html; charset=UTF-8';
            $headers[] = 'From: ' . $this->smtp_from_name . ' <' . $this->smtp_from . '>';
            $headers[] = 'Reply-To: ' . $this->smtp_from;
            $headers[] = 'X-Mailer: Pix Transfer System';
            
            if ($cc) {
                $headers[] = 'Cc: ' . $cc;
            }

            $result = mail($to, $subject, $message, implode("\r\n", $headers));
            if ($result) {
                error_log("Email sent successfully via mail() function to: $to");
                return true;
            } else {
                error_log("mail() function failed for: $to");
            }
        } catch (Exception $e) {
            error_log("mail() method failed: " . $e->getMessage());
        }

        // Método 3: Log para debug manual
        error_log("=== EMAIL DEBUG INFO ===");
        error_log("TO: $to");
        error_log("SUBJECT: $subject");
        error_log("SMTP HOST: " . $this->smtp_host);
        error_log("SMTP PORT: " . $this->smtp_port);
        error_log("SMTP USER: " . $this->smtp_user);
        error_log("========================");

        return false;
    }

    private function sendViaPHPMail($to, $subject, $message, $cc = null) {
        // Configurar PHP mail() para usar MailHog
        $original_smtp = ini_get('SMTP');
        $original_port = ini_get('smtp_port');
        
        ini_set('SMTP', $this->fallback_host);
        ini_set('smtp_port', $this->fallback_port);
        ini_set('sendmail_from', $this->smtp_from);
        
        try {
            $headers = array();
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/html; charset=UTF-8';
            $headers[] = 'From: ' . $this->smtp_from_name . ' <' . $this->smtp_from . '>';
            $headers[] = 'Reply-To: ' . $this->smtp_from;
            
            if ($cc) {
                $headers[] = 'Cc: ' . $cc;
            }
            
            $result = mail($to, $subject, $message, implode("\r\n", $headers));
            
            // Restaurar configurações originais
            ini_set('SMTP', $original_smtp);
            ini_set('smtp_port', $original_port);
            
            return $result;
            
        } catch (Exception $e) {
            // Restaurar configurações originais em caso de erro
            ini_set('SMTP', $original_smtp);
            ini_set('smtp_port', $original_port);
            throw $e;
        }
    }

    private function sendMailHogEmail($to, $subject, $message, $cc = null) {
        // MailHog funciona com SMTP simples na porta 1025
        $socket = fsockopen($this->fallback_host, $this->fallback_port, $errno, $errstr, 10);
        if (!$socket) {
            throw new Exception("Cannot connect to MailHog: $errstr ($errno)");
        }

        $sendCommand = function($command) use ($socket) {
            fwrite($socket, $command . "\r\n");
            return fgets($socket, 512);
        };

        try {
            // SMTP sem autenticação para MailHog
            fgets($socket, 512); // Banner
            $sendCommand("EHLO localhost");
            $sendCommand("MAIL FROM: <{$this->smtp_from}>");
            $sendCommand("RCPT TO: <{$to}>");
            
            if ($cc) {
                $sendCommand("RCPT TO: <{$cc}>");
            }
            
            $sendCommand("DATA");
            
            // Headers
            fwrite($socket, "From: {$this->smtp_from_name} <{$this->smtp_from}>\r\n");
            fwrite($socket, "To: {$to}\r\n");
            fwrite($socket, "Subject: {$subject}\r\n");
            fwrite($socket, "Content-Type: text/html; charset=UTF-8\r\n");
            fwrite($socket, "MIME-Version: 1.0\r\n");
            
            if ($cc) {
                fwrite($socket, "Cc: {$cc}\r\n");
            }
            
            fwrite($socket, "\r\n");
            fwrite($socket, $message);
            fwrite($socket, "\r\n.\r\n");
            
            $sendCommand("QUIT");
            fclose($socket);
            
            return true;
            
        } catch (Exception $e) {
            if (is_resource($socket)) {
                fclose($socket);
            }
            throw $e;
        }
    }

    private function sendSMTPEmail($to, $subject, $message, $headers, $cc = null) {
        // Conectar ao servidor SMTP com SSL
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        $socket = stream_socket_client(
            "ssl://{$this->smtp_host}:{$this->smtp_port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$socket) {
            throw new Exception("Cannot connect to SMTP server via SSL: $errstr ($errno)");
        }

        // Função para enviar comando e ler resposta
        $sendCommand = function($command) use ($socket) {
            fwrite($socket, $command . "\r\n");
            $response = fgets($socket, 512);
            error_log("SMTP Command: $command | Response: " . trim($response));
            return $response;
        };

        try {
            // Banner do servidor
            $banner = fgets($socket, 512);
            error_log("SMTP Banner: " . trim($banner));
            
            // Handshake SMTP
            $sendCommand("EHLO localhost");
            
            // Autenticação
            $sendCommand("AUTH LOGIN");
            $auth_response1 = $sendCommand(base64_encode($this->smtp_user));
            $auth_response2 = $sendCommand(base64_encode($this->smtp_pass));
            
            // Verificar se autenticação foi bem-sucedida
            if (strpos($auth_response2, '235') === false) {
                throw new Exception("SMTP Authentication failed");
            }
            
            // Envelope
            $sendCommand("MAIL FROM: <{$this->smtp_from}>");
            $sendCommand("RCPT TO: <{$to}>");
            
            if ($cc) {
                $sendCommand("RCPT TO: <{$cc}>");
            }
            
            // Dados
            $sendCommand("DATA");
            
            // Headers e conteúdo
            foreach ($headers as $header) {
                fwrite($socket, $header . "\r\n");
            }
            fwrite($socket, "To: {$to}\r\n");
            fwrite($socket, "Subject: {$subject}\r\n");
            fwrite($socket, "\r\n");
            fwrite($socket, $message);
            fwrite($socket, "\r\n.\r\n");
            
            $data_response = fgets($socket, 512);
            error_log("SMTP Data Response: " . trim($data_response));
            
            $sendCommand("QUIT");
            fclose($socket);
            
            error_log("Email sent successfully via SMTP SSL to: {$to}");
            return true;
            
        } catch (Exception $e) {
            if (is_resource($socket)) {
                fclose($socket);
            }
            throw $e;
        }
    }

    private function getUploadCompleteTemplate($title, $download_link, $expires_at, $file_count, $total_size) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f7fcf5; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .button { display: inline-block; background: linear-gradient(135deg, #4CAF50, #45a049); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
                .info-box { background: #f8f9fa; border-left: 4px solid #4CAF50; padding: 15px; margin: 20px 0; border-radius: 5px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ Upload Concluído com Sucesso!</h1>
                </div>
                <div class='content'>
                    <h2>Olá!</h2>
                    <p>Seu upload <strong>{$title}</strong> foi processado com sucesso e está pronto para compartilhamento.</p>
                    
                    <div class='info-box'>
                        <h3>📊 Detalhes do Upload:</h3>
                        <p><strong>Título:</strong> {$title}</p>
                        <p><strong>Arquivos:</strong> {$file_count}</p>
                        <p><strong>Tamanho Total:</strong> {$total_size}</p>
                        <p><strong>Expira em:</strong> " . date('d/m/Y H:i', strtotime($expires_at)) . "</p>
                    </div>
                    
                    <p>Use o link abaixo para acessar ou compartilhar seus arquivos:</p>
                    <p style='text-align: center;'>
                        <a href='{$download_link}' class='button'>🔗 Acessar Arquivos</a>
                    </p>
                    
                    <p><em>Este link expira automaticamente na data indicada acima.</em></p>
                </div>
                <div class='footer'>
                    <p>Pix Transfer - Sistema de Compartilhamento de Arquivos</p>
                    <p>Victor Pix Filmes © " . date('Y') . "</p>
                </div>
            </div>
        </body>
        </html>";
    }

    private function getUploadNotificationTemplate($user_email, $title, $download_link, $expires_at, $file_count, $total_size) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f7fcf5; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #FF9800, #F57C00); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .button { display: inline-block; background: linear-gradient(135deg, #FF9800, #F57C00); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
                .info-box { background: #fff3e0; border-left: 4px solid #FF9800; padding: 15px; margin: 20px 0; border-radius: 5px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔔 Nova Notificação de Upload</h1>
                </div>
                <div class='content'>
                    <h2>Novo upload realizado no sistema!</h2>
                    
                    <div class='info-box'>
                        <h3>📋 Informações do Upload:</h3>
                        <p><strong>Usuário:</strong> {$user_email}</p>
                        <p><strong>Título:</strong> {$title}</p>
                        <p><strong>Arquivos:</strong> {$file_count}</p>
                        <p><strong>Tamanho Total:</strong> {$total_size}</p>
                        <p><strong>Data:</strong> " . date('d/m/Y H:i:s') . "</p>
                        <p><strong>Expira em:</strong> " . date('d/m/Y H:i', strtotime($expires_at)) . "</p>
                    </div>
                    
                    <p style='text-align: center;'>
                        <a href='{$download_link}' class='button'>👁️ Visualizar Upload</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>Pix Transfer - Notificação Administrativa</p>
                    <p>Victor Pix Filmes © " . date('Y') . "</p>
                </div>
            </div>
        </body>
        </html>";
    }

    private function getDownloadNotificationTemplate($title, $downloader_ip, $download_link, $download_time) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f7fcf5; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #2196F3, #1976D2); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .button { display: inline-block; background: linear-gradient(135deg, #2196F3, #1976D2); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
                .info-box { background: #e3f2fd; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; border-radius: 5px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📥 Notificação de Download</h1>
                </div>
                <div class='content'>
                    <h2>Seus arquivos foram baixados!</h2>
                    
                    <div class='info-box'>
                        <h3>📊 Detalhes do Download:</h3>
                        <p><strong>Arquivo:</strong> {$title}</p>
                        <p><strong>IP do Download:</strong> {$downloader_ip}</p>
                        <p><strong>Data/Hora:</strong> {$download_time}</p>
                    </div>
                    
                    <p>Alguém acessou e baixou os arquivos do seu upload. Esta é uma notificação automática para manter você informado sobre a atividade dos seus compartilhamentos.</p>
                    
                    <p style='text-align: center;'>
                        <a href='{$download_link}' class='button'>🔗 Ver Upload</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>Pix Transfer - Notificação de Atividade</p>
                    <p>Victor Pix Filmes © " . date('Y') . "</p>
                </div>
            </div>
        </body>
        </html>";
    }

    private function getRecipientEmailTemplate($sender_email, $title, $download_link, $expires_at, $file_count, $total_size) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f7fcf5; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #9C27B0, #7B1FA2); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .button { display: inline-block; background: linear-gradient(135deg, #9C27B0, #7B1FA2); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
                .info-box { background: #f3e5f5; border-left: 4px solid #9C27B0; padding: 15px; margin: 20px 0; border-radius: 5px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📨 Você recebeu arquivos!</h1>
                </div>
                <div class='content'>
                    <h2>Olá!</h2>
                    <p><strong>{$sender_email}</strong> compartilhou alguns arquivos com você através do Pix Transfer.</p>
                    
                    <div class='info-box'>
                        <h3>📦 Detalhes dos Arquivos:</h3>
                        <p><strong>Título:</strong> {$title}</p>
                        <p><strong>Remetente:</strong> {$sender_email}</p>
                        <p><strong>Arquivos:</strong> {$file_count}</p>
                        <p><strong>Tamanho Total:</strong> {$total_size}</p>
                        <p><strong>Expira em:</strong> " . date('d/m/Y H:i', strtotime($expires_at)) . "</p>
                    </div>
                    
                    <p>Clique no botão abaixo para visualizar e baixar os arquivos:</p>
                    <p style='text-align: center;'>
                        <a href='{$download_link}' class='button'>📥 Baixar Arquivos</a>
                    </p>
                    
                    <p><em>⚠️ Este link expira automaticamente na data indicada acima.</em></p>
                </div>
                <div class='footer'>
                    <p>Pix Transfer - Sistema de Compartilhamento de Arquivos</p>
                    <p>Victor Pix Filmes © " . date('Y') . "</p>
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