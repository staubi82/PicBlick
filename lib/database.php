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
        
        return $stmt->execute();
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
        $this->execute($query, $data);
        
        return $this->db->lastInsertRowID();
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