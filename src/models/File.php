<?php
require_once __DIR__ . '/../config/database.php';

class File {
    public $id;
    public $session_id;
    public $original_name;
    public $stored_name;
    public $file_path;
    public $file_size;
    public $mime_type;
    public $uploaded_at;
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function create($session_id, $original_name, $stored_name, $file_path, $file_size, $mime_type) {
        try {
            $stmt = $this->db->query(
                "INSERT INTO files (session_id, original_name, stored_name, file_path, file_size, mime_type) VALUES (?, ?, ?, ?, ?, ?)",
                [$session_id, $original_name, $stored_name, $file_path, $file_size, $mime_type]
            );
            
            $this->id = $this->db->getConnection()->lastInsertId();
            $this->session_id = $session_id;
            $this->original_name = $original_name;
            $this->stored_name = $stored_name;
            $this->file_path = $file_path;
            $this->file_size = $file_size;
            $this->mime_type = $mime_type;
            
            return $this->id;
        } catch (Exception $e) {
            error_log("Erro ao criar arquivo: " . $e->getMessage());
            return false;
        }
    }
    
    public function getBySessionId($session_id) {
        try {
            $stmt = $this->db->query(
                "SELECT * FROM files WHERE session_id = ? ORDER BY uploaded_at ASC",
                [$session_id]
            );
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erro ao buscar arquivos da sessão: " . $e->getMessage());
            return [];
        }
    }
    
    public function getById($id) {
        try {
            $stmt = $this->db->query("SELECT * FROM files WHERE id = ?", [$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Erro ao buscar arquivo: " . $e->getMessage());
            return false;
        }
    }
    
    public function delete($id) {
        try {
            $stmt = $this->db->query("DELETE FROM files WHERE id = ?", [$id]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Erro ao deletar arquivo: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteBySessionId($session_id) {
        try {
            $stmt = $this->db->query("DELETE FROM files WHERE session_id = ?", [$session_id]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Erro ao deletar arquivos da sessão: " . $e->getMessage());
            return false;
        }
    }
    
    public function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function getTotalSizeBySession($session_id) {
        $query = "SELECT SUM(file_size) as total_size FROM files WHERE session_id = ?";
        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->bindParam(1, $session_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total_size'] ?? 0;
    }

    public function getCountBySession($session_id) {
        $query = "SELECT COUNT(*) as count FROM files WHERE session_id = ?";
        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->bindParam(1, $session_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
} 