<?php
/**
 * Fotogalerie - API-Endpoint für Favoriten
 *
 * Ermöglicht das Hinzufügen/Entfernen von Bildern zu/aus Favoriten
 */

// Konfiguration und Bibliotheken laden
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../lib/database.php';
require_once __DIR__ . '/../../app/auth/auth.php';

// Header für JSON-Response
header('Content-Type: application/json');

// Authentifizierung initialisieren
$auth = new Auth();

// Prüfen, ob Benutzer angemeldet ist
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

// POST JSON-Daten abrufen
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

// Überprüfen, ob Daten im JSON-Format (POST) oder als GET-Parameter vorliegen
if (isset($input['image_id']) && isset($input['action'])) {
    // POST JSON-Format
    $imageId = (int)$input['image_id'];
    $action = $input['action'];
} elseif (isset($_GET['id']) && isset($_GET['action'])) {
    // GET-Parameter
    $imageId = (int)$_GET['id'];
    $action = $_GET['action'];
} else {
    echo json_encode(['success' => false, 'error' => 'Fehlende Parameter']);
    exit;
}

// Benutzer-ID
$userId = $auth->getCurrentUserId();

// Datenbank initialisieren
$db = Database::getInstance();

// Prüfen, ob Bild existiert und Zugriff erlaubt ist
$image = $db->fetchOne(
    "SELECT i.*, a.user_id, a.is_public, a.path 
     FROM images i 
     JOIN albums a ON i.album_id = a.id 
     WHERE i.id = :image_id AND i.deleted_at IS NULL",
    ['image_id' => $imageId]
);

if (!$image) {
    echo json_encode(['success' => false, 'error' => 'Bild nicht gefunden']);
    exit;
}

// Zugriffsberechtigung prüfen (eigenes Bild oder öffentliches Bild)
if ($image['user_id'] != $userId && !$image['is_public']) {
    echo json_encode(['success' => false, 'error' => 'Kein Zugriff auf dieses Bild']);
    exit;
}

// Prüfen, ob bereits in Favoriten
$existingFavorite = $db->fetchOne(
    "SELECT id FROM favorites WHERE user_id = :user_id AND image_id = :image_id",
    [
        'user_id' => $userId,
        'image_id' => $imageId
    ]
);

// Aktion ausführen
if ($action === 'add') {
    // Hinzufügen, falls noch nicht in Favoriten
    if (!$existingFavorite) {
        $favoriteId = $db->insert('favorites', [
            'user_id' => $userId,
            'image_id' => $imageId
        ]);
        
        // Symlink im Benutzerverzeichnis erstellen (optional)
        $userFavoritesDir = USERS_PATH . '/user_' . $userId . '/favorites';
        if (!file_exists($userFavoritesDir)) {
            mkdir($userFavoritesDir, 0755, true);
        }
        
        // Pfade korrigieren - Prüfen, ob image[path] bereits den vollständigen Pfad enthält
        if (strpos($image['path'], 'user_' . $image['user_id']) !== false) {
            // Path enthält bereits den Benutzerpfad
            $originalPath = USERS_PATH . '/' . $image['path'] . '/' . $image['filename'];
        } else {
            // Path benötigt den vollständigen Pfad
            $originalPath = USERS_PATH . '/user_' . $image['user_id'] . '/' . $image['path'] . '/' . $image['filename'];
        }
        
        $symlinkPath = $userFavoritesDir . '/' . $image['id'] . '_' . $image['filename'];
        
        // Symlink erstellen, wenn Originalbilddatei existiert
        if (file_exists($originalPath) && !file_exists($symlinkPath)) {
            symlink($originalPath, $symlinkPath);
        }
        
        echo json_encode(['success' => true, 'action' => 'added']);
    } else {
        echo json_encode(['success' => true, 'action' => 'already_exists']);
    }
} elseif ($action === 'remove') {
    // Entfernen, falls in Favoriten
    if ($existingFavorite) {
        $db->delete('favorites', 'user_id = :user_id AND image_id = :image_id', [
            'user_id' => $userId,
            'image_id' => $imageId
        ]);
        
        // Symlink entfernen (optional)
        $symlinkPath = $userFavoritesDir . '/' . $image['id'] . '_' . $image['filename'];
        if (file_exists($symlinkPath)) {
            unlink($symlinkPath);
        }
        
        echo json_encode(['success' => true, 'action' => 'removed']);
    } else {
        echo json_encode(['success' => true, 'action' => 'not_exists']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Ungültige Aktion']);
}