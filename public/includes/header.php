<?php
/**
 * PicBlick - Header-Template
 * 
 * Enthält den Standard-Header mit Navigation
 */

// Prüfen, ob die Variable für den aktiven Menüpunkt gesetzt ist
$activePage = $activePage ?? '';
// CSS für den Header sollte im <head> der Seite eingebunden werden
// <link rel="stylesheet" href="/public/css/header.css">

// Profilbild-URL vorbereiten, falls es noch nicht gesetzt wurde und Benutzer angemeldet ist
if (!isset($profileImageUrl) && isset($currentUser) && $currentUser) {
    $profileImagesDir = defined('STORAGE_PATH') ? STORAGE_PATH . '/profile_images' : dirname(__DIR__, 2) . '/storage/profile_images';
    
    if (!empty($currentUser['profile_image']) &&
        file_exists($profileImagesDir . '/' . $currentUser['profile_image'])) {
        // Benutzer hat ein eigenes Profilbild
        $profileImageUrl = '/storage/profile_images/' . $currentUser['profile_image'];
    } else {
        // Standard-Avatar verwenden (falls vorhanden) oder generischen Avatar erstellen
        $defaultProfileImg = __DIR__ . '/../img/default-profile.jpg';
        if (file_exists($defaultProfileImg)) {
            $profileImageUrl = '/public/img/default-profile.jpg';
        } else {
            // Einen farbigen Buchstaben-Avatar erstellen
            $profileImageUrl = 'data:image/svg+xml,' . urlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="200" height="200"><rect width="100" height="100" fill="#6200ee"/><text x="50" y="60" font-family="Arial" font-size="45" fill="white" text-anchor="middle">' . strtoupper(substr($currentUser['username'], 0, 1)) . '</text></svg>');
        }
    }
}
?>
<header>
    <div class="container">
        <div class="logo-container">
            <svg class="logo-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M19 22H5a2 2 0 01-2-2V6a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2v14a2 2 0 01-2 2zM12 8a4 4 0 100 8 4 4 0 000-8z"/>
            </svg>
            <h1><a href="/public/index.php">PicBlick</a></h1>
        </div>
        
        <nav>
            <ul>
                <li>
                    <a href="/public/index.php" <?php echo $activePage === 'home' ? 'class="active"' : ''; ?>>
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M3 5v14h18V5H3zm16 12H5V7h14v10z"/>
                            <path d="M7 9h10v2H7zm0 4h5v2H7z"/>
                        </svg>
                        Alben
                    </a>
                </li>
                <li>
                    <a href="/public/users.php" <?php echo $activePage === 'users' ? 'class="active"' : ''; ?>>
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-3.3 0-6.3.3-9 .9V18c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-3.1c-2.7-.6-5.7-.9-9-.9z"/>
                        </svg>
                        Benutzer
                    </a>
                </li>
                <?php if (isset($currentUser) && $currentUser): ?>
                    <li>
                        <a href="/public/favorites.php" <?php echo $activePage === 'favorites' ? 'class="active"' : ''; ?>>
                            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M12 17.8l-6.4 3.8 1.7-7-5.7-4.9 7.5-.6L12 2.4l2.9 6.6 7.5.6-5.7 4.9 1.7 7z"/>
                            </svg>
                            Favoriten
                        </a>
                    </li>
                    <li>
                        <a href="/public/upload.php" <?php echo $activePage === 'upload' ? 'class="active"' : ''; ?>>
                            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z"/>
                            </svg>
                            Upload
                        </a>
                    </li>
                    <li>
                        <a href="/public/profile.php" <?php echo $activePage === 'profile' ? 'class="active"' : ''; ?>>
                            <?php if (isset($profileImageUrl)): ?>
                                <div class="nav-profile-img">
                                    <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="">
                                </div>
                            <?php else: ?>
                                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-3.3 0-6.3.3-9 .9V18c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-3.1c-2.7-.6-5.7-.9-9-.9z"/>
                                </svg>
                            <?php endif; ?>
                            <span class="nav-username"><?php echo htmlspecialchars($currentUser['username']); ?></span>
                        </a>
                    </li>
                    <li class="logout-item">
                        <a href="/app/auth/logout.php" title="Abmelden">
                            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M17 7l-1.4 1.4L17.6 10H9v2h8.6l-2 2L17 15.5l4.5-4.5L17 7zM5 5h7V3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h7v-2H5V5z"/>
                            </svg>
                        </a>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="/public/login.php" <?php echo $activePage === 'login' ? 'class="active"' : ''; ?>>
                            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M11 7L9.6 8.4l2.6 2.6H3v2h9.2l-2.6 2.6L11 17l5-5-5-5zm9 12h-8v2h8c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-8v2h8v14z"/>
                            </svg>
                            Anmelden
                        </a>
                    </li>
                    <li>
                        <a href="/public/register.php" <?php echo $activePage === 'register' ? 'class="active"' : ''; ?>>
                            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M15 12c2.2 0 4-1.8 4-4s-1.8-4-4-4-4 1.8-4 4 1.8 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.7 0-8 1.3-8 4v2h16v-2c0-2.7-5.3-4-8-4z"/>
                            </svg>
                            Registrieren
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>