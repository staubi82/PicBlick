<?php
/**
 * Papierkorb-Reinigungsskript
 * 
 * Dieses Skript sollte über einen Cron-Job täglich ausgeführt werden, um:
 * 1. Abgelaufene Dateien aus dem Papierkorb zu löschen
 * 2. Leere Verzeichnisse zu bereinigen
 * 
 * Beispiel Cron-Job (tägliche Ausführung um 3 Uhr morgens):
 * 0 3 * * * php /pfad/zu/app/clean-trash.php > /dev/null 2>&1
 */

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../lib/database.php';

// Papierkorb-Pfade
define('TRASH_DIR', dirname(__DIR__) . '/storage/trash');
define('TRASH_THUMBS_DIR', TRASH_DIR . '/thumbs');
define('TRASH_USERS_DIR', TRASH_DIR . '/users');

// Protokollierung aktivieren
$logfile = dirname(__DIR__) . '/storage/logs/trash_cleanup.log';
$logDir = dirname($logfile);
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Protokollierungsfunktion
function log_message($message) {
    global $logfile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($logfile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

log_message("Starte Papierkorb-Reinigung...");

// Datenbank-Verbindung herstellen
$db = Database::getInstance();

// 1. Abgelaufene Bilder aus der Datenbank abrufen
$expiredImages = $db->fetchAll(
    "SELECT * FROM images 
     WHERE deleted_at IS NOT NULL 
     AND (trash_original_path IS NOT NULL OR trash_thumbnail_path IS NOT NULL)
     AND trash_expiry < datetime('now')"
);

log_message("Gefundene abgelaufene Bilder: " . count($expiredImages));

// 2. Jedes abgelaufene Bild verarbeiten
foreach ($expiredImages as $image) {
    $imageId = $image['id'];
    log_message("Verarbeite abgelaufenes Bild ID: $imageId");
    
    // Dateipfade im Papierkorb
    $trashOriginalPath = $image['trash_original_path'];
    $trashThumbnailPath = $image['trash_thumbnail_path'];
    
    // Dateien löschen
    if ($trashOriginalPath && file_exists($trashOriginalPath)) {
        if (unlink($trashOriginalPath)) {
            log_message("Originalbild gelöscht: $trashOriginalPath");
        } else {
            log_message("Fehler beim Löschen des Originalbildes: $trashOriginalPath");
        }
    }
    
    if ($trashThumbnailPath && file_exists($trashThumbnailPath)) {
        if (unlink($trashThumbnailPath)) {
            log_message("Thumbnail gelöscht: $trashThumbnailPath");
        } else {
            log_message("Fehler beim Löschen des Thumbnails: $trashThumbnailPath");
        }
    }
    
    // Datensatz in der Datenbank aktualisieren - Trash-Pfade zurücksetzen
    $db->update(
        'images',
        [
            'trash_original_path' => null,
            'trash_thumbnail_path' => null,
            'trash_expiry' => null
        ],
        'id = :id',
        ['id' => $imageId]
    );
    
    log_message("Datensatz in der Datenbank aktualisiert für Bild ID: $imageId");
}

// 3. Leere Verzeichnisse im Papierkorb bereinigen
function cleanEmptyDirs($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            cleanEmptyDirs($path);
        }
    }
    
    // Erneut scannen, um zu prüfen, ob das Verzeichnis nach der Bereinigung leer ist
    $files = array_diff(scandir($dir), ['.', '..']);
    if (empty($files) && $dir !== TRASH_DIR && $dir !== TRASH_THUMBS_DIR && $dir !== TRASH_USERS_DIR) {
        if (rmdir($dir)) {
            log_message("Leeres Verzeichnis gelöscht: $dir");
        } else {
            log_message("Fehler beim Löschen des leeren Verzeichnisses: $dir");
        }
    }
}

log_message("Bereinige leere Verzeichnisse im Papierkorb...");
cleanEmptyDirs(TRASH_THUMBS_DIR);
cleanEmptyDirs(TRASH_USERS_DIR);

log_message("Papierkorb-Reinigung abgeschlossen.");