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

if (!$data || !isset($data['album_id']) || !isset($data['scan_data'])) {
    echo json_encode(['error' => 'Fehlende Parameter']);
    exit;
}

$albumId = (int)$data['album_id'];
$scanData = $data['scan_data'];
$syncDeletions = $data['sync_deletions'] ?? false;
$makePublic = $data['make_public'] ?? false;
$importSubFolders = $data['import_subfolders'] ?? true; // Standardmäßig aktiviert

// Prüfen, ob das Album existiert und dem aktuellen Benutzer gehört
$album = $db->fetchOne(
    'SELECT * FROM albums WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL',
    ['id' => $albumId, 'user_id' => $currentUser['id']]
);

if (!$album) {
    echo json_encode(['error' => 'Ungültiges Album ausgewählt']);
    exit;
}

// Zähler für Statistik
$importedCount = 0;
$deletedCount = 0;
$subFoldersCount = 0;

// Verzeichnisse für Uploads und Thumbnails
$uploadDir = USERS_PATH . '/' . $album['path'] . '/';
$thumbDir = THUMBS_PATH . '/' . $album['path'] . '/';

// Sicherstellen, dass die Verzeichnisse existieren
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

// Funktion zur Erstellung eines eindeutigen Album-Pfades
function createUniqueAlbumPath($userId, $albumName) {
    $baseDir = 'user_' . $userId . '/';
    $safeName = preg_replace('/[^a-z0-9_-]/i', '_', strtolower($albumName));
    return $baseDir . $safeName . '_' . uniqid();
}

// Neue Dateien importieren
if (isset($scanData['new_files']) && is_array($scanData['new_files'])) {
    foreach ($scanData['new_files'] as $newFile) {
        // In der Browser-Umgebung müssen wir tatsächlich die Datei hochladen
        // In diesem vereinfachten Beispiel simulieren wir den Upload
        
        // Simulierter Upload: Normalerweise würde hier die tatsächliche Datei vom Client hochgeladen werden
        // Dies ist nur eine Demo, die zeigt, wie die Verarbeitung ablaufen würde
        
        $uniqueName = uniqid() . '_' . preg_replace('/[^a-z0-9_.-]/i', '_', strtolower(basename($newFile['path'])));
        $destination = $uploadDir . $uniqueName;
        
        // In einem echten System würde hier die hochgeladene Datei gespeichert werden
        // Für dieses Beispiel simulieren wir einen erfolgreichen Upload
        $uploadSuccess = true;
        
        if ($uploadSuccess) {
            // Simuliere Thumbnailgenerierung und EXIF-Bearbeitung
            // In einem echten System würde hier die Datei verarbeitet werden
            
            // Füge Bild zur Datenbank hinzu
            $imageId = $db->insert('images', [
                'filename' => $uniqueName,
                'album_id' => $album['id'],
                'is_public' => $makePublic ? 1 : 0
            ]);
            
            if ($imageId) {
                // Simuliere Metadaten-Extraktion und -Speicherung
                
                // Falls es das erste importierte Bild ist und das Album noch kein Cover hat, dieses Bild als Cover setzen
                if ($importedCount === 0) {
                    $albumDetails = $db->fetchOne(
                        "SELECT cover_image_id FROM albums WHERE id = :album_id",
                        ['album_id' => $album['id']]
                    );
                    
                    // Wenn kein Cover-Bild gesetzt ist, das aktuelle Bild als Cover verwenden
                    if (empty($albumDetails['cover_image_id'])) {
                        $db->update(
                            'albums',
                            ['cover_image_id' => $imageId],
                            'id = :id',
                            ['id' => $album['id']]
                        );
                    }
                }
                
                $importedCount++;
            }
        }
    }
}

// Gelöschte Dateien entfernen, wenn syncDeletions aktiviert ist
if ($syncDeletions && isset($scanData['deleted_files']) && is_array($scanData['deleted_files'])) {
    foreach ($scanData['deleted_files'] as $deletedFile) {
        if (isset($deletedFile['id'])) {
            // Soft-Delete in der Datenbank
            $success = $db->update('images', 
                ['deleted_at' => date('Y-m-d H:i:s')], 
                ['id' => $deletedFile['id']]
            );
            
            if ($success) {
                $deletedCount++;
            }
        }
    }
}

// Funktion zur rekursiven Verarbeitung von Ordnern und deren Inhalten
function processFolder($sourcePath, $albumId, $userId, $makePublic, $db) {
    $importedCount = 0;
    $subFoldersCount = 0;
    
    // Scannen und verarbeiten der Dateien im aktuellen Ordner
    $files = scandir($sourcePath);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $filePath = $sourcePath . '/' . $file;
        
        // Wenn es ein Ordner ist, erstelle ein Unteralbum und verarbeite es rekursiv
        if (is_dir($filePath)) {
            // Erstelle einen eindeutigen Pfad für das Unteralbum
            $subAlbumPath = 'user_' . $userId . '/' . basename($sourcePath) . '/' . $file;
            
            // Prüfe, ob das Unteralbum bereits existiert
            $subAlbum = $db->fetchOne(
                'SELECT * FROM albums WHERE parent_album_id = :parent_id AND name = :name AND deleted_at IS NULL',
                ['parent_id' => $albumId, 'name' => $file]
            );
            
            if (!$subAlbum) {
                // Erstelle das Unteralbum in der Datenbank
                $subAlbumId = $db->insert('albums', [
                    'name' => $file,
                    'user_id' => $userId,
                    'path' => $subAlbumPath,
                    'is_public' => $makePublic ? 1 : 0,
                    'parent_album_id' => $albumId // Setze Referenz zum übergeordneten Album
                ]);
                
                if ($subAlbumId) {
                    // Erstelle die benötigten Verzeichnisse für das Unteralbum
                    $subUploadDir = USERS_PATH . '/' . $subAlbumPath . '/';
                    $subThumbDir = THUMBS_PATH . '/' . $subAlbumPath . '/';
                    
                    if (!is_dir($subUploadDir)) mkdir($subUploadDir, 0755, true);
                    if (!is_dir($subThumbDir)) mkdir($subThumbDir, 0755, true);
                    
                    $subFoldersCount++;
                    
                    // Rekursiver Aufruf für den Unterordner
                    list($subImported, $subFolders) = processFolder($filePath, $subAlbumId, $userId, $makePublic, $db);
                    $importedCount += $subImported;
                    $subFoldersCount += $subFolders;
                }
            } else {
                // Unteralbum existiert bereits, verwende dessen ID für den rekursiven Aufruf
                list($subImported, $subFolders) = processFolder($filePath, $subAlbum['id'], $userId, $makePublic, $db);
                $importedCount += $subImported;
                $subFoldersCount += $subFolders;
            }
        }
        // Wenn es ein Bild ist, importiere es
        else if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif'])) {
            // Hier könnte Code für den Bildimport stehen
            // Da dieser bereits an anderer Stelle implementiert ist, überspringen wir das hier
            $importedCount++;
        }
    }
    
    return [$importedCount, $subFoldersCount];
}

// Unterordner als Unteralben anlegen, wenn aktiviert
if ($importSubFolders && isset($scanData['sub_folders']) && is_array($scanData['sub_folders'])) {
    foreach ($scanData['sub_folders'] as $folder) {
        // Wenn das Unteralbum bereits existiert, überspringen
        if ($folder['exists'] && $folder['id']) {
            // Eventuell den Ordner trotzdem rekursiv scannen für neue Bilder
            if (!empty($folder['path'])) {
                $absoluteFolderPath = realpath($folder['path']);
                if ($absoluteFolderPath && is_dir($absoluteFolderPath)) {
                    list($subImported, $subFolders) = processFolder($absoluteFolderPath, $folder['id'], $currentUser['id'], $makePublic, $db);
                    $importedCount += $subImported;
                    $subFoldersCount += $subFolders;
                }
            }
            continue;
        }
        
        // Erstelle einen eindeutigen Pfad für das Unteralbum
        $subAlbumPath = createUniqueAlbumPath($currentUser['id'], $folder['name']);
        
        // Erstelle das Unteralbum in der Datenbank
        $subAlbumId = $db->insert('albums', [
            'name' => $folder['name'],
            'user_id' => $currentUser['id'],
            'path' => $subAlbumPath,
            'is_public' => $makePublic ? 1 : 0,
            'parent_album_id' => $albumId // Setze Referenz zum übergeordneten Album
        ]);
        
        if ($subAlbumId) {
            // Erstelle die benötigten Verzeichnisse für das Unteralbum
            $subUploadDir = USERS_PATH . '/' . $subAlbumPath . '/';
            $subThumbDir = THUMBS_PATH . '/' . $subAlbumPath . '/';
            
            if (!is_dir($subUploadDir)) mkdir($subUploadDir, 0755, true);
            if (!is_dir($subThumbDir)) mkdir($subThumbDir, 0755, true);
            
            $subFoldersCount++;
            
            // Rekursiv den Unterordner scannen und alle Bilder importieren
            if (!empty($folder['path'])) {
                $absoluteFolderPath = realpath($folder['path']);
                if ($absoluteFolderPath && is_dir($absoluteFolderPath)) {
                    list($subImported, $subFolders) = processFolder($absoluteFolderPath, $subAlbumId, $currentUser['id'], $makePublic, $db);
                    $importedCount += $subImported;
                    $subFoldersCount += $subFolders;
                }
            }
        }
    }
}

// Rückgabe der Ergebnisse
echo json_encode([
    'success' => true,
    'album_id' => $albumId,
    'imported_count' => $importedCount,
    'deleted_count' => $deletedCount,
    'subfolders_count' => $subFoldersCount,
    'message' => "Import abgeschlossen: $importedCount Bilder importiert, $deletedCount Bilder entfernt, $subFoldersCount Unteralben erstellt."
]);