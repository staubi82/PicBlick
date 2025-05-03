-- SQLite Schema Update für Papierkorb-Funktionalität

-- Hinzufügen der Papierkorb-Spalten zur images-Tabelle
ALTER TABLE images ADD COLUMN trash_original_path TEXT DEFAULT NULL;
ALTER TABLE images ADD COLUMN trash_thumbnail_path TEXT DEFAULT NULL;
ALTER TABLE images ADD COLUMN trash_expiry DATETIME DEFAULT NULL;

-- View für Bilder im Papierkorb
CREATE VIEW IF NOT EXISTS trash_images AS
    SELECT * FROM images 
    WHERE deleted_at IS NOT NULL 
    AND (trash_original_path IS NOT NULL OR trash_thumbnail_path IS NOT NULL)
    AND trash_expiry > datetime('now');