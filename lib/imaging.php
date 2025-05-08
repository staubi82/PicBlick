<?php
/**
 * Imaging Class
 * 
 * Stellt Methoden zur Bildverarbeitung und Thumbnail-Generierung bereit
 */
class Imaging
{
    /**
     * Erzeugt ein Thumbnail aus einem Originalbild
     * 
     * @param string $sourcePath Quellpfad des Originalbildes
     * @param string $destPath Zielpfad des Thumbnails
     * @param int $width Breite des Thumbnails
     * @param int $height Höhe des Thumbnails
     * @param bool $crop Flag für Zuschneiden oder proportionale Skalierung
     * @return bool true bei Erfolg, false bei Fehler
     */
    public static function createThumbnail($sourcePath, $destPath, $width = THUMB_WIDTH, $height = THUMB_HEIGHT, $crop = true)
    {
        if (!file_exists($sourcePath)) {
            error_log("Thumbnail-Quelldatei nicht gefunden: $sourcePath");
            return false;
        }
        
        // Stelle sicher, dass der Zielordner existiert
        $destDir = dirname($destPath);
        if (!file_exists($destDir)) {
            if (!mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                error_log("Konnte Zielverzeichnis nicht erstellen: $destDir");
                return false;
            }
        }
        
        // Bestimme Bildtyp
        $sourceInfo = getimagesize($sourcePath);
        if ($sourceInfo === false) {
            error_log("Ungültiges Bildformat: $sourcePath");
            return false;
        }
        
        $sourceType = $sourceInfo[2];
        $sourceWidth = $sourceInfo[0];
        $sourceHeight = $sourceInfo[1];
        
        // Lade Originalbild
        $sourceImage = null;
        switch ($sourceType) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            default:
                error_log("Nicht unterstütztes Bildformat: $sourcePath");
                return false;
        }
        
        if (!$sourceImage) {
            error_log("Fehler beim Laden des Bildes: $sourcePath");
            return false;
        }
        
        // Berechne neue Dimensionen
        if ($crop) {
            // Variante 1: Zuschneiden, behält die angegebenen Dimensionen bei
            $aspectRatio = $sourceWidth / $sourceHeight;
            $thumbAspectRatio = $width / $height;
            
            if ($aspectRatio >= $thumbAspectRatio) {
                // Quellbild ist breiter - beschneide die Seiten
                $newHeight = $sourceHeight;
                $newWidth = $newHeight * $thumbAspectRatio;
                $srcX = ($sourceWidth - $newWidth) / 2;
                $srcY = 0;
            } else {
                // Quellbild ist höher - beschneide oben/unten
                $newWidth = $sourceWidth;
                $newHeight = $newWidth / $thumbAspectRatio;
                $srcX = 0;
                $srcY = ($sourceHeight - $newHeight) / 2;
            }
            
            $thumbImage = imagecreatetruecolor($width, $height);
            
            // Behandlung für Transparenz bei PNG
            if ($sourceType === IMAGETYPE_PNG) {
                imagealphablending($thumbImage, false);
                imagesavealpha($thumbImage, true);
                $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
                imagefilledrectangle($thumbImage, 0, 0, $width, $height, $transparent);
            }
            
            imagecopyresampled($thumbImage, $sourceImage, 0, 0, (int)$srcX, (int)$srcY, $width, $height, (int)$newWidth, (int)$newHeight);
        } else {
            // Variante 2: Proportionales Skalieren, behält das Seitenverhältnis bei
            $aspectRatio = $sourceWidth / $sourceHeight;
            
            if ($sourceWidth > $sourceHeight) {
                $newWidth = (int)$width;
                $newHeight = (int)($newWidth / $aspectRatio);
                
                if ($newHeight > $height) {
                    $newHeight = $height;
                    $newWidth = intval($newHeight * $aspectRatio);
                }
            } else {
                $newHeight = $height;
                $newWidth = intval($newHeight * $aspectRatio);
                
                if ($newWidth > $width) {
                    $newWidth = $width;
                    $newHeight = intval($newWidth / $aspectRatio);
                }
            }
            
            $thumbImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Behandlung für Transparenz bei PNG
            if ($sourceType === IMAGETYPE_PNG) {
                imagealphablending($thumbImage, false);
                imagesavealpha($thumbImage, true);
                $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
                imagefilledrectangle($thumbImage, 0, 0, $newWidth, $newHeight, $transparent);
            }
            
            imagecopyresampled($thumbImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
        }
        
        // Speichere Thumbnail
        $success = false;
        switch ($sourceType) {
            case IMAGETYPE_JPEG:
                $success = imagejpeg($thumbImage, $destPath, THUMB_QUALITY);
                break;
            case IMAGETYPE_PNG:
                // PNG-Qualität ist von 0-9, daher umrechnen
                $pngQuality = floor((100 - THUMB_QUALITY) / 10);
                $success = imagepng($thumbImage, $destPath, $pngQuality);
                break;
            case IMAGETYPE_GIF:
                $success = imagegif($thumbImage, $destPath);
                break;
        }
        
        // Ressourcen freigeben
        // Fallback zum Originalbild wenn Thumbnail fehlschlägt
        if (!$success && file_exists($sourcePath)) {
            copy($sourcePath, $destPath);
            error_log("Thumbnail-Generierung fehlgeschlagen, verwende Original: $sourcePath");
            $success = true;
        }
        
        imagedestroy($sourceImage);
        imagedestroy($thumbImage);
        
        return $success;
    }
    
    /**
     * Rotiert ein Bild basierend auf EXIF-Daten
     * 
     * @param string $imagePath Pfad zum Bild
     * @return bool Erfolg/Misserfolg
     */
    public static function autoRotateImage($imagePath)
    {
        if (!function_exists('exif_read_data') || !file_exists($imagePath)) {
            return false;
        }
        
        $exif = @exif_read_data($imagePath);
        if (!$exif || !isset($exif['Orientation'])) {
            return false;
        }
        
        $orientation = $exif['Orientation'];
        $rotationNeeded = false;
        $degrees = 0;
        
        switch ($orientation) {
            case 3:
                $degrees = 180;
                $rotationNeeded = true;
                break;
            case 6:
                $degrees = 270;
                $rotationNeeded = true;
                break;
            case 8:
                $degrees = 90;
                $rotationNeeded = true;
                break;
            default:
                return true; // Keine Rotation notwendig
        }
        
        if ($rotationNeeded) {
            return self::rotateImage($imagePath, $degrees);
        }
        
        return true;
    }
    
    /**
     * Rotiert ein Bild um den angegebenen Winkel und speichert es
     * 
     * @param string $imagePath Pfad zum Bild
     * @param int $rotation Rotation in Grad (0, 90, 180, 270)
     * @return bool Erfolg/Misserfolg
     */
    public static function rotateImage($imagePath, $rotation)
    {
        if (!file_exists($imagePath)) {
            return false;
        }
        
        // Nur bei tatsächlicher Rotation etwas tun
        if ($rotation == 0) {
            return true;
        }
        
        // Bildtyp erkennen
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        
        try {
            // Bild laden
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($imagePath);
                    break;
                case 'png':
                    $image = imagecreatefrompng($imagePath);
                    break;
                case 'gif':
                    $image = imagecreatefromgif($imagePath);
                    break;
                default:
                    return false;
            }
            
            if (!$image) {
                return false;
            }
            
            // Rotieren
            $rotatedImage = imagerotate($image, $rotation, 0);
            
            // Speichern
            $result = false;
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $result = imagejpeg($rotatedImage, $imagePath, 95);
                    break;
                case 'png':
                    $result = imagepng($rotatedImage, $imagePath, 0);
                    break;
                case 'gif':
                    $result = imagegif($rotatedImage, $imagePath);
                    break;
            }
            
            // Ressourcen freigeben
            imagedestroy($image);
            imagedestroy($rotatedImage);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Fehler beim Rotieren des Bildes: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Extrahiert EXIF-Metadaten aus einem Bild
     * 
     * @param string $imagePath Pfad zum Bild
     * @return array|bool Array mit Metadaten oder false bei Fehler
     */
    public static function getImageMetadata($imagePath)
    {
        // Prüfen ob die exif-Erweiterung verfügbar ist
        if (!function_exists('exif_read_data') || !file_exists($imagePath)) {
            // Fallback: Nur Grundlegende Metadaten ohne EXIF
            $metadata = [];
            
            // Bildgröße ermitteln
            if (list($width, $height) = getimagesize($imagePath)) {
                $metadata['width'] = $width;
                $metadata['height'] = $height;
                $metadata['date_taken'] = date('Y-m-d H:i:s', filemtime($imagePath));
            }
            
            return $metadata;
        }
        
        try {
            $exif = @exif_read_data($imagePath, 'ANY_TAG', true);
        } catch (Exception $e) {
            return [];
        }
        
        if (!$exif) {
            return [];
        }
        
        $metadata = [];
        
        // Grundlegende EXIF-Daten extrahieren
        if (isset($exif['COMPUTED']['Width'])) $metadata['width'] = $exif['COMPUTED']['Width'];
        if (isset($exif['COMPUTED']['Height'])) $metadata['height'] = $exif['COMPUTED']['Height'];
        
        // Zeitstempel der Aufnahme
        if (isset($exif['EXIF']['DateTimeOriginal'])) {
            $metadata['date_taken'] = $exif['EXIF']['DateTimeOriginal'];
        } elseif (isset($exif['IFD0']['DateTime'])) {
            $metadata['date_taken'] = $exif['IFD0']['DateTime'];
        } else {
            $metadata['date_taken'] = date('Y-m-d H:i:s', filemtime($imagePath));
        }
        
        // Kameradaten
        if (isset($exif['IFD0']['Make'])) $metadata['camera_make'] = $exif['IFD0']['Make'];
        if (isset($exif['IFD0']['Model'])) $metadata['camera_model'] = $exif['IFD0']['Model'];
        
        // GPS-Daten
        $gpsData = self::extractGpsData($exif);
        if ($gpsData) {
            $metadata['gps'] = $gpsData;
        }
        
        return $metadata;
    }
    
    /**
     * Extrahiert GPS-Daten aus EXIF-Metadaten
     * 
     * @param array $exif EXIF-Daten
     * @return array|bool GPS-Daten oder false
     */
    private static function extractGpsData($exif)
    {
        if (!isset($exif['GPS']) || empty($exif['GPS'])) {
            return false;
        }
        
        // Prüfen, ob die grundlegenden GPS-Daten vorhanden sind
        if (!isset($exif['GPS']['GPSLatitude']) || !isset($exif['GPS']['GPSLongitude']) ||
            !isset($exif['GPS']['GPSLatitudeRef']) || !isset($exif['GPS']['GPSLongitudeRef'])) {
            return false;
        }
        
        $lat = self::convertGpsToDecimal($exif['GPS']['GPSLatitude']);
        $long = self::convertGpsToDecimal($exif['GPS']['GPSLongitude']);
        
        // Referenz berücksichtigen (N/S, E/W)
        if ($exif['GPS']['GPSLatitudeRef'] == 'S') $lat = -$lat;
        if ($exif['GPS']['GPSLongitudeRef'] == 'W') $long = -$long;
        
        return [
            'latitude' => $lat,
            'longitude' => $long,
            'formatted' => abs($lat) . '° ' . ($lat >= 0 ? 'N' : 'S') . ', ' .
                          abs($long) . '° ' . ($long >= 0 ? 'E' : 'W')
        ];
    }
    
    /**
     * Konvertiert GPS-Koordinaten ins Dezimalformat
     * 
     * @param array $parts Array mit Grad, Minuten, Sekunden
     * @return float Dezimalwert
     */
    private static function convertGpsToDecimal($parts)
    {
        if (count($parts) != 3) {
            return 0;
        }
        
        // Format: Grad, Minuten, Sekunden
        $deg = self::convertToDecimal($parts[0]);
        $min = self::convertToDecimal($parts[1]);
        $sec = self::convertToDecimal($parts[2]);
        
        return $deg + ($min / 60) + ($sec / 3600);
    }
    
    /**
     * Konvertiert EXIF-Rationalwerte ins Dezimalformat
     * 
     * @param string $rational Rational-Wert im Format "A/B"
     * @return float Dezimalwert
     */
    private static function convertToDecimal($rational)
    {
        if (!is_array($rational) && preg_match('/(\d+)\/(\d+)/', $rational, $parts)) {
            return intval($parts[1]) / intval($parts[2]);
        }
        if (count($parts) == 2 && intval($parts[1]) != 0) {
            return intval($parts[0]) / intval($parts[1]);
        }
        return 0;
    }
    
    /**
     * Prüft, ob FFmpeg auf dem System installiert ist
     *
     * @return bool true wenn FFmpeg verfügbar ist, sonst false
     */
    public static function isFFmpegAvailable()
    {
        static $available = null;
        
        if ($available === null) {
            // Prüfe nur einmal pro Request
            $output = [];
            $returnCode = -1;
            @exec("ffmpeg -version 2>&1", $output, $returnCode);
            $available = ($returnCode === 0);
            
            if (!$available) {
                error_log("FFmpeg ist nicht installiert oder nicht im PATH. Video-Thumbnails können nicht erstellt werden.");
            }
        }
        
        return $available;
    }
    
    /**
     * Ermittelt die Länge eines Videos in Sekunden
     *
     * @param string $videoPath Pfad zum Video
     * @return float|false Länge in Sekunden oder false bei Fehler
     */
    public static function getVideoDuration($videoPath)
    {
        if (!self::isFFmpegAvailable() || !file_exists($videoPath)) {
            return false;
        }
        
        // FFprobe-Befehl zum Ermitteln der Videolänge
        $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " .
               escapeshellarg($videoPath) . " 2>&1";
        
        $output = [];
        $returnCode = -1;
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0 || empty($output)) {
            error_log("Fehler beim Ermitteln der Videolänge: " . implode(" ", $output));
            return false;
        }
        
        // Versuche die Ausgabe als Float zu parsen
        $duration = (float)trim($output[0]);
        return ($duration > 0) ? $duration : false;
    }
    
    /**
     * Erzeugt ein Thumbnail aus einem Video
     *
     * @param string $sourcePath Quellpfad des Videos
     * @param string $destPath Zielpfad des Thumbnails
     * @param int $width Breite des Thumbnails
     * @param int $height Höhe des Thumbnails
     * @param int $framePos Position des Frames in Sekunden (Standard: 10 Sekunden)
     * @return bool true bei Erfolg, false bei Fehler
     */
    public static function createVideoThumbnail($sourcePath, $destPath, $width = THUMB_WIDTH, $height = THUMB_HEIGHT, $framePos = 10)
    {
        // Prüfe, ob FFmpeg verfügbar ist
        if (!self::isFFmpegAvailable()) {
            error_log("Konnte Video-Thumbnail nicht erstellen: FFmpeg ist nicht installiert");
            return false;
        }
        
        if (!file_exists($sourcePath)) {
            error_log("Video-Quelldatei nicht gefunden: $sourcePath");
            return false;
        }
        
        // Ermittle die Länge des Videos
        $duration = self::getVideoDuration($sourcePath);
        
        // Wenn die Länge nicht ermittelt werden konnte oder das Video kürzer als der gewünschte Frame ist
        if ($duration === false) {
            error_log("Konnte Videolänge nicht ermitteln, verwende Standard-Frame bei {$framePos}s");
        } elseif ($duration < $framePos) {
            // Wenn das Video kürzer als der gewünschte Frame ist, nehme die Hälfte der Videolänge
            $newFramePos = max(0, $duration / 2);
            error_log("Video ist kürzer als {$framePos}s, verwende Frame bei {$newFramePos}s");
            $framePos = $newFramePos;
        }
        
        // Temporärer Pfad für den extrahierten Frame
        $tempFramePath = tempnam(sys_get_temp_dir(), 'vtmp_') . '.jpg';
        
        // FFmpeg-Befehl zum Extrahieren eines Frames
        $ffmpegCmd = sprintf('ffmpeg -i %s -ss %f -vframes 1 -f image2 -y %s 2>&1',
                          escapeshellarg($sourcePath),
                          $framePos,
                          escapeshellarg($tempFramePath));
        
        // FFmpeg ausführen
        $output = [];
        $returnCode = -1;
        exec($ffmpegCmd, $output, $returnCode);
        
        // Prüfen, ob der Frame erfolgreich extrahiert wurde
        if ($returnCode !== 0 || !file_exists($tempFramePath)) {
            error_log("Fehler beim Extrahieren des Video-Frames: " . implode("\n", $output));
            return false;
        }
        
        // Jetzt den Frame zum Thumbnail verarbeiten
        $success = self::createThumbnail($tempFramePath, $destPath, $width, $height, true);
        
        // Temporäre Datei löschen
        if (file_exists($tempFramePath)) {
            unlink($tempFramePath);
        }
        
        return $success;
    }
}