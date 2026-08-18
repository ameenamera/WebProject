<?php
/**
 * Téléchargement d'un document.
 * Le fichier est servi par PHP : le dossier /uploads reste inaccessible
 * directement, et les droits sont vérifiés avant l'envoi.
 */

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion();

$id  = intOrNull(get('id'));
$doc = $id ? fetchOne('SELECT * FROM document WHERE id_document = ?', [$id]) : null;

if (!$doc) {
    http_response_code(404);
    exit('Document introuvable.');
}

if (!peutVoirStage((int) $doc['id_stage'])) {
    http_response_code(403);
    exit('Accès refusé à ce document.');
}

// basename() neutralise toute tentative de remontée de répertoire.
$chemin = UPLOAD_DIR . '/' . basename($doc['chemin']);

if (!is_file($chemin)) {
    http_response_code(404);
    exit('Le fichier n\'existe plus sur le serveur.');
}

$types = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
];
$ext = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));

header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . basename($doc['nom_fichier']) . '"');
header('Content-Length: ' . filesize($chemin));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($chemin);
exit;
