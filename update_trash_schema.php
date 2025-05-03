<?php
/**
 * Update-Script für Papierkorb-Funktionalität
 * 
 * Führt das SQL-Schema-Update aus und erstellt die notwendigen Verzeichnisse
 */

require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/lib/database.php';

echo "Aktualisiere Datenbankschema für Papierkorb-Funktionalität...\n";

// Datenbank-Verbindung herstellen
$db = Database::getInstance();

try {
    // SQL-Anweisungen importieren
    $db->importSQL(__DIR__ . '/lib/update_trash_schema.sql');
    echo "Datenbankschema wurde erfolgreich aktualisiert.\n";
} catch (Exception $e) {
    echo "Fehler beim Aktualisieren des Datenbankschemas: " . $e->getMessage() . "\n";
    exit(1);
}

// Papierkorb-Verzeichnisse erstellen
$trashDir = __DIR__ . '/storage/trash';
$trashThumbsDir = $trashDir . '/thumbs';
$trashUsersDir = $trashDir . '/users';

if (!file_exists($trashDir)) {
    if (mkdir($trashDir, 0755, true)) {
        echo "Papierkorb-Verzeichnis wurde erstellt: $trashDir\n";
    } else {
        echo "Fehler beim Erstellen des Papierkorb-Verzeichnisses: $trashDir\n";
    }
}

if (!file_exists($trashThumbsDir)) {
    if (mkdir($trashThumbsDir, 0755, true)) {
        echo "Papierkorb-Thumbnails-Verzeichnis wurde erstellt: $trashThumbsDir\n";
    } else {
        echo "Fehler beim Erstellen des Papierkorb-Thumbnails-Verzeichnisses: $trashThumbsDir\n";
    }
}

if (!file_exists($trashUsersDir)) {
    if (mkdir($trashUsersDir, 0755, true)) {
        echo "Papierkorb-Benutzer-Verzeichnis wurde erstellt: $trashUsersDir\n";
    } else {
        echo "Fehler beim Erstellen des Papierkorb-Benutzer-Verzeichnisses: $trashUsersDir\n";
    }
}

echo "Die Aktualisierung wurde abgeschlossen.\n";