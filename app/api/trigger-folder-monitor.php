<?php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../lib/database.php';
require_once __DIR__ . '/../../lib/imaging.php';
require_once __DIR__ . '/../../app/auth/auth.php';

// Fehlererkennung aktivieren
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Fehlerbehandlungs-Funktion
function handleError($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno]: $errstr in $errfile on line $errline");
    echo json_encode(['error' => $errstr, 'file' => $errfile, 'line' => $errline]);
    exit;
}

// Exception-Handler
function handleException($exception) {
    error_log("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    echo json_encode(['error' => $exception->getMessage(), 'file' => $exception->getFile(), 'line' => $exception->getLine()]);
    exit;
}

// Fehlerhandler und Exception-Handler registrieren
set_error_handler('handleError');
set_exception_handler('handleException');

header('Content-Type: application/json');

try {
    $auth = new Auth();
    $auth->checkSession();
    if (!$auth->isLoggedIn()) {
        echo json_encode(['error' => 'Nicht eingeloggt']);
        exit;
    }

    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();

    // Die Logik aus monitor-user-folders.php hier als Funktion auslagern
    function monitorUserFolders() {
        global $db, $currentUser;
        
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
            return !!$album;
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

        // Funktion: Importiert Bilder aus einem Ordner in ein Album
        function importImagesFromFolder($albumId, $folderPath, $relativePath, $db, $userId, $scanRecursively = false) {
            if (!file_exists($folderPath) || !is_dir($folderPath)) {
                return;
            }

            // Ordnerinhalte durchgehen
            $files = scandir($folderPath);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $fullPath = $folderPath . '/' . $file;
                
                // Unterordner verarbeiten
                if (is_dir($fullPath) && $scanRecursively) {
                    // Neuen relativen Pfad für Unterordner erstellen
                    $newRelativePath = $relativePath . '/' . $file;
                    
                    // Für Unterordner ein Subalbum erstellen
                    $subAlbumId = createAlbum($userId, $newRelativePath, $file, $db, $albumId);
                    
                    // Rekursiver Aufruf für Unterordner
                    importImagesFromFolder($subAlbumId, $fullPath, $newRelativePath, $db, $userId, true);
                    continue;
                }
                
                // Datei verarbeiten wenn es kein Ordner ist
                if (!is_dir($fullPath)) {
                    // Prüfen ob es ein unterstütztes Medienformat ist
                    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    
                    $mediaType = null;
                    if (in_array($extension, SUPPORTED_IMAGE_TYPES)) {
                        $mediaType = 'image';
                    } else if (in_array($extension, SUPPORTED_VIDEO_TYPES)) {
                        $mediaType = 'video';
                    } else {
                        // Keine unterstützte Datei, überspringen
                        continue;
                    }
                    
                    // Prüfen, ob das Bild bereits importiert wurde
                    $existingImage = $db->fetchOne(
                        'SELECT * FROM images WHERE album_id = :album_id AND filename = :filename AND deleted_at IS NULL',
                        ['album_id' => $albumId, 'filename' => $file]
                    );
                    
                    if ($existingImage) {
                        continue; // Bild bereits importiert, überspringen
                    }
                    
                    // Quellpfad des Bildes (voller Dateisystempfad)
                    $sourcePath = $fullPath;
                    
                    // Relativer Pfad in der Datenbank (z.B. user_1/vacation/beach.jpg)
                    $dbPath = $relativePath . '/' . $file;
                    
                    // Thumbnail-Pfad erstellen
                    $thumbDir = STORAGE_PATH . '/thumbs/' . dirname($relativePath);
                    if (!file_exists($thumbDir)) {
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
                            }
                        }
                    }
                    
                    // Metadaten extrahieren, falls es ein Bild ist
                    $metadata = [];
                    if ($mediaType === 'image' && function_exists('exif_read_data')) {
                        $exifData = Imaging::getImageMetadata($sourcePath);
                        if ($exifData) {
                            $metadata = json_encode($exifData);
                        }
                    }
                    
                    // Dateigröße
                    $filesize = filesize($sourcePath);
                    
                    // MIME-Typ ermitteln
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $sourcePath);
                    finfo_close($finfo);
                    
                    // Bild in die Datenbank einfügen
                    $imageId = $db->insert('images', [
                        'album_id' => $albumId,
                        'user_id' => $userId,
                        'filename' => $file,
                        'path' => $dbPath,
                        'type' => $mediaType,
                        'mime_type' => $mime_type,
                        'size' => $filesize,
                        'metadata' => $metadata ? $metadata : null,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
        }
        
        // Hauptlogik: Nutzerordner und deren Unterordner überwachen
        $userId = $currentUser['id'];
        $userFolder = USER_STORAGE_PATH . '/user_' . $userId;
        
        // Stelle sicher, dass der Benutzerordner existiert
        if (!file_exists($userFolder)) {
            mkdir($userFolder, 0755, true);
        }
        
        // Durchlaufe alle Ordner und erstelle automatisch Alben
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

    try {
        monitorUserFolders();
        echo json_encode(['success' => true, 'message' => 'Ordnerüberwachung ausgeführt']);
    } catch (Exception $e) {
        error_log("Fehler bei der Ordnerüberwachung: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
} catch (Exception $e) {
    error_log("Hauptfehler: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}