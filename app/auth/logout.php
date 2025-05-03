<?php
/**
 * Fotogalerie - Logout-Skript
 *
 * Meldet den Benutzer ab und leitet auf die Startseite weiter
 */

// Konfiguration und Bibliotheken laden
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../lib/database.php';
require_once __DIR__ . '/auth.php';

// Authentifizierung initialisieren
$auth = new Auth();

// Benutzer abmelden
$auth->logout();

// Weiterleitung zur Startseite
header('Location: /public/index.php');
exit;