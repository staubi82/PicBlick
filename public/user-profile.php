<?php
/**
 * Fotogalerie - Öffentliches Benutzerprofil
 *
 * Zeigt das öffentliche Profil eines Benutzers mit seinen öffentlichen Alben
 */

// Konfiguration und Bibliotheken laden
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../app/auth/auth.php';

// Authentifizierung initialisieren
$auth = new Auth();
$auth->checkSession();

// Aktuellen Benutzer abrufen
$currentUser = $auth->getCurrentUser();

// Datenbank initialisieren
$db = Database::getInstance();

// Benutzer-ID prüfen
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId <= 0) {
    header('Location: users.php');
    exit;
}

// Benutzer abrufen
$user = $db->fetchOne(
    "SELECT * FROM users WHERE id = :id",
    ['id' => $userId]
);

if (!$user) {
    header('Location: users.php?error=usernotfound');
    exit;
}

// Öffentliche Alben des Benutzers abrufen
$albums = $db->fetchAll(
    "SELECT * FROM albums WHERE 
     user_id = :user_id AND is_public = 1 AND deleted_at IS NULL
     ORDER BY created_at DESC",
    ['user_id' => $userId]
);

// Funktion zum Sammeln aller Unteralben-IDs
function getAllSubalbumIds($db, $albumId) {
    $allIds = [$albumId];
    
    // Prüfen, ob die parent_album_id-Spalte existiert
    $hasParentColumn = false;
    $columns = $db->fetchAll("PRAGMA table_info(albums)", []);
    foreach ($columns as $col) {
        if ($col['name'] === 'parent_album_id') {
            $hasParentColumn = true;
            break;
        }
    }
    
    if ($hasParentColumn) {
        $subAlbums = $db->fetchAll(
            "SELECT id FROM albums WHERE parent_album_id = :album_id AND deleted_at IS NULL",
            ['album_id' => $albumId]
        );
        
        foreach ($subAlbums as $subAlbum) {
            $subIds = getAllSubalbumIds($db, $subAlbum['id']);
            $allIds = array_merge($allIds, $subIds);
        }
    }
    
    return $allIds;
}

// Anzahl der Medien pro Album ermitteln (inkl. Unteralben)
foreach ($albums as &$album) {
    // Alle Album-IDs inklusive Unteralben ermitteln
    $allAlbumIds = getAllSubalbumIds($db, $album['id']);
    
    // In SQL-kompatiblen String umwandeln
    $albumIdsString = implode(',', $allAlbumIds);
    
    // Gesamtzahl aller Medien in diesem Album und allen Unteralben
    $mediaCount = $db->fetchValue(
        "SELECT COUNT(*) FROM images WHERE album_id IN ($albumIdsString) AND deleted_at IS NULL"
    );
    $album['image_count'] = $mediaCount;
    
    // Thumbnail des ersten Bildes im Album für die Vorschau
    $firstImage = $db->fetchOne(
        "SELECT filename FROM images WHERE album_id = :album_id AND deleted_at IS NULL LIMIT 1",
        ['album_id' => $album['id']]
    );
    
    if ($firstImage) {
        $albumPath = $album['path'];
        $thumbnailPath = THUMBS_PATH . '/' . $albumPath . '/' . $firstImage['filename'];
        
        // Prüfen, ob Thumbnail existiert
        if (file_exists($thumbnailPath)) {
            $album['thumbnail'] = 'storage/thumbs/' . $albumPath . '/' . $firstImage['filename'];
        } else {
            $album['thumbnail'] = 'img/default-album.jpg';
        }
    } else {
        $album['thumbnail'] = 'img/default-album.jpg';
    }
}

// Statistiken abrufen
$publicAlbumCount = count($albums);

$publicImageCount = $db->fetchValue(
    "SELECT COUNT(i.id) 
     FROM images i
     JOIN albums a ON i.album_id = a.id
     WHERE a.user_id = :user_id AND a.is_public = 1 AND i.deleted_at IS NULL AND a.deleted_at IS NULL",
    ['user_id' => $userId]
);

// Profilbild-URL vorbereiten
if (!empty($user['profile_image']) &&
    file_exists(STORAGE_PATH . '/profile_images/' . $user['profile_image'])) {
    // Benutzer hat ein eigenes Profilbild
    $profileImageUrl = '/storage/profile_images/' . $user['profile_image'];
} else {
    // Standard-Avatar verwenden (falls vorhanden) oder generischen Avatar erstellen
    $defaultProfileImg = __DIR__ . '/img/default-profile.jpg';
    if (file_exists($defaultProfileImg)) {
        $profileImageUrl = '/public/img/default-profile.jpg';
    } else {
        // Einen farbigen Buchstaben-Avatar erstellen
        $profileImageUrl = 'data:image/svg+xml,' . urlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="200" height="200"><rect width="100" height="100" fill="#6200ee"/><text x="50" y="60" font-family="Arial" font-size="45" fill="white" text-anchor="middle">' . strtoupper(substr($user['username'], 0, 1)) . '</text></svg>');
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['username']); ?> - PicBlick</title>
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <style>
        /* User profile header styling */
        .user-profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 20px;
            background-color: rgba(255, 255, 255, 0.07);
            border-radius: 10px;
        }
        
        .user-profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            border: 3px solid rgba(255, 255, 255, 0.2);
        }
        
        .user-profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-profile-info h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .section-title {
            margin: 30px 0 15px;
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary-text);
        }
        
        /* Stats styling */
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            gap: 10px;
            margin-top: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px 15px;
            background-color: rgba(255, 255, 255, 0.07);
            border-radius: 8px;
            min-width: 100px;
        }
        
        .stat-value {
            font-size: 1.6rem;
            font-weight: 600;
            display: block;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        /* Album grid styling */
        .album-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .album-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s;
            overflow: hidden;
            position: relative;
            height: 180px;
            aspect-ratio: 16 / 9;
        }
        
        .album-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }
        
        .album-card a {
            display: block;
            height: 100%;
            text-decoration: none;
            color: inherit;
        }
        
        .album-thumbnail {
            position: relative;
            height: 100%;
            width: 100%;
            overflow: hidden;
        }
        
        .album-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .album-card:hover .album-thumbnail img {
            transform: scale(1.1);
        }
        
        .album-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.5) 60%, rgba(0,0,0,0) 100%);
            color: white;
            padding: 20px 15px 15px;
            transition: opacity 0.3s;
        }
        
        .album-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 4px;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        }
        
        .album-count {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        /* Styling für das Weltkugel-Icon */
        .globe-icon {
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, rgba(0, 120, 50, 0.8), rgba(0, 150, 70, 0.9));
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .globe {
            color: white;
            stroke-width: 1.5;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .profile-section {
            margin-top: 20px;
        }
        
        .profile-section h3 {
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        
        /* Dunkelmodus Anpassungen */
        @media (prefers-color-scheme: dark) {
            .album-card {
                background-color: #2d2d2d;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            }
            
            .album-card:hover {
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.4);
            }
            
            .album-info h3 {
                color: #e0e0e0;
            }
            
            .album-description {
                color: #b0b0b0;
            }
            
            .image-count {
                color: #b0b0b0;
            }
            
            .empty-state {
                background-color: #2d2d2d;
                color: #e0e0e0;
            }
            
            .profile-section h3 {
                color: #e0e0e0;
            }
        }
    </style>
</head>
<body>
    <?php
    // Aktive Seite für die Navigation
    $activePage = 'users';
    
    // Header einbinden
    require_once __DIR__ . '/includes/header.php';
    ?>

    <main>
        <div class="container">
            <section class="gallery-stats">
                <div class="user-profile-header">
                    <div class="user-profile-avatar">
                        <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Profilbild"
                             onerror="this.onerror=null;this.src='/public/img/default-profile.jpg';">
                    </div>
                    <div class="user-profile-info">
                        <h1><?php echo htmlspecialchars($user['username']); ?></h1>
                    </div>
                </div>
                
                <div class="stats-container">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $publicAlbumCount; ?></div>
                        <div class="stat-label">Öffentliche Alben</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $publicImageCount; ?></div>
                        <div class="stat-label">Öffentliche Medien</div>
                    </div>
                </div>
            </section>

            <section class="albums">
                <h3 class="section-title">Öffentliche Alben</h3>
                
                <?php if (empty($albums)): ?>
                    <div class="empty-state">
                        <p>Dieser Benutzer hat noch keine öffentlichen Alben.</p>
                    </div>
                <?php else: ?>
                    <div class="album-grid">
                        <?php foreach ($albums as $album): ?>
                            <div class="album-card">
                                <a href="album.php?id=<?php echo $album['id']; ?>">
                                    <div class="album-thumbnail">
                                        <img src="<?php echo htmlspecialchars($album['thumbnail']); ?>"
                                             alt="<?php echo htmlspecialchars($album['name']); ?>"
                                             onerror="this.onerror=null;this.src='img/default-album.jpg';">
                                        <div class="album-overlay">
                                            <div class="album-title"><?php echo htmlspecialchars($album['name']); ?></div>
                                            <div class="album-count">
                                                <?php echo $album['image_count']; ?>
                                            </div>
                                        </div>
                                        <span class="public-badge globe-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="globe">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <line x1="2" y1="12" x2="22" y2="12"></line>
                                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                                            </svg>
                                        </span>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php
    // Footer einbinden
    require_once __DIR__ . '/includes/footer.php';
    ?>

    <script src="/public/js/main.js"></script>
</body>
</html>