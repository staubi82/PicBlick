<?php
/**
 * Fotogalerie - Bild-Update-API
 * 
 * Aktualisiert Bildinformationen wie Beschreibung
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../lib/database.php';
require_once __DIR__ . '/../../app/auth/auth.php';

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
if (!isset($data['image_id'])) {
    $response['message'] = 'Fehlende Parameter: image_id ist erforderlich';
    echo json_encode($response);
    exit;
}

// Daten extrahieren
$imageId = (int)$data['image_id'];
$description = isset($data['description']) ? trim($data['description']) : '';
$isPublic = isset($data['is_public']) ? (int)$data['is_public'] : null;

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
    "SELECT i.*, a.user_id 
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

// Update-Daten vorbereiten
$updateData = [];
if ($description !== '') {
    $updateData['description'] = $description;
}
if ($isPublic !== null) {
    $updateData['is_public'] = $isPublic;
}

// Wenn keine Änderungen vorhanden sind
if (empty($updateData)) {
    $response['message'] = 'Keine Änderungen angegeben';
    echo json_encode($response);
    exit;
}

// Bild in der Datenbank aktualisieren
$result = $db->update(
    'images',
    $updateData,
    'id = :id',
    ['id' => $imageId]
);

if (!$result) {
    $response['message'] = 'Fehler beim Aktualisieren des Bildes';
    echo json_encode($response);
    exit;
}

// Erfolgreiche Antwort
$response['success'] = true;
$response['message'] = 'Bild erfolgreich aktualisiert';
$response['data'] = array_merge(['id' => $imageId], $updateData);

echo json_encode($response);