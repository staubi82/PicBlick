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
            $albumId = $db->insert('albums', [
                'name' => $albumName,
                'user_id' => $currentUser['id'],
                'path' => $albumPath,
                'is_public' => $isPublic,
                'description' => trim($_POST['album_description'] ?? '')
            ]);
            
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
    <title>Neues Album - Fotogalerie</title>
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
                    <h2>Neues Album erstellen</h2>
                    
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
                       <div class="form-group">
                           <label for="album_name">Album-Name</label>
                           <input type="text" id="album_name" name="album_name" required autofocus>
                       </div>
                       
                       <div class="form-group">
                           <label for="album_description">Album-Beschreibung (optional)</label>
                           <textarea id="album_description" name="album_description" rows="3" placeholder="Beschreiben Sie dieses Album"></textarea>
                       </div>
                       
                       <div class="form-group checkbox-group">
                           <input type="checkbox" id="is_public" name="is_public">
                           <label for="is_public">Öffentlich (für alle sichtbar)</label>
                       </div>
                       
                       <div class="form-actions">
                           <button type="submit" class="btn btn-primary">Album erstellen</button>
                           <a href="/public/index.php" class="btn">Abbrechen</a>
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