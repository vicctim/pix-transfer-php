<?php
require_once __DIR__ . '/../config/database.php';

class ShortUrl {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Cria uma URL curta para um token de download
     */
    public function create($original_token, $expires_at = null) {
        try {
            // Gerar código curto de 6 caracteres
            $short_code = $this->generateShortCode();
            
            // Verificar se já existe
            while ($this->exists($short_code)) {
                $short_code = $this->generateShortCode();
            }
            
            $stmt = $this->db->query(
                "INSERT INTO short_urls (short_code, original_token, expires_at, created_at) VALUES (?, ?, ?, NOW())",
                [$short_code, $original_token, $expires_at]
            );
            
            return $short_code;
            
        } catch (Exception $e) {
            error_log("Erro ao criar URL curta: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Busca o token original a partir do código curto
     */
    public function getOriginalToken($short_code) {
        try {
            $stmt = $this->db->query(
                "SELECT original_token, expires_at FROM short_urls WHERE short_code = ? AND (expires_at IS NULL OR expires_at > NOW())",
                [$short_code]
            );
            
            $result = $stmt->fetch();
            return $result ? $result['original_token'] : false;
            
        } catch (Exception $e) {
            error_log("Erro ao buscar URL curta: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica se um código curto existe
     */
    public function exists($short_code) {
        try {
            $stmt = $this->db->query(
                "SELECT COUNT(*) as count FROM short_urls WHERE short_code = ?",
                [$short_code]
            );
            
            $result = $stmt->fetch();
            return $result['count'] > 0;
            
        } catch (Exception $e) {
            error_log("Erro ao verificar existência de URL curta: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gera um código curto aleatório
     */
    private function generateShortCode($length = 6) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        
        return $randomString;
    }
    
    /**
     * Remove URLs expiradas
     */
    public function cleanExpired() {
        try {
            $stmt = $this->db->query(
                "DELETE FROM short_urls WHERE expires_at IS NOT NULL AND expires_at < NOW()"
            );
            
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            error_log("Erro ao limpar URLs expiradas: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registra acesso a uma URL curta
     */
    public function logAccess($short_code, $ip_address, $user_agent = '') {
        try {
            $stmt = $this->db->query(
                "INSERT INTO short_url_access (short_code, ip_address, user_agent, accessed_at) VALUES (?, ?, ?, NOW())",
                [$short_code, $ip_address, $user_agent]
            );
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao registrar acesso de URL curta: " . $e->getMessage());
            return false;
        }
    }
}
?>