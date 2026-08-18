<?php
/**
 * Configuration globale de l'application.
 */

// --- Base de données -----------------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'gestion_stages');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- Application ---------------------------------------------------------
define('APP_NAME', 'Gestion des Stages');
define('APP_ROOT', dirname(__DIR__));

// URL de base : détectée automatiquement (ex: /stage_Amine/public)
define('BASE_URL', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/'));

// --- Documents -----------------------------------------------------------
define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 Mo
const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
const DOCUMENT_TYPES     = ['Convention', 'Rapport', 'Attestation', 'Autre'];

// --- Métier --------------------------------------------------------------
const STAGE_STATUTS   = ['En cours', 'Terminé', 'Annulé'];
const TACHE_ETATS     = ['En attente', 'Validée', 'Rejetée'];
const PRESENCE_STATUTS = ['Present', 'Absent', 'Retard', 'Congé'];

// --- Encodage ------------------------------------------------------------
// Indispensable : sans cet en-tête le navigateur devine l'encodage et
// renvoie des accents mal encodés (« Validée », « Congé »…), que les
// contrôles in_array() rejetteraient alors silencieusement.
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

// --- Erreurs -------------------------------------------------------------
// Passer à 0 / 'Off' en production.
error_reporting(E_ALL);
ini_set('display_errors', '1');

date_default_timezone_set('Africa/Tunis');
