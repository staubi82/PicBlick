<?php
/**
 * Fotogalerie - Favoriten-Ansicht
 *
 * Zeigt alle favorisierten Bilder des aktuellen Benutzers
 */

// Konfiguration und Bibliotheken laden
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../app/auth/auth.php';

// Authentifizierung initialisieren
$auth = new Auth();
$auth->checkSession();

// Prüfen, ob Benutzer angemeldet ist
if (!$auth->isLoggedIn()) {
    header('Location: login.php?redirect=favorites.php');
    exit;
}

// Aktuellen Benutzer abrufen
$currentUser = $auth->getCurrentUser();

// Datenbank initialisieren
$db = Database::getInstance();

// Favoriten abrufen
$favorites = $db->fetchAll(
    "SELECT i.*, a.name as album_name, a.path as album_path, 
     a.user_id as album_owner_id, u.username as owner_name,
     1 as is_favorite
     FROM favorites f
     JOIN images i ON f.image_id = i.id
     JOIN albums a ON i.album_id = a.id
     JOIN users u ON a.user_id = u.id
     WHERE f.user_id = :user_id AND i.deleted_at IS NULL
     ORDER BY f.created_at DESC",
    ['user_id' => $currentUser['id']]
);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meine Favoriten - Fotogalerie</title>
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/header.css">
</head>
<body>
    <?php
    // Aktive Seite für die Navigation
    $activePage = 'favorites';
    
    // Header einbinden
    require_once __DIR__ . '/includes/header.php';
    ?>

    <main>
        <div class="container">
            <section>
                <div class="section-header">
                    <h2>Meine Favoriten</h2>
                </div>
                
                <?php if (isset($_GET['removed']) && $_GET['removed'] == 1): ?>
                    <div class="alert alert-success">
                        Bild wurde aus den Favoriten entfernt.
                    </div>
                <?php endif; ?>
                
                <?php if (empty($favorites)): ?>
                    <div class="empty-state">
                        <p>Sie haben noch keine Favoriten hinzugefügt.</p>
                        <p>Klicken Sie auf den Stern bei Bildern, die Ihnen gefallen, um sie zu Ihren Favoriten hinzuzufügen.</p>
                        <a href="/public/index.php" class="btn btn-primary">Zu den Alben</a>
                    </div>
                <?php else: ?>
                    <div class="image-grid">
                        <?php foreach ($favorites as $image): ?>
                            <?php
                            // Debug-Ausgabe
                            //echo "<!-- DEBUG: filename = " . htmlspecialchars($image['filename']) . " -->";
                            
                            $userId = $image['album_owner_id'];
                            $albumName = $image['album_name'];
                            
                            // Prüfen, ob $image['filename'] schon einen Pfad enthält
                            if (strpos($image['filename'], 'user_') === 0 || strpos($image['filename'], '/') !== false) {
                                // Der Dateiname enthält bereits einen Pfad - extrahiere nur den Dateinamen
                                $justFilename = basename($image['filename']);
                            } else {
                                $justFilename = $image['filename'];
                            }
                            
                            // Einfache, direkte Pfadkonstruktion
                            $thumbnailUrl = "/storage/thumbs/user_{$userId}/{$albumName}/{$justFilename}";
                            $fullImageUrl = "/storage/users/user_{$userId}/{$albumName}/{$justFilename}";
                            ?>
                            <div class="image-card" data-image-id="<?php echo $image['id']; ?>" 
                                 data-full-src="<?php echo $fullImageUrl; ?>"
                                 data-image-name="<?php echo htmlspecialchars($image['filename']); ?>"
                                 data-favorite="true">
                                <img src="<?php echo $thumbnailUrl; ?>" alt="<?php echo htmlspecialchars($image['filename']); ?>">
                                
                                <div class="image-overlay">
                                    <div class="image-actions">
                                        <button type="button" class="favorite-button active" title="Aus Favoriten entfernen">★</button>
                                        
                                        <a href="/public/album.php?id=<?php echo $image['album_id']; ?>" 
                                           class="album-link" title="Album öffnen">
                                            <i class="icon-folder"></i>
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="image-info">
                                    <span>Album: <?php echo htmlspecialchars($image['album_name']); ?></span>
                                    <span>Von: <?php echo htmlspecialchars($image['owner_name']); ?></span>
                                </div>
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
    <script src="/public/js/fullscreen-viewer.js"></script>
</body>
</html>