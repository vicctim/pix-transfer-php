<?php
require_once __DIR__ . '/../config/database.php';

class User {
    public $id;
    public $username;
    public $email;
    public $role;
    public $created_at;
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function authenticate($username, $password) {
        try {
            file_put_contents('/var/www/html/logs/app.log', "[AUTH_START] Autenticando usuário: $username\n", FILE_APPEND);

            $stmt = $this->db->query(
                "SELECT id, username, email, password_hash, role FROM users WHERE username = ? OR email = ?",
                [$username, $username]
            );
            
            $user = $stmt->fetch();
            
            if ($user) {
                file_put_contents('/var/www/html/logs/app.log', "[AUTH_USER_FOUND] Usuário encontrado: " . print_r($user, true) . "\n", FILE_APPEND);
                
                $isPasswordCorrect = password_verify($password, $user['password_hash']);
                file_put_contents('/var/www/html/logs/app.log', "[AUTH_PASSWORD_VERIFY] A senha está correta? " . ($isPasswordCorrect ? 'Sim' : 'Não') . "\n", FILE_APPEND);

                if ($isPasswordCorrect) {
                    $this->id = $user['id'];
                    $this->username = $user['username'];
                    $this->email = $user['email'];
                    $this->role = $user['role'];
                    file_put_contents('/var/www/html/logs/app.log', "[AUTH_SUCCESS] Autenticação bem-sucedida para: $username\n", FILE_APPEND);
                    return true;
                }
            } else {
                file_put_contents('/var/www/html/logs/app.log', "[AUTH_USER_NOT_FOUND] Usuário não encontrado no banco de dados: $username\n", FILE_APPEND);
            }
            
            file_put_contents('/var/www/html/logs/app.log', "[AUTH_FAIL] Falha na autenticação para: $username\n", FILE_APPEND);
            return false;
        } catch (Exception $e) {
            error_log("Erro na autenticação: " . $e->getMessage());
            file_put_contents('/var/www/html/logs/app.log', "[AUTH_EXCEPTION] Exceção na autenticação: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }
    
    public function create($username, $email, $password, $role = 'user') {
        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $this->db->query(
                "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)",
                [$username, $email, $password_hash, $role]
            );
            
            return $this->db->getConnection()->lastInsertId();
        } catch (Exception $e) {
            error_log("Erro ao criar usuário: " . $e->getMessage());
            return false;
        }
    }
    
    public function getById($id) {
        try {
            $stmt = $this->db->query("SELECT * FROM users WHERE id = ?", [$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Erro ao buscar usuário: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAll() {
        try {
            $stmt = $this->db->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erro ao buscar usuários: " . $e->getMessage());
            return [];
        }
    }
    
    public function getAllUsers() {
        return $this->getAll();
    }
    
    public function isAdmin($id) {
        try {
            $user = $this->getById($id);
            return $user && $user['role'] === 'admin';
        } catch (Exception $e) {
            error_log("Erro ao verificar se usuário é admin: " . $e->getMessage());
            return false;
        }
    }
    
    public function delete($id) {
        try {
            $stmt = $this->db->query("DELETE FROM users WHERE id = ?", [$id]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Erro ao deletar usuário: " . $e->getMessage());
            return false;
        }
    }
} 