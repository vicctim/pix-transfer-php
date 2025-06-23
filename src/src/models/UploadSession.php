<?php
require_once __DIR__ . '/../config/database.php';

class UploadSession {
    private $conn;
    private $table_name = "upload_sessions";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function create($user_id, $title = null, $description = null, $expires_in_days = 7) {
        $session_token = $this->generateToken();
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_in_days} days"));
        
        $query = "INSERT INTO " . $this->table_name . " (user_id, session_token, title, description, expires_at) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(1, $user_id);
        $stmt->bindParam(2, $session_token);
        $stmt->bindParam(3, $title);
        $stmt->bindParam(4, $description);
        $stmt->bindParam(5, $expires_at);
        
        if ($stmt->execute()) {
            return $session_token;
        }
        return false;
    }

    public function getByToken($token) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE session_token = ? AND expires_at > NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $token);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByUserId($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete($session_id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $session_id);
        
        return $stmt->execute();
    }

    public function cleanupExpired() {
        $query = "DELETE FROM " . $this->table_name . " WHERE expires_at < NOW()";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute();
    }

    private function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
} 