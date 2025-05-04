<?php
/**
 * Fotogalerie - Hauptkonfiguration
 * 
 * Diese Datei enthält alle zentralen Konfigurationsparameter
 * der Fotogalerie-Anwendung.
 */

// Debug-Modus (auf false setzen in Produktion)
define('DEBUG_MODE', true);

// Datenbankeinstellungen
define('DB_PATH', __DIR__ . '/../storage/gallery.db');

// Pfad-Einstellungen
define('ROOT_PATH', realpath(__DIR__ . '/..'));
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('USERS_PATH', STORAGE_PATH . '/users');
define('THUMBS_PATH', STORAGE_PATH . '/thumbs');
define('TRASH_PATH', STORAGE_PATH . '/trash');

// Thumbnail-Einstellungen
define('THUMB_WIDTH', 300);
define('THUMB_HEIGHT', 200);
define('THUMB_QUALITY', 85);

// Sicherheitseinstellungen
define('SESSION_LIFETIME', 3600); // 1 Stunde
define('PASSWORD_ITERATIONS', 20000);
define('PASSWORD_ALGO', PASSWORD_BCRYPT);
define('SESSION_NAME', 'fotogallery_session');
define('CSRF_TOKEN_NAME', 'fotogallery_csrf');

// Initialisierungs-Einstellungen für ersten Admin
define('INIT_ADMIN_USER', true); // Admin-Benutzer bei Erstinstallation erstellen
define('INIT_ADMIN_USERNAME', 'admin'); // Standardbenutzername
define('INIT_ADMIN_PASSWORD', 'admin123'); // Standardpasswort - UNBEDINGT ÄNDERN!
define('INIT_ADMIN_EMAIL', 'admin@example.com'); // Standard-E-Mail

// Dateisystem-Einstellungen
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('STRIP_EXIF', true);

// Favoritensystem-Einstellungen
define('FAVORITES_ENABLED', true);

// Papierkorb-Einstellungen
define('TRASH_RETENTION_DAYS', 30); // 30 Tage Aufbewahrung

// URL-Einstellungen (anpassen für Produktionsumgebung)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . $host);

// Fehlerbehandlung
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
}

// Zeitzonen-Einstellung
date_default_timezone_set('Europe/Berlin');

// Spracheinstellungen
define('DEFAULT_LANGUAGE', 'de');

// Automatische Ladezeit für AJAX-Requests (in Millisekunden)
define('AJAX_REFRESH_INTERVAL', 5000);