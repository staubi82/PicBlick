<?php
/**
 * Fotogalerie - Initialisierungsskript
 *
 * Initialisiert die Datenbank und Grundstrukturen der Anwendung
 */

// Konfiguration laden
require_once __DIR__ . '/../app/config.php';

// Datenbank-Klasse laden
require_once __DIR__ . '/database.php';

// Prüfen, ob Bildverarbeitung verfügbar ist
if (!extension_loaded('gd')) {
    die('Die GD-Bildverarbeitungsbibliothek ist nicht verfügbar. Bitte installieren Sie die PHP-GD-Erweiterung.');
}

// Prüfen, ob SQLite verfügbar ist
if (!extension_loaded('sqlite3')) {
    die('Die SQLite3-Erweiterung ist nicht verfügbar. Bitte installieren Sie die PHP-SQLite3-Erweiterung.');
}

// Basisverzeichnisse erstellen, falls nicht vorhanden
$directories = [
    STORAGE_PATH,
    USERS_PATH,
    THUMBS_PATH,
    TRASH_PATH,
    TRASH_PATH . '/users',
    TRASH_PATH . '/content'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0755, true)) {
            die("Fehler beim Erstellen des Verzeichnisses: $dir");
        }
    }
}

// Datei- und Verzeichnisberechtigungen prüfen
if (!is_writable(STORAGE_PATH)) {
    die("Das Verzeichnis " . STORAGE_PATH . " ist nicht beschreibbar. Bitte setzen Sie die entsprechenden Berechtigungen.");
}

// Verbindung zur Datenbank herstellen
try {
    $db = Database::getInstance();
    
    // Prüfen, ob Datenbankdatei existiert, wenn nicht, dann neu erstellen
    $dbExists = file_exists(DB_PATH);
    
    // Wenn die Datenbank nicht existiert, Schema importieren
    if (!$dbExists) {
        echo "Datenbank wird initialisiert...\n";
        
        // Schema importieren
        $schemaFile = __DIR__ . '/schema.sql';
        $db->importSQL($schemaFile);
        
        echo "Datenbank erfolgreich initialisiert.\n";
        
        // Beispiel-Administrator erstellen
        if (defined('INIT_ADMIN_USER') && INIT_ADMIN_USER) {
            $adminUsername = defined('INIT_ADMIN_USERNAME') ? INIT_ADMIN_USERNAME : 'admin';
            $adminPassword = defined('INIT_ADMIN_PASSWORD') ? INIT_ADMIN_PASSWORD : 'admin123';
            $adminEmail = defined('INIT_ADMIN_EMAIL') ? INIT_ADMIN_EMAIL : 'admin@example.com';
            
            // Passwort hashen
            $hashedPassword = password_hash($adminPassword, PASSWORD_ALGO);
            
            // Admin-Benutzer einfügen
            $adminId = $db->insert('users', [
                'username' => $adminUsername,
                'password' => $hashedPassword,
                'email' => $adminEmail
            ]);
            
            if ($adminId) {
                echo "Administrator-Konto erstellt (Benutzername: $adminUsername).\n";
                
                // Admin-Verzeichnis erstellen
                $adminDir = USERS_PATH . '/user_' . $adminId;
                if (!file_exists($adminDir)) {
                    mkdir($adminDir, 0755, true);
                }
                
                // Admin-Favoriten-Verzeichnis erstellen
                $adminFavoritesDir = $adminDir . '/favorites';
                if (!file_exists($adminFavoritesDir)) {
                    mkdir($adminFavoritesDir, 0755, true);
                }
            } else {
                echo "Fehler beim Erstellen des Administrator-Kontos.\n";
            }
        }
    } else {
        echo "Datenbank bereits vorhanden.\n";
    }
} catch (Exception $e) {
    die("Datenbankfehler: " . $e->getMessage());
}

echo "Initialisierung abgeschlossen.\n";