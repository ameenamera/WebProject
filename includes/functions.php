<?php
/**
 * Fonctions utilitaires partagées.
 */

require_once __DIR__ . '/db.php';

// ---------------------------------------------------------------- Affichage

/** Échappe une valeur pour l'affichage HTML. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Construit une URL absolue depuis la racine publique. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Redirige puis stoppe le script. */
function redirect(string $path): void
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

/** Formate une date SQL en jj/mm/aaaa. */
function dateFr(?string $date, string $format = 'd/m/Y'): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

/** Initiales d'une personne, pour les avatars. */
function initials(string $nom, string $prenom = ''): string
{
    return strtoupper(mb_substr($prenom ?: $nom, 0, 1) . mb_substr($prenom ? $nom : '', 0, 1));
}

// ---------------------------------------------------------------- Messages flash

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// ---------------------------------------------------------------- Sécurité CSRF

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/** Vérifie le jeton CSRF d'une requête POST ; stoppe si invalide. */
function checkCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Jeton de sécurité invalide ou expiré. Revenez en arrière et réessayez.');
    }
}

// ---------------------------------------------------------------- Entrées

/** Récupère une valeur POST nettoyée. */
function post(string $key, $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

/** Récupère une valeur GET nettoyée. */
function get(string $key, $default = ''): string
{
    return trim((string) ($_GET[$key] ?? $default));
}

/** Retourne un entier positif, ou null. */
function intOrNull($value): ?int
{
    return ($value === '' || $value === null || (int) $value <= 0) ? null : (int) $value;
}

// ---------------------------------------------------------------- Métier

/**
 * Taux d'avancement d'un stage = tâches validées / tâches totales.
 * @return array{total:int, validees:int, attente:int, rejetees:int, taux:int}
 */
function progressionStage(int $idStage): array
{
    $row = fetchOne(
        "SELECT COUNT(*) AS total,
                SUM(etat = 'Validée')   AS validees,
                SUM(etat = 'En attente') AS attente,
                SUM(etat = 'Rejetée')   AS rejetees
         FROM tache WHERE id_stage = ?",
        [$idStage]
    );

    $total = (int) ($row['total'] ?? 0);

    return [
        'total'    => $total,
        'validees' => (int) ($row['validees'] ?? 0),
        'attente'  => (int) ($row['attente'] ?? 0),
        'rejetees' => (int) ($row['rejetees'] ?? 0),
        'taux'     => $total > 0 ? (int) round(((int) $row['validees'] / $total) * 100) : 0,
    ];
}

/** Classe CSS associée à un statut de stage. */
function statutBadge(string $statut): string
{
    return match ($statut) {
        'En cours' => 'badge badge--info',
        'Terminé'  => 'badge badge--success',
        'Annulé'   => 'badge badge--muted',
        default    => 'badge',
    };
}

/** Classe CSS associée à un état de tâche. */
function etatBadge(string $etat): string
{
    return match ($etat) {
        'Validée'    => 'badge badge--success',
        'En attente' => 'badge badge--warning',
        'Rejetée'    => 'badge badge--danger',
        default      => 'badge',
    };
}

/** Formate un poids en octets de façon lisible. */
function formatSize(?int $bytes): string
{
    if (!$bytes) {
        return '—';
    }
    $units = ['o', 'Ko', 'Mo', 'Go'];
    $i = (int) floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);
    return round($bytes / (1024 ** $i), 1) . ' ' . $units[$i];
}

/** Liste des services (pour les listes déroulantes). */
function listeServices(): array
{
    return fetchAll('SELECT * FROM service ORDER BY nom_service');
}

/** Liste des encadrants. */
function listeEncadrants(): array
{
    return fetchAll('SELECT * FROM encadrant ORDER BY nom, prenom');
}

/** Liste des types de stage. */
function listeTypes(): array
{
    return fetchAll('SELECT * FROM type_stage ORDER BY libelle');
}

/** Liste des instituts. */
function listeInstituts(): array
{
    return fetchAll('SELECT * FROM institut ORDER BY nom_institut');
}
