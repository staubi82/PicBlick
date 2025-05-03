<?php
/**
 * Auth Class
 * 
 * Stellt Funktionen für die Authentifizierung bereit
 */
class Auth
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Registriert einen neuen Benutzer
     * 
     * @param string $username Benutzername
     * @param string $password Passwort
     * @param string $email E-Mail-Adresse
     * @return array|bool Benutzer-Array bei Erfolg, false bei Fehler
     */
    public function register($username, $password, $email)
    {
        // Prüfe, ob Benutzername oder E-Mail bereits existieren
        $existingUser = $this->db->fetchOne(
            "SELECT id FROM users WHERE username = :username OR email = :email",
            [
                'username' => $username,
                'email' => $email
            ]
        );
        
        if ($existingUser) {
            return false;
        }
        
        // Passwort hashen
        $hashedPassword = password_hash($password, PASSWORD_ALGO);
        
        // Benutzer in die Datenbank einfügen
        $userId = $this->db->insert('users', [
            'username' => $username,
            'password' => $hashedPassword,
            'email' => $email
        ]);
        
        if (!$userId) {
            return false;
        }
        
        // Benutzerverzeichnis erstellen
        $userDir = USERS_PATH . '/user_' . $userId;
        if (!file_exists($userDir)) {
            mkdir($userDir, 0755, true);
        }
        
        // Favoriten-Verzeichnis erstellen
        $favoritesDir = $userDir . '/favorites';
        if (!file_exists($favoritesDir)) {
            mkdir($favoritesDir, 0755, true);
        }
        
        return $this->getUserById($userId);
    }
    
    /**
     * Authentifiziert einen Benutzer
     * 
     * @param string $username Benutzername
     * @param string $password Passwort
     * @return array|bool Benutzer-Array bei Erfolg, false bei Fehler
     */
    public function login($username, $password)
    {
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE username = :username",
            ['username' => $username]
        );
        
        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }
        
        // Sitzung starten, wenn noch nicht geschehen
        if (session_status() == PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
        
        // In der Sitzung speichern
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_time'] = time();
        
        // CSRF-Token generieren
        $this->regenerateCsrfToken();
        
        return $user;
    }
    
    /**
     * Meldet den aktuellen Benutzer ab
     * 
     * @return bool true bei Erfolg
     */
    public function logout()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
        
        // Session-Variablen löschen
        $_SESSION = [];
        
        // Session-Cookie löschen
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Session zerstören
        session_destroy();
        
        return true;
    }
    
    /**
     * Prüft, ob ein Benutzer angemeldet ist
     * 
     * @return bool true wenn angemeldet
     */
    public function isLoggedIn()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
        
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Generiert ein neues CSRF-Token
     * 
     * @return string Das generierte Token
     */
    public function regenerateCsrfToken()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION[CSRF_TOKEN_NAME] = $token;
        
        return $token;
    }
    
    /**
     * Prüft ein CSRF-Token
     * 
     * @param string $token Das zu prüfende Token
     * @return bool true wenn gültig
     */
    public function validateCsrfToken($token)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
        
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            return false;
        }
        
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * Gibt das CSRF-Token zurück
     * 
     * @return string Das aktuelle CSRF-Token
     */
    public function getCsrfToken()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
        
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            return $this->regenerateCsrfToken();
        }
        
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Gibt die ID des aktuell angemeldeten Benutzers zurück
     * 
     * @return int|null ID des Benutzers oder null
     */
    public function getCurrentUserId()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $_SESSION['user_id'];
    }
    
    /**
     * Gibt den aktuell angemeldeten Benutzer zurück
     * 
     * @return array|null Benutzer-Array oder null
     */
    public function getCurrentUser()
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return null;
        }
        
        return $this->getUserById($userId);
    }
    
    /**
     * Gibt einen Benutzer anhand seiner ID zurück
     * 
     * @param int $id Benutzer-ID
     * @return array|null Benutzer-Array oder null
     */
    public function getUserById($id)
    {
        $user = $this->db->fetchOne(
            "SELECT id, username, email, profile_image, created_at FROM users WHERE id = :id",
            ['id' => $id]
        );
        
        return $user ?: null;
    }
    
    /**
     * Setzt ein neues Passwort für einen Benutzer
     * 
     * @param int $userId Benutzer-ID
     * @param string $newPassword Neues Passwort
     * @return bool true bei Erfolg
     */
    public function changePassword($userId, $newPassword)
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_ALGO);
        
        $affected = $this->db->update(
            'users',
            ['password' => $hashedPassword],
            'id = :id',
            ['id' => $userId]
        );
        
        return $affected > 0;
    }
    
    /**
     * Überprüft ein Passwort für einen bestimmten Benutzer
     * 
     * @param int $userId Benutzer-ID
     * @param string $password Zu prüfendes Passwort
     * @return bool true wenn das Passwort korrekt ist
     */
    public function verifyPassword($userId, $password)
    {
        $user = $this->db->fetchOne(
            "SELECT password FROM users WHERE id = :id",
            ['id' => $userId]
        );
        
        if (!$user) {
            return false;
        }
        
        return password_verify($password, $user['password']);
    }
    
    /**
     * Prüft die Sitzung auf Gültigkeit und aktualisiert ggf.
     * 
     * @return void
     */
    public function checkSession()
    {
        if (!$this->isLoggedIn()) {
            return;
        }
        
        // Prüfe, ob die Sitzung abgelaufen ist
        $sessionLifetime = SESSION_LIFETIME;
        $currentTime = time();
        $loginTime = $_SESSION['login_time'] ?? 0;
        
        if ($currentTime - $loginTime > $sessionLifetime) {
            $this->logout();
            header('Location: /login.php?expired=1');
            exit;
        }
        
        // Aktualisiere den Login-Zeitstempel, wenn die Hälfte der Lebensdauer abgelaufen ist
        if ($currentTime - $loginTime > ($sessionLifetime / 2)) {
            $_SESSION['login_time'] = $currentTime;
        }
    }
}