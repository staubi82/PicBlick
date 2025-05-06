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
        
        list($sourceWidth, $sourceHeight, $sourceType) = getimagesize($sourcePath);
        
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
                return false; // Nicht unterstütztes Format
        }
        
        if (!$sourceImage) {
            return false;
        }
        
        if ($crop) {
            // Bild zuschneiden
            $aspectRatio = $sourceWidth / $sourceHeight;
            $thumbRatio = $width / $height;
            
            if ($aspectRatio > $thumbRatio) {
                // Originalbild ist breiter
                $newHeight = $sourceHeight;
                $newWidth = $sourceHeight * $thumbRatio;
                $cropX = ($sourceWidth - $newWidth) / 2;
                $cropY = 0;
            } else {
                // Originalbild ist höher
                $newWidth = $sourceWidth;
                $newHeight = $sourceWidth / $thumbRatio;
                $cropX = 0;
                $cropY = ($sourceHeight - $newHeight) / 2;
            }
            
            $thumbImage = imagecreatetruecolor($width, $height);
            
            // Transparenz für PNG beibehalten
            if ($sourceType === IMAGETYPE_PNG) {
                imagealphablending($thumbImage, false);
                imagesavealpha($thumbImage, true);
                $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
                imagefilledrectangle($thumbImage, 0, 0, $width, $height, $transparent);
            }
            
            imagecopyresampled($thumbImage, $sourceImage, 0, 0, (int)$cropX, (int)$cropY, (int)$width, (int)$height, (int)$newWidth, (int)$newHeight);
        } else {
            // Proportional skalieren
            $aspectRatio = $sourceWidth / $sourceHeight;
            
            if ($width / $height > $aspectRatio) {
                $newWidth = $height * $aspectRatio;
                $newHeight = $height;
            } else {
                $newHeight = $width / $aspectRatio;
                $newWidth = $width;
            }
            
            $thumbImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Transparenz für PNG beibehalten
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
        }
        
        imagedestroy($sourceImage);
        imagedestroy($thumbImage);
        
        return $success;
    }
    
    /**
     * Bereinigt EXIF-Daten aus einem Bild falls aktiviert
     * 
     * @param string $imagePath Pfad zum Bild
     * @return bool true bei Erfolg, false bei Fehler
     */
    public static function stripExif($imagePath)
    {
        if (!STRIP_EXIF || !file_exists($imagePath)) {
            return false;
        }
        
        // Prüfen ob die exif-Erweiterung verfügbar ist
        if (!function_exists('exif_imagetype')) {
            // Fallback: Bildtyp anhand der Dateiendung bestimmen
            $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            $isJpeg = in_array($extension, ['jpg', 'jpeg']);
        } else {
            // EXIF-Funktion ist verfügbar
            $imageType = exif_imagetype($imagePath);
            $isJpeg = ($imageType === IMAGETYPE_JPEG);
        }
        
        // Funktioniert nur für JPEG
        if (!$isJpeg) {
            return false;
        }
        
        $image = imagecreatefromjpeg($imagePath);
        if (!$image) {
            return false;
        }
        
        // Speichere ohne EXIF
        $success = imagejpeg($image, $imagePath, 100); // Originale Qualität beibehalten
        imagedestroy($image);
        
        return $success;
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
            
            // Bild rotieren (Winkel muss negativ sein für den Uhrzeigersinn)
            $rotated = imagerotate($image, -$rotation, 0);
            
            if (!$rotated) {
                imagedestroy($image);
                return false;
            }
            
            // Bild speichern
            $success = false;
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $success = imagejpeg($rotated, $imagePath, 95);
                    break;
                case 'png':
                    $success = imagepng($rotated, $imagePath, 9);
                    break;
                case 'gif':
                    $success = imagegif($rotated, $imagePath);
                    break;
            }
            
            // Speicher freigeben
            imagedestroy($image);
            imagedestroy($rotated);
            
            return $success;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Rotiert ein Bild basierend auf EXIF-Orientierung
     * 
     * @param string $imagePath Pfad zum Bild
     * @return bool true bei Erfolg, false bei Fehler
     */
    public static function autoRotateImage($imagePath)
    {
        // Prüfen ob die exif-Erweiterung verfügbar ist
        if (!function_exists('exif_read_data') || !file_exists($imagePath)) {
            return false;
        }
        
        try {
            $exif = @exif_read_data($imagePath);
        } catch (Exception $e) {
            return false;
        }
        
        if (!$exif || !isset($exif['Orientation'])) {
            return true; // Keine Rotation nötig
        }
        
        $orientation = $exif['Orientation'];
        if ($orientation == 1) {
            return true; // Bereits korrekt ausgerichtet
        }
        
        $image = imagecreatefromjpeg($imagePath);
        
        switch ($orientation) {
            case 3:
                $image = imagerotate($image, 180, 0);
                break;
            case 6:
                $image = imagerotate($image, -90, 0);
                break;
            case 8:
                $image = imagerotate($image, 90, 0);
                break;
        }
        
        $success = imagejpeg($image, $imagePath, 100);
        imagedestroy($image);
        
        return $success;
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
        
        // Extrahiere relevante Metadaten
        $metadata = [];
        
        // Kamera-Informationen
        if (isset($exif['IFD0']['Make'])) {
            $metadata['camera_make'] = $exif['IFD0']['Make'];
        }
        
        if (isset($exif['IFD0']['Model'])) {
            $metadata['camera_model'] = $exif['IFD0']['Model'];
        }
        
        // Aufnahme-Einstellungen
        if (isset($exif['EXIF']['ExposureTime'])) {
            $metadata['exposure'] = $exif['EXIF']['ExposureTime'];
        }
        
        if (isset($exif['EXIF']['FNumber'])) {
            $metadata['aperture'] = 'f/' . $exif['EXIF']['FNumber'];
        }
        
        if (isset($exif['EXIF']['ISOSpeedRatings'])) {
            $metadata['iso'] = $exif['EXIF']['ISOSpeedRatings'];
        }
        
        if (isset($exif['EXIF']['FocalLength'])) {
            $metadata['focal_length'] = $exif['EXIF']['FocalLength'] . 'mm';
        }
        
        // Aufnahmedatum
        if (isset($exif['EXIF']['DateTimeOriginal'])) {
            $metadata['date_taken'] = $exif['EXIF']['DateTimeOriginal'];
        }
        
        // GPS-Koordinaten (falls vorhanden)
        if (isset($exif['GPS']) && isset($exif['GPS']['GPSLatitude']) && isset($exif['GPS']['GPSLongitude'])) {
            $metadata['gps'] = self::formatGpsCoordinates($exif['GPS']);
        }
        
        return $metadata;
    }
    
    /**
     * Formatiert GPS-Koordinaten aus EXIF-Daten
     * 
     * @param array $gpsData GPS-EXIF-Daten
     * @return array Formatierte GPS-Daten
     */
    private static function formatGpsCoordinates($gpsData)
    {
        if (!isset($gpsData['GPSLatitude']) || !isset($gpsData['GPSLongitude'])) {
            return null;
        }
        
        $latParts = $gpsData['GPSLatitude'];
        $latRef = isset($gpsData['GPSLatitudeRef']) ? $gpsData['GPSLatitudeRef'] : 'N';
        
        $longParts = $gpsData['GPSLongitude'];
        $longRef = isset($gpsData['GPSLongitudeRef']) ? $gpsData['GPSLongitudeRef'] : 'E';
        
        $lat = self::convertGpsToDecimal($latParts);
        $lat = ($latRef == 'S') ? -$lat : $lat;
        
        $long = self::convertGpsToDecimal($longParts);
        $long = ($longRef == 'W') ? -$long : $long;
        
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
     * @param string $rational Rationalwert (z.B. "1/100")
     * @return float Dezimalwert
     */
    private static function convertToDecimal($rational)
    {
        $parts = explode('/', $rational);
        if (count($parts) == 1) {
            return floatval($parts[0]);
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
            error_log("Fehler beim Ermitteln der Videolänge: " . implode("\n", $output));
            return false;
        }
        
        return floatval(trim($output[0]));
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
            error_log("Video ist kürzer als {$framePos}s (tatsächlich: {$duration}s), verwende Frame bei {$newFramePos}s");
            $framePos = $newFramePos;
        }
        
        // Stelle sicher, dass der Zielordner existiert
        $destDir = dirname($destPath);
        if (!file_exists($destDir)) {
            if (!mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                error_log("Konnte Zielverzeichnis nicht erstellen: $destDir");
                return false;
            }
        }

        // Temporäre Datei für den Frame
        $tempFramePath = sys_get_temp_dir() . '/' . uniqid('frame_') . '.jpg';
        
        // FFmpeg-Befehl zum Extrahieren eines Frames
        $ffmpegCmd = "ffmpeg -i " . escapeshellarg($sourcePath) . " -ss " . floatval($framePos) .
                     " -frames:v 1 -q:v 2 " . escapeshellarg($tempFramePath) . " 2>&1";
        
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