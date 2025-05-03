-- Fügt eine Profilbild-Spalte zur users Tabelle hinzu
ALTER TABLE users ADD COLUMN profile_image TEXT DEFAULT NULL;