<?php
/**
 * Fotogalerie - Album-Ansicht
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
$userId = $currentUser ? $currentUser['id'] : null;

// Datenbank initialisieren
$db = Database::getInstance();

// Album-ID prüfen
$albumId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($albumId <= 0) {
    header('Location: index.php');
    exit;
}

// Album abrufen
$album = $db->fetchOne(
    "SELECT a.*, i.filename as cover_image
     FROM albums a
     LEFT JOIN images i ON a.cover_image_id = i.id
     WHERE a.id = :id AND a.deleted_at IS NULL",
    ['id' => $albumId]
);

// Prüfen, ob Album existiert und Zugriff erlaubt ist
if (!$album || (!$album['is_public'] && (!$userId || $album['user_id'] != $userId))) {
    header('Location: index.php?error=noaccess');
    exit;
}

// Album-Besitzer
$isOwner = $userId && $album['user_id'] == $userId;

// Prüfen, ob die parent_album_id-Spalte bereits existiert
$hasParentColumn = false;
try {
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

// Unteralben abrufen, falls die Spalte vorhanden ist
$subAlbums = [];
if ($hasParentColumn) {
    $subAlbums = $db->fetchAll(
        "SELECT a.*, i.filename as cover_filename
         FROM albums a
         LEFT JOIN images i ON a.cover_image_id = i.id
         WHERE a.parent_album_id = :album_id
         AND a.deleted_at IS NULL
         ORDER BY a.name ASC",
        ['album_id' => $albumId]
    );
}

// Zähle Bilder in Unteralben
foreach ($subAlbums as &$subAlbum) {
    $imageCount = $db->fetchValue(
        "SELECT COUNT(*) FROM images WHERE album_id = :album_id AND deleted_at IS NULL",
        ['album_id' => $subAlbum['id']]
    );
    $subAlbum['image_count'] = $imageCount;
    
    // Cover-Bild verwenden, wenn vorhanden, sonst zufälliges Bild
    if (!empty($subAlbum['cover_filename'])) {
        // Cover-Bild aus der Datenbank verwenden
        $cleanPath = rtrim($subAlbum['path'], '/');
        $filename = basename($subAlbum['cover_filename']);
        
        $thumbnailPath = THUMBS_PATH . '/' . $cleanPath . '/' . $filename;
        
        // Prüfen, ob Thumbnail existiert
        if (file_exists($thumbnailPath)) {
            $subAlbum['thumbnail'] = '../storage/thumbs/' . $cleanPath . '/' . $filename;
        } else {
            $subAlbum['thumbnail'] = 'img/default-album.jpg';
        }
    } else {
        // Kein Cover-Bild vorhanden - zufälliges Bild auswählen
        $randomImage = $db->fetchOne(
            "SELECT filename FROM images WHERE album_id = :album_id AND deleted_at IS NULL ORDER BY RANDOM() LIMIT 1",
            ['album_id' => $subAlbum['id']]
        );
        
        if ($randomImage) {
            // Pfad bereinigen (Schrägstriche am Ende entfernen)
            $cleanPath = rtrim($subAlbum['path'], '/');
            
            // Prüfen ob es sich um einen vollständigen Pfad handelt
            $filename = basename($randomImage['filename']);
            
            $thumbnailPath = THUMBS_PATH . '/' . $cleanPath . '/' . $filename;
            
            // Prüfen, ob Thumbnail existiert
            if (file_exists($thumbnailPath)) {
                $subAlbum['thumbnail'] = '../storage/thumbs/' . $cleanPath . '/' . $filename;
            } else {
                $subAlbum['thumbnail'] = 'img/default-album.jpg';
            }
        } else {
            $subAlbum['thumbnail'] = 'img/default-album.jpg';
        }
    }
}
unset($subAlbum); // Referenz nach foreach-Schleife aufheben

// Bilder abrufen
$images = $db->fetchAll(
    "SELECT i.*,
     (SELECT COUNT(*) FROM favorites WHERE image_id = i.id AND user_id = :user_id) > 0 AS is_favorite,
     COALESCE(i.description, '') as description,
     COALESCE(i.media_type, 'image') as media_type
     FROM images i
     WHERE i.album_id = :album_id AND i.deleted_at IS NULL
     ORDER BY i.upload_date DESC",
    [
        'album_id' => $albumId,
        'user_id' => $userId ?? 0
    ]
);

// Album-Besitzer abrufen
$owner = $db->fetchOne(
    "SELECT username FROM users WHERE id = :user_id",
    ['user_id' => $album['user_id']]
);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($album['name']); ?> - Fotogalerie</title>
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="/public/css/album-modal.css">
    <!-- Three.js für 3D-Elemente -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
</head>
<body>
    <?php
    // Aktive Seite für die Navigation
    $activePage = 'album';
    
    // Header einbinden
    require_once __DIR__ . '/includes/header.php';
    ?>

    <main>
        <div class="container">
            <section>
                <div class="section-header">
                    <?php if (false): // "Zurück"-Link in Unterkategorien entfernen ?>
                    <?php
                    // Übergeordnetes Album für die Titelzeile abrufen
                    //$parentAlbum = $db->fetchOne(
                    //    "SELECT id, name FROM albums WHERE id = :id",
                    //    ['id' => $album['parent_album_id']]
                    //);
                    ?>
                    <!-- "Zurück"-Link entfernt -->
                    <?php endif; ?>
                    
                    <!-- Album-Header aus separater Datei einbinden -->
                    <?php require_once __DIR__ . '/includes/album-header.php'; ?>
                </div>


                <!-- Horizontale Trennlinie zwischen Titel und Inhalten -->
                <hr class="album-divider">

                <!-- Album-Editor-Modal aus separater Datei einbinden -->
                <?php require_once __DIR__ . '/includes/album-edit-modal.php'; ?>


                <?php if (empty($images)): ?>
                    <div class="empty-state">
                        <p>Dieses Album enthält noch keine Bilder.</p>
                    </div>
                <?php else: ?>
                    <!-- Wenn das Album nicht leer ist -->
                    <div class="cover-selection-info" id="cover-selection-info">
                        <p>Wählen Sie ein Bild als neues Titelbild für dieses Album.</p>
                    </div>

                    <!-- Unteralben werden jetzt in der Titelleiste angezeigt -->

                    <!-- Sortier-Dropdown -->
                    <?php if (!empty($images)): ?>
                    <!-- Sortier-Dropdown aus separater Datei einbinden -->
                    <?php require_once __DIR__ . '/includes/sort-dropdown.php'; ?>

                    <!-- Bilder anzeigen, falls vorhanden -->
                    <div class="image-grid" id="album-edit-grid" data-edit-mode="false" data-cover-selection-mode="false">
                        <?php foreach ($images as $image): ?>
                            <?php
                            $cleanPath = rtrim($album['path'], '/');
                            // Konstruieren der korrekten URLs basierend auf dem Dateinamen
                            $filename = basename($image['filename']);
                            $thumbnailUrl = '../storage/thumbs/' . $cleanPath . '/' . $filename;
                            $fullImageUrl = '../storage/users/' . $image['filename'];
                            ?>
                            <div class="image-card" data-image-id="<?php echo $image['id']; ?>"
                                 data-full-src="<?php echo $fullImageUrl; ?>"
                                 data-image-name="<?php echo htmlspecialchars($image['filename']); ?>"
                                 data-favorite="<?php echo $image['is_favorite'] ? 'true' : 'false'; ?>"
                                 data-rotation="<?php echo $image['rotation'] ?? 0; ?>"
                                 data-description="<?php echo htmlspecialchars($image['description'] ?? ''); ?>">
                                <?php if ($image['media_type'] === 'video'): ?>
                                    <!-- Video-Thumbnail anzeigen mit Play-Symbol -->
                                    <div class="video-thumbnail">
                                        <img src="<?php echo file_exists(THUMBS_PATH . '/' . $cleanPath . '/' . $filename) ?
                                            $thumbnailUrl : '/public/img/default-video-thumb.png'; ?>"
                                             alt="<?php echo htmlspecialchars($image['filename']); ?>"
                                             onerror="this.onerror=null;this.src='/public/img/default-video-thumb.png';">
                                        <div class="video-play-icon">▶</div>
                                    </div>
                                <?php else: ?>
                                    <img src="<?php echo $thumbnailUrl; ?>"
                                         alt="<?php echo htmlspecialchars($image['filename']); ?>"
                                         onerror="this.onerror=null;this.src='/public/img/default-album.jpg';"
                                         style="--rotation: <?php echo $image['rotation'] ?? 0; ?>deg;">
                                <?php endif; ?>
                                
                                <div class="image-overlay">
                                    <div class="image-actions">
                                        <?php if ($userId): ?>
                                            <button type="button" class="favorite-button <?php echo $image['is_favorite'] ? 'active' : ''; ?>"
                                                    title="<?php echo $image['is_favorite'] ? 'Aus Favoriten entfernen' : 'Zu Favoriten hinzufügen'; ?>">★</button>
                                        <?php endif; ?>
                                        
                                        <?php if ($isOwner): ?>
                                            <div class="dropdown">
                                                <button type="button" class="dropdown-toggle">⋮</button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <form method="post" action="">
                                                            <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                                            <input type="hidden" name="toggle_visibility" value="1">
                                                            <button type="submit" class="text-button">
                                                                <?php echo $image['is_public'] ? 'Auf privat setzen' : 'Öffentlich machen'; ?>
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form name="delete_image_form">
                                                            <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                                            <button type="submit" class="text-button">In Papierkorb</button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form name="delete_image_form">
                                                            <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                                            <input type="hidden" name="force_delete" value="1">
                                                            <button type="submit" class="text-button text-danger">Löschen (endgültig)</button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($image['is_public']): ?>
                                    <span class="public-badge small">Öffentlich</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php
    // Footer einbinden
    require_once __DIR__ . '/includes/footer.php';
    ?>
<!-- CSS für den direkt editierbaren Modus -->
<style>
    .editable {
        border: 2px dashed var(--accent-primary);
        padding: 5px;
        background-color: rgba(98, 0, 238, 0.05);
        border-radius: var(--border-radius);
        position: relative;
    }
    
    .editable:hover {
        background-color: rgba(98, 0, 238, 0.1);
    }
    
    .editable:focus {
        outline: none;
        border-color: var(--accent-secondary);
        box-shadow: 0 0 0 2px rgba(3, 218, 198, 0.2);
    }
    
    .editable::after {
        content: '✎';
        position: absolute;
        right: 5px;
        top: 5px;
        font-size: 0.8em;
        color: var(--accent-primary);
    }
    
    /* IT-Style für Albumtitel */
    .it-style-title {
        font-family: 'JetBrains Mono', 'Fira Code', 'Source Code Pro', 'Roboto Mono', 'Courier New', monospace;
        letter-spacing: 0.03em;
        font-weight: 500;
        text-transform: uppercase;
    }
    
    /* Breadcrumb-Navigation */
    .breadcrumb {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
        font-size: 0.9em;
        flex-wrap: wrap;
    }
    
    .breadcrumb a {
        color: var(--accent-primary);
        text-decoration: none;
    }
    
    .breadcrumb a:hover {
        text-decoration: underline;
    }
    
    .breadcrumb .separator {
        margin: 0 8px;
        color: var(--text-muted);
    }
    
    /* Action Buttons */
    .action-buttons {
        margin: 20px 0;
        display: flex;
        gap: 10px;
    }
    
    .static-field {
        background-color: rgba(255, 255, 255, 0.1);
        padding: 8px 12px;
        border-radius: var(--border-radius);
        color: var(--text-muted);
    }
    
    /* Neues Layout für Album-Info und Unteralben */
    .album-info-container {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
    }
    
    .album-info {
        flex: 1;
        min-width: 250px;
    }
    
 /* Anpassungen für die Unteralben-Darstellung */
/* Anpassungen für die Unteralben-Darstellung */
.subalbums-swiper {
    flex: 1;
    max-width: 50%;
    /* Ändern von overflow-x: auto zu visible, damit der Hover-Effekt nicht abgeschnitten wird */
    overflow: visible;
    white-space: nowrap;
    padding: 8px 0;
    position: relative;
    display: flex;
    align-items: center; /* Vertikale Zentrierung der Elemente */
}

.swiper-wrapper {
    display: flex;
    gap: 10px;
    /* Sicherstellen, dass auch hier overflow visible ist */
    overflow: visible;
    align-items: center; /* Vertikale Zentrierung */
    height: 100%; /* Volle Höhe ausnutzen */
}

.swiper-slide {
    flex: 0 0 auto;
    width: 150px;
    overflow: visible;
    /* Platz für den Hover-Effekt lassen */
    padding: 5px;
    margin: 5px 0;
    display: flex;
    align-items: center;
}

.subalbum-card {
    display: block;
    text-decoration: none;
    color: var(--text-color);
    border-radius: var(--border-radius);
    overflow: visible;
    transition: transform 0.2s, box-shadow 0.2s;
    /* Positionierung für Hover-Effekt */
    position: relative;
    z-index: 1;
}

.subalbum-card:hover {
    transform: scale(1.05);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
    z-index: 10;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.subalbum-image-container {
    position: relative;
    width: 100%;
    height: 100px;
    border-radius: 8px; /* Explizite Rundung für die Ecken */
    overflow: hidden; /* Wichtig, um sicherzustellen, dass Bilder innerhalb der abgerundeten Ecken bleiben */
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.subalbum-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 8px; /* Runde Ecken auch für das Bild */
    transition: transform 0.3s ease;
}

.subalbum-card:hover img {
    transform: scale(1.03); /* Leichte Animation des Bildes selbst beim Hover */
}

.subalbum-title-overlay {
    position: absolute;
    top: auto;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 8px;
    font-size: 0.9em;
    font-weight: bold;
    text-overflow: ellipsis;
    overflow: hidden;
    text-align: center;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.8);
    border-bottom-left-radius: 8px; /* Rundung nur unten */
    border-bottom-right-radius: 8px;
}

/* Anpassungen für mobile Ansicht */
@media (max-width: 768px) {
    .subalbums-swiper {
        max-width: 100%;
        margin-top: 10px;
        overflow-x: auto; /* Auf mobilen Geräten wieder scrollbar machen */
        padding-bottom: 15px; /* Platz für Scrollbar */
    }
    
    .swiper-slide {
        width: 130px; /* Etwas kleiner auf mobilen Geräten */
    }
}

/* Video-Thumbnail Styling */
.video-thumbnail {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.video-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.video-play-icon {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: rgba(0, 0, 0, 0.7);
    color: white;
    font-size: 20px;
    opacity: 0.8;
    transition: all 0.3s ease;
}

.video-thumbnail:hover .video-play-icon {
    opacity: 1;
    transform: scale(1.1);
    background-color: rgba(0, 0, 0, 0.8);
}
</style>

<!-- CSS für das Sortier-Dropdown - Minimalistisches Design -->
<style>
/* Sortier-Dropdown - Minimalistisches Design */
.sort-dropdown {
    position: absolute;
    top: 55px;
    right: 20px;
    z-index: 100;
    background-color: #252525;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    width: 320px;
    max-width: 90vw;
    overflow: hidden;
    display: none;
    opacity: 0;
    transform: translateY(-10px);
    transition: opacity 0.25s ease, transform 0.25s ease;
}

.sort-dropdown.active {
    display: block;
    opacity: 1;
    transform: translateY(0);
}

/* Schlichter Header */
.sort-dropdown-header {
    padding: 16px 20px;
    border-bottom: 1px solid #333;
}

.sort-dropdown-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #fff;
}

.sort-dropdown-content {
    padding: 20px;
}

.sort-option-group {
    margin-bottom: 24px;
}

.sort-option-group:last-child {
    margin-bottom: 0;
}

/* Schlichte Überschriften */
.sort-option-group h5 {
    margin: 0 0 12px 0;
    font-size: 0.85rem;
    font-weight: 500;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Minimalistisches Design für Optionen */
.sort-option {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
    padding: 10px 12px;
    border-left: 3px solid transparent;
    transition: all 0.2s ease;
    cursor: pointer;
}

.sort-option:last-child {
    margin-bottom: 0;
}

.sort-option:hover {
    background-color: #2e2e2e;
    border-left-color: #444;
}

/* Aktive Option hervorheben */
.sort-option.selected,
.sort-option:has(input[type="radio"]:checked) {
    background-color: #2e2e2e;
    border-left-color: var(--accent-primary);
}

/* Verstecken der Radio-Buttons und Ersetzen durch Check-Icons */
.sort-option input[type="radio"] {
    display: none;
}

/* Labels mit Checkmark für ausgewählte Optionen */
.sort-option label {
    display: flex;
    align-items: center;
    width: 100%;
    margin: 0;
    cursor: pointer;
    font-size: 0.95rem;
    color: #ddd;
    transition: color 0.2s ease;
    position: relative;
    padding-left: 26px;
}

.sort-option label:before {
    content: "";
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    border: 1px solid #555;
    border-radius: 3px;
    transition: all 0.2s ease;
}

/* Checkmark für ausgewählte Option */
.sort-option input[type="radio"]:checked + label:before {
    background-color: var(--accent-primary);
    border-color: var(--accent-primary);
}

.sort-option input[type="radio"]:checked + label:after {
    content: "✓";
    position: absolute;
    left: 4px;
    top: 46%;
    transform: translateY(-50%);
    color: white;
    font-size: 12px;
}

.sort-option:hover label {
    color: #fff;
}

/* Schlichter Footer */
.sort-dropdown-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 15px 20px;
    border-top: 1px solid #333;
    background-color: #222;
}

/* Minimalistischer Primary Button */
#sort-dropdown .btn-primary {
    background-color: var(--accent-primary);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    font-size: 0.9rem;
    transition: background-color 0.2s ease;
}

#sort-dropdown .btn-primary:hover {
    background-color: var(--accent-primary-hover);
}

/* Minimalistischer Secondary Button */
#sort-dropdown .btn-secondary {
    background-color: transparent;
    color: #ddd;
    border: 1px solid #444;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 400;
    font-size: 0.9rem;
    transition: background-color 0.2s ease, border-color 0.2s ease;
}

#sort-dropdown .btn-secondary:hover {
    background-color: #2a2a2a;
    border-color: #555;
    color: #fff;
}

/* Responsive Anpassungen */
@media (max-width: 768px) {
    .sort-dropdown {
        right: 10px;
        width: calc(100% - 20px);
    }
}
</style>

<!-- CSS für die Album-Navigation -->
<link rel="stylesheet" href="/public/css/album-navigation.css">

<script src="/public/js/main.js"></script>
<script src="/public/album-edit.js"></script>
<script src="/public/js/fullscreen-viewer.js"></script>
<script src="/public/js/trash-handler.js"></script>
<script src="/public/js/album-navigation.js"></script>
</body>
</html>

