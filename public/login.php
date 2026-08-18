<?php
/**
 * Connexion — trois espaces : administrateur, encadrant, stagiaire.
 */

require_once __DIR__ . '/../includes/auth.php';

if (estConnecte()) {
    redirect('dashboard.php');
}

$roles = [
    'admin'     => 'Administrateur',
    'encadrant' => 'Encadrant',
    'stagiaire' => 'Stagiaire',
];

$role   = get('role', 'admin');
$role   = array_key_exists($role, $roles) ? $role : 'admin';
$erreur = '';
$login  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    $role  = array_key_exists(post('role'), $roles) ? post('role') : 'admin';
    $login = post('login');
    $mdp   = $_POST['mot_de_passe'] ?? '';

    if ($login === '' || $mdp === '') {
        $erreur = 'Veuillez saisir votre identifiant et votre mot de passe.';
    } elseif (tentativeConnexion($login, $mdp, $role)) {
        flash('Bienvenue, ' . utilisateur()['nom_complet'] . ' !');
        redirect('dashboard.php');
    } else {
        $erreur = 'Identifiants incorrects pour l\'espace « ' . $roles[$role] . ' ».';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
</head>
<body>
<div class="login-page">

    <aside class="login-aside">
        <div class="login-aside__content">
            <div class="login-aside__logo">
                <span>🎓</span> <?= e(APP_NAME) ?>
            </div>

            <div class="login-aside__info">
                <h2>Gestion Complète<br>des Stages</h2>
                <p>Une plateforme intégrée pour simplifier le suivi, l'évaluation et la gestion administrative de vos stagères.</p>
                <ul class="login-features">
                    <li>Dossiers stagiaires &amp; suivi administratif</li>
                    <li>Tâches et progression en temps réel</li>
                    <li>Évaluations et rapports numériques</li>
                    <li>Génération de documents officiels</li>
                </ul>
            </div>
        </div>
        <div class="login-aside__foot">&copy; <?= date('Y') ?> — Plateforme de Gestion des Stages</div>
    </aside>

    <main class="login-main">
        <div class="login-container">
            <div class="login-header">
                <h1>Connexion</h1>
                <p>Accédez à votre espace</p>
            </div>

            <div class="role-selector">
                <?php foreach ($roles as $key => $libelle): ?>
                    <a class="role-btn<?= $role === $key ? ' is-active' : '' ?>"
                       href="?role=<?= e($key) ?>" title="<?= e($libelle) ?>">
                       <span class="role-btn__label"><?= e($libelle) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($erreur): ?>
                <div class="alert alert--error" style="margin-bottom: 24px;">
                    <span><?= e($erreur) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off" class="login-form">
                <?= csrfField() ?>
                <input type="hidden" name="role" value="<?= e($role) ?>">

                <div class="form-group">
                    <input class="input-field" type="text" id="login" name="login"
                           value="<?= e($login) ?>" required autofocus
                           placeholder="Identifiant ou email">
                </div>

                <div class="form-group">
                    <input class="input-field" type="password" id="mot_de_passe" name="mot_de_passe"
                           required placeholder="Mot de passe">
                </div>

                <button class="btn-signin" type="submit">
                    <span>Se connecter</span>
                    <span class="btn-signin__arrow">→</span>
                </button>
            </form>

            <div class="login-credentials">
                <div class="credentials-header">Test Accounts</div>
                <div class="credentials-grid">
                    <div class="credential-item">
                        <span class="credential-role">Admin</span>
                        <span class="credential-value">admin / admin123</span>
                    </div>
                    <div class="credential-item">
                        <span class="credential-role">Encadrant</span>
                        <span class="credential-value">encadrant / encadrant123</span>
                    </div>
                    <div class="credential-item">
                        <span class="credential-role">Stagiaire</span>
                        <span class="credential-value">stagiaire / stagiaire123</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

</div>
</body>
</html>
