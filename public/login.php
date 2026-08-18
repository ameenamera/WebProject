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
        <div class="login-aside__logo">
            <span>🎓</span> <?= e(APP_NAME) ?>
        </div>

        <div>
            <h2>Pilotez vos stages<br>de bout en bout.</h2>
            <p>
                Une plateforme unique pour gérer les stagiaires, suivre les tâches
                quotidiennes, centraliser les documents et évaluer chaque stage.
            </p>
            <ul class="login-features">
                <li>Dossiers stagiaires &amp; affectation par service</li>
                <li>Suivi des tâches et taux d'avancement</li>
                <li>Convention, rapport et attestation en ligne</li>
                <li>Évaluation et décision finale</li>
            </ul>
        </div>

        <div class="login-aside__foot">&copy; <?= date('Y') ?> — Tous droits réservés</div>
    </aside>

    <main class="login-main">
        <div class="login-box">
            <h1>Connexion</h1>
            <p>Choisissez votre espace, puis identifiez-vous.</p>

            <div class="role-tabs">
                <?php foreach ($roles as $key => $libelle): ?>
                    <a class="role-tab<?= $role === $key ? ' is-active' : '' ?>"
                       href="?role=<?= e($key) ?>"><?= e($libelle) ?></a>
                <?php endforeach; ?>
            </div>

            <?php if ($erreur): ?>
                <div class="alert alert--error"><span><?= e($erreur) ?></span></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <?= csrfField() ?>
                <input type="hidden" name="role" value="<?= e($role) ?>">

                <div class="field">
                    <label for="login">Identifiant</label>
                    <input class="input" type="text" id="login" name="login"
                           value="<?= e($login) ?>" required autofocus
                           placeholder="ex. admin">
                </div>

                <div class="field">
                    <label for="mot_de_passe">Mot de passe</label>
                    <input class="input" type="password" id="mot_de_passe" name="mot_de_passe"
                           required placeholder="••••••••">
                </div>

                <button class="btn btn--block" type="submit">
                    Se connecter en tant que <?= e(strtolower($roles[$role])) ?>
                </button>
            </form>

            <div class="login-demo">
                <strong>Comptes de démonstration</strong>
                Administrateur : <code>admin</code> / <code>admin123</code><br>
                Encadrant : <code>encadrant</code> / <code>encadrant123</code><br>
                Stagiaire : <code>stagiaire</code> / <code>stagiaire123</code>
            </div>
        </div>
    </main>

</div>
</body>
</html>
