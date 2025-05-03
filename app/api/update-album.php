<?php
/**
 * Fotogalerie - Album-Update-API
 * 
 * Aktualisiert Album-Informationen wie Name, Sichtbarkeit und Titelbild
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
if (!isset($data['album_id'])) {
    $response['message'] = 'Fehlende Parameter: album_id ist erforderlich';
    echo json_encode($response);
    exit;
}

// Daten extrahieren
$albumId = (int)$data['album_id'];
$name = isset($data['name']) ? trim($data['name']) : null;
$description = isset($data['description']) ? trim($data['description']) : null;
$isPublic = isset($data['is_public']) ? (int)$data['is_public'] : null;
$coverImageId = isset($data['cover_image_id']) ? (int)$data['cover_image_id'] : null;
// Prüfen, ob das Album gelöscht werden soll
$delete = isset($data['delete']) && $data['delete'] === true;

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

// Album abrufen
$album = $db->fetchOne(
    "SELECT * FROM albums WHERE id = :id AND deleted_at IS NULL",
    ['id' => $albumId]
);

// Prüfen, ob das Album existiert und der Benutzer Eigentümer ist
if (!$album || $album['user_id'] != $currentUser['id']) {
    $response['message'] = 'Album nicht gefunden oder keine Berechtigung';
    echo json_encode($response);
    exit;
}

// Album löschen, wenn angefordert
if ($delete) {
    // Aktiviere detailliertes Logging
    error_log("Album-Löschung gestartet für Album ID: $albumId, Pfad: {$album['path']}");
    
    // 1. Alle Bilder des Albums abrufen
    $images = $db->fetchAll(
        "SELECT * FROM images WHERE album_id = :album_id AND deleted_at IS NULL",
        ['album_id' => $albumId]
    );
    
    error_log("Anzahl der zu löschenden Bilder: " . count($images));
    
    // Album-Pfad aus der Datenbank holen
    $albumPath = rtrim($album['path'], '/');
    
    // Dateien und Ordner auf dem Server löschen
    $deletionErrors = [];
    $deletedFilesCount = 0;
    $deletedThumbsCount = 0;
    
    // 2. Für jedes Bild die physischen Dateien löschen
    foreach ($images as $image) {
        // Original-Bild löschen - exakter Pfad wie in der Datenbank
        $originalPath = STORAGE_PATH . '/users/' . $image['filename'];
        error_log("Versuche Original zu löschen: $originalPath");
        
        if (file_exists($originalPath)) {
            if (unlink($originalPath)) {
                $deletedFilesCount++;
                error_log("Original gelöscht: $originalPath");
            } else {
                $deletionErrors[] = "Konnte Originalbild nicht löschen: " . $originalPath;
                error_log("Fehler beim Löschen des Originals: $originalPath");
            }
        } else {
            error_log("Original existiert nicht: $originalPath");
            $deletionErrors[] = "Original existiert nicht: " . $originalPath;
        }
        
        // Thumbnail löschen - muss aus dem korrekten Pfad abgeleitet werden
        $filename = basename($image['filename']);
        $thumbnailPath = STORAGE_PATH . '/thumbs/' . $albumPath . '/' . $filename;
        error_log("Versuche Thumbnail zu löschen: $thumbnailPath");
        
        if (file_exists($thumbnailPath)) {
            if (unlink($thumbnailPath)) {
                $deletedThumbsCount++;
                error_log("Thumbnail gelöscht: $thumbnailPath");
            } else {
                $deletionErrors[] = "Konnte Thumbnail nicht löschen: " . $thumbnailPath;
                error_log("Fehler beim Löschen des Thumbnails: $thumbnailPath");
            }
        } else {
            error_log("Thumbnail existiert nicht: $thumbnailPath");
            $deletionErrors[] = "Thumbnail existiert nicht: " . $thumbnailPath;
        }
        
        // Prüfe alternative Thumbnail-Pfade (falls das Benennungsschema anders ist)
        $altThumbnailPath = STORAGE_PATH . '/thumbs/' . $albumPath . '/' . $image['filename'];
        if (file_exists($altThumbnailPath)) {
            error_log("Alternatives Thumbnail gefunden: $altThumbnailPath");
            if (unlink($altThumbnailPath)) {
                $deletedThumbsCount++;
                error_log("Alternatives Thumbnail gelöscht: $altThumbnailPath");
            } else {
                $deletionErrors[] = "Konnte alternatives Thumbnail nicht löschen: " . $altThumbnailPath;
            }
        }
        
        // Soft Delete für das Bild durchführen
        $db->update(
            'images',
            ['deleted_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $image['id']]
        );
    }
    
    // 3. Album-Ordner löschen (wenn leer)
    $thumbsDir = STORAGE_PATH . '/thumbs/' . $albumPath;
    error_log("Prüfe Album-Ordner: $thumbsDir");
    
    if (is_dir($thumbsDir)) {
        // Alle verbleibenden Dateien im Ordner auflisten
        $remainingFiles = scandir($thumbsDir);
        $remainingFiles = array_diff($remainingFiles, ['.', '..']);
        
        if (count($remainingFiles) > 0) {
            error_log("Ordner nicht leer, verbleibende Dateien: " . implode(", ", $remainingFiles));
            // Versuche alle verbleibenden Dateien zu löschen
            foreach ($remainingFiles as $file) {
                $filePath = $thumbsDir . '/' . $file;
                error_log("Versuche verbleibende Datei zu löschen: $filePath");
                if (unlink($filePath)) {
                    error_log("Verbleibende Datei gelöscht: $filePath");
                } else {
                    error_log("Konnte verbleibende Datei nicht löschen: $filePath");
                    $deletionErrors[] = "Konnte verbleibende Datei nicht löschen: " . $filePath;
                }
            }
        }
        
        // Jetzt nochmal versuchen, den Ordner zu löschen
        $isEmpty = count(array_diff(scandir($thumbsDir), ['.', '..'])) === 0;
        if ($isEmpty) {
            if (rmdir($thumbsDir)) {
                error_log("Album-Ordner gelöscht: $thumbsDir");
            } else {
                error_log("Konnte Album-Ordner nicht löschen: $thumbsDir");
                $deletionErrors[] = "Konnte Album-Ordner nicht löschen: " . $thumbsDir;
            }
        } else {
            error_log("Album-Ordner immer noch nicht leer: $thumbsDir");
            $deletionErrors[] = "Album-Ordner ist nicht leer: " . $thumbsDir;
        }
    } else {
        error_log("Album-Ordner existiert nicht: $thumbsDir");
    }
    
    // Prüfe nach Userspace-Ordner
    $userSpaceDir = STORAGE_PATH . '/users/' . $albumPath;
    if (is_dir($userSpaceDir)) {
        error_log("Prüfe Userspace-Ordner: $userSpaceDir");
        // Versuche, auch diesen Ordner zu löschen
        $isEmpty = count(array_diff(scandir($userSpaceDir), ['.', '..'])) === 0;
        if ($isEmpty) {
            if (rmdir($userSpaceDir)) {
                error_log("Userspace-Ordner gelöscht: $userSpaceDir");
            } else {
                error_log("Konnte Userspace-Ordner nicht löschen: $userSpaceDir");
                $deletionErrors[] = "Konnte Userspace-Ordner nicht löschen: " . $userSpaceDir;
            }
        } else {
            error_log("Userspace-Ordner nicht leer: $userSpaceDir");
            $deletionErrors[] = "Userspace-Ordner ist nicht leer: " . $userSpaceDir;
        }
    }
    
    // 4. Soft Delete für das Album durchführen
    $result = $db->update(
        'albums',
        ['deleted_at' => date('Y-m-d H:i:s')],
        'id = :id',
        ['id' => $albumId]
    );
    
    if (!$result) {
        $response['message'] = 'Fehler beim Löschen des Albums';
        echo json_encode($response);
        exit;
    }
    
    // Erfolgreiche Antwort für Löschvorgang, ggf. mit Warnungen
    $response['success'] = true;
    $response['deleted_files'] = $deletedFilesCount;
    $response['deleted_thumbs'] = $deletedThumbsCount;
    
    if (empty($deletionErrors)) {
        $response['message'] = 'Album und alle zugehörigen Dateien erfolgreich gelöscht';
    } else {
        $response['message'] = 'Album aus der Datenbank gelöscht, aber es gab Probleme beim Löschen einiger Dateien';
        $response['errors'] = $deletionErrors;
    }
    
    error_log("Album-Löschung abgeschlossen. Gelöschte Dateien: $deletedFilesCount, Gelöschte Thumbnails: $deletedThumbsCount");
    echo json_encode($response);
    exit;
}

// Update-Daten vorbereiten
$updateData = [];
if ($name !== null && $name !== '') {
    $updateData['name'] = $name;
}
if ($description !== null) {
    $updateData['description'] = $description;
}
if ($isPublic !== null) {
    $updateData['is_public'] = $isPublic;
}
if ($coverImageId !== null) {
    // Prüfen ob das Bild zum Album gehört
    $image = $db->fetchOne(
        "SELECT id FROM images WHERE id = :id AND album_id = :album_id AND deleted_at IS NULL",
        ['id' => $coverImageId, 'album_id' => $albumId]
    );
    
    if (!$image) {
        $response['message'] = 'Ungültiges Titelbild ausgewählt';
        echo json_encode($response);
        exit;
    }
    
    $updateData['cover_image_id'] = $coverImageId;
}

// Wenn keine Änderungen vorhanden sind
if (empty($updateData)) {
    $response['message'] = 'Keine Änderungen angegeben';
    echo json_encode($response);
    exit;
}

// Album in der Datenbank aktualisieren
$result = $db->update(
    'albums',
    $updateData,
    'id = :id',
    ['id' => $albumId]
);

if (!$result) {
    $response['message'] = 'Fehler beim Aktualisieren des Albums';
    echo json_encode($response);
    exit;
}

// Erfolgreiche Antwort
$response['success'] = true;
$response['message'] = 'Album erfolgreich aktualisiert';
$response['data'] = array_merge(['id' => $albumId], $updateData);

echo json_encode($response);