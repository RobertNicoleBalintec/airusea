<?php
// admin_auth.php - Super-Admin Authentication
session_start();
require_once 'db.php';

class SuperAdminAuth {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function login($email, $password) {
        // Prepare statement to prevent SQL injection
        $stmt = $this->conn->prepare("
            SELECT adminID, adminName, adminEmail, password, roleID 
            FROM ADMINS 
            WHERE adminEmail = ? AND roleID = 3
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            
            // Verify password (assuming passwords are hashed)
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['adminID'];
                $_SESSION['admin_name'] = $admin['adminName'];
                $_SESSION['admin_email'] = $admin['adminEmail'];
                $_SESSION['admin_role'] = 'superadmin';
                $_SESSION['admin_logged_in'] = true;
                
                // Log login action
                $this->logAction($admin['adminID'], 'login', 'system', 0, 'Admin logged in');
                
                return ['success' => true, 'message' => 'Login successful'];
            }
        }
        
        return ['success' => false, 'message' => 'Invalid credentials or insufficient privileges'];
    }
    
    public function logout() {
        if (isset($_SESSION['admin_id'])) {
            $this->logAction($_SESSION['admin_id'], 'logout', 'system', 0, 'Admin logged out');
        }
        
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header("Location: admin_index.php");
            exit();
        }
    }
    
    private function logAction($adminID, $action, $targetType, $targetID, $details) {
        $stmt = $this->conn->prepare("
            INSERT INTO ADMIN_AUDIT_LOG (adminID, action_type, target_type, target_id, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt->bind_param("ississs", $adminID, $action, $targetType, $targetID, $details, $ip, $agent);
        $stmt->execute();
    }
}

// Initialize auth system
$adminAuth = new SuperAdminAuth($conn);
?>