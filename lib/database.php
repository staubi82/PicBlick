<?php
/**
 * Database Abstraction Class
 * Unterstützt SQLite3, MySQL (mysqli) und PDO-Implementierungen
 */
abstract class Database
{
    protected $inTransaction = false;

    abstract public function prepare($query);
    abstract public function execute($query, $params = []);
    abstract public function fetchAll($query, $params = []);
    abstract public function fetchOne($query, $params = []);
    abstract public function fetchValue($query, $params = []);
    abstract public function insert($table, $data);
    abstract public function update($table, $data, $where, $whereParams = []);
    abstract public function delete($table, $where, $params = []);
    abstract public function softDelete($table, $where, $params = []);
    abstract public function beginTransaction();
    abstract public function commit();
    abstract public function rollback();
    abstract public function close();
    abstract public function lastError();
    abstract public function escapeString($string);
    abstract public function getTableInfo($table);
    abstract public function importSQL($filename);

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            if (defined('DB_TYPE')) {
                if (DB_TYPE === 'mysql') {
                    // Prüfe, ob mysqli verfügbar ist
                    if (extension_loaded('mysqli')) {
                        $instance = new MySQLDatabase();
                    } else if (extension_loaded('pdo_mysql')) {
                        // Fallback auf PDO, wenn mysqli nicht verfügbar ist
                        $instance = new PDOMySQLDatabase();
                    } else {
                        throw new Exception("Weder mysqli noch pdo_mysql Erweiterungen sind installiert. MySQL kann nicht verwendet werden.");
                    }
                } else if (DB_TYPE === 'pdo_mysql') {
                    // Explizite Verwendung von PDO MySQL
                    if (!extension_loaded('pdo_mysql')) {
                        throw new Exception("PDO MySQL-Treiber ist nicht installiert.");
                    }
                    $instance = new PDOMySQLDatabase();
                } else if (DB_TYPE === 'pdo_sqlite') {
                    // Explizite Verwendung von PDO SQLite
                    if (!extension_loaded('pdo_sqlite')) {
                        throw new Exception("PDO SQLite-Treiber ist nicht installiert.");
                    }
                    $instance = new PDOSQLiteDatabase();
                } else {
                    // Standardmäßig SQLite verwenden
                    if (extension_loaded('sqlite3')) {
                        $instance = new SQLiteDatabase();
                    } else if (extension_loaded('pdo_sqlite')) {
                        $instance = new PDOSQLiteDatabase();
                    } else {
                        throw new Exception("Weder SQLite3 noch PDO SQLite-Erweiterungen sind installiert. Datenbank kann nicht verwendet werden.");
                    }
                }
            } else {
                // Standardmäßig SQLite verwenden
                if (extension_loaded('sqlite3')) {
                    $instance = new SQLiteDatabase();
                } else if (extension_loaded('pdo_sqlite')) {
                    $instance = new PDOSQLiteDatabase();
                } else {
                    throw new Exception("Weder SQLite3 noch PDO SQLite-Erweiterungen sind installiert. Datenbank kann nicht verwendet werden.");
                }
            }
        }
        return $instance;
    }
}

class SQLiteDatabase extends Database
{
    private $db;

    public function __construct()
    {
        if (!file_exists(dirname(DB_PATH))) {
            mkdir(dirname(DB_PATH), 0755, true);
        }

        $this->db = new SQLite3(DB_PATH);
        $this->db->enableExceptions(true);
        $this->db->exec('PRAGMA foreign_keys = ON');
        $this->db->exec('PRAGMA journal_mode = WAL');
        $this->db->exec('PRAGMA synchronous = NORMAL');
    }

    public function prepare($query)
    {
        return $this->db->prepare($query);
    }

    public function execute($query, $params = [])
    {
        $stmt = $this->db->prepare($query);
        if (!$stmt) {
            throw new Exception("SQL-Fehler: " . $this->db->lastErrorMsg());
        }
        foreach ($params as $key => $value) {
            $paramType = is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT;
            $stmt->bindValue(":$key", $value, $paramType);
        }
        return $stmt->execute();
    }

    public function fetchAll($query, $params = [])
    {
        $result = $this->execute($query, $params);
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function fetchOne($query, $params = [])
    {
        $result = $this->execute($query, $params);
        return $result->fetchArray(SQLITE3_ASSOC);
    }

    public function fetchValue($query, $params = [])
    {
        $result = $this->execute($query, $params);
        $row = $result->fetchArray(SQLITE3_NUM);
        return $row ? $row[0] : null;
    }

    public function insert($table, $data)
    {
        $columns = array_keys($data);
        $placeholders = array_map(function ($col) {
            return ":$col";
        }, $columns);

        $columnsStr = implode(', ', $columns);
        $placeholdersStr = implode(', ', $placeholders);

        $query = "INSERT INTO $table ($columnsStr) VALUES ($placeholdersStr)";
        $this->execute($query, $data);

        return $this->db->lastInsertRowID();
    }

    public function update($table, $data, $where, $whereParams = [])
    {
        $setParts = array_map(function ($col) {
            return "$col = :$col";
        }, array_keys($data));

        $setStr = implode(', ', $setParts);

        $query = "UPDATE $table SET $setStr WHERE $where";
        $params = array_merge($data, $whereParams);

        $this->execute($query, $params);
        return $this->db->changes();
    }

    public function delete($table, $where, $params = [])
    {
        $query = "DELETE FROM $table WHERE $where";
        $this->execute($query, $params);
        return $this->db->changes();
    }

    public function softDelete($table, $where, $params = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $query = "UPDATE $table SET deleted_at = :timestamp WHERE $where";
        $params['timestamp'] = $timestamp;

        $this->execute($query, $params);
        return $this->db->changes();
    }

    public function beginTransaction()
    {
        if (!$this->inTransaction) {
            $this->db->exec('BEGIN TRANSACTION');
            $this->inTransaction = true;
            return true;
        }
        return false;
    }

    public function commit()
    {
        if ($this->inTransaction) {
            $this->db->exec('COMMIT');
            $this->inTransaction = false;
            return true;
        }
        return false;
    }

    public function rollback()
    {
        if ($this->inTransaction) {
            $this->db->exec('ROLLBACK');
            $this->inTransaction = false;
            return true;
        }
        return false;
    }

    public function close()
    {
        if ($this->db) {
            $this->db->close();
        }
    }

    public function lastError()
    {
        return $this->db->lastErrorMsg();
    }

    public function escapeString($string)
    {
        return $this->db->escapeString($string);
    }

    public function getTableInfo($table)
    {
        return $this->fetchAll("PRAGMA table_info($table)");
    }
    
    public function importSQL($filename) {
        if (!file_exists($filename)) {
            throw new Exception("SQL-Datei nicht gefunden: $filename");
        }
        
        $sql = file_get_contents($filename);
        $statements = explode(';', $sql);
        
        // Keine Transaktion verwenden, jedes Statement einzeln ausführen
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                try {
                    $this->db->exec($statement);
                } catch (Exception $e) {
                    throw new Exception("Fehler beim Ausführen des SQL-Statements: " . $e->getMessage() . " (Statement: $statement)");
                }
            }
        }
        
        return true;
    }
}

class MySQLDatabase extends Database
{
    private $db;

    public function __construct()
    {
        // Host und Port extrahieren (für Abwärtskompatibilität)
        $host = trim(DB_HOST);
        $port = trim(DB_PORT);

        // Falls der Host ein Port-Format enthält
        if (strpos($host, ':') !== false) {
            list($host, $customPort) = explode(':', $host, 2);
            // Wenn ein numerischer Port in der Host-Angabe gefunden wurde, diesen verwenden
            if (is_numeric($customPort)) {
                $port = $customPort;
            }
        }

        $this->db = new mysqli($host, DB_USER, DB_PASS, DB_NAME, $port);
        if ($this->db->connect_error) {
            throw new Exception("MySQL-Verbindungsfehler: " . $this->db->connect_error . " (Host: ".DB_HOST.", Port: ".DB_PORT.")");
        }
        $this->db->set_charset('utf8mb4');
    }

    public function prepare($query)
    {
        return $this->db->prepare($query);
    }

    public function execute($query, $params = [])
    {
        $stmt = $this->db->prepare($query);
        if (!$stmt) {
            throw new Exception("MySQL-Fehler: " . $this->db->error);
        }
        if ($params) {
            // Dynamische Bindung der Parameter
            $types = '';
            $values = [];
            foreach ($params as $value) {
                if (is_int($value)) {
                    $types .= 'i';
                } elseif (is_double($value)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
                $values[] = $value;
            }
            $stmt->bind_param($types, ...$values);
        }
        $stmt->execute();
        return $stmt;
    }

    public function fetchAll($query, $params = [])
    {
        $stmt = $this->execute($query, $params);
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function fetchOne($query, $params = [])
    {
        $stmt = $this->execute($query, $params);
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function fetchValue($query, $params = [])
    {
        $stmt = $this->execute($query, $params);
        $result = $stmt->get_result();
        $row = $result->fetch_row();
        return $row ? $row[0] : null;
    }

    public function insert($table, $data)
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $columnsStr = implode(', ', $columns);
        $placeholdersStr = implode(', ', $placeholders);

        $query = "INSERT INTO $table ($columnsStr) VALUES ($placeholdersStr)";
        $this->execute($query, array_values($data));

        return $this->db->insert_id;
    }

    public function update($table, $data, $where, $whereParams = [])
    {
        $setParts = array_map(function ($col) {
            return "$col = ?";
        }, array_keys($data));

        $setStr = implode(', ', $setParts);

        $query = "UPDATE $table SET $setStr WHERE $where";
        $params = array_merge(array_values($data), $whereParams);

        $stmt = $this->execute($query, $params);
        return $stmt->affected_rows;
    }

    public function delete($table, $where, $params = [])
    {
        $query = "DELETE FROM $table WHERE $where";
        $stmt = $this->execute($query, $params);
        return $stmt->affected_rows;
    }

    public function softDelete($table, $where, $params = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $query = "UPDATE $table SET deleted_at = ? WHERE $where";
        $params = array_merge([$timestamp], $params);

        $stmt = $this->execute($query, $params);
        return $stmt->affected_rows;
    }

    public function beginTransaction()
    {
        if (!$this->inTransaction) {
            $this->db->begin_transaction();
            $this->inTransaction = true;
            return true;
        }
        return false;
    }

    public function commit()
    {
        if ($this->inTransaction) {
            $this->db->commit();
            $this->inTransaction = false;
            return true;
        }
        return false;
    }

    public function rollback()
    {
        if ($this->inTransaction) {
            $this->db->rollback();
            $this->inTransaction = false;
            return true;
        }
        return false;
    }

    public function close()
    {
        if ($this->db) {
            $this->db->close();
        }
    }
    
    public function importSQL($filename) {
        if (!file_exists($filename)) {
            throw new Exception("SQL-Datei nicht gefunden: $filename");
        }
        
        $sql = file_get_contents($filename);
        $statements = explode(';', $sql);
        
        // Keine Transaktion verwenden, jedes Statement einzeln ausführen
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                try {
                    $this->db->query($statement);
                } catch (Exception $e) {
                    throw new Exception("Fehler beim Ausführen des SQL-Statements: " . $e->getMessage() . " (Statement: $statement)");
                }
            }
        }
        
        return true;
    }

    public function lastError()
    {
        return $this->db->error;
    }

    public function escapeString($string)
    {
        return $this->db->real_escape_string($string);
    }

    public function getTableInfo($table)
    {
        return $this->fetchAll("SHOW COLUMNS FROM $table");
    }
}

class PDOMySQLDatabase extends Database
{
    private $db;

    public function __construct()
    {
        try {
            // Prüfen, ob ein gültiger Host angegeben wurde
            $host = trim(DB_HOST);
            $port = trim(DB_PORT);

            // Falls der Host ein Port-Format enthält (für Abwärtskompatibilität)
            if (strpos($host, ':') !== false) {
                list($host, $customPort) = explode(':', $host, 2);
                // Wenn ein numerischer Port in der Host-Angabe gefunden wurde, diesen verwenden
                if (is_numeric($customPort)) {
                    $port = $customPort;
                }
            }

            $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            $this->db = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            throw new Exception("PDO MySQL-Verbindungsfehler: " . $e->getMessage() . " (Host: ".DB_HOST.", Port: ".DB_PORT.")");
        }
    }

    public function prepare($query)
    {
        return $this->db->prepare($query);
    }

    public function execute($query, $params = [])
    {
        $stmt = $this->db->prepare($query);
        if (!$stmt) {
            throw new Exception("PDO MySQL-Fehler: " . $this->lastError());
        }

        // Parameter mit benannten Platzhaltern binden
        $namedParams = [];
        foreach ($params as $key => $value) {
            $paramName = is_numeric($key) ? ":param$key" : ":$key";
            $namedParams[$paramName] = $value;
            
            // Type bestimmen und entsprechenden PDO-Parameter setzen
            $type = PDO::PARAM_STR;
            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_BOOL;
            } elseif (is_null($value)) {
                $type = PDO::PARAM_NULL;
            }
            
            $stmt->bindValue($paramName, $value, $type);
        }

        // Query anpassen, wenn Parameter mit numerischen Indizes
        if (count($params) > 0 && array_keys($params) !== range(0, count($params) - 1)) {
            // Benannte Parameter wurden verwendet - keine Anpassung notwendig
        } else {
            // Ersetze ? mit benannten Parametern, wenn numerische Indizes
            $i = 0;
            $query = preg_replace_callback('/\?/', function($matches) use (&$i) {
                return ":param$i++";
            }, $query);
            $stmt = $this->db->prepare($query);
        }

        $stmt->execute($namedParams);
        return $stmt;
    }

    public function fetchAll($query, $params = [])
    {
        $stmt = $this->execute($query, $params);
        return $stmt->fetchAll();
    }

    public function fetchOne($query, $params = [])
    {
        $stmt = $this->execute($query, $params);
        return $stmt->fetch();
    }

    public function fetchValue($query, $params = [])
    {
        $stmt = $this->execute($query, $params);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return $row ? $row[0] : null;
    }

    public function insert($table, $data)
    {
        $columns = array_keys($data);
        $placeholders = array_map(function ($col) {
            return ":$col";
        }, $columns);

        $columnsStr = implode(', ', $columns);
        $placeholdersStr = implode(', ', $placeholders);

        $query = "INSERT INTO $table ($columnsStr) VALUES ($placeholdersStr)";
        $this->execute($query, $data);

        return $this->db->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = [])
    {
        $setParts = array_map(function ($col) {
            return "$col = :$col";
        }, array_keys($data));

        $setStr = implode(', ', $setParts);

        $query = "UPDATE $table SET $setStr WHERE $where";
        $params = array_merge($data, $whereParams);

        $stmt = $this->execute($query, $params);
        return $stmt->rowCount();
    }

    public function delete($table, $where, $params = [])
    {
        $query = "DELETE FROM $table WHERE $where";
        $stmt = $this->execute($query, $params);
        return $stmt->rowCount();
    }

    public function softDelete($table, $where, $params = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $query = "UPDATE $table SET deleted_at = :timestamp WHERE $where";
        $params['timestamp'] = $timestamp;

        $stmt = $this->execute($query, $params);
        return $stmt->rowCount();
    }

    public function beginTransaction()
    {
        if (!$this->inTransaction) {
            $this->db->beginTransaction();
            $this->inTransaction = true;
            return true;
        }
        return false;
    }

    public function commit()
    {
        if ($this->inTransaction) {
            $this->db->commit();
            $this->inTransaction = false;
            return true;
        }
        return false;
    }

    public function rollback()
    {
        if ($this->inTransaction) {
            $this->db->rollBack();
            $this->inTransaction = false;
            return true;
        }
        return false;
    }

    public function close()
    {
        // PDO hat keine explizite close-Methode
        $this->db = null;
    }

    public function lastError()
    {
        $errorInfo = $this->db->errorInfo();
        return $errorInfo[2] ?? 'Unbekannter Fehler';
    }

    public function escapeString($string)
    {
        // PDO verwendet Prepared Statements, aber für Kompatibilität
        return str_replace("'", "''", $string);
    }

    public function getTableInfo($table)
    {
        return $this->fetchAll("SHOW COLUMNS FROM `$table`");
    }
    
    public function importSQL($filename) {
        if (!file_exists($filename)) {
            throw new Exception("SQL-Datei nicht gefunden: $filename");
        }
        
        $sql = file_get_contents($filename);
        $statements = explode(';', $sql);
        
        // Keine Transaktion verwenden, jedes Statement einzeln ausführen
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                try {
                    $this->db->exec($statement);
                } catch (Exception $e) {
                    throw new Exception("Fehler beim Ausführen des SQL-Statements: " . $e->getMessage() . " (Statement: $statement)");
                }
            }
        }
        
        return true;
    }
}

class PDOSQLiteDatabase extends Database
{
    private $db;

    public function __construct()
    {
        try {
            if (!file_exists(dirname(DB_PATH))) {
                mkdir(dirname(DB_PATH), 0755, true);
            }

            $dsn = 'sqlite:' . DB_PATH;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ];
            $this->db = new PDO($dsn, null, null, $options);
            
            // SQLite-spezifische Einstellungen
            $this->db->exec('PRAGMA foreign_keys = ON');
            $this->db->exec('PRAGMA journal_mode = WAL');
            $this->db->exec('PRAGMA synchronous = NORMAL');
        } catch (PDOException $e) {
            throw new Exception("PDO SQLite-Fehler: " . $e->getMessage());
        }
    }

    public function prepare($query)
    {
        return $this->db->prepare($query);
    }

    public function execute($query, $params = [])
    {
        $stmt = $this->db->prepare($query);
        if (!$stmt) {
            throw new Exception("PDO SQLite-Fehler: " . $this->lastError());
        }

        // Benannte Parameter sind in SQLite-Abfragen erforderlich
        $namedParams = [];
        foreach ($params as $key => $value) {
            if (is_numeric($key)) {
                // Nur für numerische Schlüssel, benannte Schlüssel bleiben unverändert
                $namedParams[":param$key"] = $value;
            } else {
                // Bereits benannte Parameter
                $namedParams[":$key"] = $value;
            }
        }

        // Wenn numerische Indizes verwendet wurden, passe Query an
        if (array_keys($params) === range(0, count($params) - 1)) {
            $i = 0;
            $query = preg_replace_callback('/\?/', function($matches) use (&$i) {
                return ":param$i++";
            }, $query);
            $stmt = $this->db->prepare($query);
        }

        $stmt->execute($namedParams);
        return $stmt;
    }

    public function fetchAll($query, $params = [])
    {
        $stmt = $this->execute($query, $params);
        return $stmt->fetchAll();
    }

    public function fetchOne($query, $params = [])
    {
        $stmt = $this->execute($query, $params);
        return $stmt->fetch();
    }

    public function fetchValue($query, $params = [])
    {
        $stmt = $this->execute($query, $params);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return $row ? $row[0] : null;
    }

    public function insert($table, $data)
    {
        $columns = array_keys($data);
        $placeholders = array_map(function ($col) {
            return ":$col";
        }, $columns);

        $columnsStr = implode(', ', $columns);
        $placeholdersStr = implode(', ', $placeholders);

        $query = "INSERT INTO $table ($columnsStr) VALUES ($placeholdersStr)";
        $this->execute($query, $data);

        return $this->db->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = [])
    {
        $setParts = array_map(function ($col) {
            return "$col = :$col";
        }, array_keys($data));

        $setStr = implode(', ', $setParts);

        $query = "UPDATE $table SET $setStr WHERE $where";
        $params = array_merge($data, $whereParams);

        $stmt = $this->execute($query, $params);
        return $stmt->rowCount();
    }

    public function delete($table, $where, $params = [])
    {
        $query = "DELETE FROM $table WHERE $where";
        $stmt = $this->execute($query, $params);
        return $stmt->rowCount();
    }

    public function softDelete($table, $where, $params = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $query = "UPDATE $table SET deleted_at = :timestamp WHERE $where";
        $params['timestamp'] = $timestamp;

        $stmt = $this->execute($query, $params);
        return $stmt->rowCount();
    }

    public function beginTransaction()
    {
        if (!$this->inTransaction) {
            $this->db->beginTransaction();
            $this->inTransaction = true;
            return true;
        }
        return false;
    }

    public function commit()
    {
        if ($this->inTransaction) {
            $this->db->commit();
            $this->inTransaction = false;
            return true;
        }
        return false;
    }

    public function rollback()
    {
        if ($this->inTransaction) {
            $this->db->rollBack();
            $this->inTransaction = false;
            return true;
        }
        return false;
    }

    public function close()
    {
        // PDO hat keine explizite close-Methode
        $this->db = null;
    }

    public function lastError()
    {
        $errorInfo = $this->db->errorInfo();
        return $errorInfo[2] ?? 'Unbekannter Fehler';
    }

    public function escapeString($string)
    {
        // PDO verwendet Prepared Statements, aber für Kompatibilität
        return str_replace("'", "''", $string);
    }

    public function getTableInfo($table)
    {
        return $this->fetchAll("PRAGMA table_info($table)");
    }
    
    public function importSQL($filename) {
        if (!file_exists($filename)) {
            throw new Exception("SQL-Datei nicht gefunden: $filename");
        }
        
        $sql = file_get_contents($filename);
        $statements = explode(';', $sql);
        
        // Keine Transaktion verwenden, jedes Statement einzeln ausführen
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                try {
                    $this->db->exec($statement);
                } catch (Exception $e) {
                    throw new Exception("Fehler beim Ausführen des SQL-Statements: " . $e->getMessage() . " (Statement: $statement)");
                }
            }
        }
        
        return true;
    }
}