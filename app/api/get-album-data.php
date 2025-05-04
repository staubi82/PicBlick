<?php
/**
 * Album-Daten als JSON zurückgeben
 * Dieser Endpunkt wird für die AJAX-basierte Album-Navigation verwendet
 */
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../lib/database.php';
require_once __DIR__ . '/../../app/auth/auth.php';

// Authentifizierung initialisieren
$auth = new Auth();
$auth->checkSession();
$currentUser = $auth->getCurrentUser();
$userId = $currentUser ? $currentUser['id'] : null;

// Datenbank initialisieren
$db = Database::getInstance();

// Album-ID prüfen
$albumId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($albumId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Ungültige Album-ID']);
    exit;
}

// Album abrufen
$album = $db->fetchOne(
    "SELECT a.*, i.filename as cover_image, u.username as owner_name 
     FROM albums a
     LEFT JOIN images i ON a.cover_image_id = i.id
     LEFT JOIN users u ON a.user_id = u.id
     WHERE a.id = :id AND a.deleted_at IS NULL",
    ['id' => $albumId]
);

// Prüfen, ob Album existiert und Zugriff erlaubt ist
if (!$album || (!$album['is_public'] && (!$userId || $album['user_id'] != $userId))) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Kein Zugriff auf dieses Album']);
    exit;
}

// Album-Besitzer
$isOwner = $userId && $album['user_id'] == $userId;

// Unteralben abrufen
$subAlbums = $db->fetchAll(
    "SELECT a.*, i.filename as cover_filename
     FROM albums a
     LEFT JOIN images i ON a.cover_image_id = i.id
     WHERE a.parent_album_id = :album_id
     AND a.deleted_at IS NULL
     ORDER BY a.name ASC",
    ['album_id' => $albumId]
);

// Bilder des Albums
$images = $db->fetchAll(
    "SELECT i.*,
     (SELECT COUNT(*) FROM favorites WHERE image_id = i.id AND user_id = :user_id) > 0 AS is_favorite,
     COALESCE(i.description, '') as description
     FROM images i
     WHERE i.album_id = :album_id AND i.deleted_at IS NULL
     ORDER BY i.upload_date DESC",
    [
        'album_id' => $albumId,
        'user_id' => $userId ?? 0
    ]
);

// Thumbnails für Unteralben vorbereiten
foreach ($subAlbums as &$subAlbum) {
    $imageCount = $db->fetchValue(
        "SELECT COUNT(*) FROM images WHERE album_id = :album_id AND deleted_at IS NULL",
        ['album_id' => $subAlbum['id']]
    );
    $subAlbum['image_count'] = $imageCount;
    
    // Thumbnail-Pfad bestimmen
    if (!empty($subAlbum['cover_filename'])) {
        $cleanPath = rtrim($subAlbum['path'], '/');
        $filename = basename($subAlbum['cover_filename']);
        $thumbnailPath = THUMBS_PATH . '/' . $cleanPath . '/' . $filename;
        
        $subAlbum['thumbnail'] = file_exists($thumbnailPath) 
            ? '../storage/thumbs/' . $cleanPath . '/' . $filename
            : 'img/default-album.jpg';
    } else {
        $randomImage = $db->fetchOne(
            "SELECT filename FROM images WHERE album_id = :album_id AND deleted_at IS NULL ORDER BY RANDOM() LIMIT 1",
            ['album_id' => $subAlbum['id']]
        );
        
        if ($randomImage) {
            $cleanPath = rtrim($subAlbum['path'], '/');
            $filename = basename($randomImage['filename']);
            $thumbnailPath = THUMBS_PATH . '/' . $cleanPath . '/' . $filename;
            
            $subAlbum['thumbnail'] = file_exists($thumbnailPath)
                ? '../storage/thumbs/' . $cleanPath . '/' . $filename
                : 'img/default-album.jpg';
        } else {
            $subAlbum['thumbnail'] = 'img/default-album.jpg';
        }
    }
}
unset($subAlbum);

// Album-Cover-Pfad
$coverPath = '';
if ($album['cover_image']) {
    $cleanPath = rtrim($album['path'], '/');
    $filename = basename($album['cover_image']);
    $thumbnailPath = THUMBS_PATH . '/' . $cleanPath . '/' . $filename;
    
    $coverPath = file_exists($thumbnailPath) 
        ? '../storage/thumbs/' . $cleanPath . '/' . $filename
        : 'img/default-album.jpg';
}

// Vorbereiten der Image-URLs
$processedImages = [];
foreach ($images as $image) {
    $cleanPath = rtrim($album['path'], '/');
    $filename = basename($image['filename']);
    $thumbnailUrl = '../storage/thumbs/' . $cleanPath . '/' . $filename;
    $fullImageUrl = '../storage/users/' . $image['filename'];
    
    $processedImages[] = [
        'id' => $image['id'],
        'filename' => $image['filename'],
        'description' => $image['description'],
        'is_favorite' => (bool)$image['is_favorite'],
        'rotation' => $image['rotation'] ?? 0,
        'is_public' => (bool)$image['is_public'],
        'thumbnail_url' => $thumbnailUrl,
        'full_url' => $fullImageUrl
    ];
}

// Elterninformationen (für Breadcrumbs)
$parentInfo = null;
if (isset($album['parent_album_id']) && $album['parent_album_id'] > 0) {
    $parentAlbum = $db->fetchOne(
        "SELECT id, name, path, cover_image_id, 
         (SELECT filename FROM images WHERE id = cover_image_id) as cover_filename
         FROM albums 
         WHERE id = :id",
        ['id' => $album['parent_album_id']]
    );
    
    if ($parentAlbum) {
        $parentCoverPath = 'img/default-album.jpg';
        if ($parentAlbum['cover_filename']) {
            $cleanPath = rtrim($parentAlbum['path'], '/');
            $filename = basename($parentAlbum['cover_filename']);
            $thumbnailPath = THUMBS_PATH . '/' . $cleanPath . '/' . $filename;
            
            $parentCoverPath = file_exists($thumbnailPath) 
                ? '../storage/thumbs/' . $cleanPath . '/' . $filename
                : 'img/default-album.jpg';
        }
        
        $parentInfo = [
            'id' => $parentAlbum['id'],
            'name' => $parentAlbum['name'],
            'cover_path' => $parentCoverPath
        ];
    }
}

// JSON-Antwort zusammenstellen und senden
header('Content-Type: application/json');
echo json_encode([
    'id' => $album['id'],
    'name' => $album['name'],
    'description' => $album['description'] ?? '',
    'is_public' => (bool)$album['is_public'],
    'cover_path' => $coverPath,
    'owner' => $album['owner_name'],
    'created_at' => isset($album['created_at']) ? date('d.m.Y', strtotime($album['created_at'])) : '01.01.2003',
    'parent' => $parentInfo,
    'subalbums' => $subAlbums,
    'images' => $processedImages,
    'is_owner' => $isOwner
]);