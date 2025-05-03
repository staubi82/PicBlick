<?php
/**
 * Fotogalerie - Profilseite
 *
 * Zeigt das Benutzerprofil und ermöglicht das Hochladen eines Profilbildes
 * sowie das vollständige Löschen des Benutzerkontos
 */

// Konfiguration und Bibliotheken laden
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../app/auth/auth.php';
require_once __DIR__ . '/../lib/imaging.php';

// Authentifizierung initialisieren
$auth = new Auth();
$auth->checkSession();

// Prüfen, ob Benutzer angemeldet ist
if (!$auth->isLoggedIn()) {
    header('Location: login.php?redirect=profile.php');
    exit;
}

// Aktuellen Benutzer abrufen
$currentUser = $auth->getCurrentUser();

// Datenbank initialisieren
$db = Database::getInstance();

// Profilaktualisierung
$success = '';
$error = '';

// Profilbild-Ordner
$profileImagesDir = STORAGE_PATH . '/profile_images';
if (!is_dir($profileImagesDir)) {
    mkdir($profileImagesDir, 0755, true);
}

// Rekursive Funktion zum Löschen eines Verzeichnisses
function deleteDir($dirPath) {
    if (!is_dir($dirPath)) {
        return;
    }
    $files = array_diff(scandir($dirPath), array('.', '..'));
    foreach ($files as $file) {
        $path = $dirPath . '/' . $file;
        is_dir($path) ? deleteDir($path) : unlink($path);
    }
    return rmdir($dirPath);
}

// Profilbild hochladen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $file = $_FILES['profile_image'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        // Prüfen, ob es sich um ein Bild handelt
        $fileType = $file['type'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (in_array($fileType, $allowedTypes)) {
            // Dateigröße prüfen (max. 5 MB)
            if ($file['size'] <= 5 * 1024 * 1024) {
                // Eindeutigen Dateinamen erstellen
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newFilename = 'profile_' . $currentUser['id'] . '_' . uniqid() . '.' . $extension;
                $targetPath = $profileImagesDir . '/' . $newFilename;
                
                // Altes Profilbild löschen, falls vorhanden
                if (!empty($currentUser['profile_image'])) {
                    $oldImagePath = $profileImagesDir . '/' . $currentUser['profile_image'];
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                
                // Neues Bild hochladen
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // Bild verkleinern und optimieren
                    // Thumbnail erstellen und als Profilbild verwenden
                    Imaging::createThumbnail($targetPath, $targetPath, 250, 250);
                    
                    // Datenbankaktualisierung
                    $query = "UPDATE users SET profile_image = :profile_image WHERE id = :id";
                    $params = [
                        'profile_image' => $newFilename,
                        'id' => $currentUser['id']
                    ];
                    
                    $db->execute($query, $params);
                    
                    // Benutzer neu laden
                    $currentUser = $db->fetchOne(
                        "SELECT * FROM users WHERE id = :id",
                        ['id' => $currentUser['id']]
                    );
                    
                    // Session aktualisieren
                    $_SESSION['user'] = $currentUser;
                    
                    $success = 'Profilbild wurde erfolgreich aktualisiert.';
                } else {
                    $error = 'Beim Hochladen des Bildes ist ein Fehler aufgetreten.';
                }
            } else {
                $error = 'Die Datei ist zu groß. Maximale Größe: 5 MB.';
            }
        } else {
            $error = 'Bitte laden Sie nur Bilder hoch (JPG, PNG oder GIF).';
        }
    } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
        // Fehlercode auswerten
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error = 'Die Datei ist zu groß.';
                break;
            default:
                $error = 'Beim Hochladen ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.';
        }
    }
}

// Passwort ändern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword)) {
        $error = 'Bitte geben Sie Ihr aktuelles Passwort ein.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Die Passwörter stimmen nicht überein.';
    } else {
        // Prüfen, ob das aktuelle Passwort korrekt ist
        if ($auth->verifyPassword($currentUser['id'], $currentPassword)) {
            // Passwort aktualisieren
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $query = "UPDATE users SET password = :password WHERE id = :id";
            $params = [
                'password' => $hashedPassword,
                'id' => $currentUser['id']
            ];
            
            $db->execute($query, $params);
            
            $success = 'Ihr Passwort wurde erfolgreich aktualisiert.';
        } else {
            $error = 'Das aktuelle Passwort ist nicht korrekt.';
        }
    }
}

// Account löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_account') {
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $deleteConfirmation = $_POST['delete_confirmation'] ?? '';
    
    if (empty($confirmPassword)) {
        $error = 'Bitte geben Sie Ihr Passwort ein, um den Account zu löschen.';
    } elseif ($deleteConfirmation !== 'DELETE') {
        $error = 'Bitte geben Sie "DELETE" ein, um die Löschung zu bestätigen.';
    } else {
        // Prüfen, ob das Passwort korrekt ist
        if ($auth->verifyPassword($currentUser['id'], $confirmPassword)) {
            // 1. Alle Favoriten des Benutzers löschen
            $db->execute(
                "DELETE FROM favorites WHERE user_id = :user_id",
                ['user_id' => $currentUser['id']]
            );
            
            // 2. Alle Thumbnails der Bilder des Benutzers löschen
            $userImages = $db->fetchAll(
                "SELECT i.id, i.filename FROM images i
                JOIN albums a ON i.album_id = a.id
                WHERE a.user_id = :user_id",
                ['user_id' => $currentUser['id']]
            );
            
            foreach ($userImages as $image) {
                $thumbPath = THUMBS_PATH . '/' . $image['filename'];
                if (file_exists($thumbPath)) {
                    unlink($thumbPath);
                }
            }
            
            // 2b. Thumbnail-Ordner des Benutzers löschen, falls vorhanden
            $userThumbsDir = THUMBS_PATH . '/user_' . $currentUser['id'];
            if (is_dir($userThumbsDir)) {
                deleteDir($userThumbsDir);
            }
            
            // 3. Alle Bilder in den Alben des Benutzers komplett löschen
            $db->execute(
                "DELETE FROM images
                WHERE album_id IN (SELECT id FROM albums WHERE user_id = :user_id)",
                ['user_id' => $currentUser['id']]
            );
            
            // 4. Alle Alben des Benutzers komplett löschen
            $db->execute(
                "DELETE FROM albums WHERE user_id = :user_id",
                ['user_id' => $currentUser['id']]
            );
            
            // 5. Profilbild des Benutzers löschen, falls vorhanden
            if (!empty($currentUser['profile_image'])) {
                $profileImagePath = STORAGE_PATH . '/profile_images/' . $currentUser['profile_image'];
                if (file_exists($profileImagePath)) {
                    unlink($profileImagePath);
                }
            }
            
            // 5. Benutzerverzeichnis löschen - rekursiv alle Dateien entfernen
            $userDir = USERS_PATH . '/user_' . $currentUser['id'];
            if (is_dir($userDir)) {
                // Verzeichnis löschen
                deleteDir($userDir);
            }
            
            // 6. Benutzer vollständig aus der Datenbank löschen
            $db->execute(
                "DELETE FROM users WHERE id = :user_id",
                ['user_id' => $currentUser['id']]
            );
            
            // 7. Session beenden und ausloggen
            session_unset();
            session_destroy();
            
            // 8. Zur Login-Seite weiterleiten
            header('Location: login.php?message=account_deleted');
            exit;
        } else {
            $error = 'Das eingegebene Passwort ist nicht korrekt.';
        }
    }
}

// Statistiken abrufen - verbesserte Abfragen für akkurate Zahlen
$albumCount = $db->fetchValue(
    "SELECT COUNT(*) FROM albums
     WHERE user_id = :user_id
     AND deleted_at IS NULL",
    ['user_id' => $currentUser['id']]
);

$imageCount = $db->fetchValue(
    "SELECT COUNT(*) FROM images i
     JOIN albums a ON i.album_id = a.id
     WHERE a.user_id = :user_id
     AND i.deleted_at IS NULL
     AND a.deleted_at IS NULL",
    ['user_id' => $currentUser['id']]
);

$favoriteCount = $db->fetchValue(
    "SELECT COUNT(*) FROM favorites f
     JOIN images i ON f.image_id = i.id
     JOIN albums a ON i.album_id = a.id
     WHERE f.user_id = :user_id
     AND i.deleted_at IS NULL
     AND a.deleted_at IS NULL",
    ['user_id' => $currentUser['id']]
);

// Profilbild-URL vorbereiten
if (!empty($currentUser['profile_image']) &&
    file_exists(STORAGE_PATH . '/profile_images/' . $currentUser['profile_image'])) {
    // Benutzer hat ein eigenes Profilbild
    $profileImageUrl = '/storage/profile_images/' . $currentUser['profile_image'];
} else {
    // Standard-Avatar verwenden (falls vorhanden) oder generischen Avatar erstellen
    $defaultProfileImg = __DIR__ . '/img/default-profile.jpg';
    if (file_exists($defaultProfileImg)) {
        $profileImageUrl = '/public/img/default-profile.jpg';
    } else {
        // Einen farbigen Buchstaben-Avatar erstellen
        $profileImageUrl = 'data:image/svg+xml,' . urlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="200" height="200"><rect width="100" height="100" fill="#6200ee"/><text x="50" y="60" font-family="Arial" font-size="45" fill="white" text-anchor="middle">' . strtoupper(substr($currentUser['username'], 0, 1)) . '</text></svg>');
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mein Profil - Fotogalerie</title>
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/profile-modern.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
</head>
<body class="profile-page">
    <?php
    // Aktive Seite für die Navigation
    $activePage = 'profile';
    
    // Header einbinden
    require_once __DIR__ . '/includes/header.php';
    ?>

    <main class="profile-main">
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 10px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 10px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-hero">
            <div class="profile-hero-content">
                <div class="profile-avatar-wrapper">
                    <div class="profile-avatar">
                        <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Profilbild">
                    </div>
                    <form method="post" enctype="multipart/form-data" class="profile-avatar-upload">
                        <input type="file" name="profile_image" id="profile-image-input" accept="image/*">
                        <label for="profile-image-input">
                            <span class="avatar-upload-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7"></path><path d="M16 5h6v6"></path><path d="M8 12l9-9"></path></svg>
                            </span>
                        </label>
                        <button type="submit" class="avatar-upload-btn">Hochladen</button>
                    </form>
                </div>
                <div class="profile-hero-details">
                    <h1><?php echo htmlspecialchars($currentUser['username']); ?></h1>
                    <p class="profile-email"><?php echo htmlspecialchars($currentUser['email']); ?></p>
                    <div class="profile-stats-summary">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $albumCount; ?></span>
                            <span class="stat-label">Alben</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $imageCount; ?></span>
                            <span class="stat-label">Bilder</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $favoriteCount; ?></span>
                            <span class="stat-label">Favoriten</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-content">
            <div class="profile-section profile-example-section">
                <h3>Profilbild-Beispiel</h3>
                <div class="profile-example-content">
                    <div class="example-image-container">
                        <img src="/public/img/default-profile.jpg" alt="Beispiel eines Profilbilds" class="profile-example-image">
                    </div>
                    <div class="example-description">
                        <p>So könnte Ihr Profilbild aussehen. Laden Sie ein quadratisches Bild hoch für optimale Darstellung.</p>
                        <ul class="example-tips">
                            <li>Empfohlene Größe: mindestens 250 x 250 Pixel</li>
                            <li>Maximale Dateigröße: 5 MB</li>
                            <li>Unterstützte Formate: JPG, PNG, GIF</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="profile-section profile-membership-card">
                <div class="membership-header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"></path></svg>
                    <h3>Mitgliedschaft</h3>
                </div>
                <div class="membership-body">
                    <p>Mitglied seit <strong><?php echo date('d.m.Y', strtotime($currentUser['created_at'])); ?></strong></p>
                    <p>Status: <strong>Aktiv</strong></p>
                </div>
            </div>
            
            <div class="profile-section password-section">
                <h3>Passwort ändern</h3>
                <form method="post" class="password-form">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <div class="password-field">
                            <input type="password" id="current_password" name="current_password" required placeholder=" ">
                            <label for="current_password">Aktuelles Passwort</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="password-field">
                            <input type="password" id="new_password" name="new_password" required placeholder=" ">
                            <label for="new_password">Neues Passwort</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="password-field">
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder=" ">
                            <label for="confirm_password">Passwort bestätigen</label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-update-password">Passwort aktualisieren</button>
                    </div>
                </form>
            </div>
            
            <div class="profile-section danger-section">
                <div class="section-header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"></polygon><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <h3>Gefahrenzone</h3>
                </div>
                <div class="danger-body">
                    <p>Wenn Sie Ihren Account löschen, werden alle Ihre Daten (Alben, Bilder, Ordner, Thumbnails, Profilbild) unwiderruflich entfernt. Diese Aktion kann nicht rückgängig gemacht werden, aber Sie können sich danach sofort mit demselben Benutzernamen und derselben E-Mail wieder registrieren.</p>
                    
                    <button type="button" id="show-delete-modal" class="btn-delete-account">Account löschen</button>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Modal für Account-Löschung -->
    <div id="delete-account-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Account löschen</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Sie sind dabei, Ihren Account unwiderruflich zu löschen. Alle Ihre Daten, Alben, Bilder, Thumbnails und Profilbilder werden vollständig entfernt. Sie können sich nach dem Löschen sofort mit demselben Benutzernamen und derselben E-Mail wieder registrieren.</p>
                
                <form method="post" class="delete-account-form">
                    <input type="hidden" name="action" value="delete_account">
                    
                    <div class="form-group">
                        <label for="confirm_password_delete">Passwort bestätigen:</label>
                        <input type="password" id="confirm_password_delete" name="confirm_password" required class="modal-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="delete_confirmation">Zur Bestätigung "DELETE" eingeben:</label>
                        <input type="text" id="delete_confirmation" name="delete_confirmation" required class="modal-input">
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-cancel">Abbrechen</button>
                        <button type="submit" class="btn-confirm-delete">Account endgültig löschen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php
    // Footer einbinden
    require_once __DIR__ . '/includes/footer.php';
    ?>

    <script src="/public/js/main.js"></script>
    <script>
        // Modal-Funktionalität für Account-Löschung
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('delete-account-modal');
            const showModalBtn = document.getElementById('show-delete-modal');
            const closeModalBtn = document.querySelector('.close-modal');
            const cancelBtn = document.querySelector('.btn-cancel');
            
            // Modal öffnen
            showModalBtn.addEventListener('click', function() {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden'; // Scrollen verhindern
            });
            
            // Modal schließen (X-Button)
            closeModalBtn.addEventListener('click', function() {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            });
            
            // Modal schließen (Abbrechen-Button)
            cancelBtn.addEventListener('click', function() {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            });
            
            // Modal schließen, wenn außerhalb geklickt wird
            window.addEventListener('click', function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        });
    </script>
</body>
</html>