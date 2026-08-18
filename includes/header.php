<?php
/**
 * Entête + barre latérale. À inclure au début de chaque page protégée.
 * Variables attendues : $pageTitle (string), $activeMenu (string)
 */

require_once __DIR__ . '/auth.php';
exigerConnexion();

$pageTitle  = $pageTitle  ?? APP_NAME;
$activeMenu = $activeMenu ?? '';
$u          = utilisateur();

/** Renvoie ' is-active' si l'entrée de menu correspond à la page courante. */
function menuActive(string $key): string
{
    global $activeMenu;
    return $activeMenu === $key ? ' is-active' : '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
</head>
<body>
<div class="layout">

    <!-- ===================== Barre latérale ===================== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__brand">
            <span class="sidebar__logo">GS</span>
            <div>
                <strong>Gestion des Stages</strong>
                <small>Espace <?= e($u['role']) ?></small>
            </div>
        </div>

        <nav class="sidebar__nav">
            <a class="nav-item<?= menuActive('dashboard') ?>" href="<?= url('dashboard.php') ?>">
                <span class="nav-item__icon">▦</span> Tableau de bord
            </a>

            <?php if (estAdmin()): ?>
                <p class="nav-title">Gestion</p>
                <a class="nav-item<?= menuActive('stagiaires') ?>" href="<?= url('stagiaires.php') ?>">
                    <span class="nav-item__icon">👥</span> Stagiaires
                </a>
                <a class="nav-item<?= menuActive('encadrants') ?>" href="<?= url('encadrants.php') ?>">
                    <span class="nav-item__icon">🧑‍🏫</span> Encadrants
                </a>
                <a class="nav-item<?= menuActive('stages') ?>" href="<?= url('stages.php') ?>">
                    <span class="nav-item__icon">📋</span> Stages
                </a>
            <?php else: ?>
                <p class="nav-title">Suivi</p>
                <a class="nav-item<?= menuActive('stages') ?>" href="<?= url('stages.php') ?>">
                    <span class="nav-item__icon">📋</span>
                    <?= estStagiaire() ? 'Mon stage' : 'Mes stages' ?>
                </a>
            <?php endif; ?>

            <a class="nav-item<?= menuActive('taches') ?>" href="<?= url('taches.php') ?>">
                <span class="nav-item__icon">✓</span> Tâches
            </a>
            <a class="nav-item<?= menuActive('presence') ?>" href="<?= url('presence.php') ?>">
                <span class="nav-item__icon">🕘</span> Présence
            </a>
            <a class="nav-item<?= menuActive('documents') ?>" href="<?= url('documents.php') ?>">
                <span class="nav-item__icon">📎</span> Documents
            </a>
            <a class="nav-item<?= menuActive('evaluations') ?>" href="<?= url('evaluations.php') ?>">
                <span class="nav-item__icon">★</span> Évaluations
            </a>

            <?php if (estAdmin()): ?>
                <p class="nav-title">Paramètres</p>
                <a class="nav-item<?= menuActive('services') ?>" href="<?= url('services.php') ?>">
                    <span class="nav-item__icon">🏢</span> Services
                </a>
                <a class="nav-item<?= menuActive('types') ?>" href="<?= url('types_stage.php') ?>">
                    <span class="nav-item__icon">🏷️</span> Types de stage
                </a>
                <a class="nav-item<?= menuActive('instituts') ?>" href="<?= url('instituts.php') ?>">
                    <span class="nav-item__icon">🎓</span> Instituts
                </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar__footer">
            <div class="user-chip">
                <span class="avatar"><?= e(mb_substr($u['nom_complet'], 0, 1)) ?></span>
                <div class="user-chip__text">
                    <strong><?= e($u['nom_complet']) ?></strong>
                    <small><?= e($u['login']) ?></small>
                </div>
            </div>
            <a class="btn btn--ghost btn--block" href="<?= url('logout.php') ?>">Déconnexion</a>
        </div>
    </aside>

    <!-- ===================== Contenu ===================== -->
    <div class="main">
        <header class="topbar">
            <button class="topbar__toggle" onclick="document.getElementById('sidebar').classList.toggle('is-open')" aria-label="Menu">☰</button>
            <h1 class="topbar__title"><?= e($pageTitle) ?></h1>
            <div class="topbar__meta"><?= e(ucfirst(strftime_fr())) ?></div>
        </header>

        <main class="content">
            <?php foreach (getFlashes() as $f): ?>
                <div class="alert alert--<?= e($f['type']) ?>">
                    <span><?= e($f['message']) ?></span>
                    <button type="button" class="alert__close" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endforeach; ?>
<?php
/** Date du jour en français, sans dépendre de setlocale(). */
function strftime_fr(): string
{
    $jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $mois  = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin',
              'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return $jours[(int) date('w')] . ' ' . date('j') . ' ' . $mois[(int) date('n') - 1] . ' ' . date('Y');
}
