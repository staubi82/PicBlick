<?php
// Fehlerbehandlung aktivieren
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ausgabe der Umgebung für Diagnosezwecke, falls erforderlich
// echo "Dokument-Wurzel: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
// echo "Skript-Pfad: " . __FILE__ . "<br>";

// Ermittle den relativen Pfad abhängig von der Umgebung
$publicPath = 'public/index.php';

// Prüfen, ob die öffentliche Datei unter dem Standard-Pfad existiert
$scriptDir = dirname(__FILE__);
if (file_exists($scriptDir . '/' . $publicPath)) {
    // Standard-Pfad funktioniert
    header("Location: " . $publicPath);
    exit;
}

// Falls Standard nicht funktioniert, HTTP_HOST verwenden (für vHost-Setups)
if (isset($_SERVER['HTTP_HOST'])) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $fullUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/public/index.php';
    header("Location: " . $fullUrl);
    exit;
}

// Fallback, wenn nichts anderes funktioniert
echo "Fehler bei der Weiterleitung. Bitte besuchen Sie <a href='public/index.php'>public/index.php</a> manuell.";
exit;