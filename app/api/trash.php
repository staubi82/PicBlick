<?php
/**
 * Fotogalerie - Papierkorb-API
 * 
 * Verwaltet den Papierkorb für gelöschte Bilder
 * - Verschieben von Bildern in den Papierkorb
 * - Automatisches Löschen nach 30 Tagen
 * - Manuelles sofortiges Löschen
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../lib/database.php';
require_once __DIR__ . '/../../app/auth/auth.php';

// Ergebnisarrays definieren
$response = [
    'success' => false,
    'message' => 'Unbekannter Fehler'
];

// Papierkorb-Pfade
define('TRASH_DIR', dirname(dirname(__DIR__)) . '/storage/trash');
define('TRASH_THUMBS_DIR', TRASH_DIR . '/thumbs');
define('TRASH_USERS_DIR', TRASH_DIR . '/users');

// Verzeichnisse erstellen, falls sie nicht existieren
if (!file_exists(TRASH_DIR)) {
    mkdir(TRASH_DIR, 0755, true);
}
if (!file_exists(TRASH_THUMBS_DIR)) {
    mkdir(TRASH_THUMBS_DIR, 0755, true);
}
if (!file_exists(TRASH_USERS_DIR)) {
    mkdir(TRASH_USERS_DIR, 0755, true);
}

// Nur POST-Anfragen erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Nur POST-Anfragen erlaubt';
    echo json_encode($response);
    exit;
}

// JSON-Daten aus dem Request-Body dekodieren
$data = json_decode(file_get_contents('php://input'), true);

// Prüfen, ob alle erforderlichen Daten vorhanden sind
if (!isset($data['image_id'])) {
    $response['message'] = 'Fehlende Parameter: image_id ist erforderlich';
    echo json_encode($response);
    exit;
}

// Daten extrahieren
$imageId = (int)$data['image_id'];
$forceDelete = isset($data['force_delete']) && $data['force_delete'] === true;

// Authentifizierung initialisieren
$auth = new Auth();
$auth->checkSession();
$currentUser = $auth->getCurrentUser();

if (!$currentUser) {
    $response['message'] = 'Nicht autorisiert';
    echo json_encode($response);
    exit;
}

// Datenbank initialisieren
$db = Database::getInstance();

// Bild mit Album-Informationen abrufen
$image = $db->fetchOne(
    "SELECT i.*, a.path, a.user_id 
     FROM images i 
     JOIN albums a ON i.album_id = a.id 
     WHERE i.id = :id AND i.deleted_at IS NULL",
    ['id' => $imageId]
);

// Prüfen, ob das Bild existiert und der Benutzer Eigentümer ist
if (!$image || $image['user_id'] != $currentUser['id']) {
    $response['message'] = 'Bild nicht gefunden oder keine Berechtigung';
    echo json_encode($response);
    exit;
}

// Pfade der Dateien
$filename = basename($image['filename']);
$albumPath = rtrim($image['path'], '/');
$originalPath = dirname(dirname(__DIR__)) . '/storage/users/' . $image['filename'];
$thumbnailPath = dirname(dirname(__DIR__)) . '/storage/thumbs/' . $albumPath . '/' . $filename;

// Wenn erzwungenes Löschen angefordert wird
if ($forceDelete) {
    // Dateien löschen
    if (file_exists($originalPath)) {
        unlink($originalPath);
    }
    if (file_exists($thumbnailPath)) {
        unlink($thumbnailPath);
    }
    
    // Bild als gelöscht markieren
    $db->update(
        'images',
        ['deleted_at' => date('Y-m-d H:i:s')],
        'id = :id',
        ['id' => $imageId]
    );
    
    $response['success'] = true;
    $response['message'] = 'Bild wurde endgültig gelöscht';
} else {
    // In den Papierkorb verschieben
    $trashOriginalPath = TRASH_USERS_DIR . '/' . $image['filename'];
    $trashThumbnailPath = TRASH_THUMBS_DIR . '/' . $albumPath . '/' . $filename;
    
    // Verzeichnis für Thumbnails erstellen
    $trashThumbnailDir = dirname($trashThumbnailPath);
    if (!file_exists($trashThumbnailDir)) {
        mkdir($trashThumbnailDir, 0755, true);
    }
    
    // Verschieben der Dateien
    $origMoved = false;
    $thumbMoved = false;
    
    if (file_exists($originalPath)) {
        $origMoved = rename($originalPath, $trashOriginalPath);
    }
    
    if (file_exists($thumbnailPath)) {
        $thumbMoved = rename($thumbnailPath, $trashThumbnailPath);
    }
    
    // Bild als gelöscht markieren und Papierkorb-Informationen speichern
    $db->update(
        'images',
        [
            'deleted_at' => date('Y-m-d H:i:s'),
            'trash_original_path' => $origMoved ? $trashOriginalPath : null,
            'trash_thumbnail_path' => $thumbMoved ? $trashThumbnailPath : null,
            'trash_expiry' => date('Y-m-d H:i:s', strtotime('+30 days'))
        ],
        'id = :id',
        ['id' => $imageId]
    );
    
    $response['success'] = true;
    $response['message'] = 'Bild wurde in den Papierkorb verschoben';
}

echo json_encode($response);