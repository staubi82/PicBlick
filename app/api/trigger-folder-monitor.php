<?php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../lib/database.php';
require_once __DIR__ . '/../../lib/imaging.php';
require_once __DIR__ . '/../../app/auth/auth.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->checkSession();
if (!$auth->isLoggedIn()) {
    echo json_encode(['error' => 'Nicht eingeloggt']);
    exit;
}

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// Die Logik aus monitor-user-folders.php hier als Funktion auslagern
function monitorUserFolders($db) {
    define('USER_STORAGE_PATH', STORAGE_PATH . '/users');
    define('SUPPORTED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
    define('SUPPORTED_VIDEO_TYPES', ['mp4', 'mov', 'avi', 'mkv', 'webm']);
    define('SUPPORTED_MEDIA_TYPES', array_merge(SUPPORTED_IMAGE_TYPES, SUPPORTED_VIDEO_TYPES));

    // Funktion: Prüft, ob ein Ordner bereits als Album existiert
    function albumExists($userId, $folderName, $db) {
        $album = $db->fetchOne('SELECT * FROM albums WHERE user_id = :user_id AND path = :path AND deleted_at IS NULL', [
            'user_id' => $userId,
            'path' => $folderName
        ]);
        return $album !== false;
    }

    // Funktion: Legt ein neues Album an
    function createAlbum($userId, $folderName, $albumName, $db, $parentAlbumId = null) {
        // Prüfen, ob das Album bereits existiert
        $existingAlbum = $db->fetchOne('SELECT id FROM albums WHERE user_id = :user_id AND name = :name AND deleted_at IS NULL', [
            'user_id' => $userId,
            'name' => $albumName
        ]);
        if ($existingAlbum) {
            return $existingAlbum['id'];
        }
        
        // Die insert-Methode gibt bereits die ID zurück
        return $db->insert('albums', [
            'user_id' => $userId,
            'name' => $albumName, // Nur den Album-Namen ohne user_X/ verwenden
            'path' => $folderName, // Vollständiger Pfad für die Dateispeicherung
            'is_public' => 0,
            'parent_album_id' => $parentAlbumId, // Hier wird die Verbindung zum übergeordneten Album hergestellt
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    // Funktion zum Ermitteln des korrekten MIME-Typs basierend auf der Dateiendung
    function getMimeTypeForExtension($extension) {
        $mimeTypes = [
            // Bilder
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            
            // Videos
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'mkv' => 'video/x-matroska',
            'webm' => 'video/webm'
        ];
        
        return isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : 'application/octet-stream';
    }

    // Funktion: Importiert Medien aus einem Ordner in ein Album
    // Optional: Rekursiver Import von Unterordnern (mit Unteralben-Erstellung)
    function importImagesFromFolder($albumId, $folderPath, $albumPath, $db, $userId = null, $scanSubFolders = true) {
        $files = scandir($folderPath);
        foreach ($files as $file) {
            // Überspringe . und ..
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $sourcePath = $folderPath . '/' . $file;
            
            // Wenn es ein Ordner ist und rekursiver Import aktiviert ist
            if (is_dir($sourcePath) && $scanSubFolders) {
                // Erstelle ein Unteralbum
                $subAlbumPath = $albumPath . '/' . $file;
                
                // Prüfe, ob dieses Unteralbum bereits existiert
                $subAlbum = $db->fetchOne('SELECT * FROM albums WHERE path = :path AND deleted_at IS NULL', [
                    'path' => $subAlbumPath
                ]);
                
                // Wenn das Unteralbum noch nicht existiert, erstelle es
                if (!$subAlbum) {
                    // Statt direkt einzufügen, createAlbum verwenden, um Duplikate zu vermeiden
                    // Wichtig: Hier übergeben wir die albumId als parentAlbumId
                    $subAlbumId = createAlbum($userId, $subAlbumPath, $file, $db, $albumId);
                    
                    // Rekursiver Aufruf für das Unteralbum
                    importImagesFromFolder($subAlbumId, $sourcePath, $subAlbumPath, $db, $userId, $scanSubFolders);
                } else {
                    // Unteralbum existiert bereits, verwende dessen ID für den rekursiven Aufruf
                    // Stelle sicher, dass das Unteralbum das richtige Elternalbum hat
                    $db->execute('UPDATE albums SET parent_album_id = :parent_id WHERE id = :id', [
                        'parent_id' => $albumId,
                        'id' => $subAlbum['id']
                    ]);
                    
                    importImagesFromFolder($subAlbum['id'], $sourcePath, $subAlbumPath, $db, $userId, $scanSubFolders);
                }
            }
            // Wenn es eine Datei ist, prüfe ob es ein Bild oder Video ist
            else {
                $fileExtension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($fileExtension, SUPPORTED_MEDIA_TYPES)) {
                    // Den Originalpfad des Mediums beibehalten, nicht kopieren
                    $relativeSourcePath = str_replace(USER_STORAGE_PATH . '/', '', $sourcePath);
                    
                    // Bestimme den Medientyp (Bild oder Video)
                    $mediaType = in_array($fileExtension, SUPPORTED_IMAGE_TYPES) ? 'image' : 'video';
                    
                    // MIME-Typ ermitteln und speichern
                    $mimeType = getMimeTypeForExtension($fileExtension);
                    
                    // Stelle sicher, dass die Zielverzeichnisse für Thumbnails existieren
                    if (!is_dir(THUMBS_PATH)) {
                        mkdir(THUMBS_PATH, 0755, true);
                    }
                    
                    // Erstelle Thumbnail mit korrekter Verzeichnisstruktur
                    $thumbDir = THUMBS_PATH . '/' . $albumPath;
                    if (!is_dir($thumbDir)) {
                        mkdir($thumbDir, 0755, true);
                    }
                    
                    // Generiere einen eindeutigen Thumbnail-Namen basierend auf dem Original
                    $thumbFilename = basename($sourcePath);
                    $thumbPath = $thumbDir . '/' . $thumbFilename;
                    
                    // Thumbnail erstellen basierend auf Medientyp
                    if ($mediaType === 'image') {
                        // Bild-Thumbnail erstellen
                        Imaging::createThumbnail($sourcePath, $thumbPath);
                        
                        // Drehe das Thumbnail wenn nötig
                        if (function_exists('exif_read_data')) {
                            Imaging::autoRotateImage($thumbPath);
                        }
                    } else if ($mediaType === 'video') {
                        // Prüfen, ob FFmpeg verfügbar ist
                        $ffmpegAvailable = Imaging::isFFmpegAvailable();
                        
                        if ($ffmpegAvailable) {
                            // FFmpeg verfügbar - erstelle Thumbnail aus Video (Frame bei 10 Sekunden)
                            $success = Imaging::createVideoThumbnail($sourcePath, $thumbPath, THUMB_WIDTH, THUMB_HEIGHT, 10);
                            
                            if (!$success) {
                                error_log("Konnte Video-Thumbnail nicht erstellen für: $thumbFilename");
                                // Fallback: Standard-Video-Thumbnail
                                copy(__DIR__ . "/../../public/img/video-thumbnail.jpg", $thumbPath);
                            }
                        } else {
                            // FFmpeg nicht verfügbar - Standard-Thumbnail verwenden
                            if (file_exists(__DIR__ . "/../../public/img/video-thumbnail.jpg")) {
                                copy(__DIR__ . "/../../public/img/video-thumbnail.jpg", $thumbPath);
                            } else {
                                error_log("Standard-Video-Thumbnail nicht gefunden und FFmpeg nicht installiert.");
                                // Erstelle einfaches Text-Thumbnail
                                $img = imagecreatetruecolor(320, 240);
                                $textcolor = imagecolorallocate($img, 255, 255, 255);
                                $bg = imagecolorallocate($img, 0, 0, 0);
                                imagefilledrectangle($img, 0, 0, 320, 240, $bg);
                                imagestring($img, 5, 110, 100, 'VIDEO', $textcolor);
                                imagestring($img, 3, 60, 120, 'FFmpeg not installed', $textcolor);
                                imagejpeg($img, $thumbPath, 90);
                                imagedestroy($img);
                            }
                        }
                    }
                    
                    // Überprüfe ob das Thumbnail erstellt wurde
                    if (!file_exists($thumbPath)) {
                        error_log("Thumbnail konnte nicht erstellt werden: " . $thumbPath);
                    }
                    // Für Videos könnten wir später ein Standbild als Thumbnail erstellen
                    // Aber für den Moment fügen wir sie einfach ohne Thumbnail hinzu
                    
                    // Speichere den relativen Pfad zur Originaldatei in der Datenbank
                    $imageData = [
                        'filename' => $relativeSourcePath,
                        'album_id' => $albumId,
                        'media_type' => $mediaType,
                        'is_public' => 0,
                        'upload_date' => date('Y-m-d H:i:s')
                    ];
                    
                    // Prüfen, ob die mime_type-Spalte existiert
                    try {
                        static $hasColumn = null;
                        if ($hasColumn === null) {
                            $hasColumn = false;
                            $columns = $db->fetchAll("PRAGMA table_info(images)");
                            foreach ($columns as $column) {
                                if ($column['name'] === 'mime_type') {
                                    $hasColumn = true;
                                    break;
                                }
                            }
                        }
                        
                        // MIME-Type nur hinzufügen, wenn die Spalte existiert
                        if ($hasColumn && isset($mimeType)) {
                            $imageData['mime_type'] = $mimeType;
                        }
                    } catch (Exception $e) {
                        // Bei Fehler einfach ohne mime_type fortfahren
                        error_log("Fehler beim Prüfen der mime_type-Spalte: " . $e->getMessage());
                    }
                    
                    $imageId = $db->insert('images', $imageData);
                    
                    // Debug-Log für Video-Dateien
                    if ($mediaType === 'video') {
                        error_log("Video hinzugefügt: $relativeSourcePath, MIME-Typ: $mimeType");
                    }
                }
            }
        }
    }

    $users = $db->fetchAll('SELECT id FROM users WHERE deleted_at IS NULL');
    foreach ($users as $user) {
        $userId = $user['id'];
        $userFolder = USER_STORAGE_PATH . "/user_$userId";
        if (!is_dir($userFolder)) {
            continue;
        }
        $folders = scandir($userFolder);
        foreach ($folders as $folder) {
            // Überspringe . und .. sowie den Favorites-Ordner
            if ($folder === '.' || $folder === '..' || strtolower($folder) === 'favorites') {
                continue;
            }
            $folderPath = $userFolder . '/' . $folder;
            if (is_dir($folderPath)) {
                $fullPath = "user_$userId/$folder";
                if (!albumExists($userId, $fullPath, $db)) {
                    // Als Album-Namen nur den Ordnernamen ohne user_X/ verwenden
                    $albumId = createAlbum($userId, $fullPath, $folder, $db);
                    importImagesFromFolder($albumId, $folderPath, $fullPath, $db, $userId, true);
                }
            }
        }
    }
}

monitorUserFolders($db);

echo json_encode(['success' => true, 'message' => 'Ordnerüberwachung ausgeführt']);