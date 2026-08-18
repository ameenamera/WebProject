<?php
/**
 * Authentification et contrôle d'accès (admin / encadrant / stagiaire).
 */

require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Tente de connecter un utilisateur.
 * Le rôle attendu est imposé par l'onglet de connexion choisi.
 */
function tentativeConnexion(string $login, string $motDePasse, string $roleAttendu): bool
{
    $user = fetchOne(
        'SELECT * FROM utilisateur WHERE login = ? AND role = ? AND actif = 1',
        [$login, $roleAttendu]
    );

    if (!$user || !password_verify($motDePasse, $user['mot_de_passe'])) {
        return false;
    }

    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'           => (int) $user['id_utilisateur'],
        'login'        => $user['login'],
        'role'         => $user['role'],
        'id_stagiaire' => $user['id_stagiaire'] !== null ? (int) $user['id_stagiaire'] : null,
        'id_encadrant' => $user['id_encadrant'] !== null ? (int) $user['id_encadrant'] : null,
    ];
    $_SESSION['user']['nom_complet'] = nomCompletUtilisateur($_SESSION['user']);

    query('UPDATE utilisateur SET derniere_connexion = NOW() WHERE id_utilisateur = ?', [$user['id_utilisateur']]);

    return true;
}

/** Nom affiché dans l'entête, selon le profil lié au compte. */
function nomCompletUtilisateur(array $user): string
{
    if ($user['role'] === 'stagiaire' && $user['id_stagiaire']) {
        $row = fetchOne('SELECT nom, prenom FROM stagiaire WHERE id_stagiaire = ?', [$user['id_stagiaire']]);
    } elseif ($user['role'] === 'encadrant' && $user['id_encadrant']) {
        $row = fetchOne('SELECT nom, prenom FROM encadrant WHERE id_encadrant = ?', [$user['id_encadrant']]);
    } else {
        return 'Administrateur';
    }

    return $row ? $row['prenom'] . ' ' . $row['nom'] : $user['login'];
}

function deconnexion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function estConnecte(): bool
{
    return isset($_SESSION['user']);
}

/** Utilisateur courant, ou null. */
function utilisateur(): ?array
{
    return $_SESSION['user'] ?? null;
}

function role(): ?string
{
    return $_SESSION['user']['role'] ?? null;
}

function estAdmin(): bool
{
    return role() === 'admin';
}

function estEncadrant(): bool
{
    return role() === 'encadrant';
}

function estStagiaire(): bool
{
    return role() === 'stagiaire';
}

/** Bloque l'accès aux visiteurs non connectés. */
function exigerConnexion(): void
{
    if (!estConnecte()) {
        redirect('login.php');
    }
}

/**
 * Restreint une page à une liste de rôles.
 * @param string[] $roles
 */
function exigerRole(array $roles): void
{
    exigerConnexion();
    if (!in_array(role(), $roles, true)) {
        http_response_code(403);
        require __DIR__ . '/../public/403.php';
        exit;
    }
}

/**
 * Un encadrant ne voit que ses propres stages ; un stagiaire que le sien.
 * Renvoie un fragment SQL et remplit $params.
 */
function filtreStagesParRole(array &$params): string
{
    if (estEncadrant()) {
        $params[] = utilisateur()['id_encadrant'];
        return ' AND s.id_encadrant = ? ';
    }
    if (estStagiaire()) {
        $params[] = utilisateur()['id_stagiaire'];
        return ' AND s.id_stagiaire = ? ';
    }
    return '';
}

/** Vérifie que l'utilisateur courant a le droit de consulter ce stage. */
function peutVoirStage(int $idStage): bool
{
    if (estAdmin()) {
        return true;
    }
    if (estEncadrant()) {
        return (bool) fetchValue(
            'SELECT 1 FROM stage WHERE id_stage = ? AND id_encadrant = ?',
            [$idStage, utilisateur()['id_encadrant']]
        );
    }
    if (estStagiaire()) {
        return (bool) fetchValue(
            'SELECT 1 FROM stage WHERE id_stage = ? AND id_stagiaire = ?',
            [$idStage, utilisateur()['id_stagiaire']]
        );
    }
    return false;
}

/** Stoppe la page si le stage n'est pas accessible. */
function exigerAccesStage(int $idStage): void
{
    exigerConnexion();
    if (!peutVoirStage($idStage)) {
        http_response_code(403);
        require __DIR__ . '/../public/403.php';
        exit;
    }
}
