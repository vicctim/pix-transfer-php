<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/SystemSettings.php';

class EmailTemplate {
    private $db;
    private $settings;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->settings = new SystemSettings();
    }
    
    public function getTemplate($templateName) {
        try {
            $stmt = $this->db->query(
                "SELECT * FROM email_templates WHERE template_name = ? AND is_active = 1",
                [$templateName]
            );
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Erro ao buscar template: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAllTemplates() {
        try {
            $stmt = $this->db->query("SELECT * FROM email_templates ORDER BY template_name");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erro ao buscar templates: " . $e->getMessage());
            return [];
        }
    }
    
    public function updateTemplate($templateName, $subject, $body) {
        try {
            $stmt = $this->db->query(
                "UPDATE email_templates SET subject = ?, body = ?, updated_at = NOW() WHERE template_name = ?",
                [$subject, $body, $templateName]
            );
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Erro ao atualizar template: " . $e->getMessage());
            return false;
        }
    }
    
    public function createTemplate($templateName, $subject, $body, $variables = []) {
        try {
            $stmt = $this->db->query(
                "INSERT INTO email_templates (template_name, subject, body, variables) VALUES (?, ?, ?, ?)",
                [$templateName, $subject, $body, json_encode($variables)]
            );
            return $this->db->getConnection()->lastInsertId();
        } catch (Exception $e) {
            error_log("Erro ao criar template: " . $e->getMessage());
            return false;
        }
    }
    
    public function renderTemplate($templateName, $variables = []) {
        $template = $this->getTemplate($templateName);
        if (!$template) {
            return false;
        }
        
        // Add system variables
        $systemVars = [
            'site_name' => $this->settings->get('site_name', 'Pix Transfer'),
            'site_url' => $this->settings->getSiteUrl(),
            'admin_email' => $this->settings->get('admin_email', 'admin@transfer.com')
        ];
        
        $allVariables = array_merge($systemVars, $variables);
        
        $subject = $this->replaceVariables($template['subject'], $allVariables);
        $body = $this->replaceVariables($template['body'], $allVariables);
        
        return [
            'subject' => $subject,
            'body' => $body,
            'template' => $template
        ];
    }
    
    private function replaceVariables($content, $variables) {
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $content = str_replace($placeholder, $value, $content);
        }
        return $content;
    }
    
    public function getAvailableVariables($templateName) {
        $template = $this->getTemplate($templateName);
        if (!$template || !$template['variables']) {
            return [];
        }
        
        return json_decode($template['variables'], true) ?: [];
    }
    
    public function sendUploadNotification($sessionId, $recipientEmails, $customMessage = '', $notifySender = false) {
        require_once __DIR__ . '/UploadSession.php';
        require_once __DIR__ . '/File.php';
        
        $uploadSession = new UploadSession();
        $fileModel = new File();
        
        // Get session data
        if (!$uploadSession->getById($sessionId)) {
            return false;
        }
        
        $files = $fileModel->getBySessionId($sessionId);
        $totalSize = $fileModel->getTotalSizeBySession($sessionId);
        
        // Prepare variables
        $variables = [
            'custom_message' => $customMessage ?: 'Você recebeu arquivos compartilhados.',
            'upload_title' => $uploadSession->title,
            'file_count' => count($files),
            'total_size' => $fileModel->formatFileSize($totalSize),
            'expiration_date' => date('d/m/Y H:i', strtotime($uploadSession->expires_at)),
            'download_url' => $this->settings->getSiteUrl() . '/download/' . $uploadSession->token
        ];
        
        // Render template
        $rendered = $this->renderTemplate('upload_notification', $variables);
        if (!$rendered) {
            return false;
        }
        
        // Send emails
        $emailSent = false;
        foreach ($recipientEmails as $email) {
            if ($this->sendEmail($email, $rendered['subject'], $rendered['body'])) {
                $this->logEmail($sessionId, $email, 'upload_complete', 'sent');
                $emailSent = true;
            } else {
                $this->logEmail($sessionId, $email, 'upload_complete', 'failed');
            }
        }
        
        // Send to sender if requested
        if ($notifySender && !empty($uploadSession->recipient_email)) {
            if ($this->sendEmail($uploadSession->recipient_email, $rendered['subject'], $rendered['body'])) {
                $this->logEmail($sessionId, $uploadSession->recipient_email, 'upload_complete', 'sent');
                $emailSent = true;
            }
        }
        
        return $emailSent;
    }
    
    public function sendAdminUploadNotification($sessionId) {
        require_once __DIR__ . '/UploadSession.php';
        require_once __DIR__ . '/File.php';
        require_once __DIR__ . '/User.php';
        
        $uploadSession = new UploadSession();
        $fileModel = new File();
        $userModel = new User();
        
        // Get session data
        if (!$uploadSession->getById($sessionId)) {
            return false;
        }
        
        $files = $fileModel->getBySessionId($sessionId);
        $totalSize = $fileModel->getTotalSizeBySession($sessionId);
        $userData = $userModel->getById($uploadSession->user_id);
        
        // Prepare variables for admin notification
        $variables = [
            'upload_title' => $uploadSession->title,
            'user_name' => $userData['username'] ?? 'Usuário desconhecido',
            'user_email' => $userData['email'] ?? 'Email desconhecido',
            'file_count' => count($files),
            'total_size' => $fileModel->formatFileSize($totalSize),
            'upload_date' => date('d/m/Y H:i', strtotime($uploadSession->created_at)),
            'expiration_date' => date('d/m/Y H:i', strtotime($uploadSession->expires_at)),
            'download_url' => $this->settings->getSiteUrl() . '/download/' . $uploadSession->token
        ];
        
        // Get admin email
        $adminEmail = $this->settings->get('admin_email');
        if (!$adminEmail) {
            return false;
        }
        
        // Render template or use fallback
        $rendered = $this->renderTemplate('admin_upload_notification', $variables);
        if (!$rendered) {
            // Fallback template
            $subject = "Novo Upload no Sistema - " . $variables['upload_title'];
            $body = "Um novo upload foi realizado no sistema.\n\n";
            $body .= "Usuário: " . $variables['user_name'] . " (" . $variables['user_email'] . ")\n";
            $body .= "Título: " . $variables['upload_title'] . "\n";
            $body .= "Arquivos: " . $variables['file_count'] . "\n";
            $body .= "Tamanho Total: " . $variables['total_size'] . "\n";
            $body .= "Data do Upload: " . $variables['upload_date'] . "\n";
            $body .= "Expira em: " . $variables['expiration_date'] . "\n\n";
            $body .= "Link para download: " . $variables['download_url'];
            
            $rendered = ['subject' => $subject, 'body' => $body];
        }
        
        // Send email to admin
        if ($this->sendEmail($adminEmail, $rendered['subject'], $rendered['body'])) {
            $this->logEmail($sessionId, $adminEmail, 'admin_notification', 'sent');
            return true;
        } else {
            $this->logEmail($sessionId, $adminEmail, 'admin_notification', 'failed');
            return false;
        }
    }
    
    public function sendUserUploadCompleteNotification($sessionId, $userEmail) {
        require_once __DIR__ . '/UploadSession.php';
        require_once __DIR__ . '/File.php';
        
        $uploadSession = new UploadSession();
        $fileModel = new File();
        
        // Get session data
        if (!$uploadSession->getById($sessionId)) {
            return false;
        }
        
        $files = $fileModel->getBySessionId($sessionId);
        $totalSize = $fileModel->getTotalSizeBySession($sessionId);
        
        // Prepare variables
        $variables = [
            'upload_title' => $uploadSession->title,
            'file_count' => count($files),
            'total_size' => $fileModel->formatFileSize($totalSize),
            'upload_date' => date('d/m/Y H:i', strtotime($uploadSession->created_at)),
            'expiration_date' => date('d/m/Y H:i', strtotime($uploadSession->expires_at)),
            'download_url' => $this->settings->getSiteUrl() . '/download/' . $uploadSession->token
        ];
        
        // Render template or use fallback
        $rendered = $this->renderTemplate('user_upload_complete', $variables);
        if (!$rendered) {
            // Fallback template
            $subject = "Upload Concluído - " . $variables['upload_title'];
            $body = "Seu upload foi concluído com sucesso!\n\n";
            $body .= "Título: " . $variables['upload_title'] . "\n";
            $body .= "Arquivos: " . $variables['file_count'] . "\n";
            $body .= "Tamanho Total: " . $variables['total_size'] . "\n";
            $body .= "Data do Upload: " . $variables['upload_date'] . "\n";
            $body .= "Expira em: " . $variables['expiration_date'] . "\n\n";
            $body .= "Link para download: " . $variables['download_url'];
            
            $rendered = ['subject' => $subject, 'body' => $body];
        }
        
        // Send email to user
        if ($this->sendEmail($userEmail, $rendered['subject'], $rendered['body'])) {
            $this->logEmail($sessionId, $userEmail, 'upload_complete_user', 'sent');
            return true;
        } else {
            $this->logEmail($sessionId, $userEmail, 'upload_complete_user', 'failed');
            return false;
        }
    }
    
    public function testEmailSettings($to, $subject, $body, $smtpConfig = null) {
        if ($smtpConfig) {
            return $this->sendEmailWithConfig($to, $subject, $body, $smtpConfig);
        }
        return $this->sendEmail($to, $subject, $body);
    }
    
    private function sendEmailWithConfig($to, $subject, $body, $smtpConfig) {
        try {
            // If no SMTP config, try simple mail
            if (empty($smtpConfig['smtp_host'])) {
                $headers = [
                    'MIME-Version: 1.0',
                    'Content-type: text/plain; charset=UTF-8',
                    'From: ' . ($smtpConfig['smtp_from_name'] ?: 'Sistema de Upload') . ' <' . ($smtpConfig['smtp_from_email'] ?: 'noreply@example.com') . '>'
                ];
                
                // Ensure UTF-8 encoding
                $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
                
                return mail($to, $subject, $body, implode("\r\n", $headers));
            }
            
            // Use PHPMailer for SMTP
            require_once '/var/www/html/vendor/autoload.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host = $smtpConfig['smtp_host'];
            $mail->SMTPAuth = !empty($smtpConfig['smtp_username']);
            $mail->Username = $smtpConfig['smtp_username'];
            $mail->Password = $smtpConfig['smtp_password'];
            $mail->SMTPSecure = $smtpConfig['smtp_encryption'] === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtpConfig['smtp_port'];
            
            $mail->setFrom($smtpConfig['smtp_from_email'], $smtpConfig['smtp_from_name']);
            $mail->addAddress($to);
            
            $mail->isHTML(false);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            return $mail->send();
        } catch (Exception $e) {
            error_log("Erro ao enviar email de teste: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendEmail($to, $subject, $body) {
        try {
            $smtpConfig = $this->settings->getSmtpConfig();
            
            // If no SMTP config, try simple mail
            if (empty($smtpConfig['smtp_host'])) {
                $headers = [
                    'MIME-Version: 1.0',
                    'Content-type: text/html; charset=UTF-8',
                    'From: ' . $this->settings->get('smtp_from_name', 'Pix Transfer') . ' <' . $this->settings->get('smtp_from_email', 'noreply@example.com') . '>'
                ];
                
                return mail($to, $subject, $body, implode("\r\n", $headers));
            }
            
            // Use PHPMailer for SMTP
            require_once '/var/www/html/vendor/autoload.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host = $smtpConfig['smtp_host'];
            $mail->SMTPAuth = !empty($smtpConfig['smtp_username']);
            $mail->Username = $smtpConfig['smtp_username'];
            $mail->Password = $smtpConfig['smtp_password'];
            $mail->SMTPSecure = $smtpConfig['smtp_encryption'] === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtpConfig['smtp_port'];
            
            $mail->setFrom($smtpConfig['smtp_from_email'], $smtpConfig['smtp_from_name']);
            $mail->addAddress($to);
            
            $mail->isHTML(false);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            return $mail->send();
        } catch (Exception $e) {
            error_log("Erro ao enviar email: " . $e->getMessage());
            return false;
        }
    }
    
    public function logEmail($sessionId, $email, $type, $status, $errorMessage = null) {
        try {
            $this->db->query(
                "INSERT INTO email_logs (session_id, recipient_email, email_type, status, error_message) VALUES (?, ?, ?, ?, ?)",
                [$sessionId, $email, $type, $status, $errorMessage]
            );
        } catch (Exception $e) {
            error_log("Erro ao registrar log de email: " . $e->getMessage());
        }
    }
}