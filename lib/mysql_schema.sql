-- MySQL Schema für Fotogalerie

-- Benutzer-Tabelle
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL
);

-- Alben-Tabelle
CREATE TABLE IF NOT EXISTS albums (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    path VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    is_public TINYINT DEFAULT 0,  -- 0 = privat, 1 = öffentlich
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE(user_id, name)
);

-- Bilder-Tabelle (erweitert für Videos)
CREATE TABLE IF NOT EXISTS images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    album_id INT NOT NULL,
    media_type VARCHAR(10) DEFAULT 'image', -- 'image' oder 'video'
    is_public TINYINT DEFAULT 0,  -- 0 = privat, 1 = öffentlich
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (album_id) REFERENCES albums(id)
);

-- Favoriten-Tabelle
CREATE TABLE IF NOT EXISTS favorites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    image_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (image_id) REFERENCES images(id),
    UNIQUE(user_id, image_id)
);

-- Metadaten-Tabelle für Bilder (EXIF-Daten)
CREATE TABLE IF NOT EXISTS image_metadata (
    id INT PRIMARY KEY AUTO_INCREMENT,
    image_id INT NOT NULL,
    camera_make VARCHAR(50),
    camera_model VARCHAR(50),
    exposure VARCHAR(20),
    aperture VARCHAR(10),
    iso VARCHAR(10),
    focal_length VARCHAR(10),
    date_taken DATETIME,
    gps_latitude FLOAT,
    gps_longitude FLOAT,
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
CREATE OR REPLACE VIEW active_users AS
SELECT * FROM users WHERE deleted_at IS NULL;

CREATE OR REPLACE VIEW active_albums AS
SELECT * FROM albums WHERE deleted_at IS NULL;

CREATE OR REPLACE VIEW active_images AS
SELECT * FROM images WHERE deleted_at IS NULL;

-- View für öffentliche Alben
CREATE OR REPLACE VIEW public_albums AS
SELECT * FROM albums WHERE is_public = 1 AND deleted_at IS NULL;

-- View für öffentliche Bilder
CREATE OR REPLACE VIEW public_images AS
SELECT * FROM images WHERE is_public = 1 AND deleted_at IS NULL;