<?php
/**
 * Database Class
 * 
 * Ein SQLite3-Wrapper für vereinfachte Datenbankoperationen
 */
class Database 
{
    private static $instance = null;
    private $db;
    private $inTransaction = false;
    private $lastUniqueError = null;
    
    /**
     * Konstruktor - stellt Verbindung zur Datenbank her
     */
    private function __construct()
    {
        if (!file_exists(dirname(DB_PATH))) {
            mkdir(dirname(DB_PATH), 0755, true);
        }
        
        $this->db = new SQLite3(DB_PATH);
        $this->db->enableExceptions(true);
        $this->db->exec('PRAGMA foreign_keys = ON');
        
        // Für bessere Leistung
        $this->db->exec('PRAGMA journal_mode = WAL');
        $this->db->exec('PRAGMA synchronous = NORMAL');
    }
    
    /**
     * Singleton-Pattern: Gibt Datenbankinstanz zurück
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Bereitet ein SQL-Statement vor
     */
    public function prepare($query)
    {
        return $this->db->prepare($query);
    }
    
    /**
     * Führt ein SQL-Statement aus und gibt das Ergebnisobjekt zurück
     */
    public function query($query)
    {
        return $this->db->query($query);
    }
    
    /**
     * Führt ein SQL-Statement mit Parametern aus
     * 
     * @param string $query Das SQL-Statement
     * @param array $params Assoziatives Array mit Parametern
     * @return bool|SQLite3Result Ergebnis der Ausführung
     */
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
        
        try {
            return $stmt->execute();
        } catch (SQLite3Exception $e) {
            // Check für "UNIQUE constraint failed" Fehler
            if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                // Speichere die Fehlernachricht für später
                $this->lastUniqueError = $e->getMessage();
                error_log("UNIQUE constraint erkannt: " . $e->getMessage());
                return false;
            }
            
            // Andere Fehler weiterwerfen
            throw $e;
        }
    }
    
    /**
     * Führt ein SELECT-Statement aus und gibt alle Ergebnisse als Array zurück
     */
    public function fetchAll($query, $params = [])
    {
        $result = $this->execute($query, $params);
        $rows = [];
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        
        return $rows;
    }
    
    /**
     * Führt ein SELECT-Statement aus und gibt eine einzelne Zeile zurück
     */
    public function fetchOne($query, $params = [])
    {
        $result = $this->execute($query, $params);
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    /**
     * Führt ein SELECT-Statement aus und gibt einen einzelnen Wert zurück
     */
    public function fetchValue($query, $params = [])
    {
        $result = $this->execute($query, $params);
        $row = $result->fetchArray(SQLITE3_NUM);
        return $row ? $row[0] : null;
    }
    
    /**
     * Fügt einen Datensatz in eine Tabelle ein
     * 
     * @param string $table Tabellenname
     * @param array $data Assoziatives Array mit Spaltennamen und Werten
     * @return int Die ID des eingefügten Datensatzes
     */
    public function insert($table, $data)
    {
        $columns = array_keys($data);
        $placeholders = array_map(function ($col) {
            return ":$col";
        }, $columns);
        
        $columnsStr = implode(', ', $columns);
        $placeholdersStr = implode(', ', $placeholders);
        
        $query = "INSERT INTO $table ($columnsStr) VALUES ($placeholdersStr)";
        
        $result = $this->execute($query, $data);
        
        // Wenn execute false zurückgibt, ist ein UNIQUE constraint-Fehler aufgetreten
        if ($result === false && $this->lastUniqueError !== null) {
            error_log("UNIQUE constraint in $table: " . $this->lastUniqueError);
            
            // Prüfen, ob es sich um einen Fehler für die albums-Tabelle handelt
            if ($table === 'albums' && isset($data['user_id']) && isset($data['name'])) {
                // Versuchen, den vorhandenen Eintrag zu finden
                // Suche nach existierenden Einträgen (auch gelöschten)
                $existingRecord = $this->fetchOne(
                    "SELECT id, deleted_at FROM $table WHERE user_id = :user_id AND name = :name",
                    ['user_id' => $data['user_id'], 'name' => $data['name']]
                );
                
                // Wenn ein bereits existierender Eintrag gefunden wurde, dessen ID zurückgeben
                if ($existingRecord) {
                    error_log("Album existiert bereits, gebe existierende ID zurück: " . $existingRecord['id']);
                    return $existingRecord['id'];
                }
                
                // Zusätzliche Suche basierend auf dem Pfad, falls vorhanden
                if (isset($data['path'])) {
                    $existingRecord = $this->fetchOne(
                        "SELECT id FROM $table WHERE user_id = :user_id AND path = :path AND deleted_at IS NULL",
                        ['user_id' => $data['user_id'], 'path' => $data['path']]
                    );
                    
                    if ($existingRecord) {
                        error_log("Album mit gleichem Pfad existiert bereits, gebe existierende ID zurück: " . $existingRecord['id']);
                        return $existingRecord['id'];
                    }
                }
            }
            
            // Fehler werfen, falls keine passende Behandlung gefunden wurde
            error_log("Keine Behandlung für UNIQUE constraint gefunden: " . $this->lastUniqueError);
            throw new SQLite3Exception($this->lastUniqueError);
        } else if ($result) {
            // Erfolgreicher Insert, ID zurückgeben
            return $this->db->lastInsertRowID();
        }
        
        // Sollte nicht erreicht werden, aber für den Fall eines unbehandelten Fehlers
        throw new Exception("Unbekannter Fehler beim Einfügen in $table");
    }
    
    /**
     * Aktualisiert einen Datensatz in einer Tabelle
     */
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
    
    /**
     * Löscht einen Datensatz aus einer Tabelle
     */
    public function delete($table, $where, $params = [])
    {
        $query = "DELETE FROM $table WHERE $where";
        $this->execute($query, $params);
        return $this->db->changes();
    }
    
    /**
     * Führt Soft-Delete durch (setzt deleted_at)
     */
    public function softDelete($table, $where, $params = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $query = "UPDATE $table SET deleted_at = :timestamp WHERE $where";
        $params['timestamp'] = $timestamp;
        
        $this->execute($query, $params);
        return $this->db->changes();
    }
    
    /**
     * Startet eine Transaktion
     */
    public function beginTransaction()
    {
        if (!$this->inTransaction) {
            $this->db->exec('BEGIN TRANSACTION');
            $this->inTransaction = true;
            return true;
        }
        return false;
    }
    
    /**
     * Bestätigt eine Transaktion
     */
    public function commit()
    {
        if ($this->inTransaction) {
            $this->db->exec('COMMIT');
            $this->inTransaction = false;
            return true;
        }
        return false;
    }
    
    /**
     * Macht eine Transaktion rückgängig
     */
    public function rollback()
    {
        if ($this->inTransaction) {
            $this->db->exec('ROLLBACK');
            $this->inTransaction = false;
            return true;
        }
        return false;
    }
    
    /**
     * Schließt die Datenbankverbindung
     */
    public function close()
    {
        if ($this->db) {
            $this->db->close();
            self::$instance = null;
        }
    }
    
    /**
     * Gibt den letzten Fehler zurück
     */
    public function lastError()
    {
        return $this->db->lastErrorMsg();
    }
    
    /**
     * Escape-Funktion für Strings
     */
    public function escapeString($string)
    {
        return $this->db->escapeString($string);
    }
    
    /**
     * Führt ein SQL-Skript aus (z.B. für Schema-Erstellung)
     */
    public function importSQL($sqlFile)
    {
        if (!file_exists($sqlFile)) {
            throw new Exception("SQL-Datei nicht gefunden: $sqlFile");
        }
        
        $sql = file_get_contents($sqlFile);
        $statements = explode(';', $sql);
        
        $this->beginTransaction();
        
        try {
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $this->db->exec($statement);
                }
            }
            $this->commit();
            return true;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
}