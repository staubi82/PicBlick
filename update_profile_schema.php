<?php
/**
 * Schema-Aktualisierung für Profilbilder
 *
 * Führt die SQL-Aktualisierung aus und erstellt den Profilbilder-Ordner
 */

// Konfiguration und Bibliotheken laden
require_once 'app/config.php';
require_once 'lib/database.php';

// Datenbank initialisieren
$db = Database::getInstance();

try {
    // Schema aktualisieren
    $db->importSQL('lib/update_schema.sql');
    
    echo "Datenbank-Schema erfolgreich aktualisiert.\n";
    
    // Ordner für Profilbilder erstellen
    $profileImagesDir = STORAGE_PATH . '/profile_images';
    if (!is_dir($profileImagesDir)) {
        mkdir($profileImagesDir, 0755, true);
        echo "Profilbilder-Verzeichnis erstellt: $profileImagesDir\n";
    } else {
        echo "Profilbilder-Verzeichnis existiert bereits.\n";
    }
    
    // Ordner für Standard-Bilder erstellen
    $defaultImgDir = __DIR__ . '/public/img';
    if (!is_dir($defaultImgDir)) {
        mkdir($defaultImgDir, 0755, true);
        echo "Standard-Bilder-Verzeichnis erstellt: $defaultImgDir\n";
    } else {
        echo "Standard-Bilder-Verzeichnis existiert bereits.\n";
    }
    
    echo "Update abgeschlossen.\n";
} catch (Exception $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
}