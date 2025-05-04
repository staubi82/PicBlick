<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../app/auth/auth.php';
require_once __DIR__ . '/../lib/imaging.php';

$auth = new Auth();
$auth->checkSession();
if (!$auth->isLoggedIn()) {
    header('Location: login.php?redirect=upload.php');
    exit;
}

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

$albums = $db->fetchAll(
    'SELECT * FROM albums WHERE user_id = :user_id AND deleted_at IS NULL ORDER BY created_at DESC',
    ['user_id' => $currentUser['id']]
);

$error = '';
$success = '';
$selectedAlbumId = $_POST['album_id'] ?? '';

// Album erstellen
$createAlbumError = '';
$createAlbumSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_album_form'])) {
    $albumName = trim($_POST['album_name'] ?? '');
    $isPublic = isset($_POST['album_is_public']) ? 1 : 0;
    
    // Validierung
    if (empty($albumName)) {
        $createAlbumError = 'Bitte geben Sie einen Namen für das Album ein.';
    } else {
        // Prüfen, ob Album mit diesem Namen bereits existiert
        $existingAlbum = $db->fetchOne(
            "SELECT id FROM albums WHERE user_id = :user_id AND name = :name AND deleted_at IS NULL",
            [
                'user_id' => $currentUser['id'],
                'name' => $albumName
            ]
        );
        
        if ($existingAlbum) {
            $createAlbumError = 'Ein Album mit diesem Namen existiert bereits.';
        } else {
            // Sicheren Pfad für das Album erstellen
            $albumPath = 'user_' . $currentUser['id'] . '/' . preg_replace('/[^a-z0-9_-]/i', '_', strtolower($albumName)) . '_' . uniqid();
            
            // Album in die Datenbank einfügen
            $albumId = $db->insert('albums', [
                'name' => $albumName,
                'user_id' => $currentUser['id'],
                'path' => $albumPath,
                'is_public' => $isPublic,
                'description' => trim($_POST['album_description'] ?? '')
            ]);
            
            if ($albumId) {
                // Verzeichnis für das Album erstellen
                $albumDir = USERS_PATH . '/' . $albumPath;
                if (!file_exists($albumDir)) {
                    mkdir($albumDir, 0755, true);
                }
                
                $createAlbumSuccess = 'Album "' . htmlspecialchars($albumName) . '" wurde erfolgreich erstellt!';
                
                // Alben neu laden
                $albums = $db->fetchAll(
                    'SELECT * FROM albums WHERE user_id = :user_id AND deleted_at IS NULL ORDER BY created_at DESC',
                    ['user_id' => $currentUser['id']]
                );
            } else {
                $createAlbumError = 'Fehler beim Erstellen des Albums. Bitte versuchen Sie es erneut.';
            }
        }
    }
}

// Album löschen
$deleteAlbumError = '';
$deleteAlbumSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_album_form'])) {
    $albumIdToDelete = $_POST['album_to_delete'] ?? '';
    
    if (empty($albumIdToDelete)) {
        $deleteAlbumError = 'Bitte wählen Sie ein Album zum Löschen aus.';
    } else {
        // Prüfen, ob Album existiert und dem Benutzer gehört
        $albumToDelete = $db->fetchOne(
            "SELECT * FROM albums WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL",
            [
                'id' => $albumIdToDelete,
                'user_id' => $currentUser['id']
            ]
        );
        
        if (!$albumToDelete) {
            $deleteAlbumError = 'Ungültiges Album ausgewählt.';
        } else {
            // Album als gelöscht markieren (soft delete)
            $db->update(
                'albums',
                ['deleted_at' => date('Y-m-d H:i:s')],
                'id = :id',
                ['id' => $albumIdToDelete]
            );
            
            $deleteAlbumSuccess = 'Album "' . htmlspecialchars($albumToDelete['name']) . '" wurde erfolgreich gelöscht.';
            
            // Alben neu laden
            $albums = $db->fetchAll(
                'SELECT * FROM albums WHERE user_id = :user_id AND deleted_at IS NULL ORDER BY created_at DESC',
                ['user_id' => $currentUser['id']]
            );
        }
    }
}

// Bilder hochladen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images'])) {
    if ($selectedAlbumId === 'create') {
        header('Location: album-create.php');
        exit;
    }
    if ($selectedAlbumId === '') {
        $error = 'Bitte wählen Sie ein Album aus.';
    } else {
        $album = $db->fetchOne(
            'SELECT * FROM albums WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL',
            ['id' => (int)$selectedAlbumId, 'user_id' => $currentUser['id']]
        );
        if (!$album) {
            $error = 'Ungültiges Album ausgewählt.';
        } else {
            $files = $_FILES['images'];
            $count = count($files['name']);
            $uploadedCount = 0;

            if ($count > 0 && $files['name'][0] !== '') {
                $uploadDir = USERS_PATH . '/' . $album['path'] . '/';
                $thumbDir  = THUMBS_PATH . '/' . $album['path'] . '/';

                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                if (!is_dir($thumbDir))  mkdir($thumbDir, 0755, true);

                for ($i = 0; $i < $count; $i++) {
                    $name     = $files['name'][$i];
                    $type     = $files['type'][$i];
                    $tmpName  = $files['tmp_name'][$i];
                    $errorVal = $files['error'][$i];
                    $size     = $files['size'][$i];

                    if ($errorVal !== UPLOAD_ERR_OK) continue;
                    if (!in_array($type, ['image/jpeg','image/png','image/gif'])) continue;
                    if ($size > MAX_UPLOAD_SIZE) continue;

                    $uniqueName = uniqid() . '_' . preg_replace('/[^a-z0-9_.-]/i', '_', strtolower($name));
                    $destination = $uploadDir . $uniqueName;

                    if (move_uploaded_file($tmpName, $destination)) {
                        if (STRIP_EXIF) Imaging::stripExif($destination);
                        Imaging::autoRotateImage($destination);
                        Imaging::createThumbnail($destination, $thumbDir . $uniqueName);

                        $imageId = $db->insert('images', [
                            'filename' => $uniqueName,
                            'album_id' => $album['id'],
                            'is_public'=> isset($_POST['make_public']) ? 1 : 0
                        ]);

                        $metadata = Imaging::getImageMetadata($destination);
                        if ($metadata && $imageId) {
                            $metaDataToSave = [
                                'image_id'    => $imageId,
                                'camera_make' => $metadata['camera_make'] ?? null,
                                'camera_model'=> $metadata['camera_model'] ?? null,
                                'exposure'    => $metadata['exposure'] ?? null,
                                'aperture'    => $metadata['aperture'] ?? null,
                                'iso'         => $metadata['iso'] ?? null,
                                'focal_length'=> $metadata['focal_length'] ?? null,
                                'date_taken'  => $metadata['date_taken'] ?? null,
                            ];
                            if (isset($metadata['gps'])) {
                                $metaDataToSave['gps_latitude']  = $metadata['gps']['latitude'] ?? null;
                                $metaDataToSave['gps_longitude'] = $metadata['gps']['longitude'] ?? null;
                            }
                            $db->insert('image_metadata', $metaDataToSave);
                        }
                        
                        // Prüfen ob das Album ein Cover-Bild hat, falls nicht, dieses Bild als Cover setzen
                        // Aber nur beim ersten hochgeladenen Bild im aktuellen Batch prüfen
                        if ($uploadedCount === 0) {
                            $albumDetails = $db->fetchOne(
                                "SELECT cover_image_id FROM albums WHERE id = :album_id",
                                ['album_id' => $album['id']]
                            );
                            
                            // Wenn kein Cover-Bild gesetzt ist, das aktuelle Bild als Cover verwenden
                            if (empty($albumDetails['cover_image_id'])) {
                                $db->update(
                                    'albums',
                                    ['cover_image_id' => $imageId],
                                    'id = :id',
                                    ['id' => $album['id']]
                                );
                            }
                        }
                        
                        $uploadedCount++;
                    }
                }
                $success = "$uploadedCount von $count Bilder erfolgreich hochgeladen.";
                if ($uploadedCount === 0) {
                    $error = 'Keine Bilder hochgeladen. Prüfen Sie Typ und Größe.';
                }
            } else {
                $error = 'Bitte wählen Sie mindestens eine Datei aus.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Medienverwaltung - Fotogalerie</title>
    <link rel="stylesheet" href="css/style.css" />
    <link rel="stylesheet" href="css/header.css" />
    <style>
        :root {
            --tab-active-color: #0066cc; /* Blau wie der Header */
            --tab-inactive-bg: var(--bg-tertiary);
            --tab-active-bg: var(--bg-secondary);
            --tab-border-radius: 8px 8px 0 0;
            --tab-transition: all 0.3s ease;
            --accent-blue: #0066cc; /* Header-Blau */
            --accent-white: #ffffff; /* Weiß statt Mint */
        }
        
        /* Loading-Spinner */
        #spinnerOverlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }
        
        #spinnerOverlay .loader {
            border: 8px solid rgba(255, 255, 255, 0.2);
            border-top: 8px solid var(--accent-blue);
            border-radius: 50%;
            width: 60px; height: 60px;
            animation: spin 1s linear infinite;
            box-shadow: 0 0 20px rgba(0, 102, 204, 0.5);
        }
        
        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
        
        /* Moderne Tabs */
        .tabs-container {
            margin-bottom: 30px;
            background-color: var(--bg-secondary);
            border-radius: var(--border-radius);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .tabs {
            display: flex;
            flex-wrap: wrap;
            background-color: var(--bg-tertiary);
            position: relative;
            border-bottom: 2px solid var(--accent-blue);
        }
        
        .tab {
            padding: 15px 20px;
            cursor: pointer;
            flex: 1;
            text-align: center;
            transition: var(--tab-transition);
            font-weight: 500;
            position: relative;
            min-width: 120px;
            color: var(--text-secondary);
            border-right: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .tab:last-child {
            border-right: none;
        }
        
        .tab.active {
            background-color: var(--tab-active-bg);
            color: var(--accent-blue);
            border-bottom: 2px solid var(--accent-blue);
            margin-bottom: -2px;
        }
        
        .tab:hover:not(.active) {
            background-color: rgba(0, 102, 204, 0.08);
            color: var(--accent-blue);
        }
        
        .tab svg {
            width: 18px;
            height: 18px;
            opacity: 0.7;
            transition: all 0.3s ease;
        }
        
        .tab.active svg {
            opacity: 1;
        }
        
        .tab-content {
            display: none;
            padding: 25px;
            background-color: var(--bg-secondary);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Upload-Bereich mit 3D-Schatten */
        .upload-area {
            border: 2px dashed var(--border);
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            margin-bottom: 1.5em;
            background-color: rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 15px;
        }
        
        .upload-area:before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: radial-gradient(circle at center, rgba(0, 102, 204, 0.05) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .upload-area:after {
            content: '';
            position: absolute;
            bottom: 0; right: 0;
            width: 100%; height: 100%;
            background: radial-gradient(circle at bottom right, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .upload-area.highlight {
            border-color: var(--accent-blue);
            background-color: rgba(0, 102, 204, 0.05);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .upload-area.highlight:before,
        .upload-area.highlight:after {
            opacity: 1;
        }
        
        .upload-icon {
            width: 50px;
            height: 50px;
            margin-bottom: 15px;
            color: var(--text-secondary);
            opacity: 0.7;
            transition: all 0.3s ease;
        }
        
        .upload-area:hover .upload-icon {
            transform: translateY(-5px);
            color: var(--accent-blue);
            opacity: 1;
        }
        
        .upload-area p {
            margin-bottom: 15px;
            color: var(--text-secondary);
            font-size: 1rem;
        }
        
        /* Bilder-Vorschau mit 3D-Effekt */
        .upload-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .upload-preview-item {
            aspect-ratio: 1;
            overflow: hidden;
            position: relative;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            transform: translateZ(0);
            background-color: var(--bg-tertiary);
        }
        
        .upload-preview-item:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.3);
            z-index: 5;
        }
        
        .upload-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .upload-preview-item:hover img {
            transform: scale(1.1);
        }
        
        .upload-preview-path {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            color: white;
            font-size: 10px;
            padding: 4px 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            opacity: 0;
            transform: translateY(100%);
            transition: all 0.3s ease;
        }
        
        .upload-preview-item:hover .upload-preview-path {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Formulare mit einheitlichem Design */
        .form-panel {
            background-color: var(--bg-tertiary);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .form-panel-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: white;
            border-bottom: 1px solid var(--accent-blue);
            padding-bottom: 10px;
        }
        
        /* Folder Scanner verbessert */
        .folder-scanner {
            margin-bottom: 25px;
        }
        
        .folder-path-input {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .scan-result {
            margin-top: 20px;
            border: 1px solid var(--accent-blue);
            border-radius: 8px;
            padding: 15px;
            max-height: 350px;
            overflow-y: auto;
            background-color: rgba(0, 0, 0, 0.1);
            box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .scan-result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        
        .scan-result-item {
            display: flex;
            margin-bottom: 8px;
            padding: 8px;
            border-radius: 5px;
            align-items: center;
            background-color: rgba(255, 255, 255, 0.03);
            transition: all 0.2s ease;
        }
        
        .scan-result-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
            transform: translateX(3px);
        }
        
        .scan-result-item img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            margin-right: 15px;
            border-radius: 5px;
            box-shadow: 0 3px 5px rgba(0, 0, 0, 0.2);
        }
        
        .scan-result-item.deleted {
            text-decoration: line-through;
            color: var(--text-muted);
            opacity: 0.7;
        }
        
        .scan-result-item .status {
            margin-left: auto;
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .status.new {
            background-color: rgba(255, 255, 255, 0.25);
            color: var(--accent-blue);
        }
        
        .status.synced {
            background-color: rgba(76, 175, 80, 0.15);
            color: var(--success);
        }
        
        .status.deleted {
            background-color: rgba(207, 102, 121, 0.15);
            color: var(--error);
        }
        
        /* Info-Box mit besserem Design */
        .info-box {
            background-color: var(--bg-tertiary);
            border-left: 4px solid var(--accent-blue);
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .info-box p {
            margin: 0 0 10px 0;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .info-box ul {
            margin: 0 0 0 20px;
            padding: 0;
        }
        
        .info-box li {
            margin-bottom: 5px;
            color: var(--text-secondary);
        }
        
        /* Verbesserte Select-Inputs */
        select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%230066cc' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px;
            padding-right: 30px;
            border: 1px solid rgba(0, 102, 204, 0.3);
            transition: all 0.3s ease;
        }
        
        select:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(0, 102, 204, 0.2);
            outline: none;
        }
        
        /* Abbrechen-Buttons einheitlich gestalten */
        .btn.btn-cancel,
        a.btn:not(.btn-primary):not(.btn-secondary):not(.btn-danger) {
            background-color: rgba(255,255,255,0.1);
            border-color: transparent;
            color: var(--text-secondary);
        }
        
        .btn.btn-cancel:hover,
        a.btn:not(.btn-primary):not(.btn-secondary):not(.btn-danger):hover {
            background-color: rgba(255,255,255,0.2);
            color: var(--text-primary);
        }
        
        /* Responsive Anpassungen */
        @media (max-width: 768px) {
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                border-right: none;
                border-bottom: 1px solid var(--border);
            }
            
            .tab:last-child {
                border-bottom: none;
            }
            
            .tab.active {
                border-bottom: 1px solid var(--border);
                border-left: 4px solid var(--accent-blue);
                margin-bottom: 0;
            }
            
            .upload-preview {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
        }
    </style>
</head>
<body>
<?php $activePage = 'upload'; require_once __DIR__ . '/includes/header.php'; ?>
<div id="spinnerOverlay"><div class="loader"></div></div>

<main>
    <div class="container">
        <section>
            <h2>Medienverwaltung</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <!-- Egal ob Alben existieren oder nicht, zeige die Tabs immer an -->
                <div class="tabs-container">
                    <!-- Tabs Navigation -->
                    <div class="tabs">
                        <div class="tab" data-tab="create-album">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                                <line x1="12" y1="17" x2="12" y2="17"></line>
                                <line x1="12" y1="15" x2="12" y2="19"></line>
                            </svg>
                            Album erstellen
                        </div>
                        <div class="tab active" data-tab="upload">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            Bilder hochladen
                        </div>
                        <div class="tab" data-tab="scan">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <line x1="21" y1="11" x2="21" y2="11"></line>
                                <circle cx="21" cy="11" r="1"></circle>
                                <line x1="21" y1="7" x2="21" y2="7"></line>
                                <circle cx="21" cy="7" r="1"></circle>
                                <line x1="21" y1="15" x2="21" y2="15"></line>
                                <circle cx="21" cy="15" r="1"></circle>
                            </svg>
                            Ordner scannen
                        </div>
                        <div class="tab" data-tab="delete-album">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                <line x1="14" y1="11" x2="14" y2="17"></line>
                            </svg>
                            Alben löschen
                        </div>
                    </div>
                    
                    <!-- Album erstellen Tab -->
                    <div id="create-album-tab" class="tab-content">
                        <?php if ($createAlbumError): ?>
                            <div class="alert alert-error"><?php echo htmlspecialchars($createAlbumError); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($createAlbumSuccess): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($createAlbumSuccess); ?></div>
                        <?php endif; ?>
                        
                        <div class="form-panel">
                            <h3 class="form-panel-title" style="color: white;">Neues Album erstellen</h3>
                            <form method="post" action="">
                                <input type="hidden" name="create_album_form" value="1">
                                
                                <div class="form-group">
                                    <label for="album_name">Album-Name</label>
                                    <input type="text" id="album_name" name="album_name" required autofocus>
                                </div>
                                
                                <div class="form-group">
                                    <label for="album_description">Album-Beschreibung (optional)</label>
                                    <textarea id="album_description" name="album_description" rows="3" placeholder="Beschreiben Sie dieses Album"></textarea>
                                </div>
                                
                                <div class="form-group checkbox-group">
                                    <input type="checkbox" id="album_is_public" name="album_is_public">
                                    <label for="album_is_public">Öffentlich (für alle sichtbar)</label>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary" style="background-color: var(--accent-blue); border-color: var(--accent-blue);">Album erstellen</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Bilder hochladen Tab -->
                    <div id="upload-tab" class="tab-content active">
                        <form id="uploadForm" method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="album_id">Album auswählen</label>
                                <select id="album_id" name="album_id" required>
                                    <option value="">-- Bitte wählen --</option>
                                    <?php if (empty($albums)): ?>
                                        <option value="create">Neues Album erstellen</option>
                                    <?php else: ?>
                                        <?php foreach ($albums as $alb): ?>
                                            <option value="<?php echo $alb['id']; ?>" <?php echo $selectedAlbumId == $alb['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($alb['name']); ?><?php if ($alb['is_public']): ?> (Öffentlich)<?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="create">Neues Album erstellen</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="upload-area" id="uploadArea">
                                <svg class="upload-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                                <p>Dateien hierher ziehen oder klicken zum Auswählen</p>
                                <input type="file" name="images[]" id="file-input" multiple accept="image/*" style="opacity:0; position:absolute; width:1px; height:1px; overflow:hidden; z-index:-1;">
                                <button type="button" id="browseBtn" class="btn btn-primary" style="background-color: var(--accent-blue); border-color: var(--accent-blue);">Dateien auswählen</button>
                            </div>
                            <div class="upload-preview" id="uploadPreview"></div>
                            <div class="form-group checkbox-group">
                                <input type="checkbox" id="make_public" name="make_public" />
                                <label for="make_public">Bilder öffentlich sichtbar machen</label>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary" style="background-color: var(--accent-blue); border-color: var(--accent-blue);">Hochladen</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Ordner-Scan-Tab -->
                    <div id="scan-tab" class="tab-content">
                        <form id="scanFolderForm">
                            <!-- Ordnerüberwachung -->
                            <div class="form-panel">
                                <h3 class="form-panel-title" style="color: white;">Automatischer Import</h3>
                                <p>Startet den automatischen Import von Bildern aus allen konfigurierten Benutzerordnern.</p>
                                <div class="form-group">
                                    <button id="triggerFolderMonitorBtn" class="btn btn-secondary" style="box-shadow: 0 4px 8px rgba(0,0,0,0.15); padding: 10px 15px; font-weight: 500; background-color: var(--accent-blue); color: white; border: none; border-radius: 5px; transition: all 0.3s ease;">Automatischen Import starten</button>
                                    <div id="monitorResult" style="margin-top:10px;"></div>
                                </div>
                            </div>
                            
                            <!-- Manueller Ordnerscan -->
                            <div class="form-panel">
                                <h3 class="form-panel-title" style="color: white;">Manueller Ordnerscan</h3>
                                
                                <div class="form-group">
                                    <label for="scan_album_id">Album auswählen</label>
                                    <select id="scan_album_id" name="scan_album_id" required>
                                        <option value="">-- Bitte wählen --</option>
                                        <?php if (empty($albums)): ?>
                                            <option value="create">Neues Album erstellen</option>
                                        <?php else: ?>
                                            <?php foreach ($albums as $alb): ?>
                                                <option value="<?php echo $alb['id']; ?>">
                                                    <?php echo htmlspecialchars($alb['name']); ?><?php if ($alb['is_public']): ?> (Öffentlich)<?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <option value="create">Neues Album erstellen</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="folder_path">Zu scannenden Ordner auswählen</label>
                                    <div class="folder-path-input">
                                        <input type="text" id="folder_path" name="folder_path" class="form-control" placeholder="Pfad zum Ordner" readonly required>
                                        <button type="button" id="browseFolderBtn" class="btn" style="box-shadow: 0 4px 8px rgba(0,0,0,0.15); padding: 10px 15px; font-weight: 500; background-color: var(--accent-blue); color: white; border: none; border-radius: 5px; transition: all 0.3s ease;">Ordner wählen</button>
                                    </div>
                                </div>
                                
                                <div class="info-box">
                                    <p><strong>Hinweis zur Ordner-Scan-Funktion:</strong></p>
                                    <ul>
                                        <li>Wählen Sie einen Ordner mit Bildern aus - alle unterstützten Bilder (JPG, PNG, GIF) werden erkannt.</li>
                                        <li>Bereits im Album vorhandene Bilder werden erkannt und nicht dupliziert.</li>
                                        <li>Mit der Option "Gelöschte Bilder synchronisieren" werden Bilder, die im Album sind aber nicht mehr im Ordner, aus dem Album entfernt.</li>
                                        <li>Nach dem Scan können Sie alle neuen Bilder auf einmal importieren.</li>
                                    </ul>
                                </div>
                                
                                <div class="form-group checkbox-group">
                                    <input type="checkbox" id="sync_deletions" name="sync_deletions" checked />
                                    <label for="sync_deletions">Gelöschte Bilder synchronisieren (aus dem Album entfernen)</label>
                                </div>
                                
                                <div class="form-group checkbox-group">
                                    <input type="checkbox" id="import_subfolders" name="import_subfolders" checked />
                                    <label for="import_subfolders">Unterordner als Unteralben importieren</label>
                                </div>
                                
                                <div class="form-group checkbox-group">
                                    <input type="checkbox" id="scan_make_public" name="scan_make_public" />
                                    <label for="scan_make_public">Bilder öffentlich sichtbar machen</label>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="button" id="scanFolderBtn" class="btn btn-primary" style="background-color: var(--accent-blue); border-color: var(--accent-blue);">Ordner scannen</button>
                                </div>
                            </div>
                        </form>
                        
                        <div id="scanResults" class="scan-result" style="display: none;">
                            <div class="scan-result-header">
                                <h3 style="color: white;">Scan-Ergebnisse</h3>
                                <span id="scan-summary"></span>
                            </div>
                            <div id="scanResultsList"></div>
                            <div class="form-actions" style="margin-top: 20px;">
                                <button type="button" id="importScannedBtn" class="btn btn-primary" style="background-color: var(--accent-blue); border-color: var(--accent-blue);">Alle importieren</button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Alben löschen Tab -->
                    <div id="delete-album-tab" class="tab-content">
                        <?php if ($deleteAlbumError): ?>
                            <div class="alert alert-error"><?php echo htmlspecialchars($deleteAlbumError); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($deleteAlbumSuccess): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($deleteAlbumSuccess); ?></div>
                        <?php endif; ?>
                        
                        <?php if (empty($albums)): ?>
                            <div class="empty-state">
                                <p>Du hast noch keine Alben zum Löschen.</p>
                                <a href="album-create.php" class="btn btn-primary">Neues Album erstellen</a>
                            </div>
                        <?php else: ?>
                            <div class="form-panel danger-zone">
                                <h3 class="form-panel-title" style="color: white;">Album löschen</h3>
                                <p class="text-danger">Achtung: Das Löschen eines Albums kann nicht rückgängig gemacht werden!</p>
                                
                                <form method="post" action="">
                                    <input type="hidden" name="delete_album_form" value="1">
                                    
                                    <div class="form-group">
                                        <label for="album_to_delete">Album zum Löschen auswählen</label>
                                        <select id="album_to_delete" name="album_to_delete" required>
                                            <option value="">-- Bitte wählen --</option>
                                            <?php foreach ($albums as $alb): ?>
                                                <option value="<?php echo $alb['id']; ?>">
                                                    <?php echo htmlspecialchars($alb['name']); ?>
                                                    <?php if ($alb['is_public']): ?>(Öffentlich)<?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group checkbox-group">
                                        <input type="checkbox" id="confirm_delete" name="confirm_delete" required>
                                        <label for="confirm_delete">Ich bestätige, dass ich dieses Album löschen möchte</label>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-danger" style="background-color: #dc3545; border-color: #dc3545;">Album löschen</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
        </section>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Tab-Funktionalität
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const tabId = tab.getAttribute('data-tab');
            
            // Tabs umschalten
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            
            // Tab-Inhalte umschalten
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(`${tabId}-tab`).classList.add('active');
        });
    });

    // "Neues Album erstellen" Option für Upload und Scan
    const albumSelect = document.getElementById('album_id');
    const scanAlbumSelect = document.getElementById('scan_album_id');
    
    // Event-Listener für das Album-Dropdown im Upload-Bereich
    if (albumSelect) {
        albumSelect.addEventListener('change', function() {
            if (this.value === 'create') {
                window.location.href = 'album-create.php';
            }
        });
    }
    
    // Event-Listener für das Album-Dropdown im Scan-Bereich
    if (scanAlbumSelect) {
        scanAlbumSelect.addEventListener('change', function() {
            if (this.value === 'create') {
                window.location.href = 'album-create.php';
            }
        });
    }

    // Bestehender Upload-Bereich Code
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('file-input');
    const browseBtn = document.getElementById('browseBtn');
    const uploadPreview = document.getElementById('uploadPreview');
    const uploadForm = document.getElementById('uploadForm');
    const spinnerOverlay = document.getElementById('spinnerOverlay');
    let selectedFiles = [];
    const MAX_PREVIEWS = 50;

    function appendPreview(files) {
        if (!uploadPreview) return;

        if (selectedFiles.length > 0) {
            document.querySelectorAll('.upload-info').forEach(el => el.remove());
            const infoBox = document.createElement('div');
            infoBox.className = 'upload-info';
            infoBox.textContent = `Sie haben ${selectedFiles.length} Dateien ausgewählt.`;
            uploadPreview.appendChild(infoBox);
        }

        const previewCount = Math.min(files.length, MAX_PREVIEWS);
        for (let i = 0; i < previewCount; i++) {
            const file = files[i];
            const reader = new FileReader();
            reader.onload = (e) => {
                const div = document.createElement('div');
                div.className = 'upload-preview-item';
                const img = document.createElement('img');
                img.src = e.target.result;
                img.alt = file.name;
                div.appendChild(img);

                if (file.webkitRelativePath) {
                    const pathInfo = document.createElement('div');
                    pathInfo.className = 'upload-preview-path';
                    pathInfo.textContent = file.webkitRelativePath;
                    div.appendChild(pathInfo);
                }

                uploadPreview.appendChild(div);
            };
            reader.readAsDataURL(file);
        }
    }

    browseBtn.addEventListener('click', () => {
        fileInput.click(); // Dateiauswahl-Dialog öffnen
    });
    
    // Button-Hover-Effekte für besser erkennbare Buttons
    document.querySelectorAll('#browseFolderBtn, #triggerFolderMonitorBtn').forEach(button => {
        button.addEventListener('mouseenter', () => {
            button.style.transform = 'translateY(-2px)';
            button.style.boxShadow = '0 6px 12px rgba(0,0,0,0.2)';
        });
        
        button.addEventListener('mouseleave', () => {
            button.style.transform = 'translateY(0)';
            button.style.boxShadow = '0 4px 8px rgba(0,0,0,0.15)';
        });
    });

    uploadArea.addEventListener('dragenter', (e) => {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.classList.add('highlight');
    });

    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.classList.add('highlight');
    });

    uploadArea.addEventListener('dragleave', (e) => {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.classList.remove('highlight');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.classList.remove('highlight');

        const dt = e.dataTransfer;
        const files = dt.files;

        const newFiles = Array.from(files);
        selectedFiles = [...selectedFiles, ...newFiles];

        appendPreview(newFiles);

        const dataTransfer = new DataTransfer();
        selectedFiles.forEach(file => dataTransfer.items.add(file));
        fileInput.files = dataTransfer.files;
    });

    fileInput.addEventListener('change', () => {
        const newFiles = Array.from(fileInput.files);
        selectedFiles = [...selectedFiles, ...newFiles];
        appendPreview(newFiles);
        fileInput.value = '';
    });

    uploadForm.addEventListener('submit', (e) => {
        e.preventDefault();

        if (selectedFiles.length === 0) {
            alert('Bitte wählen Sie mindestens eine Datei aus.');
            return;
        }

        spinnerOverlay.style.display = 'flex';

        const formData = new FormData(uploadForm);
        for (const pair of formData.entries()) {
            if (pair[0] === 'images[]') {
                formData.delete('images[]');
            }
        }
        selectedFiles.forEach(file => formData.append('images[]', file));

        fetch(uploadForm.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            document.open();
            document.write(html);
            document.close();
        })
        .catch(() => {
            spinnerOverlay.style.display = 'none';
            alert('Fehler beim Hochladen. Bitte versuchen Sie es erneut.');
        });
    });
    
    // Ordnerscan-Funktionalität
    const browseFolderBtn = document.getElementById('browseFolderBtn');
    const folderPathInput = document.getElementById('folder_path');
    const scanFolderBtn = document.getElementById('scanFolderBtn');
    const scanResults = document.getElementById('scanResults');
    const scanResultsList = document.getElementById('scanResultsList');
    const importScannedBtn = document.getElementById('importScannedBtn');
    const scanSummary = document.getElementById('scan-summary');
    
    // Ordner wählen Button
    browseFolderBtn.addEventListener('click', () => {
        const folderSelector = document.createElement('input');
        folderSelector.type = 'file';
        folderSelector.webkitdirectory = true;
        folderSelector.directory = true;
        folderSelector.style.display = 'none';
        
        folderSelector.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                // Den Pfad des ersten Files verwenden, um den Ordnerpfad zu bekommen
                const path = e.target.files[0].webkitRelativePath.split('/')[0];
                folderPathInput.value = path;
                
                // Session-Storage für spätere Verwendung speichern
                sessionStorage.setItem('selectedFolderPath', path);
                // Die ausgewählten Dateien speichern für den Scan
                const files = Array.from(e.target.files);
                sessionStorage.setItem('selectedFolderFiles', JSON.stringify(
                    files.map(file => ({
                        name: file.name,
                        path: file.webkitRelativePath,
                        size: file.size,
                        type: file.type,
                        lastModified: file.lastModified
                    }))
                ));
                // Hier kein vorzeitiges Entfernen des Elements
            }
        });
        
        document.body.appendChild(folderSelector);
        folderSelector.click();
    });
    
    // Ordner scannen
    scanFolderBtn.addEventListener('click', () => {
        const albumId = document.getElementById('scan_album_id').value;
        const folderPath = folderPathInput.value;
        const syncDeletions = document.getElementById('sync_deletions').checked;
        const makePublic = document.getElementById('scan_make_public').checked;
        
        if (!albumId) {
            alert('Bitte wählen Sie ein Album aus.');
            return;
        }
        
        if (!folderPath) {
            alert('Bitte wählen Sie einen Ordner zum Scannen aus.');
            return;
        }
        
        spinnerOverlay.style.display = 'flex';
        
        // Hole die Dateien aus dem Session Storage
        const folderFiles = JSON.parse(sessionStorage.getItem('selectedFolderFiles') || '[]');
        
        // Filtere nur Bilder
        const imageFiles = folderFiles.filter(file =>
            file.type.startsWith('image/') &&
            ['image/jpeg', 'image/png', 'image/gif'].includes(file.type)
        );
        
        // Anfrage an den Server zum Scannen des Ordners
        fetch('../app/api/scan-folder.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                album_id: albumId,
                folder_path: folderPath,
                sync_deletions: syncDeletions,
                make_public: makePublic,
                files: imageFiles
            })
        })
        .then(response => response.json())
        .then(data => {
            spinnerOverlay.style.display = 'none';
            
            if (data.error) {
                alert(data.error);
                return;
            }
            
            // Zeige die Scan-Ergebnisse an
            scanResultsList.innerHTML = '';
            
            // Zusammenfassung anzeigen
            const newCount = data.new_files ? data.new_files.length : 0;
            const existingCount = data.existing_files ? data.existing_files.length : 0;
            const deletedCount = data.deleted_files ? data.deleted_files.length : 0;
            
            const subFoldersCount = data.sub_folders ? data.sub_folders.length : 0;
            
            scanSummary.textContent = `${newCount} neue, ${existingCount} vorhandene, ${deletedCount} gelöschte Dateien, ${subFoldersCount} Unterordner`;
            
            // Unterordner anzeigen
            if (data.sub_folders && data.sub_folders.length > 0) {
                const subFoldersTitle = document.createElement('h4');
                subFoldersTitle.textContent = 'Gefundene Unterordner';
                subFoldersTitle.style.marginTop = '20px';
                subFoldersTitle.style.marginBottom = '10px';
                scanResultsList.appendChild(subFoldersTitle);
                
                data.sub_folders.forEach(folder => {
                    const item = document.createElement('div');
                    item.className = 'scan-result-item';
                    const statusText = folder.exists ? 'Vorhanden' : 'Neu';
                    const statusClass = folder.exists ? 'synced' : 'new';
                    
                    item.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="min-width: 40px; margin-right: 15px;">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                        </svg>
                        <span><strong>Unterordner:</strong> ${folder.name}</span>
                        <span class="status ${statusClass}">${statusText}</span>
                    `;
                    scanResultsList.appendChild(item);
                });
                
                const separator = document.createElement('hr');
                separator.style.margin = '20px 0';
                separator.style.border = 'none';
                separator.style.borderTop = '1px solid var(--border)';
                scanResultsList.appendChild(separator);
            }
            
            // Neue Dateien
            if (data.new_files && data.new_files.length > 0) {
                const filesTitle = document.createElement('h4');
                filesTitle.textContent = 'Gefundene Dateien';
                filesTitle.style.marginTop = '20px';
                filesTitle.style.marginBottom = '10px';
                scanResultsList.appendChild(filesTitle);
                data.new_files.forEach(file => {
                    const item = document.createElement('div');
                    item.className = 'scan-result-item';
                    item.innerHTML = `
                        <img src="data:image/jpeg;base64,${file.preview}" alt="${file.name}">
                        <span>${file.path}</span>
                        <span class="status new">Neu</span>
                    `;
                    scanResultsList.appendChild(item);
                });
            }
            
            // Bereits vorhandene Dateien
            if (data.existing_files && data.existing_files.length > 0) {
                data.existing_files.forEach(file => {
                    const item = document.createElement('div');
                    item.className = 'scan-result-item';
                    item.innerHTML = `
                        <img src="${file.thumb_url}" alt="${file.name}">
                        <span>${file.path}</span>
                        <span class="status synced">Vorhanden</span>
                    `;
                    scanResultsList.appendChild(item);
                });
            }
            
            // Gelöschte Dateien
            if (data.deleted_files && data.deleted_files.length > 0) {
                data.deleted_files.forEach(file => {
                    const item = document.createElement('div');
                    item.className = 'scan-result-item deleted';
                    item.innerHTML = `
                        <img src="${file.thumb_url}" alt="${file.name}">
                        <span>${file.name}</span>
                        <span class="status deleted">Gelöscht</span>
                    `;
                    scanResultsList.appendChild(item);
                });
            }
            
            // Zeige die Ergebnisse an
            scanResults.style.display = 'block';
            
            // Setze Session-Daten für den Import
            sessionStorage.setItem('scanResultData', JSON.stringify(data));
        })
        .catch(error => {
            spinnerOverlay.style.display = 'none';
            console.error('Fehler beim Scannen des Ordners:', error);
            alert('Fehler beim Scannen des Ordners. Bitte versuchen Sie es erneut.');
        });
    });
    
    // Import-Button für gescannte Dateien
    importScannedBtn.addEventListener('click', () => {
        const albumId = document.getElementById('scan_album_id').value;
        const syncDeletions = document.getElementById('sync_deletions').checked;
        const makePublic = document.getElementById('scan_make_public').checked;
        
        if (!albumId) {
            alert('Bitte wählen Sie ein Album aus.');
            return;
        }
        
        const scanData = JSON.parse(sessionStorage.getItem('scanResultData') || '{}');
        
        if (!scanData || (!scanData.new_files && !scanData.deleted_files)) {
            alert('Keine Daten zum Importieren vorhanden.');
            return;
        }
        
        spinnerOverlay.style.display = 'flex';
        
        // Anfrage an den Server zum Importieren der Dateien
        fetch('../app/api/import-scanned-files.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                album_id: albumId,
                scan_data: scanData,
                sync_deletions: syncDeletions,
                make_public: makePublic,
                import_subfolders: document.getElementById('import_subfolders').checked
            })
        })
        .then(response => response.json())
        .then(data => {
            spinnerOverlay.style.display = 'none';
            
            if (data.error) {
                alert(data.error);
                return;
            }
            
            // Erfolgsmeldung anzeigen und zur Album-Seite weiterleiten
            alert(`Import abgeschlossen: ${data.imported_count} Bilder importiert, ${data.deleted_count} Bilder entfernt.`);
            
            // Zur Album-Seite weiterleiten
            window.location.href = `album.php?id=${albumId}`;
        })
        .catch(error => {
            spinnerOverlay.style.display = 'none';
            console.error('Fehler beim Importieren:', error);
            alert('Fehler beim Importieren der Dateien. Bitte versuchen Sie es erneut.');
        });
    });
    
    // Ordnerüberwachung Button
    document.getElementById('triggerFolderMonitorBtn').addEventListener('click', (e) => {
        e.preventDefault();
        
        // Ladeanimation anzeigen
        spinnerOverlay.style.display = 'flex';
        
        // Status-Meldung anzeigen
        const resultDiv = document.getElementById('monitorResult');
        resultDiv.innerHTML = '<div class="alert alert-info">Ordnerüberwachung läuft... Dies kann einige Sekunden dauern.</div>';
        
        fetch('../app/api/trigger-folder-monitor.php', {
            method: 'POST',
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            // Ladeanimation ausblenden
            spinnerOverlay.style.display = 'none';
            
            if (data.success) {
                resultDiv.innerHTML = '<div class="alert alert-success">Ordnerüberwachung erfolgreich ausgeführt.</div>';
            } else {
                resultDiv.innerHTML = '<div class="alert alert-error">Fehler: ' + (data.error || 'Unbekannter Fehler') + '</div>';
            }
        })
        .catch(error => {
            // Ladeanimation ausblenden
            spinnerOverlay.style.display = 'none';
            resultDiv.innerHTML = '<div class="alert alert-error">Fehler beim Ausführen: ' + error + '</div>';
        });
    });
    
    // Auto-Aktivierung des Album-löschen Buttons mit Checkbox
    const confirmDeleteCheckbox = document.getElementById('confirm_delete');
    const deleteAlbumButton = document.querySelector('.btn-danger');
    
    if (confirmDeleteCheckbox && deleteAlbumButton) {
        confirmDeleteCheckbox.addEventListener('change', () => {
            deleteAlbumButton.disabled = !confirmDeleteCheckbox.checked;
            if (confirmDeleteCheckbox.checked) {
                deleteAlbumButton.classList.remove('btn-disabled');
            } else {
                deleteAlbumButton.classList.add('btn-disabled');
            }
        });
        
        // Initial deaktivieren
        deleteAlbumButton.disabled = true;
        deleteAlbumButton.classList.add('btn-disabled');
    }
});
</script>
</body>
</html>
