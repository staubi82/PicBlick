<?php
session_start();

// Benötigte Datenbankklassen einbinden
require_once __DIR__ . '/lib/database.php';

$error = '';
$success = '';

// Prüfen, ob wir auf finalize redirected wurden (erfolgreiche DB-Einrichtung)
if (isset($_GET['step']) && $_GET['step'] === 'finalize') {
    $dbType = isset($_SESSION['db_type']) ? $_SESSION['db_type'] : 'sqlite';
    
    switch ($dbType) {
        case 'sqlite':
            $success = "SQLite-Datenbank wurde erfolgreich eingerichtet.";
            break;
        case 'pdo_sqlite':
            $success = "PDO SQLite-Datenbank wurde erfolgreich eingerichtet.";
            break;
        case 'mysql':
            $success = "MySQL-Datenbank (mysqli) wurde erfolgreich eingerichtet.";
            break;
        case 'pdo_mysql':
            $success = "PDO MySQL-Datenbank wurde erfolgreich eingerichtet.";
            break;
        default:
            $success = "Datenbank wurde erfolgreich eingerichtet.";
    }
    
    // Konfiguration in config.php speichern
    $configPath = __DIR__ . '/app/config.php';
    if (file_exists($configPath)) {
        $configContent = file_get_contents($configPath);
        
        // DB_TYPE anpassen
        $configContent = preg_replace('/define\(\'DB_TYPE\',\s*\'.*?\'\);/', "define('DB_TYPE', '$dbType');", $configContent);
        
        // Bei MySQL auch die anderen Einstellungen aktualisieren
        if ($dbType === 'mysql' || $dbType === 'pdo_mysql') {
            $dbHost = isset($_SESSION['db_host']) ? $_SESSION['db_host'] : 'localhost';
            $dbPort = isset($_SESSION['db_port']) ? $_SESSION['db_port'] : '3306';
            $dbName = isset($_SESSION['db_name']) ? $_SESSION['db_name'] : 'picblick';
            $dbUser = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : '';
            $dbPass = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : '';
            
            $configContent = preg_replace('/define\(\'DB_HOST\',\s*\'.*?\'\);/', "define('DB_HOST', '$dbHost');", $configContent);
            $configContent = preg_replace('/define\(\'DB_PORT\',\s*\'.*?\'\);/', "define('DB_PORT', '$dbPort');", $configContent);
            $configContent = preg_replace('/define\(\'DB_NAME\',\s*\'.*?\'\);/', "define('DB_NAME', '$dbName');", $configContent);
            $configContent = preg_replace('/define\(\'DB_USER\',\s*\'.*?\'\);/', "define('DB_USER', '$dbUser');", $configContent);
            $configContent = preg_replace('/define\(\'DB_PASS\',\s*\'.*?\'\);/', "define('DB_PASS', '$dbPass');", $configContent);
        }
        
        // Konfiguration speichern
        file_put_contents($configPath, $configContent);
        $success .= " Die Konfiguration wurde in config.php gespeichert.";
    }
    
    // Nach der Finalisierung die Session-Variablen löschen
    unset($_SESSION['db_type']);
    unset($_SESSION['db_host']);
    unset($_SESSION['db_port']);
    unset($_SESSION['db_name']);
    unset($_SESSION['db_user']);
    unset($_SESSION['db_pass']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['db_type'])) {
    $dbType = $_POST['db_type'];
    
    if ($dbType === 'mysql' || $dbType === 'pdo_mysql') {
        // MySQL-Optionen (mysqli oder PDO)
        $dbHost = isset($_POST['db_host']) ? trim($_POST['db_host']) : 'localhost';
        $dbPort = isset($_POST['db_port']) ? trim($_POST['db_port']) : '3306';
        $dbName = isset($_POST['db_name']) ? trim($_POST['db_name']) : '';
        $dbUser = isset($_POST['db_user']) ? trim($_POST['db_user']) : '';
        $dbPass = isset($_POST['db_pass']) ? $_POST['db_pass'] : '';

        $_SESSION['db_type'] = $dbType;
        $_SESSION['db_host'] = $dbHost;
        $_SESSION['db_port'] = $dbPort;
        $_SESSION['db_name'] = $dbName;
        $_SESSION['db_user'] = $dbUser;
        $_SESSION['db_pass'] = $dbPass;

        if ($dbType === 'mysql') {
            // Prüfe ob mysqli-Erweiterung verfügbar ist
            if (!extension_loaded('mysqli')) {
                $error = "MySQL-Treiber (mysqli) wird nicht unterstützt: Die mysqli-Erweiterung ist nicht installiert. Bitte verwenden Sie SQLite, PDO MySQL oder installieren Sie die mysqli-Erweiterung.";
            } else {
                // Host und Port extrahieren (für Abwärtskompatibilität)
                $host = trim($dbHost);
                $port = trim($dbPort);

                // Falls jemand versehentlich "host:port" im Host-Feld eingibt
                if (strpos($host, ':') !== false) {
                    list($host, $customPort) = explode(':', $host, 2);
                    // Wenn ein Port in der Host-Angabe gefunden wurde, diesen verwenden
                    if (is_numeric($customPort)) {
                        $port = $customPort;
                    }
                }

                // Prüfe MySQL-Verbindung vor Weiterleitung
                $mysqli = @new mysqli($host, $dbUser, $dbPass, $dbName, $port);
                if ($mysqli->connect_error) {
                    $error = "MySQL-Verbindungsfehler: " . $mysqli->connect_error .
                             "<br>Server: $host, Port: $port, Datenbank: $dbName";
                } else {
                    $mysqli->close();
                    
                    // Datenbankschema mit unserer Datenbankklasse importieren
                    try {
                        // DB_* Konstanten für temporäre Verwendung definieren
                        define('DB_TYPE', 'mysql');
                        define('DB_HOST', $host);
                        define('DB_PORT', $port);
                        define('DB_NAME', $dbName);
                        define('DB_USER', $dbUser);
                        define('DB_PASS', $dbPass);
                        
                        // Temporäre Datenbank-Instanz erstellen
                        $db = new MySQLDatabase();
                        
                        // Schema-Datei importieren (MySQL-Version)
                        $schemaFile = __DIR__ . '/lib/mysql_schema.sql';
                        if (file_exists($schemaFile)) {
                            $db->importSQL($schemaFile);
                        }
                        
                        $db->close();
                        header('Location: setup.php?step=finalize');
                        exit;
                    } catch (Exception $e) {
                        $error = "Fehler beim Initialisieren des Datenbankschemas: " . $e->getMessage();
                    }
                }
            }
        } else {
            // pdo_mysql
            if (!extension_loaded('pdo_mysql')) {
                $error = "PDO MySQL-Treiber wird nicht unterstützt: Die pdo_mysql-Erweiterung ist nicht installiert. Bitte verwenden Sie SQLite, mysqli oder installieren Sie die pdo_mysql-Erweiterung.";
            } else {
                // Host und Port extrahieren (für Abwärtskompatibilität)
                $host = trim($dbHost);
                $port = trim($dbPort);

                // Falls jemand versehentlich "host:port" im Host-Feld eingibt
                if (strpos($host, ':') !== false) {
                    list($host, $customPort) = explode(':', $host, 2);
                    // Wenn ein Port in der Host-Angabe gefunden wurde, diesen verwenden
                    if (is_numeric($customPort)) {
                        $port = $customPort;
                    }
                }

                // Prüfe PDO MySQL-Verbindung vor Weiterleitung
                try {
                    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbName . ';charset=utf8mb4';
                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ];
                    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
                    $pdo = null; // PDO-Verbindung schließen
                    
                    // Datenbankschema mit unserer Datenbankklasse importieren
                    try {
                        // DB_* Konstanten für temporäre Verwendung definieren
                        define('DB_TYPE', 'pdo_mysql');
                        define('DB_HOST', $host);
                        define('DB_PORT', $port);
                        define('DB_NAME', $dbName);
                        define('DB_USER', $dbUser);
                        define('DB_PASS', $dbPass);
                        
                        // Temporäre Datenbank-Instanz erstellen
                        $db = new PDOMySQLDatabase();
                        
                        // Schema-Datei importieren (MySQL-Version)
                        $schemaFile = __DIR__ . '/lib/mysql_schema.sql';
                        if (file_exists($schemaFile)) {
                            $db->importSQL($schemaFile);
                        }
                        
                        $db->close();
                        header('Location: setup.php?step=finalize');
                        exit;
                    } catch (Exception $e) {
                        $error = "Fehler beim Initialisieren des Datenbankschemas: " . $e->getMessage();
                    }
                } catch (PDOException $e) {
                    $error = "PDO MySQL-Verbindungsfehler: " . $e->getMessage() .
                             "<br>DSN: mysql:host=$host;port=$port;dbname=$dbName";
                }
            }
        }
    } else if ($dbType === 'sqlite') {
        // Traditionelles SQLite
        $_SESSION['db_type'] = 'sqlite';
        
        // Prüfe ob SQLite3-Erweiterung verfügbar ist
        if (!extension_loaded('sqlite3')) {
            $error = "SQLite wird nicht unterstützt: Die SQLite3-Erweiterung ist nicht installiert. Bitte verwenden Sie PDO SQLite oder MySQL.";
        } else {
            try {
                // Test, ob wir eine SQLite-Datenbank erstellen können
                $dbPath = __DIR__ . '/data/gallery.db';
                if (!file_exists(dirname($dbPath))) {
                    mkdir(dirname($dbPath), 0755, true);
                }
                $db = new SQLite3($dbPath);
                $db->close();
                
                // Datenbankschema mit unserer Datenbankklasse importieren
                try {
                    // DB_* Konstanten für temporäre Verwendung definieren
                    define('DB_TYPE', 'sqlite');
                    define('DB_PATH', $dbPath);
                    
                    // Temporäre Datenbank-Instanz erstellen
                    $sqliteDb = new SQLiteDatabase();
                    
                    // Schema-Datei importieren
                    $schemaFile = __DIR__ . '/lib/schema.sql';
                    if (file_exists($schemaFile)) {
                        $sqliteDb->importSQL($schemaFile);
                    }
                    
                    $sqliteDb->close();
                    header('Location: setup.php?step=finalize');
                    exit;
                } catch (Exception $e) {
                    $error = "Fehler beim Initialisieren des Datenbankschemas: " . $e->getMessage();
                }
            } catch (Exception $e) {
                $error = "SQLite-Fehler: " . $e->getMessage();
            }
        }
    } else if ($dbType === 'pdo_sqlite') {
        // PDO SQLite
        $_SESSION['db_type'] = 'pdo_sqlite';
        
        // Prüfe ob PDO SQLite-Erweiterung verfügbar ist
        if (!extension_loaded('pdo_sqlite')) {
            $error = "PDO SQLite wird nicht unterstützt: Die pdo_sqlite-Erweiterung ist nicht installiert. Bitte verwenden Sie SQLite3 oder MySQL.";
        } else {
            try {
                // Test, ob wir eine PDO SQLite-Datenbank erstellen können
                $dbPath = __DIR__ . '/data/gallery.db';
                if (!file_exists(dirname($dbPath))) {
                    mkdir(dirname($dbPath), 0755, true);
                }
                $dsn = 'sqlite:' . $dbPath;
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ];
                $db = new PDO($dsn, null, null, $options);
                $db = null; // Verbindung schließen
                
                // Datenbankschema mit unserer Datenbankklasse importieren
                try {
                    // DB_* Konstanten für temporäre Verwendung definieren
                    define('DB_TYPE', 'pdo_sqlite');
                    define('DB_PATH', $dbPath);
                    
                    // Temporäre Datenbank-Instanz erstellen
                    $pdoDb = new PDOSQLiteDatabase();
                    
                    // Schema-Datei importieren
                    $schemaFile = __DIR__ . '/lib/schema.sql';
                    if (file_exists($schemaFile)) {
                        $pdoDb->importSQL($schemaFile);
                    }
                    
                    $pdoDb->close();
                    header('Location: setup.php?step=finalize');
                    exit;
                } catch (Exception $e) {
                    $error = "Fehler beim Initialisieren des Datenbankschemas: " . $e->getMessage();
                }
            } catch (PDOException $e) {
                $error = "PDO SQLite-Fehler: " . $e->getMessage();
            }
        }
    } else {
        $error = "Ungültiger Datenbanktyp ausgewählt.";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Fotogalerie - Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #121212;
            color: #e0e0e0;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2 {
            color: #6200ee;
        }
        .step {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #1e1e1e;
            border-radius: 8px;
        }
        label {
            display: block;
            margin: 10px 0 5px;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border-radius: 4px;
            border: 1px solid #444;
            background-color: #2a2a2a;
            color: #e0e0e0;
        }
        button {
            margin-top: 15px;
            padding: 10px 20px;
            background-color: #6200ee;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #5000c5;
        }
        .error {
            color: #cf6679;
            background-color: rgba(207, 102, 121, 0.1);
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .success {
            color: #03dac6;
            background-color: rgba(3, 218, 198, 0.1);
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .actions {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h1>Fotogalerie - Setup</h1>
    <div class="step">
        <h2>Datenbank auswählen</h2>
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <div class="actions">
                <p>Sie können jetzt:</p>
                <ul>
                    <li><a href="index.php" style="color: #03dac6;">Zur Startseite gehen</a></li>
                    <li><a href="public/login.php" style="color: #03dac6;">Sich einloggen</a></li>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" action="setup.php">
            <label>
                <input type="radio" name="db_type" value="sqlite" <?php if (!isset($_POST['db_type']) || $_POST['db_type'] === 'sqlite') echo 'checked'; ?>> SQLite - SQLite3 (Standard, einfach)
            </label>
            <label>
                <input type="radio" name="db_type" value="pdo_sqlite" <?php if (isset($_POST['db_type']) && $_POST['db_type'] === 'pdo_sqlite') echo 'checked'; ?>> SQLite - PDO (Alternative wenn SQLite3 nicht funktioniert)
            </label>
            <label>
                <input type="radio" name="db_type" value="mysql" <?php if (isset($_POST['db_type']) && $_POST['db_type'] === 'mysql') echo 'checked'; ?>> MySQL - mysqli (klassische MySQL-Erweiterung)
            </label>
            <label>
                <input type="radio" name="db_type" value="pdo_mysql" <?php if (isset($_POST['db_type']) && $_POST['db_type'] === 'pdo_mysql') echo 'checked'; ?>> MySQL - PDO (für externe Server empfohlen)
            </label>
            <div id="mysql-settings" style="display:none; margin-top:10px;">
                <label for="db_host">Server:</label>
                <input type="text" id="db_host" name="db_host" value="<?php echo isset($_POST['db_host']) ? htmlspecialchars($_POST['db_host']) : 'localhost'; ?>" />
                <label for="db_port">Port:</label>
                <input type="text" id="db_port" name="db_port" value="<?php echo isset($_POST['db_port']) ? htmlspecialchars($_POST['db_port']) : '3306'; ?>" />
                <label for="db_name">Datenbankname:</label>
                <input type="text" id="db_name" name="db_name" value="<?php echo isset($_POST['db_name']) ? htmlspecialchars($_POST['db_name']) : 'picblick'; ?>" />
                <label for="db_user">Benutzer:</label>
                <input type="text" id="db_user" name="db_user" value="<?php echo isset($_POST['db_user']) ? htmlspecialchars($_POST['db_user']) : ''; ?>" />
                <label for="db_pass">Passwort:</label>
                <input type="password" id="db_pass" name="db_pass" />
            </div>
            <button type="submit">Weiter</button>
        </form>
    </div>
    <script>
        document.querySelectorAll('input[name="db_type"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                document.getElementById('mysql-settings').style.display =
                    (this.value === 'mysql' || this.value === 'pdo_mysql') ? 'block' : 'none';
            });
        });
        window.addEventListener('load', function() {
            var selected = document.querySelector('input[name="db_type"]:checked').value;
            document.getElementById('mysql-settings').style.display =
                (selected === 'mysql' || selected === 'pdo_mysql') ? 'block' : 'none';
        });
    </script>
</body>
</html>