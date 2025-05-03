<?php
// Konfiguration und Bibliotheken laden
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/lib/database.php';

// Datenbank initialisieren
$db = Database::getInstance();

// Admin-Benutzer erstellen
$username = 'admin';
$email = 'admin@example.com';
$password = 'admin123';

// Passwort hashen mit PHP's password_hash Funktion
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

echo "Erstelle Admin-Benutzer: " . $username . "\n";
echo "Mit Passwort: " . $password . "\n";
echo "Hash: " . $hashedPassword . "\n";

// Bestehende Benutzer löschen
$db->execute("DELETE FROM users");

// Neuen Admin-Benutzer einfügen
$userId = $db->insert('users', [
    'username' => $username,
    'password' => $hashedPassword,
    'email' => $email,
    'created_at' => date('Y-m-d H:i:s')
]);

if ($userId) {
    echo "Admin-Benutzer erfolgreich erstellt mit ID: " . $userId . "\n";
    
    // Benutzerverzeichnis erstellen
    $userDir = USERS_PATH . '/user_' . $userId;
    if (!file_exists($userDir)) {
        mkdir($userDir, 0755, true);
        echo "Benutzerverzeichnis erstellt: " . $userDir . "\n";
    }
    
    // Favoriten-Verzeichnis erstellen
    $favoritesDir = $userDir . '/favorites';
    if (!file_exists($favoritesDir)) {
        mkdir($favoritesDir, 0755, true);
        echo "Favoriten-Verzeichnis erstellt: " . $favoritesDir . "\n";
    }
} else {
    echo "Fehler beim Erstellen des Admin-Benutzers.\n";
}