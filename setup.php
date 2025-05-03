<?php
/**
 * Fotogalerie - Setup-Skript
 *
 * Dieses Skript initialisiert die Anwendung und erstellt die grundlegende Datenbankstruktur
 */

// Maximale Ausführungszeit erhöhen
ini_set('max_execution_time', 300);
ini_set('display_errors', 1);

// Startzeit festhalten
$startTime = microtime(true);

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Fotogalerie - Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #121212;
            color: #e0e0e0;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2 {
            color: #6200ee;
        }
        .success {
            color: #4caf50;
            background-color: rgba(76, 175, 80, 0.1);
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .error {
            color: #cf6679;
            background-color: rgba(207, 102, 121, 0.1);
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .warning {
            color: #ff9800;
            background-color: rgba(255, 152, 0, 0.1);
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        pre {
            background-color: #1e1e1e;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .btn {
            display: inline-block;
            background-color: #6200ee;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background-color: #5000c5;
        }
        .step {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #1e1e1e;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <h1>Fotogalerie - Setup</h1>";

// Prüfen, ob Konfigurationsdatei existiert
if (!file_exists(__DIR__ . '/app/config.php')) {
    echo "<div class='error'>Konfigurationsdatei nicht gefunden. Bitte stellen Sie sicher, dass die Anwendung korrekt installiert ist.</div>";
    echo "</body></html>";
    exit;
}

// Lade Konfiguration
require_once __DIR__ . '/app/config.php';

echo "<div class='step'>
    <h2>Schritt 1: Umgebungsprüfung</h2>";

// Prüfe PHP-Version
$requiredPhpVersion = '7.4.0';
if (version_compare(PHP_VERSION, $requiredPhpVersion, '<')) {
    echo "<div class='error'>Ihre PHP-Version (" . PHP_VERSION . ") ist zu alt. Mindestens PHP $requiredPhpVersion wird benötigt.</div>";
    echo "</body></html>";
    exit;
}
echo "<div class='success'>PHP-Version: " . PHP_VERSION . " ✓</div>";

// Prüfe erforderliche Erweiterungen
$requiredExtensions = [
    'sqlite3' => 'SQLite3-Datenbankunterstützung',
    'gd' => 'GD-Bildbearbeitung',
    'exif' => 'EXIF-Daten für Bilder',
];

$missingExtensions = [];
foreach ($requiredExtensions as $extension => $description) {
    if (!extension_loaded($extension)) {
        $missingExtensions[] = "$extension ($description)";
    }
}

if (!empty($missingExtensions)) {
    echo "<div class='error'>Fehlende PHP-Erweiterungen:<br>";
    echo "<ul>";
    foreach ($missingExtensions as $ext) {
        echo "<li>$ext</li>";
    }
    echo "</ul></div>";
    
    if (in_array('sqlite3', array_keys($missingExtensions))) {
        echo "<div class='error'>Die SQLite3-Erweiterung ist erforderlich für die Datenbank. Installation nicht möglich.</div>";
        echo "</body></html>";
        exit;
    }
    
    echo "<div class='warning'>Einige nicht-kritische Erweiterungen fehlen. Die Anwendung funktioniert möglicherweise nicht vollständig.</div>";
} else {
    echo "<div class='success'>Alle erforderlichen PHP-Erweiterungen sind installiert. ✓</div>";
}

// Prüfe Schreibrechte
$dirsToCheck = [
    __DIR__,
    __DIR__ . '/storage',
    __DIR__ . '/storage/users',
    __DIR__ . '/storage/thumbs',
    __DIR__ . '/storage/trash'
];

$notWritable = [];
foreach ($dirsToCheck as $dir) {
    if (!is_writable($dir)) {
        $notWritable[] = $dir;
    }
}

if (!empty($notWritable)) {
    echo "<div class='error'>Folgende Verzeichnisse haben keine Schreibrechte:<br>";
    echo "<ul>";
    foreach ($notWritable as $dir) {
        echo "<li>" . htmlspecialchars($dir) . "</li>";
    }
    echo "</ul></div>";
    echo "<div class='warning'>Bitte setzen Sie die Rechte mit: <code>chmod -R 755 " . __DIR__ . "</code></div>";
} else {
    echo "<div class='success'>Alle erforderlichen Verzeichnisse haben Schreibrechte. ✓</div>";
}

echo "</div>"; // Ende Schritt 1

echo "<div class='step'>
    <h2>Schritt 2: Datenbankinitialisierung</h2>";

// Lade Datenbankklasse
require_once __DIR__ . '/lib/database.php';

try {
    // Datenbankverbindung herstellen
    $db = Database::getInstance();
    
    // Prüfe, ob die Datenbank bereits initialisiert ist
    $dbExists = file_exists(DB_PATH);
    
    if ($dbExists) {
        echo "<div class='warning'>Datenbank existiert bereits. Überspringen der Initialisierung.</div>";
    } else {
        // Schema-Datei laden und ausführen
        $schemaFile = __DIR__ . '/lib/schema.sql';
        
        if (!file_exists($schemaFile)) {
            echo "<div class='error'>Schema-Datei nicht gefunden: $schemaFile</div>";
            echo "</body></html>";
            exit;
        }
        
        $db->importSQL($schemaFile);
        echo "<div class='success'>Datenbankschema erfolgreich importiert. ✓</div>";
        
        // Erstelle Demo-Admin
        $adminId = $db->insert('users', [
            'username' => INIT_ADMIN_USERNAME,
            'password' => password_hash(INIT_ADMIN_PASSWORD, PASSWORD_ALGO),
            'email' => INIT_ADMIN_EMAIL
        ]);
        
        if ($adminId) {
            echo "<div class='success'>Standard-Administrator erstellt:<br>
                 Benutzername: " . htmlspecialchars(INIT_ADMIN_USERNAME) . "<br>
                 Passwort: " . htmlspecialchars(INIT_ADMIN_PASSWORD) . "<br>
                 <strong>Bitte ändern Sie das Passwort nach dem ersten Login!</strong>
                 </div>";
            
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
            
            echo "<div class='success'>Benutzerverzeichnisse erstellt. ✓</div>";
        } else {
            echo "<div class='error'>Fehler beim Erstellen des Administrator-Kontos.</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='error'>Datenbankfehler: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "</body></html>";
    exit;
}

echo "</div>"; // Ende Schritt 2

echo "<div class='step'>
    <h2>Schritt 3: Verzeichnisstruktur</h2>";

// Erstelle Verzeichnisse
$directories = [
    STORAGE_PATH,
    USERS_PATH,
    THUMBS_PATH,
    TRASH_PATH,
    TRASH_PATH . '/users',
    TRASH_PATH . '/content'
];

$dirErrors = [];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0755, true)) {
            $dirErrors[] = $dir;
        } else {
            echo "<div class='success'>Verzeichnis erstellt: " . htmlspecialchars($dir) . " ✓</div>";
        }
    } else {
        echo "<div class='success'>Verzeichnis existiert bereits: " . htmlspecialchars($dir) . " ✓</div>";
    }
}

if (!empty($dirErrors)) {
    echo "<div class='error'>Folgende Verzeichnisse konnten nicht erstellt werden:<br>";
    echo "<ul>";
    foreach ($dirErrors as $dir) {
        echo "<li>" . htmlspecialchars($dir) . "</li>";
    }
    echo "</ul></div>";
} else {
    echo "<div class='success'>Alle Verzeichnisse wurden erfolgreich erstellt. ✓</div>";
}

// Erstelle .htaccess-Schutz für Speicherverzeichnisse
$htaccessContent = "# Verbiete direkten Zugriff auf dieses Verzeichnis
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ - [F,L]
</IfModule>

<FilesMatch \".(jpg|jpeg|png|gif)$\">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
</FilesMatch>";

$htaccessFiles = [
    USERS_PATH . '/.htaccess',
    THUMBS_PATH . '/.htaccess',
    TRASH_PATH . '/.htaccess'
];

foreach ($htaccessFiles as $htaccessFile) {
    if (!file_exists($htaccessFile)) {
        if (file_put_contents($htaccessFile, $htaccessContent)) {
            echo "<div class='success'>.htaccess-Schutz erstellt: " . htmlspecialchars($htaccessFile) . " ✓</div>";
        } else {
            echo "<div class='error'>.htaccess konnte nicht erstellt werden: " . htmlspecialchars($htaccessFile) . "</div>";
        }
    } else {
        echo "<div class='success'>.htaccess existiert bereits: " . htmlspecialchars($htaccessFile) . " ✓</div>";
    }
}

echo "</div>"; // Ende Schritt 3

// Endzeit berechnen
$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);

echo "<div class='success'>
    Setup erfolgreich abgeschlossen! (Dauer: {$executionTime}s)
</div>";

echo "<p>Sie können sich jetzt mit den folgenden Zugangsdaten anmelden:</p>
<pre>
Benutzername: " . htmlspecialchars(INIT_ADMIN_USERNAME) . "
Passwort: " . htmlspecialchars(INIT_ADMIN_PASSWORD) . "
</pre>";

echo "<a href='/public/index.php' class='btn'>Zur Fotogalerie</a>";

echo "</body></html>";