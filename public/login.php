<?php
/**
 * Fotogalerie - Login-Seite
 *
 * Ermöglicht die Anmeldung registrierter Benutzer
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

// Formular wurde abgeschickt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Bitte geben Sie Benutzername und Passwort ein.';
    } else {
        $result = $auth->login($username, $password);
        
        if ($result) {
            // Erfolgreich angemeldet
            header('Location: index.php');
            exit;
        } else {
            $error = 'Ungültiger Benutzername oder Passwort.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anmelden - Fotogalerie</title>
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/header.css">
</head>
<body>
    <?php
    // Aktive Seite für die Navigation
    $activePage = 'login';
    
    // Header einbinden
    require_once __DIR__ . '/includes/header.php';
    ?>

    <main>
        <div class="container">
            <section>
                <div class="form-container">
                    <h2>Anmelden</h2>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['expired']) && $_GET['expired'] == 1): ?>
                        <div class="alert alert-warning">
                            Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['registered']) && $_GET['registered'] == 1): ?>
                        <div class="alert alert-success">
                            Registrierung erfolgreich. Bitte melden Sie sich an.
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['message']) && $_GET['message'] == 'account_deleted'): ?>
                        <div class="alert alert-info">
                            Ihr Account wurde erfolgreich gelöscht. Alle Daten wurden entfernt.
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="username">Benutzername</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required autofocus>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Passwort</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Anmelden</button>
                            <a href="/public/register.php">Konto erstellen</a>
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