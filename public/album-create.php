<?php
/**
 * Fotogalerie - Neues Album erstellen
 *
 * Ermöglicht die Erstellung neuer Alben
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
    header('Location: login.php');
    exit;
}

// Aktueller Benutzer
$currentUser = $auth->getCurrentUser();
$error = '';
$success = '';

// Datenbank initialisieren
$db = Database::getInstance();

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

// Prüfen, ob ein übergeordnetes Album-ID übergeben wurde (für Unteralbum)
$parentId = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : 0;
$parentAlbum = null;

// Wenn ein übergeordnetes Album angegeben ist und die Spalte existiert, prüfen ob es existiert
if ($hasParentColumn && $parentId > 0) {
    // Übergeordnetes Album abrufen
    $parentAlbum = $db->fetchOne(
        "SELECT * FROM albums WHERE id = :id AND deleted_at IS NULL",
        ['id' => $parentId]
    );
    
    // Prüfen, ob übergeordnetes Album existiert und Benutzer Zugriffsrechte hat
    if (!$parentAlbum || $parentAlbum['user_id'] != $currentUser['id']) {
        $error = 'Das übergeordnete Album existiert nicht oder Sie haben keine Zugriffsrechte.';
        $parentAlbum = null;
        $parentId = 0;
    }
} else if (!$hasParentColumn && $parentId > 0) {
    // Wenn die Spalte nicht existiert, aber ein parent_id Parameter übergeben wurde,
    // ignorieren wir diesen und erstellen ein normales Album
    $error = 'Die Unteralben-Funktion ist noch nicht aktiviert. Bitte führen Sie zuerst die Setup-Datei aus.';
    $parentAlbum = null;
    $parentId = 0;
}

// Formular wurde abgeschickt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $albumName = trim($_POST['album_name'] ?? '');
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    
    // Validierung
    if (empty($albumName)) {
        $error = 'Bitte geben Sie einen Namen für das Album ein.';
    } else {
        // Datenbank initialisieren
        $db = Database::getInstance();
        
        // Prüfen, ob Album mit diesem Namen bereits existiert
        $existingAlbum = $db->fetchOne(
            "SELECT id FROM albums WHERE user_id = :user_id AND name = :name AND deleted_at IS NULL",
            [
                'user_id' => $currentUser['id'],
                'name' => $albumName
            ]
        );
        
        if ($existingAlbum) {
            $error = 'Ein Album mit diesem Namen existiert bereits.';
        } else {
            // Sicheren Pfad für das Album erstellen
            $albumPath = 'user_' . $currentUser['id'] . '/' . preg_replace('/[^a-z0-9_-]/i', '_', strtolower($albumName)) . '_' . uniqid();
            
            // Album in die Datenbank einfügen
            $albumData = [
                'name' => $albumName,
                'user_id' => $currentUser['id'],
                'path' => $albumPath,
                'is_public' => $isPublic,
                'description' => trim($_POST['album_description'] ?? '')
            ];
            
            // Wenn es ein Unteralbum ist und die Spalte existiert, parent_album_id hinzufügen
            if ($hasParentColumn && $parentId > 0) {
                $albumData['parent_album_id'] = $parentId;
                
                // Wenn das übergeordnete Album privat ist, muss das Unteralbum auch privat sein
                if (!$parentAlbum['is_public']) {
                    $albumData['is_public'] = 0;
                }
            }
            
            $albumId = $db->insert('albums', $albumData);
            
            if ($albumId) {
                // Verzeichnis für das Album erstellen
                $albumDir = USERS_PATH . '/' . $albumPath;
                if (!file_exists($albumDir)) {
                    mkdir($albumDir, 0755, true);
                }
                
                // Erfolgsseite mit Weiterleitung anzeigen
                echo '<!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta http-equiv="refresh" content="1;url=index.php?nocache=' . time() . '">
                    <title>Album erstellt</title>
                    <link rel="stylesheet" href="/public/css/style.css">
                </head>
                <body>
                    <div style="text-align: center; margin-top: 50px;">
                        <h2>Album "' . htmlspecialchars($albumName) . '" wurde erfolgreich erstellt!</h2>
                        <p>Sie werden in einer Sekunde weitergeleitet...</p>
                        <p><a href="index.php?nocache=' . time() . '">Falls die Weiterleitung nicht funktioniert, klicken Sie hier</a></p>
                    </div>
                </body>
                </html>';
                exit;
            } else {
                $error = 'Fehler beim Erstellen des Albums. Bitte versuchen Sie es erneut.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $parentAlbum ? 'Neues Unteralbum - ' . htmlspecialchars($parentAlbum['name']) : 'Neues Album'; ?> - Fotogalerie</title>
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Fotogalerie</h1>
            <nav>
                <ul>
                    <li><a href="/public/index.php">Alben</a></li>
                    <li><a href="/public/favorites.php">Favoriten</a></li>
                    <li><a href="/public/upload.php">Upload</a></li>
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle"><?php echo htmlspecialchars($currentUser['username']); ?></a>
                        <ul class="dropdown-menu">
                            <li><a href="/public/profile.php">Profil</a></li>
                            <li><a href="/app/auth/logout.php">Abmelden</a></li>
                        </ul>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <section>
                <div class="form-container">
                    <h2><?php echo $parentAlbum ? 'Neues Unteralbum in "' . htmlspecialchars($parentAlbum['name']) . '" erstellen' : 'Neues Album erstellen'; ?></h2>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                       <?php if ($hasParentColumn && $parentAlbum): ?>
                       <div class="form-group">
                           <label>Übergeordnetes Album</label>
                           <div class="static-field"><?php echo htmlspecialchars($parentAlbum['name']); ?></div>
                           <input type="hidden" name="parent_id" value="<?php echo $parentId; ?>">
                       </div>
                       <?php endif; ?>

                       <div class="form-group">
                           <label for="album_name"><?php echo ($hasParentColumn && $parentAlbum) ? 'Unteralbum-Name' : 'Album-Name'; ?></label>
                           <input type="text" id="album_name" name="album_name" required autofocus>
                       </div>
                       
                       <div class="form-group">
                           <label for="album_description">Album-Beschreibung (optional)</label>
                           <textarea id="album_description" name="album_description" rows="3" placeholder="Beschreiben Sie dieses Album"></textarea>
                       </div>
                       
                       <div class="form-group checkbox-group">
                           <input type="checkbox" id="is_public" name="is_public" <?php echo ($hasParentColumn && $parentAlbum && !$parentAlbum['is_public']) ? 'disabled' : ''; ?>>
                           <label for="is_public">Öffentlich (für alle sichtbar)</label>
                           <?php if ($hasParentColumn && $parentAlbum && !$parentAlbum['is_public']): ?>
                           <p class="hint">Das übergeordnete Album ist privat, daher muss dieses Unteralbum ebenfalls privat sein.</p>
                           <?php endif; ?>
                       </div>
                       
                       <div class="form-actions">
                           <button type="submit" class="btn btn-primary"><?php echo ($hasParentColumn && $parentAlbum) ? 'Unteralbum erstellen' : 'Album erstellen'; ?></button>
                           <?php if ($hasParentColumn && $parentAlbum): ?>
                           <a href="/public/album.php?id=<?php echo $parentId; ?>" class="btn">Abbrechen</a>
                           <?php else: ?>
                           <a href="/public/index.php" class="btn">Abbrechen</a>
                           <?php endif; ?>
                       </div>
                   </form>
                </div>
            </section>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Fotogalerie</p>
        </div>
    </footer>

    <script src="/public/js/main.js"></script>
</body>
</html>

<style>
.static-field {
    background-color: rgba(255, 255, 255, 0.1);
    padding: 8px 12px;
    border-radius: 4px;
    color: #ccc;
    margin-bottom: 10px;
}

.hint {
    font-size: 0.9em;
    color: #999;
    margin-top: 5px;
}
</style>