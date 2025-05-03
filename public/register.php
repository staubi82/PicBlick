<?php
/**
 * Fotogalerie - Registrierungsseite
 *
 * Ermöglicht die Erstellung neuer Benutzerkonten
 */

// Konfiguration und Bibliotheken laden
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../app/auth/auth.php';

// Authentifizierung initialisieren
$auth = new Auth();

// Redirect wenn bereits angemeldet
if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';
$email = '';

// Formular wurde abgeschickt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    // Validierung
    if (empty($username) || empty($email) || empty($password) || empty($passwordConfirm)) {
        $error = 'Alle Felder müssen ausgefüllt werden.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
    } elseif (strlen($password) < 8) {
        $error = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Die Passwörter stimmen nicht überein.';
    } else {
        // Versuche, den Benutzer zu registrieren
        $result = $auth->register($username, $password, $email);
        
        if ($result) {
            // Erfolgreich registriert, weiterleiten zur Login-Seite
            header('Location: login.php?registered=1');
            exit;
        } else {
            $error = 'Der Benutzername oder die E-Mail-Adresse wird bereits verwendet.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrieren - Fotogalerie</title>
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/header.css">
</head>
<body>
    <?php
    // Aktive Seite für die Navigation
    $activePage = 'register';
    
    // Header einbinden
    require_once __DIR__ . '/includes/header.php';
    ?>

    <main>
        <div class="container">
            <section>
                <div class="form-container">
                    <h2>Registrieren</h2>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="username">Benutzername</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required autofocus>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">E-Mail-Adresse</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Passwort (mind. 8 Zeichen)</label>
                            <input type="password" id="password" name="password" minlength="8" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password_confirm">Passwort bestätigen</label>
                            <input type="password" id="password_confirm" name="password_confirm" minlength="8" required>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Registrieren</button>
                            <a href="/public/login.php">Bereits registriert?</a>
                        </div>
                    </form>
                </div>
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