<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../lib/imaging.php';

// Dieses Skript überwacht den storage/users-Ordner auf neue Unterordner
// und legt automatisch Alben an und importiert Bilder.

// Konfiguration
define('USER_STORAGE_PATH', STORAGE_PATH . '/users');
define('SUPPORTED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Datenbankverbindung
$db = Database::getInstance();

// Funktion: Prüft, ob ein Ordner bereits als Album existiert
function albumExists($userId, $folderName, $db) {
    $album = $db->fetchOne('SELECT * FROM albums WHERE user_id = :user_id AND path = :path AND deleted_at IS NULL', [
        'user_id' => $userId,
        'path' => $folderName
    ]);
    return $album !== false;
}

// Funktion: Legt ein neues Album an
function createAlbum($userId, $folderName, $db) {
    $db->insert('albums', [
        'user_id' => $userId,
        'name' => $folderName,
        'path' => $folderName,
        'is_public' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    return $db->lastInsertId();
}

// Funktion: Importiert Bilder aus einem Ordner in ein Album
function importImagesFromFolder($albumId, $folderPath, $db) {
    $files = scandir($folderPath);
    foreach ($files as $file) {
        if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), SUPPORTED_IMAGE_TYPES)) {
            $sourcePath = $folderPath . '/' . $file;
            $uniqueName = uniqid() . '_' . preg_replace('/[^a-z0-9_.-]/i', '_', strtolower($file));
            $destinationPath = USERS_PATH . '/' . $uniqueName;
            if (!copy($sourcePath, $destinationPath)) {
                continue;
            }
            Imaging::autoRotateImage($destinationPath);
            Imaging::createThumbnail($destinationPath, THUMBS_PATH . '/' . $uniqueName);
            $imageId = $db->insert('images', [
                'filename' => $uniqueName,
                'album_id' => $albumId,
                'is_public' => 0,
                'upload_date' => date('Y-m-d H:i:s')
            ]);
        }
    }
}

// Hauptlogik: Überwacht alle Benutzerordner
$users = $db->fetchAll('SELECT id FROM users WHERE deleted_at IS NULL');
foreach ($users as $user) {
    $userId = $user['id'];
    $userFolder = USER_STORAGE_PATH . "/user_$userId";
    if (!is_dir($userFolder)) {
        continue;
    }
    $folders = scandir($userFolder);
    foreach ($folders as $folder) {
        if ($folder === '.' || $folder === '..') {
            continue;
        }
        $folderPath = $userFolder . '/' . $folder;
        if (is_dir($folderPath)) {
            if (!albumExists($userId, "user_$userId/$folder", $db)) {
                $albumId = createAlbum($userId, "user_$userId/$folder", $db);
                importImagesFromFolder($albumId, $folderPath, $db);
                echo "Album '$folder' für Benutzer $userId erstellt und Bilder importiert.\n";
            }
        }
    }
}