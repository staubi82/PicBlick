<?php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../lib/database.php';
require_once __DIR__ . '/../../app/auth/auth.php';
require_once __DIR__ . '/../../lib/imaging.php';

header('Content-Type: application/json');

// Authentifizierung prüfen
$auth = new Auth();
$auth->checkSession();
if (!$auth->isLoggedIn()) {
    echo json_encode(['error' => 'Nicht eingeloggt']);
    exit;
}

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// POST-Daten lesen
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['album_id']) || !isset($data['folder_path'])) {
    echo json_encode(['error' => 'Fehlende Parameter']);
    exit;
}

$albumId = (int)$data['album_id'];
$folderPath = $data['folder_path'];
$syncDeletions = $data['sync_deletions'] ?? false;
$makePublic = $data['make_public'] ?? false;
$files = $data['files'] ?? [];

// Prüfen, ob das Album existiert und dem aktuellen Benutzer gehört
$album = $db->fetchOne(
    'SELECT * FROM albums WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL',
    ['id' => $albumId, 'user_id' => $currentUser['id']]
);

if (!$album) {
    echo json_encode(['error' => 'Ungültiges Album ausgewählt']);
    exit;
}

// Alle Bilder aus dem Album abrufen
$existingImages = $db->fetchAll(
    'SELECT i.id, i.filename, i.is_public, i.created_at 
     FROM images i 
     WHERE i.album_id = :album_id AND i.deleted_at IS NULL',
    ['album_id' => $albumId]
);

// Filtern der Dateien, um nur unterstützte Bildformate zu haben und Unterordner zu identifizieren
$imageFiles = [];
$subFolders = [];

foreach ($files as $file) {
    $type = $file['type'] ?? '';
    if ($type === 'directory') {
        // Es ist ein Unterordner
        $subFolders[] = $file;
    } else if (in_array($type, ['image/jpeg', 'image/png', 'image/gif'])) {
        // Es ist ein Bild
        $imageFiles[] = $file;
    } else if (in_array($type, ['video/mp4', 'video/webm', 'video/ogg'])) {
        // Es ist ein Video
        $imageFiles[] = $file; // Wir verwenden weiterhin imageFiles, da die DB-Tabelle erweitert wurde
    }
}

// Vorbereiten der Rückgabewerte
$newFiles = [];
$existingFiles = [];
$deletedFiles = [];

// Dateisystem-Pfade für das Album
$uploadDir = USERS_PATH . '/' . $album['path'] . '/';
$thumbDir = THUMBS_PATH . '/' . $album['path'] . '/';

// Erkennen von neuen und bereits vorhandenen Dateien
$existingFilenames = array_column($existingImages, 'filename');

foreach ($imageFiles as $file) {
    $filename = basename($file['path']);
    $uniqueName = uniqid() . '_' . preg_replace('/[^a-z0-9_.-]/i', '_', strtolower($filename));
    
    // Prüfen, ob das Bild bereits im Album existiert (einfache Namensprüfung)
    $existsInAlbum = false;
    $existingImageId = null;
    
    foreach ($existingImages as $existingImage) {
        // Hier verwenden wir eine einfache Namensprüfung - im Produktionseinsatz sollte man
        // eventuell einen robusteren Abgleich (z.B. via Hash) implementieren
        if (strpos($existingImage['filename'], basename($file['path'])) !== false) {
            $existsInAlbum = true;
            $existingImageId = $existingImage['id'];
            break;
        }
    }
    
    if ($existsInAlbum) {
        // Datei existiert bereits im Album
        $existingFiles[] = [
            'id' => $existingImageId,
            'name' => $filename,
            'path' => $file['path'],
            'thumb_url' => "storage/thumbs/{$album['path']}/{$existingImage['filename']}"
        ];
    } else {
        // Es ist eine neue Datei
        // Für neue Dateien würden wir normalerweise ein Vorschaubild generieren
        // Da wir aber im Browser sind und keinen direkten Zugriff auf die Datei haben,
        // können wir nur die Informationen zurückgeben
        $newFiles[] = [
            'name' => $filename,
            'path' => $file['path'],
            'size' => $file['size'] ?? 0,
            'type' => $file['type'] ?? '',
            'preview' => "" // In einem echten System würde hier ein Base64-Preview erstellt werden
        ];
    }
}

// Neue Dateien in die Datenbank einfügen
foreach ($newFiles as $file) {
    $filename = $file['name'];
    $mediaType = strpos($file['type'], 'video') === 0 ? 'video' : 'image';
    
    // Einfügen in die images-Tabelle
    $db->execute(
        "INSERT INTO images (filename, album_id, media_type, is_public, upload_date) VALUES (:filename, :album_id, :media_type, :is_public, datetime('now'))",
        [
            ':filename' => $filename,
            ':album_id' => $albumId,
            ':media_type' => $mediaType,
            ':is_public' => $makePublic ? 1 : 0
        ]
    );
}

// Wenn Sync-Deletions aktiviert ist, identifiziere Dateien, die im Album sind aber nicht im Ordner
if ($syncDeletions) {
    $scannedFilenames = array_map(function($file) {
        return basename($file['path']);
    }, $imageFiles);
    
    foreach ($existingImages as $existingImage) {
        $found = false;
        foreach ($scannedFilenames as $scannedFilename) {
            if (strpos($existingImage['filename'], $scannedFilename) !== false) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $deletedFiles[] = [
                'id' => $existingImage['id'],
                'name' => $existingImage['filename'],
                'thumb_url' => "storage/thumbs/{$album['path']}/{$existingImage['filename']}"
            ];
        }
    }
}

// Prüfen, ob Unterordner im System bereits als Unteralben existieren
$subFolderInfo = [];
foreach ($subFolders as $folder) {
    $folderName = basename($folder['path']);
    $folderPath = $folder['path'];
    
    // Suche nach einem existierenden Unteralbum mit diesem Namen
    $subAlbum = $db->fetchOne(
        'SELECT * FROM albums WHERE parent_album_id = :parent_id AND name = :name AND deleted_at IS NULL',
        ['parent_id' => $albumId, 'name' => $folderName]
    );
    
    $subFolderInfo[] = [
        'name' => $folderName,
        'path' => $folderPath,
        'exists' => !empty($subAlbum),
        'id' => $subAlbum ? $subAlbum['id'] : null,
        'album_path' => $subAlbum ? $subAlbum['path'] : null
    ];
}

// Rückgabe der Ergebnisse
echo json_encode([
    'album' => [
        'id' => $album['id'],
        'name' => $album['name'],
        'path' => $album['path']
    ],
    'folder_path' => $folderPath,
    'new_files' => $newFiles,
    'existing_files' => $existingFiles,
    'deleted_files' => $deletedFiles,
    'sub_folders' => $subFolderInfo,
    'total_new' => count($newFiles),
    'total_existing' => count($existingFiles),
    'total_deleted' => count($deletedFiles),
    'total_sub_folders' => count($subFolderInfo)
]);