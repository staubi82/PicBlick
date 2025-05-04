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

// Prüfe ob Konfigurationsdatei existiert
if (!file_exists(__DIR__ . '/app/config.php')) {
    http_response_code(500);
    echo "Konfigurationsdatei nicht gefunden. Bitte stellen Sie sicher, dass die Anwendung korrekt installiert ist.";
    exit;
}

// Lade Konfiguration
require_once __DIR__ . '/app/config.php';

// Datenbankpfad prüfen
$dbExists = file_exists(DB_PATH);

// Wenn kein Modus gewählt wurde und die Datenbank existiert, zeige Auswahlseite
if (!isset($_GET['mode']) && $dbExists) {
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
            .options {
                display: flex;
                gap: 20px;
                margin: 30px 0;
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
            .btn-warning {
                background-color: #ff9800;
            }
            .btn-warning:hover {
                background-color: #e68a00;
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
        <h1>Fotogalerie - Setup</h1>
        <div class='step'>
            <h2>Installationsmodus wählen</h2>
            <p>Es wurde eine bestehende Datenbank gefunden. Bitte wählen Sie einen Modus:</p>
            
            <div class='options'>
                <a href='setup.php?mode=update' class='btn'>Update - Daten behalten</a>
                <a href='setup.php?mode=new' class='btn btn-warning'>Neuinstallation - Alles löschen</a>
            </div>
            
            <div class='warning'>
                <p><strong>Achtung:</strong> Bei einer Neuinstallation werden alle Daten gelöscht!</p>
            </div>
        </div>
    </body>
    </html>";
    exit;
}

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
    
    // Prüfe, ob die Datenbank existiert
    $dbExists = file_exists(DB_PATH);
    
    // Update-Modus oder Neuinstallation?
    $updateMode = isset($_GET['mode']) && $_GET['mode'] === 'update';
    
    if ($dbExists) {
        if (!$updateMode) {
            // Schließe Datenbankverbindung, um die Datei zu löschen
            $db->close();
            
            if (unlink(DB_PATH)) {
                echo "<div class='warning'>Bestehende Datenbank wurde gelöscht für Neuinitialisierung.</div>";
                // Datenbankverbindung erneut herstellen
                $db = Database::getInstance();
            } else {
                echo "<div class='error'>Konnte bestehende Datenbank nicht löschen. Versuche trotzdem zu initialisieren.</div>";
            }
        } else {
            echo "<div class='success'>Update-Modus: Bestehende Datenbank wird aktualisiert.</div>";
        }
    }
    
    // Schema-Datei laden und ausführen wenn im Neuinstallationsmodus oder DB nicht existiert
    if (!$dbExists || !$updateMode) {
        $schemaFile = __DIR__ . '/lib/schema.sql';
    
        if (!file_exists($schemaFile)) {
            echo "<div class='error'>Schema-Datei nicht gefunden: $schemaFile</div>";
            echo "</body></html>";
            exit;
        }
        
        // Lese und korrigiere das SQL-Schema
        $schemaContent = file_get_contents($schemaFile);
        
        // Korrigiere fehlerhafte albums-Tabellendefinition (doppelte Spalten entfernen)
        $schemaContent = str_replace(
            "CREATE TABLE IF NOT EXISTS albums (\n    user_id INTEGER NOT NULL,\n    name TEXT NOT NULL,\n    UNIQUE(user_id, name),",
            "CREATE TABLE IF NOT EXISTS albums (",
            $schemaContent
        );
        
        // Speichere korrigiertes Schema temporär
        $tempSchemaFile = __DIR__ . '/temp_schema.sql';
        file_put_contents($tempSchemaFile, $schemaContent);
        
        // Importiere korrigiertes Schema
        $db->importSQL($tempSchemaFile);
        
        // Lösche temporäre Schemadatei
        unlink($tempSchemaFile);
        
        echo "<div class='success'>Datenbankschema erfolgreich importiert. ✓</div>";
    }
    
    // Schema-Updates werden immer angewendet, unabhängig vom Modus, aber nur wenn die Spalten nicht bereits existieren
    
    // Prüfe vorhandene Spalten in der users-Tabelle
    $usersColumns = [];
    $albumsColumns = [];
    
    try {
        $columnsUsers = $db->fetchAll("PRAGMA table_info(users)", []);
        foreach ($columnsUsers as $col) {
            $usersColumns[] = $col['name'];
        }
        
        $columnsAlbums = $db->fetchAll("PRAGMA table_info(albums)", []);
        foreach ($columnsAlbums as $col) {
            $albumsColumns[] = $col['name'];
        }
    } catch (Exception $e) {
        echo "<div class='error'>Fehler beim Prüfen der Tabellenspalten: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    // Wende allgemeine Schema-Updates an
    $updateSchemaFile = __DIR__ . '/lib/update_schema.sql';
    if (file_exists($updateSchemaFile)) {
        // Prüfe, ob profile_image-Spalte bereits existiert
        if (in_array('profile_image', $usersColumns)) {
            echo "<div class='success'>Profil-Bild-Spalte existiert bereits. ✓</div>";
        } else {
            try {
                $db->execute("ALTER TABLE users ADD COLUMN profile_image TEXT DEFAULT NULL");
                echo "<div class='success'>Profil-Bild-Spalte zur users-Tabelle hinzugefügt. ✓</div>";
            } catch (Exception $e) {
                echo "<div class='warning'>Profil-Bild-Spalte konnte nicht hinzugefügt werden: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
        
        echo "<div class='success'>Schema-Updates berücksichtigt. ✓</div>";
    }
    
    // Wende Trash-Schema-Updates an
    $trashSchemaFile = __DIR__ . '/lib/update_trash_schema.sql';
    if (file_exists($trashSchemaFile)) {
        // Das Trash-Schema fügt Spalten zur images-Tabelle hinzu
        
        // Prüfe zuerst die vorhandenen Spalten in der images-Tabelle
        if (empty($imagesColumns)) {
            try {
                $columnsImages = $db->fetchAll("PRAGMA table_info(images)", []);
                foreach ($columnsImages as $col) {
                    $imagesColumns[] = $col['name'];
                }
            } catch (Exception $e) {
                echo "<div class='error'>Fehler beim Prüfen der images-Tabellenspalten: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
        
        // Prüfe, ob die trash-Spalten bereits in der images-Tabelle existieren
        $addTrashOriginalPath = !in_array('trash_original_path', $imagesColumns);
        $addTrashThumbnailPath = !in_array('trash_thumbnail_path', $imagesColumns);
        $addTrashExpiry = !in_array('trash_expiry', $imagesColumns);
        
        // Füge nur fehlende Spalten hinzu
        $updated = false;
        
        if ($addTrashOriginalPath) {
            try {
                $db->execute("ALTER TABLE images ADD COLUMN trash_original_path TEXT DEFAULT NULL");
                echo "<div class='success'>trash_original_path-Spalte zur images-Tabelle hinzugefügt. ✓</div>";
                $updated = true;
            } catch (Exception $e) {
                echo "<div class='warning'>trash_original_path-Spalte konnte nicht hinzugefügt werden: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            echo "<div class='success'>trash_original_path-Spalte existiert bereits in der images-Tabelle. ✓</div>";
        }
        
        if ($addTrashThumbnailPath) {
            try {
                $db->execute("ALTER TABLE images ADD COLUMN trash_thumbnail_path TEXT DEFAULT NULL");
                echo "<div class='success'>trash_thumbnail_path-Spalte zur images-Tabelle hinzugefügt. ✓</div>";
                $updated = true;
            } catch (Exception $e) {
                echo "<div class='warning'>trash_thumbnail_path-Spalte konnte nicht hinzugefügt werden: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            echo "<div class='success'>trash_thumbnail_path-Spalte existiert bereits in der images-Tabelle. ✓</div>";
        }
        
        if ($addTrashExpiry) {
            try {
                $db->execute("ALTER TABLE images ADD COLUMN trash_expiry DATETIME DEFAULT NULL");
                echo "<div class='success'>trash_expiry-Spalte zur images-Tabelle hinzugefügt. ✓</div>";
                $updated = true;
            } catch (Exception $e) {
                echo "<div class='warning'>trash_expiry-Spalte konnte nicht hinzugefügt werden: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            echo "<div class='success'>trash_expiry-Spalte existiert bereits in der images-Tabelle. ✓</div>";
        }
        
        // Erstelle oder aktualisiere die trash_images-View
        try {
            // Zuerst die View löschen, falls sie existiert, um sie zu aktualisieren
            $db->execute("DROP VIEW IF EXISTS trash_images");
            
            // View neu erstellen
            $db->execute("CREATE VIEW IF NOT EXISTS trash_images AS
                SELECT * FROM images
                WHERE deleted_at IS NOT NULL
                AND (trash_original_path IS NOT NULL OR trash_thumbnail_path IS NOT NULL)
                AND trash_expiry > datetime('now')");
                
            echo "<div class='success'>trash_images-View erfolgreich erstellt/aktualisiert. ✓</div>";
        } catch (Exception $e) {
            echo "<div class='warning'>trash_images-View konnte nicht erstellt werden: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        if ($updated) {
            echo "<div class='success'>Trash-Schema-Updates erfolgreich angewendet. ✓</div>";
        } else {
            echo "<div class='success'>Trash-Schema ist bereits aktuell. ✓</div>";
        }
    }
    
    // Wende Unteralben-Schema-Updates an
    $subalbumsSchemaFile = __DIR__ . '/lib/update_subalbums_schema.sql';
    if (file_exists($subalbumsSchemaFile)) {
        // Prüfe, ob parent_album_id-Spalte bereits existiert
        if (in_array('parent_album_id', $albumsColumns)) {
            echo "<div class='success'>Unteralben-Spalte existiert bereits. ✓</div>";
        } else {
            try {
                $db->execute("ALTER TABLE albums ADD COLUMN parent_album_id INTEGER DEFAULT NULL REFERENCES albums(id)");
                $db->execute("CREATE INDEX IF NOT EXISTS idx_albums_parent ON albums(parent_album_id)");
                echo "<div class='success'>Unteralben-Schema-Updates erfolgreich angewendet. ✓</div>";
            } catch (Exception $e) {
                echo "<div class='warning'>Unteralben-Spalte konnte nicht hinzugefügt werden: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
    
    // Prüfe vorhandene Spalten in der images-Tabelle
    $imagesColumns = [];
    try {
        $columnsImages = $db->fetchAll("PRAGMA table_info(images)", []);
        foreach ($columnsImages as $col) {
            $imagesColumns[] = $col['name'];
        }
    } catch (Exception $e) {
        echo "<div class='error'>Fehler beim Prüfen der images-Tabellenspalten: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    // Füge fehlende cover_image_id Spalte zur albums-Tabelle hinzu
    if (in_array('cover_image_id', $albumsColumns)) {
        echo "<div class='success'>Cover-Image-Spalte existiert bereits in der albums-Tabelle. ✓</div>";
    } else {
        try {
            $db->execute("ALTER TABLE albums ADD COLUMN cover_image_id INTEGER DEFAULT NULL REFERENCES images(id)");
            echo "<div class='success'>Cover-Image-Spalte zur albums-Tabelle hinzugefügt. ✓</div>";
        } catch (Exception $e) {
            echo "<div class='warning'>Cover-Image-Spalte konnte nicht hinzugefügt werden: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    
    // Füge fehlende description Spalte zur images-Tabelle hinzu
    if (in_array('description', $imagesColumns)) {
        echo "<div class='success'>Description-Spalte existiert bereits in der images-Tabelle. ✓</div>";
    } else {
        try {
            $db->execute("ALTER TABLE images ADD COLUMN description TEXT DEFAULT NULL");
            echo "<div class='success'>Description-Spalte zur images-Tabelle hinzugefügt. ✓</div>";
        } catch (Exception $e) {
            echo "<div class='warning'>Description-Spalte konnte nicht hinzugefügt werden: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    
    // Erstelle Demo-Admin nur bei Neuinstallation
    if (!$updateMode) {
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