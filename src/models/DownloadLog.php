<?php
require_once __DIR__ . '/../config/database.php';

class DownloadLog {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function logDownload($sessionId, $fileId = null) {
        try {
            $ipAddress = $this->getRealIpAddress();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $location = $this->getLocationFromIP($ipAddress);
            
            $stmt = $this->db->query(
                "INSERT INTO download_logs (session_id, file_id, ip_address, user_agent, country, city) VALUES (?, ?, ?, ?, ?, ?)",
                [$sessionId, $fileId, $ipAddress, $userAgent, $location['country'], $location['city']]
            );
            
            $insertId = $this->db->getConnection()->lastInsertId();
            
            // Update download count (async to avoid blocking)
            if ($insertId) {
                $this->updateDownloadCount($sessionId);
            }
            
            return $insertId;
        } catch (Exception $e) {
            error_log("Erro ao registrar download: " . $e->getMessage());
            return false;
        }
    }
    
    public function getDownloadsBySession($sessionId) {
        try {
            $stmt = $this->db->query(
                "SELECT * FROM download_logs WHERE session_id = ? ORDER BY downloaded_at DESC",
                [$sessionId]
            );
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erro ao buscar downloads: " . $e->getMessage());
            return [];
        }
    }
    
    public function getDownloadCount($sessionId) {
        try {
            $stmt = $this->db->query(
                "SELECT COUNT(*) as count FROM download_logs WHERE session_id = ?",
                [$sessionId]
            );
            $result = $stmt->fetch();
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            error_log("Erro ao contar downloads: " . $e->getMessage());
            return 0;
        }
    }
    
    private function updateDownloadCount($sessionId) {
        try {
            $count = $this->getDownloadCount($sessionId);
            $this->db->query(
                "UPDATE upload_sessions SET download_count = ? WHERE id = ?",
                [$count, $sessionId]
            );
        } catch (Exception $e) {
            error_log("Erro ao atualizar contador de downloads: " . $e->getMessage());
        }
    }
    
    private function getRealIpAddress() {
        // Check for various header types that might contain the real IP
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/Proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    private function getLocationFromIP($ip) {
        // Default location - simplificado para evitar timeout
        $location = [
            'country' => 'Brasil',
            'city' => 'Local'
        ];
        
        // Skip external API call for now to avoid timeouts
        // TODO: Implement async location lookup or cache
        
        return $location;
    }
    
    public function getLocationString($country, $city) {
        if ($country === 'Unknown' && $city === 'Unknown') {
            return 'Localização desconhecida';
        }
        
        if ($country === 'Local' && $city === 'Local') {
            return 'Acesso local';
        }
        
        if ($city === 'Unknown' || $city === $country) {
            return $country;
        }
        
        return "{$city}, {$country}";
    }
    
    public function getDownloadStats($sessionId) {
        try {
            $stmt = $this->db->query(
                "SELECT 
                    COUNT(*) as total_downloads,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    MIN(downloaded_at) as first_download,
                    MAX(downloaded_at) as last_download,
                    country,
                    city,
                    COUNT(*) as location_count
                FROM download_logs 
                WHERE session_id = ? 
                GROUP BY country, city
                ORDER BY location_count DESC",
                [$sessionId]
            );
            
            $locations = $stmt->fetchAll();
            
            $stmt = $this->db->query(
                "SELECT COUNT(*) as total, COUNT(DISTINCT ip_address) as unique_count FROM download_logs WHERE session_id = ?",
                [$sessionId]
            );
            $totals = $stmt->fetch();
            
            return [
                'total_downloads' => $totals['total'] ?? 0,
                'unique_downloads' => $totals['unique_count'] ?? 0,
                'locations' => $locations
            ];
        } catch (Exception $e) {
            error_log("Erro ao buscar estatísticas de download: " . $e->getMessage());
            return [
                'total_downloads' => 0,
                'unique_downloads' => 0,
                'locations' => []
            ];
        }
    }
}