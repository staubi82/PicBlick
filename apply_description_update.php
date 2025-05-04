<?php
// Konfiguration und Bibliotheken laden
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/lib/database.php';

// Datenbankverbindung herstellen
$db = Database::getInstance();

echo "Beginne mit der Datenbank-Aktualisierung...\n";

try {
    // Füge die description-Spalte zur albums-Tabelle hinzu
    $db->execute("ALTER TABLE albums ADD COLUMN description TEXT DEFAULT NULL");
    echo "✅ description-Spalte erfolgreich zur albums-Tabelle hinzugefügt.\n";
} catch (Exception $e) {
    echo "❌ Fehler beim Hinzufügen der description-Spalte: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Datenbank-Aktualisierung erfolgreich abgeschlossen.\n";
?>