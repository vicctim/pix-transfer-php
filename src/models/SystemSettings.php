<?php
require_once __DIR__ . '/../config/database.php';

class SystemSettings {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function get($key, $default = null) {
        try {
            $stmt = $this->db->query("SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ?", [$key]);
            $result = $stmt->fetch();
            
            if (!$result) {
                return $default;
            }
            
            return $this->convertValue($result['setting_value'], $result['setting_type']);
        } catch (Exception $e) {
            error_log("Erro ao buscar configuração: " . $e->getMessage());
            return $default;
        }
    }
    
    public function set($key, $value, $type = 'string') {
        try {
            $storedValue = $this->prepareValue($value, $type);
            
            $stmt = $this->db->query(
                "INSERT INTO system_settings (setting_key, setting_value, setting_type) VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE setting_value = ?, setting_type = ?",
                [$key, $storedValue, $type, $storedValue, $type]
            );
            
            return true;
        } catch (Exception $e) {
            error_log("Erro ao salvar configuração: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAll() {
        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value, setting_type FROM system_settings ORDER BY setting_key");
            $results = $stmt->fetchAll();
            
            $settings = [];
            foreach ($results as $row) {
                $settings[$row['setting_key']] = $this->convertValue($row['setting_value'], $row['setting_type']);
            }
            
            return $settings;
        } catch (Exception $e) {
            error_log("Erro ao buscar todas as configurações: " . $e->getMessage());
            return [];
        }
    }
    
    public function getMultiple($keys) {
        try {
            $placeholders = str_repeat('?,', count($keys) - 1) . '?';
            $stmt = $this->db->query(
                "SELECT setting_key, setting_value, setting_type FROM system_settings WHERE setting_key IN ($placeholders)",
                $keys
            );
            $results = $stmt->fetchAll();
            
            $settings = [];
            foreach ($results as $row) {
                $settings[$row['setting_key']] = $this->convertValue($row['setting_value'], $row['setting_type']);
            }
            
            return $settings;
        } catch (Exception $e) {
            error_log("Erro ao buscar configurações múltiplas: " . $e->getMessage());
            return [];
        }
    }
    
    public function delete($key) {
        try {
            $stmt = $this->db->query("DELETE FROM system_settings WHERE setting_key = ?", [$key]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Erro ao deletar configuração: " . $e->getMessage());
            return false;
        }
    }
    
    public function isSetupComplete() {
        return $this->get('is_setup_complete', false) === true;
    }
    
    public function markSetupComplete() {
        return $this->set('is_setup_complete', true, 'boolean');
    }
    
    private function convertValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                return is_numeric($value) ? (strpos($value, '.') !== false ? (float)$value : (int)$value) : 0;
            case 'json':
                return json_decode($value, true) ?: [];
            default:
                return $value;
        }
    }
    
    private function prepareValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'number':
                return (string)$value;
            case 'json':
                return json_encode($value);
            default:
                return (string)$value;
        }
    }
    
    public function getTimezone() {
        return $this->get('system_timezone', 'America/Sao_Paulo');
    }
    
    public function getSiteUrl() {
        $url = $this->get('site_url', 'http://localhost:3131');
        
        // Support for Cloudflare tunnel
        if ($this->get('cloudflare_tunnel_enabled', false)) {
            $tunnelUrl = $this->get('cloudflare_tunnel_url', '');
            if (!empty($tunnelUrl)) {
                return rtrim($tunnelUrl, '/');
            }
        }
        
        return rtrim($url, '/');
    }
    
    public function getSmtpConfig() {
        return $this->getMultiple([
            'smtp_host',
            'smtp_port', 
            'smtp_username',
            'smtp_password',
            'smtp_encryption',
            'smtp_from_email',
            'smtp_from_name'
        ]);
    }
}