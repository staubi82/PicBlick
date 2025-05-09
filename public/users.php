<?php
/**
 * Fotogalerie - Benutzerliste
 *
 * Zeigt eine Liste aller Benutzer an
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

// Alle Benutzer abrufen, die öffentliche Alben haben
$users = $db->fetchAll(
    "SELECT u.id, u.username, u.profile_image, 
     COUNT(DISTINCT a.id) as album_count,
     SUM(CASE WHEN i.id IS NOT NULL THEN 1 ELSE 0 END) as image_count
     FROM users u
     LEFT JOIN albums a ON u.id = a.user_id AND a.is_public = 1 AND a.deleted_at IS NULL
     LEFT JOIN images i ON a.id = i.album_id AND i.deleted_at IS NULL
     GROUP BY u.id
     HAVING album_count > 0
     ORDER BY u.username ASC"
);

// Profilbild-URLs vorbereiten
foreach ($users as &$user) {
    if (!empty($user['profile_image']) &&
        file_exists(STORAGE_PATH . '/profile_images/' . $user['profile_image'])) {
        // Benutzer hat ein eigenes Profilbild
        $user['profile_image_url'] = '/storage/profile_images/' . $user['profile_image'];
    } else {
        // Standard-Avatar verwenden oder generischen Avatar erstellen
        $defaultProfileImg = __DIR__ . '/img/default-profile.jpg';
        if (file_exists($defaultProfileImg)) {
            $user['profile_image_url'] = '/public/img/default-profile.jpg';
        } else {
            // Einen farbigen Buchstaben-Avatar erstellen
            $user['profile_image_url'] = 'data:image/svg+xml,' . urlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="200" height="200"><rect width="100" height="100" fill="#6200ee"/><text x="50" y="60" font-family="Arial" font-size="45" fill="white" text-anchor="middle">' . strtoupper(substr($user['username'], 0, 1)) . '</text></svg>');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzer - Fotogalerie</title>
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <style>
        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .user-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s;
            overflow: hidden;
            position: relative;
            height: 220px;
        }
        
        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }
        
        .user-card a {
            display: block;
            height: 100%;
            text-decoration: none;
            color: inherit;
        }
        
        .user-avatar {
            position: relative;
            height: 100%;
            width: 100%;
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .user-card:hover .user-avatar img {
            transform: scale(1.1);
        }
        
        .user-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.5) 60%, rgba(0,0,0,0) 100%);
            color: white;
            padding: 20px 15px 15px;
            transition: opacity 0.3s;
        }
        
        .user-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 4px;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        }
        
        .user-count {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        /* Dunkelmodus Anpassungen */
        @media (prefers-color-scheme: dark) {
            .user-card {
                background-color: #2d2d2d;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            }
            
            .user-card:hover {
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.4);
            }
            
            .user-avatar {
                background-color: #1e1e1e;
            }
            
            .user-avatar img {
                border-color: #2d2d2d;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            }
            
            .user-info h3 {
                color: #e0e0e0;
            }
            
            .user-stats {
                color: #b0b0b0;
            }
            
            .empty-state {
                background-color: #2d2d2d;
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
            <section>
                <div class="section-header">
                    <h2>Benutzer mit öffentlichen Alben</h2>
                </div>

                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <p>Derzeit gibt es keine Benutzer mit öffentlichen Alben.</p>
                    </div>
                <?php else: ?>
                    <div class="user-grid">
                        <?php foreach ($users as $user): ?>
                            <div class="user-card">
                                <a href="/public/user-profile.php?id=<?php echo $user['id']; ?>">
                                    <div class="user-avatar">
                                        <img src="<?php echo htmlspecialchars($user['profile_image_url']); ?>"
                                             alt="<?php echo htmlspecialchars($user['username']); ?>"
                                             onerror="this.onerror=null;this.src='/public/img/default-profile.jpg';">
                                        <div class="user-overlay">
                                            <div class="user-title"><?php echo htmlspecialchars($user['username']); ?></div>
                                            <div class="user-count">
                                                <?php echo $user['album_count']; ?> Alben | <?php echo $user['image_count']; ?> Medien
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php
    // Footer einbinden
    require_once __DIR__ . '/includes/footer.php';
    ?>

    <script src="/public/js/main.js"></script>
</body>
</html>