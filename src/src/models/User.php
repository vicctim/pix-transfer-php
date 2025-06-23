<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $username;
    public $email;
    public $password_hash;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function authenticate($username, $password) {
        $query = "SELECT id, username, email, password_hash FROM " . $this->table_name . " WHERE username = ? OR email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $username);
        $stmt->bindParam(2, $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $row['password_hash'])) {
                $this->id = $row['id'];
                $this->username = $row['username'];
                $this->email = $row['email'];
                return true;
            }
        }
        return false;
    }

    public function create($username, $email, $password) {
        $query = "INSERT INTO " . $this->table_name . " (username, email, password_hash) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt->bindParam(1, $username);
        $stmt->bindParam(2, $email);
        $stmt->bindParam(3, $password_hash);
        
        return $stmt->execute();
    }

    public function getById($id) {
        $query = "SELECT id, username, email FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
} 