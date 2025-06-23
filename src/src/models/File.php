<?php
require_once __DIR__ . '/../config/database.php';

class File {
    private $conn;
    private $table_name = "files";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function create($session_id, $original_name, $stored_name, $file_path, $file_size, $mime_type = null) {
        $query = "INSERT INTO " . $this->table_name . " (session_id, original_name, stored_name, file_path, file_size, mime_type) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(1, $session_id);
        $stmt->bindParam(2, $original_name);
        $stmt->bindParam(3, $stored_name);
        $stmt->bindParam(4, $file_path);
        $stmt->bindParam(5, $file_size);
        $stmt->bindParam(6, $mime_type);
        
        return $stmt->execute();
    }

    public function getBySessionId($session_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE session_id = ? ORDER BY uploaded_at ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $session_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function delete($id) {
        $file = $this->getById($id);
        if ($file && file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
        
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        
        return $stmt->execute();
    }

    public function getTotalSizeBySession($session_id) {
        $query = "SELECT SUM(file_size) as total_size FROM " . $this->table_name . " WHERE session_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $session_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total_size'] ?? 0;
    }

    public function getCountBySession($session_id) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE session_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $session_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
} 