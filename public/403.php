<?php
/** Page d'erreur : accès refusé. */
require_once __DIR__ . '/../includes/auth.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès refusé</title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<div style="min-height:100vh;display:grid;place-items:center;padding:24px">
    <div class="card" style="max-width:460px;text-align:center">
        <div class="card__body">
            <div style="font-size:52px;line-height:1">🔒</div>
            <h2 style="margin-top:12px">Accès refusé</h2>
            <p style="color:var(--ink-muted)">
                Votre profil ne vous autorise pas à consulter cette page.
            </p>
            <a class="btn" href="<?= url('dashboard.php') ?>">Retour au tableau de bord</a>
        </div>
    </div>
</div>
</body>
</html>
