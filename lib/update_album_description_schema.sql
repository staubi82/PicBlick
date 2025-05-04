-- Fügt die description-Spalte zur albums Tabelle hinzu
ALTER TABLE albums ADD COLUMN description TEXT DEFAULT NULL;

-- Update der Datenbankversion
PRAGMA user_version = 4;