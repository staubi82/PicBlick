# PicBlick - Moderne Fotogalerie-Anwendung

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.4-8892BF)
![Lizenz](https://img.shields.io/badge/Lizenz-MIT-green)

## Übersicht

PicBlick ist eine leistungsstarke, selbst-gehostete Fotogalerie-Anwendung, die es Benutzern ermöglicht, Fotos einfach zu organisieren, zu teilen und zu verwalten. Die Anwendung bietet ein modernes Benutzerinterface mit verschiedenen Funktionen zur Bilderverwaltung und -organisation.

### Hauptfunktionen

- 🖼️ **Bilderverwaltung**: Hochladen, Anzeigen, Drehen und Verwalten von Bildern
- 📁 **Alben**: Erstellen und Verwalten von Fotoalben
- ⭐ **Favoriten-System**: Favorisieren von Bildern für schnellen Zugriff
- 🔄 **Automatischer Import**: Automatisches Importieren neuer Bilder aus überwachten Ordnern
- 👤 **Benutzerverwaltung**: Mehrbenutzer-Unterstützung mit individuellen Profilen
- 🖥️ **Modernes UI**: Responsive Benutzeroberfläche mit Vollbild-Viewer
- 🔒 **Sicherheit**: Geschützte Speicherverzeichnisse und Benutzerdaten

## Systemvoraussetzungen

- PHP 8.4 oder höher
- SQLite3-Erweiterung
- GD-Bildverarbeitungserweiterung
- EXIF-Erweiterung
- Webserver (Apache, Nginx, etc.)
- Moderne Webbrowser

## Installation

### 1. PHP 8.4 & Erweiterungen installieren

Debian (Bookworm+) liefert kein PHP 8.4 aus den Standard-Repos. Deshalb erst das SURY-Repo einbinden und dann PHP 8.4 mit den benötigten Erweiterungen installieren:

```bash
sudo apt update
sudo apt install -y ca-certificates apt-transport-https lsb-release wget gnupg

wget -qO- https://packages.sury.org/php/apt.gpg \
  | sudo gpg --dearmor -o /usr/share/keyrings/php.gpg

echo "deb [signed-by=/usr/share/keyrings/php.gpg] \
  https://packages.sury.org/php/ $(lsb_release -sc) main" \
  | sudo tee /etc/apt/sources.list.d/php-sury.list

sudo apt update

sudo apt install -y \
  php8.4 \
  php8.4-fpm \
  php8.4-gd \
  php8.4-exif \
  php8.4-sqlite3

sudo systemctl restart php8.4-fpm
```

### 2. Vorbereitung

```bash
# Repository klonen
git clone https://github.com/staubi82/PicBlick.git
cd PicBlick
```

### 3. Konfiguration anpassen

Bearbeite die Datei `app/config.php` und passe die Einstellungen an deine Umgebung an:

```php
// Datenbank-Konfiguration
define('DB_PATH', __DIR__ . '/../data/fotogalerie.db');

// Admin-Benutzer für Erstinstallation
define('INIT_ADMIN_USERNAME', 'admin');
define('INIT_ADMIN_PASSWORD', 'sicheres-passwort'); // Unbedingt ändern!
define('INIT_ADMIN_EMAIL', 'admin@example.com');
```

### 4. Verzeichnisberechtigungen setzen

Damit der Webserver (PHP-FPM) Schreibrechte auf die PicBlick-Verzeichnisse hat:

```bash
# Owner auf den Web-User ändern (meist www-data bei Debian/Ubuntu)
sudo chown -R www-data:www-data /Pfad/zu/PicBlick

# Verzeichnis- und Datei-Rechte setzen
# Alle Ordner auf 755
sudo find /Pfad/zu/PicBlick -type d -exec chmod 755 {} \;
# Alle Dateien auf 644
sudo find /Pfad/zu/PicBlick -type f -exec chmod 644 {} \;
```

### 5. Setup ausführen

Öffne in deinem Browser:
```
http://deinserver/setup.php
```

Das Setup-Skript führt dich durch den Installationsprozess:
- Überprüfung der Systemanforderungen
- Initialisierung der Datenbank
- Erstellung der Verzeichnisstruktur
- Einrichtung des Administrator-Kontos

### 6. Fertigstellung

Nach Abschluss des Setups kannst du dich mit den Admin-Zugangsdaten anmelden:
- Benutzername: Der in der Konfiguration festgelegte Wert (Standard: `admin`)
- Passwort: Das in der Konfiguration festgelegte Passwort

**Wichtig**: Ändere das Admin-Passwort nach dem ersten Login!

## Verzeichnisstruktur

```
PicBlick/
├── app/                # Anwendungslogik
│   ├── api/            # API-Endpunkte
│   └── auth/           # Authentifizierung
├── lib/                # Bibliotheken und Hilfsfunktionen
├── public/             # Öffentlich zugängliche Dateien
│   ├── css/            # Stylesheets
│   ├── img/            # Statische Bilder
│   ├── includes/       # Template-Teile
│   └── js/             # JavaScript-Dateien
└── storage/            # Bilderspeicher
    ├── thumbs/         # Thumbnails
    ├── trash/          # Papierkorb
    └── users/          # Benutzerverzeichnisse
```

## Nutzung

### Bilder hochladen

1. Nach dem Login auf "Upload" klicken
2. Bilder per Drag & Drop oder Dateiauswahl hochladen
3. Optional Tags und Beschreibungen hinzufügen

### Alben erstellen

1. Auf "Album erstellen" klicken
2. Namen und Beschreibung eingeben
3. Bilder zum Album hinzufügen

### Bilder favorisieren

- Klicke auf das Stern-Symbol (★) bei einem Bild, um es zu favorisieren
- Favorisierte Bilder sind in der "Favoriten"-Ansicht zu finden

### Automatischer Import

Die Anwendung kann Verzeichnisse überwachen und automatisch neue Bilder importieren:

1. Ordner in der Konfiguration hinzufügen
2. Bilder werden automatisch importiert

## Entwicklung

### Erweiterung der Anwendung

Die Anwendung basiert auf einer einfachen Verzeichnisstruktur und kann leicht erweitert werden:

- Neue API-Endpunkte in `/app/api/` hinzufügen
- Frontend-Komponenten in `/public/js/` und `/public/css/` erweitern
- Datenbank-Schema bei Bedarf aktualisieren

## Lizenz

Dieses Projekt ist unter der MIT-Lizenz lizenziert. Siehe LICENSE-Datei für Details.

## Mitwirken

Beiträge zum Projekt sind willkommen! Bitte erstelle einen Pull Request oder melde Issues, wenn du Probleme findest.

---

Entwickelt mit ❤️ für Fotografie-Enthusiasten