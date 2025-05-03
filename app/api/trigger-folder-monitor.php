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

    // Funktion: Prüft, ob ein Ordner bereits als Album existiert
    function albumExists($userId, $folderName, $db) {
        $album = $db->fetchOne('SELECT * FROM albums WHERE user_id = :user_id AND path = :path AND deleted_at IS NULL', [
            'user_id' => $userId,
            'path' => $folderName
        ]);
        return $album !== false;
    }

    // Funktion: Legt ein neues Album an
    function createAlbum($userId, $folderName, $albumName, $db) {
        // Die insert-Methode gibt bereits die ID zurück
        return $db->insert('albums', [
            'user_id' => $userId,
            'name' => $albumName, // Nur den Album-Namen ohne user_X/ verwenden
            'path' => $folderName, // Vollständiger Pfad für die Dateispeicherung
            'is_public' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    // Funktion: Importiert Bilder aus einem Ordner in ein Album
    function importImagesFromFolder($albumId, $folderPath, $albumPath, $db) {
        $files = scandir($folderPath);
        foreach ($files as $file) {
            if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), SUPPORTED_IMAGE_TYPES)) {
                $sourcePath = $folderPath . '/' . $file;
                
                // Den Originalpfad des Bildes beibehalten, nicht kopieren
                $relativeSourcePath = str_replace(USER_STORAGE_PATH . '/', '', $sourcePath);
                
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
                
                // Erstelle Thumbnail direkt von der Originaldatei
                Imaging::createThumbnail($sourcePath, $thumbPath);
                
                // Drehe das Thumbnail wenn nötig
                if (function_exists('exif_read_data')) {
                    Imaging::autoRotateImage($thumbPath);
                }
                
                // Überprüfe ob das Thumbnail erstellt wurde
                if (!file_exists($thumbPath)) {
                    error_log("Thumbnail konnte nicht erstellt werden: " . $thumbPath);
                }
                
                // Speichere den relativen Pfad zur Originaldatei in der Datenbank
                $imageId = $db->insert('images', [
                    'filename' => $relativeSourcePath,
                    'album_id' => $albumId,
                    'is_public' => 0,
                    'upload_date' => date('Y-m-d H:i:s')
                ]);
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
                    importImagesFromFolder($albumId, $folderPath, $fullPath, $db);
                }
            }
        }
    }
}

monitorUserFolders($db);

echo json_encode(['success' => true, 'message' => 'Ordnerüberwachung ausgeführt']);