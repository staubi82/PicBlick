<?php
/**
 * Fotogalerie - Bild-Rotations-API
 * 
 * Speichert die Rotation eines Bildes permanent
 */

// Konfiguration und Bibliotheken laden
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../lib/database.php';
require_once __DIR__ . '/../../app/auth/auth.php';
require_once __DIR__ . '/../../lib/imaging.php';

// Ergebnisarrays definieren
$response = [
    'success' => false,
    'message' => 'Unbekannter Fehler'
];

// Nur POST-Anfragen erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Nur POST-Anfragen erlaubt';
    echo json_encode($response);
    exit;
}

// JSON-Daten aus dem Request-Body dekodieren
$data = json_decode(file_get_contents('php://input'), true);

// Prüfen, ob alle erforderlichen Daten vorhanden sind
if (!isset($data['image_id']) || !isset($data['rotation'])) {
    $response['message'] = 'Fehlende Parameter: image_id und rotation sind erforderlich';
    echo json_encode($response);
    exit;
}

// Daten extrahieren
$imageId = (int)$data['image_id'];
$rotation = (int)$data['rotation'];

// Auf gültige Rotationswerte prüfen (0, 90, 180, 270)
$rotation = $rotation % 360;
if ($rotation < 0) {
    $rotation += 360;
}

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

// Bild abrufen
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

// Dateipfade bestimmen
$imagePath = USERS_PATH . '/' . $image['path'] . '/' . $image['filename'];
$thumbPath = THUMBS_PATH . '/' . $image['path'] . '/' . $image['filename'];

// Prüfen, ob die Datei existiert
if (!file_exists($imagePath)) {
    $response['message'] = 'Bilddatei nicht gefunden';
    echo json_encode($response);
    exit;
}

// Rotation in der Datenbank speichern
$result = $db->update(
    'images',
    ['rotation' => $rotation],
    'id = :id',
    ['id' => $imageId]
);

if (!$result) {
    $response['message'] = 'Fehler beim Speichern der Rotation in der Datenbank';
    echo json_encode($response);
    exit;
}

// Das physische Bild rotieren
$success = Imaging::rotateImage($imagePath, $rotation);
if (!$success) {
    $response['message'] = 'Fehler beim Rotieren des Bildes';
    echo json_encode($response);
    exit;
}

// Thumbnail neu generieren
if (file_exists($thumbPath)) {
    unlink($thumbPath); // Altes Thumbnail löschen
}
Imaging::createThumbnail($imagePath, $thumbPath);

// Erfolgreiche Antwort
$response['success'] = true;
$response['message'] = 'Bild erfolgreich rotiert';
$response['rotation'] = $rotation;

echo json_encode($response);