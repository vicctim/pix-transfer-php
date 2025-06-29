<?php
require_once __DIR__ . '/../config/database.php';

class UploadSession {
    public $id;
    public $user_id;
    public $token;
    public $title;
    public $recipient_email;
    public $created_at;
    public $expires_at;
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function create($user_id, $title, $recipient_email, $expires_in) {
        try {
            $token = $this->generateToken();

            // Calcular a data de expiração
            $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_in days"));
            
            $stmt = $this->db->query(
                "INSERT INTO upload_sessions (user_id, token, title, recipient_email, expires_at) VALUES (?, ?, ?, ?, ?)",
                [$user_id, $token, $title, $recipient_email, $expires_at]
            );
            
            $this->id = $this->db->getConnection()->lastInsertId();
            $this->token = $token;
            $this->user_id = $user_id;
            $this->title = $title;
            $this->recipient_email = $recipient_email;
            $this->expires_at = $expires_at;
            
            return $this->id;
        } catch (Exception $e) {
            error_log("Erro ao criar sessão de upload: " . $e->getMessage());
            return false;
        }
    }
    
    public function getByToken($token) {
        try {
            $stmt = $this->db->query(
                "SELECT * FROM upload_sessions WHERE token = ? AND expires_at > NOW()",
                [$token]
            );
            
            $session = $stmt->fetch();
            if ($session) {
                $this->id = $session['id'];
                $this->user_id = $session['user_id'];
                $this->token = $session['token'];
                $this->title = $session['title'];
                $this->recipient_email = $session['recipient_email'];
                $this->created_at = $session['created_at'];
                $this->expires_at = $session['expires_at'];
                return $session;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Erro ao buscar sessão: " . $e->getMessage());
            return false;
        }
    }
    
    public function getById($id) {
        try {
            $stmt = $this->db->query(
                "SELECT * FROM upload_sessions WHERE id = ?",
                [$id]
            );
            
            $session = $stmt->fetch();
            if ($session) {
                $this->id = $session['id'];
                $this->user_id = $session['user_id'];
                $this->token = $session['token'];
                $this->title = $session['title'];
                $this->recipient_email = $session['recipient_email'];
                $this->created_at = $session['created_at'];
                $this->expires_at = $session['expires_at'];
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Erro ao buscar sessão por ID: " . $e->getMessage());
            return false;
        }
    }
    
    public function getByUserId($user_id) {
        try {
            $stmt = $this->db->query(
                "SELECT us.*, u.username FROM upload_sessions us 
                 JOIN users u ON us.user_id = u.id 
                 WHERE us.user_id = ? ORDER BY us.created_at DESC",
                [$user_id]
            );
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erro ao buscar sessões do usuário: " . $e->getMessage());
            return [];
        }
    }
    
    public function delete($id) {
        try {
            $stmt = $this->db->query("DELETE FROM upload_sessions WHERE id = ?", [$id]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Erro ao deletar sessão: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateExpiration($id, $expires_at) {
        try {
            $stmt = $this->db->query(
                "UPDATE upload_sessions SET expires_at = ? WHERE id = ?", 
                [$expires_at, $id]
            );
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Erro ao atualizar expiração: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAll() {
        try {
            $stmt = $this->db->query(
                "SELECT us.*, u.username FROM upload_sessions us 
                 JOIN users u ON us.user_id = u.id 
                 ORDER BY us.created_at DESC"
            );
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erro ao buscar todas as sessões: " . $e->getMessage());
            return [];
        }
    }
    
    public function cleanupExpired() {
        try {
            $stmt = $this->db->query("DELETE FROM upload_sessions WHERE expires_at < NOW()");
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Erro ao limpar sessões expiradas: " . $e->getMessage());
            return false;
        }
    }
    
    private function generateToken() {
        return bin2hex(random_bytes(16));
    }
} 