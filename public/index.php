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
         AND a.deleted_at IS NULL
         AND (i.media_type IS NULL OR i.media_type = 'image')",
        ['user_id' => $currentUser['id']]
    );
    
    $totalVideos = $db->fetchValue(
        "SELECT COUNT(*) FROM images i
         JOIN albums a ON i.album_id = a.id
         WHERE a.user_id = :user_id
         AND i.deleted_at IS NULL
         AND a.deleted_at IS NULL
         AND i.media_type = 'video'",
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
    $totalVideos = 0;
    $favoriteImages = 0;
    $lastUploadDate = 'Nie';
}

// Funktion zum rekursiven Sammeln aller Unteralben
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

// Anzahl der Medien pro Album ermitteln, inklusive aller Unteralben
foreach ($albums as $key => &$album) {
    // Alle Unteralben-IDs sammeln
    $allAlbumIds = getAllSubalbumIds($db, $album['id']);
    $albumIdsString = implode(',', $allAlbumIds);
    
    // Falls keine Unteralben gefunden wurden (sollte nicht passieren, da mindestens das Album selbst enthalten ist)
    if (empty($albumIdsString)) {
        $albumIdsString = $album['id'];
    }
    
    // Gesamtzahl aller Medien in diesem Album und allen Unteralben
    $totalMediaCount = $db->fetchValue(
        "SELECT COUNT(*) FROM images WHERE album_id IN ($albumIdsString) AND deleted_at IS NULL"
    );
    
    $album['media_count'] = $totalMediaCount;
    
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
    <style>
/* Style für die Mediendetails */
.media-details {
display: block;
font-size: 0.85em;
opacity: 0.85;
margin-top: 3px;
}

/* Anpassungen für die Statistik-Anzeige */
.stats-container {
display: flex;
flex-wrap: wrap;
justify-content: space-around;
gap: 10px;
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

@media (max-width: 768px) {
.album-overlay .album-count {
    font-size: 0.9em;
}

.media-details {
    font-size: 0.75em;
}

.globe-icon {
    width: 20px;
    height: 20px;
}
}
</style>
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
                        <div class="stat-value"><?php echo $totalVideos; ?></div>
                        <div class="stat-label">Videos</div>
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
                                            <div class="album-count">
                                                <?php echo $album['media_count']; ?>
                                            </div>
                                        </div>
                                        <?php if ($album['is_public']): ?>
                                            <span class="public-badge globe-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="globe">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <line x1="2" y1="12" x2="22" y2="12"></line>
                                                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                                                </svg>
                                            </span>
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