<?php
/**
 * Versão simplificada do PHPMailer para SMTP
 */
class SimplePHPMailer {
    private $smtp_host;
    private $smtp_port;
    private $smtp_user;
    private $smtp_pass;
    private $smtp_encryption;
    private $from_email;
    private $from_name;
    
    public function __construct($host, $port, $user, $pass, $encryption = 'ssl') {
        $this->smtp_host = $host;
        $this->smtp_port = $port;
        $this->smtp_user = $user;
        $this->smtp_pass = $pass;
        $this->smtp_encryption = $encryption;
    }
    
    public function setFrom($email, $name = '') {
        $this->from_email = $email;
        $this->from_name = $name;
    }
    
    public function send($to, $subject, $body, $cc = null) {
        try {
            // Usar mail() do PHP com configuração SMTP personalizada
            $headers = [];
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/html; charset=UTF-8';
            $headers[] = 'From: ' . $this->from_name . ' <' . $this->from_email . '>';
            $headers[] = 'Reply-To: ' . $this->from_email;
            $headers[] = 'X-Mailer: PHP/' . phpversion();
            
            if ($cc) {
                $headers[] = 'Cc: ' . $cc;
            }
            
            // Configurar ini settings para SMTP
            ini_set('SMTP', $this->smtp_host);
            ini_set('smtp_port', $this->smtp_port);
            
            $result = mail($to, $subject, $body, implode("\r\n", $headers));
            
            if ($result) {
                error_log("Email sent successfully to: $to");
                return true;
            } else {
                error_log("Failed to send email to: $to");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return false;
        }
    }
}
?>