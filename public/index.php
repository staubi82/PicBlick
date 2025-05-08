<?php
/**
 * Fotogalerie - Hauptseite
 *
 * Zeigt die Startseite mit Albenübersicht
 */

// Cache-Header setzen, um Browser-Caching zu verhindern
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

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

// Alben abrufen (unterschiedliche Abfragen je nach Login-Status)
if ($currentUser) {
    // Nur eigene Alben für eingeloggte Benutzer
    // Prüfen, ob die parent_album_id-Spalte bereits existiert
    $hasParentColumn = false;
    try {
        $colCheck = $db->fetchOne(
            "PRAGMA table_info(albums)",
            []
        );
        
        $columns = $db->fetchAll(
            "PRAGMA table_info(albums)",
            []
        );
        
        foreach ($columns as $col) {
            if ($col['name'] === 'parent_album_id') {
                $hasParentColumn = true;
                break;
            }
        }
    } catch (Exception $e) {
        // Spalte existiert nicht, ignorieren
    }
    
    // SQL-Abfrage basierend auf vorhandenen Spalten anpassen
    if ($hasParentColumn) {
        $albums = $db->fetchAll(
            "SELECT a.*, i.filename as cover_filename
             FROM albums a
             LEFT JOIN images i ON a.cover_image_id = i.id
             WHERE a.user_id = :user_id
             AND a.deleted_at IS NULL
             AND a.parent_album_id IS NULL
             ORDER BY a.id DESC",
            ['user_id' => $currentUser['id']]
        );
    } else {
        $albums = $db->fetchAll(
            "SELECT a.*, i.filename as cover_filename
             FROM albums a
             LEFT JOIN images i ON a.cover_image_id = i.id
             WHERE a.user_id = :user_id
             AND a.deleted_at IS NULL
             ORDER BY a.id DESC",
            ['user_id' => $currentUser['id']]
        );
    }
    
    // Statistikdaten abrufen
    $totalAlbums = $db->fetchValue(
        "SELECT COUNT(*) FROM albums WHERE user_id = :user_id AND deleted_at IS NULL",
        ['user_id' => $currentUser['id']]
    );
    
    $totalImages = $db->fetchValue(
        "SELECT COUNT(*) FROM images i
         JOIN albums a ON i.album_id = a.id
         WHERE a.user_id = :user_id
         AND i.deleted_at IS NULL
         AND a.deleted_at IS NULL",
        ['user_id' => $currentUser['id']]
    );
    
    $favoriteImages = $db->fetchValue(
        "SELECT COUNT(*) FROM favorites f
         JOIN images i ON f.image_id = i.id
         JOIN albums a ON i.album_id = a.id
         WHERE f.user_id = :user_id
         AND i.deleted_at IS NULL
         AND a.deleted_at IS NULL",
        ['user_id' => $currentUser['id']]
    );
    
    $lastUpload = $db->fetchValue(
        "SELECT MAX(upload_date) FROM images i
         JOIN albums a ON i.album_id = a.id
         WHERE a.user_id = :user_id
         AND i.deleted_at IS NULL
         AND a.deleted_at IS NULL",
        ['user_id' => $currentUser['id']]
    );
    
    // Datum formatieren (wenn vorhanden)
    $lastUploadDate = $lastUpload ? date('d.m.Y', strtotime($lastUpload)) : 'Nie';
} else {
    // Für Gäste zeigen wir nichts an
    $albums = [];
    $totalAlbums = 0;
    $totalImages = 0;
    $favoriteImages = 0;
    $lastUploadDate = 'Nie';
}

// Anzahl der Bilder pro Album ermitteln
foreach ($albums as $key => &$album) {
    $imageCount = $db->fetchValue(
        "SELECT COUNT(*) FROM images WHERE album_id = :album_id AND deleted_at IS NULL",
        ['album_id' => $album['id']]
    );
    $album['image_count'] = $imageCount;
    
    // Cover-Bild verwenden, wenn vorhanden, sonst zufälliges Bild
    if (!empty($album['cover_filename'])) {
        // Cover-Bild aus der Datenbank verwenden
        $cleanPath = rtrim($album['path'], '/');
        $filename = basename($album['cover_filename']);
        
        $thumbnailPath = THUMBS_PATH . '/' . $cleanPath . '/' . $filename;
        
        // Prüfen, ob Thumbnail existiert
        if (file_exists($thumbnailPath)) {
            $album['thumbnail'] = '../storage/thumbs/' . $cleanPath . '/' . $filename;
        } else {
            $album['thumbnail'] = 'img/default-album.jpg';
        }
    } else {
        // Kein Cover-Bild vorhanden - zufälliges Bild auswählen
        $randomImage = $db->fetchOne(
            "SELECT filename FROM images WHERE album_id = :album_id AND deleted_at IS NULL ORDER BY RANDOM() LIMIT 1",
            ['album_id' => $album['id']]
        );
        
        if ($randomImage) {
            // Pfad bereinigen (Schrägstriche am Ende entfernen)
            $cleanPath = rtrim($album['path'], '/');
            
            // Prüfen ob es sich um einen vollständigen Pfad handelt
            $filename = basename($randomImage['filename']);
            
            $thumbnailPath = THUMBS_PATH . '/' . $cleanPath . '/' . $filename;
            
            // Prüfen, ob Thumbnail existiert
            if (file_exists($thumbnailPath)) {
                $album['thumbnail'] = '../storage/thumbs/' . $cleanPath . '/' . $filename;
            } else {
                $album['thumbnail'] = 'img/default-album.jpg';
            }
        } else {
            $album['thumbnail'] = 'img/default-album.jpg';
        }
    }
}
unset($album); // Referenz nach foreach-Schleife aufheben

// Debug: Ausgabe der Album-Daten
error_log("Albenliste: " . print_r($albums, true));

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PicBlick by Staubi</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/header.css">
</head>
<body>
    <?php
    // Aktive Seite für die Navigation
    $activePage = 'home';
    
    // Header einbinden
    require_once __DIR__ . '/includes/header.php';
    ?>

    <main>
        <div class="container">
            <?php if ($currentUser): ?>
            <section class="gallery-stats">
                <div class="stats-container">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $totalAlbums; ?></div>
                        <div class="stat-label">Sammlungen</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $totalImages; ?></div>
                        <div class="stat-label">Bilder</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $favoriteImages; ?></div>
                        <div class="stat-label">Favoriten</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $lastUploadDate; ?></div>
                        <div class="stat-label">Letzter Upload</div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <section class="albums">

                <?php if (empty($albums)): ?>
                    <div class="empty-state">
                        <p>Keine Alben gefunden.</p>
                        <?php if ($currentUser): ?>
                            <p></p>
                        <?php else: ?>
                            <p>Bitte <a href="login.php">melden Sie sich an</a>, um Ihre eigenen Alben zu erstellen. Sie können auch <a href="users.php">alle Benutzer</a> durchsuchen, um deren öffentliche Alben anzusehen.</p>
                        <?php endif; ?>
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
                                            <div class="album-count"><?php echo $album['image_count']; ?></div>
                                        </div>
                                        <?php if ($album['is_public']): ?>
                                            <span class="public-badge">Öffentlich</span>
                                        <?php endif; ?>
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

    <script src="js/main.js"></script>
</body>
</html>