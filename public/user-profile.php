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

// Anzahl der Bilder pro Album ermitteln
foreach ($albums as &$album) {
    $imageCount = $db->fetchValue(
        "SELECT COUNT(*) FROM images WHERE album_id = :album_id AND deleted_at IS NULL",
        ['album_id' => $album['id']]
    );
    $album['image_count'] = $imageCount;
    
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
            $album['thumbnail'] = '/storage/thumbs/' . $albumPath . '/' . $firstImage['filename'];
        } else {
            $album['thumbnail'] = '/public/img/default-album.jpg';
        }
    } else {
        $album['thumbnail'] = '/public/img/default-album.jpg';
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
    <title><?php echo htmlspecialchars($user['username']); ?> - Fotogalerie</title>
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/profile-new.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <style>
        .album-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .album-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden;
        }
        
        .album-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .album-card a {
            display: flex;
            flex-direction: column;
            height: 100%;
            text-decoration: none;
            color: inherit;
        }
        
        .album-thumbnail {
            height: 180px;
            overflow: hidden;
        }
        
        .album-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .album-card:hover .album-thumbnail img {
            transform: scale(1.05);
        }
        
        .album-info {
            padding: 15px;
        }
        
        .album-info h3 {
            margin: 0 0 10px 0;
            font-size: 1.2rem;
            color: var(--primary-text);
        }
        
        .album-description {
            font-size: 0.9rem;
            color: var(--secondary-text);
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .image-count {
            font-size: 0.85rem;
            color: var(--secondary-text);
        }
        
        .public-badge {
            display: inline-block;
            background-color: #4caf50;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 5px;
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
<body class="profile-page">
    <?php
    // Aktive Seite für die Navigation
    $activePage = 'users';
    
    // Header einbinden
    require_once __DIR__ . '/includes/header.php';
    ?>

    <main class="profile-main">
        <div class="profile-hero">
            <div class="profile-hero-content">
                <div class="profile-avatar-wrapper">
                    <div class="profile-avatar">
                        <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Profilbild">
                    </div>
                </div>
                <div class="profile-hero-details">
                    <h1><?php echo htmlspecialchars($user['username']); ?></h1>
                    <div class="profile-stats-summary">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $publicAlbumCount; ?></span>
                            <span class="stat-label">Öffentliche Alben</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $publicImageCount; ?></span>
                            <span class="stat-label">Öffentliche Bilder</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-content">
            <div class="profile-section">
                <h3>Öffentliche Alben</h3>
                
                <?php if (empty($albums)): ?>
                    <div class="empty-state">
                        <p>Dieser Benutzer hat noch keine öffentlichen Alben.</p>
                    </div>
                <?php else: ?>
                    <div class="album-grid">
                        <?php foreach ($albums as $album): ?>
                            <div class="album-card">
                                <a href="/public/album.php?id=<?php echo $album['id']; ?>">
                                    <div class="album-thumbnail">
                                        <img src="<?php echo htmlspecialchars($album['thumbnail']); ?>" alt="<?php echo htmlspecialchars($album['name']); ?>">
                                    </div>
                                    <div class="album-info">
                                        <h3><?php echo htmlspecialchars($album['name']); ?></h3>
                                        <?php if (!empty($album['description'])): ?>
                                            <p class="album-description"><?php echo htmlspecialchars($album['description']); ?></p>
                                        <?php endif; ?>
                                        <p>
                                            <span class="image-count"><?php echo $album['image_count']; ?> Bilder</span>
                                            <span class="public-badge">Öffentlich</span>
                                        </p>
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