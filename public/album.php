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

// Bilder abrufen
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
                    <?php if ($isOwner): ?>
                    <button id="edit-album-toggle" class="btn-menu-dots" aria-label="Album bearbeiten" title="Album bearbeiten">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="1"></circle>
                            <circle cx="12" cy="5" r="1"></circle>
                            <circle cx="12" cy="19" r="1"></circle>
                        </svg>
                    </button>
                    <?php endif; ?>
                    <div class="album-header">
                        <?php if ($album['cover_image']): ?>
                            <div class="album-cover">
                                <img src="../storage/thumbs/<?php echo rtrim($album['path'], '/') . '/' . basename($album['cover_image']); ?>"
                                     alt="Album-Titelbild">
                            </div>
                        <?php endif; ?>
                        <div class="album-info">
                            <h2 id="album-title" data-editable="false" class="it-style-title">
                                <?php echo htmlspecialchars($album['name']); ?>
                                <?php if ($album['is_public']): ?>
                                    <span class="public-badge">Öffentlich</span>
                                <?php endif; ?>
                            </h2>
                            <p id="album-description" data-editable="false"><?php echo htmlspecialchars($album['description'] ?? 'Keine Beschreibung'); ?></p>
                            <p>Erstellt von <strong><?php echo htmlspecialchars($owner['username']); ?></strong> am <?php echo isset($album['created_at']) ? date('d.m.Y', strtotime($album['created_at'])) : '01.01.2003'; ?></p>
                            <!-- Versteckte Input-Felder für die ursprünglichen Werte -->
                            <input type="hidden" id="original-album-title" value="<?php echo htmlspecialchars($album['name']); ?>">
                            <input type="hidden" id="original-album-description" value="<?php echo htmlspecialchars($album['description'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Horizontale Trennlinie zwischen Titel und Bildern -->
                <hr class="album-divider">

                <!-- Album-Editor-Modal (per Default ausgeblendet) -->
                <div id="album-edit-modal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Album bearbeiten</h3>
                            <button class="modal-close" id="close-album-modal" aria-label="Schließen">×</button>
                        </div>
                        <div class="modal-body">
                            <!-- Formular-Elemente -->
                            <div class="edit-section">
                                <div class="form-group">
                                    <label for="edit-album-title">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                        Titel
                                    </label>
                                    <input type="text" id="edit-album-title" value="<?php echo htmlspecialchars($album['name']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit-album-description">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="21" y1="6" x2="3" y2="6"></line>
                                            <line x1="15" y1="12" x2="3" y2="12"></line>
                                            <line x1="17" y1="18" x2="3" y2="18"></line>
                                        </svg>
                                        Beschreibung
                                    </label>
                                    <textarea id="edit-album-description" rows="3"><?php echo htmlspecialchars($album['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit-album-public" class="toggle-label">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                        Sichtbarkeit
                                    </label>
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="edit-album-public" <?php echo $album['is_public'] ? 'checked' : ''; ?>>
                                        <label for="edit-album-public" class="toggle-slider"></label>
                                        <span class="toggle-label-text"><?php echo $album['is_public'] ? 'Öffentlich' : 'Privat'; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Titelbild-Auswahl -->
                            <div class="edit-section">
                                <h4>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                    Titelbild
                                </h4>
                                
                                <!-- Aktuelle Titelbild-Vorschau -->
                                <div class="current-cover-preview">
                                    <div class="cover-image-wrapper">
                                        <?php if ($album['cover_image']): ?>
                                            <img src="../storage/thumbs/<?php echo rtrim($album['path'], '/') . '/' . basename($album['cover_image']); ?>" alt="Aktuelles Titelbild">
                                        <?php else: ?>
                                            <div class="no-cover">Kein Titelbild</div>
                                        <?php endif; ?>
                                    </div>
                                    <button id="select-cover-image" class="btn-action">
                                        Bild auswählen
                                    </button>
                                </div>
                                
                                <!-- Miniaturansicht für Titelbild-Auswahl (wird via JS angezeigt) -->
                                <div id="cover-selection-container" class="cover-selection" style="display: none;">
                                    <div class="cover-selection-header">
                                        <h5>Wählen Sie ein Titelbild</h5>
                                        <button id="close-cover-selection" class="btn-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                            </svg>
                                        </button>
                                    </div>
                                    <div id="cover-thumbnails" class="cover-thumbnails">
                                        <!-- Hier werden die Miniaturansichten via JavaScript eingefügt -->
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Danger Zone -->
                            <div class="edit-section danger-zone">
                                <h4>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                        <line x1="12" y1="9" x2="12" y2="13"></line>
                                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                                    </svg>
                                    Gefahrenbereich
                                </h4>
                                <div class="danger-description">
                                    <p>Diese Aktion kann nicht rückgängig gemacht werden. Das Album und alle enthaltenen Bilder werden dauerhaft vom Server gelöscht.</p>
                                </div>
                                <button id="delete-album" class="btn-danger" data-album-id="<?php echo $albumId; ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                    </svg>
                                    Album permanent löschen
                                </button>
                            </div>
                            
                            <!-- Formular-Aktionen -->
                            <div class="form-actions">
                                <button id="save-album-changes" class="btn-primary" data-album-id="<?php echo $albumId; ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                        <polyline points="7 3 7 8 15 8"></polyline>
                                    </svg>
                                    Speichern
                                </button>
                                <button id="cancel-album-changes" class="btn-secondary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                    Abbrechen
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (empty($images)): ?>
                    <div class="empty-state">
                        <p>Dieses Album enthält noch keine Bilder.</p>
                    </div>
                <?php else: ?>
                    <div class="cover-selection-info" id="cover-selection-info">
                        <p>Wählen Sie ein Bild als neues Titelbild für dieses Album.</p>
                    </div>
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
                                <img src="<?php echo $thumbnailUrl; ?>"
                                     alt="<?php echo htmlspecialchars($image['filename']); ?>"
                                     onerror="this.onerror=null;this.src='/public/img/default-album.jpg';"
                                     style="--rotation: <?php echo $image['rotation'] ?? 0; ?>deg;">
                                
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
</style>

<script src="/public/js/main.js"></script>
<script src="/public/album-edit.js"></script>
<script src="/public/js/fullscreen-viewer.js"></script>
<script src="/public/js/trash-handler.js"></script>
</body>
</html>

