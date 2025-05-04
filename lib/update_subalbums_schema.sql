-- Füge parent_album_id-Spalte zur albums Tabelle hinzu
ALTER TABLE albums ADD COLUMN parent_album_id INTEGER DEFAULT NULL REFERENCES albums(id);

-- Erstelle einen Index für schnellere Zugriffe
CREATE INDEX IF NOT EXISTS idx_albums_parent ON albums(parent_album_id);

-- Update der Beschreibung für die Tabelle
PRAGMA user_version = 3;