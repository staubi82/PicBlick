-- SQLite Schema für Fotogalerie

-- Benutzer-Tabelle
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL
);

-- Alben-Tabelle
CREATE TABLE IF NOT EXISTS albums (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    path TEXT NOT NULL,
    description TEXT DEFAULT NULL,
    is_public INTEGER DEFAULT 0,  -- 0 = privat, 1 = öffentlich
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE(user_id, name)
);

-- Bilder-Tabelle (erweitert für Videos)
CREATE TABLE IF NOT EXISTS images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT NOT NULL,
    album_id INTEGER NOT NULL,
    media_type TEXT DEFAULT 'image', -- 'image' oder 'video'
    is_public INTEGER DEFAULT 0,  -- 0 = privat, 1 = öffentlich
    upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (album_id) REFERENCES albums(id)
);

-- Favoriten-Tabelle
CREATE TABLE IF NOT EXISTS favorites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    image_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (image_id) REFERENCES images(id),
    UNIQUE(user_id, image_id)
);

-- Metadaten-Tabelle für Bilder (EXIF-Daten)
CREATE TABLE IF NOT EXISTS image_metadata (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    image_id INTEGER NOT NULL,
    camera_make TEXT,
    camera_model TEXT,
    exposure TEXT,
    aperture TEXT,
    iso TEXT,
    focal_length TEXT,
    date_taken DATETIME,
    gps_latitude REAL,
    gps_longitude REAL,
    FOREIGN KEY (image_id) REFERENCES images(id)
);

-- Indizes
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_albums_user_id ON albums(user_id);
CREATE INDEX IF NOT EXISTS idx_albums_is_public ON albums(is_public);
CREATE INDEX IF NOT EXISTS idx_images_album_id ON images(album_id);
CREATE INDEX IF NOT EXISTS idx_images_is_public ON images(is_public);
CREATE INDEX IF NOT EXISTS idx_favorites_user_id ON favorites(user_id);
CREATE INDEX IF NOT EXISTS idx_favorites_image_id ON favorites(image_id);

-- Views für aktive (nicht gelöschte) Einträge
CREATE VIEW IF NOT EXISTS active_users AS
    SELECT * FROM users WHERE deleted_at IS NULL;

CREATE VIEW IF NOT EXISTS active_albums AS
    SELECT * FROM albums WHERE deleted_at IS NULL;

CREATE VIEW IF NOT EXISTS active_images AS
    SELECT * FROM images WHERE deleted_at IS NULL;

-- View für öffentliche Alben
CREATE VIEW IF NOT EXISTS public_albums AS
    SELECT * FROM albums WHERE is_public = 1 AND deleted_at IS NULL;

-- View für öffentliche Bilder
CREATE VIEW IF NOT EXISTS public_images AS
    SELECT * FROM images WHERE is_public = 1 AND deleted_at IS NULL;