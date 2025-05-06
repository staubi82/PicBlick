-- Hinzufügen der mime_type-Spalte zur images-Tabelle
ALTER TABLE images ADD COLUMN mime_type TEXT DEFAULT NULL;

-- Index für schnellere Suche nach Medientypen
CREATE INDEX IF NOT EXISTS idx_images_media_type ON images(media_type);
CREATE INDEX IF NOT EXISTS idx_images_mime_type ON images(mime_type);