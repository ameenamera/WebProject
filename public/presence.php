<?php
/**
 * Gestion des présences : pointage quotidien (entrée / sortie).
 */

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion();

$pageTitle  = 'Suivi des présences';
$activeMenu = 'presence';

$idStage = intOrNull(get('stage'));

if (estStagiaire() && !$idStage) {
    $idStage = intOrNull(fetchValue(
        'SELECT id_stage FROM stage WHERE id_stagiaire = ? ORDER BY date_debut DESC LIMIT 1',
        [utilisateur()['id_stagiaire']]
    ));
}
if ($idStage) {
    exigerAccesStage($idStage);
}

// ---------------------------------------------------------------- Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = post('action');

    if ($action === 'pointer') {
        $stageCible = intOrNull(post('id_stage'));
        $date       = post('date_presence') ?: date('Y-m-d');
        $entree     = post('heure_entree');
        $sortie     = post('heure_sortie');
        $statut     = post('statut');

        if (!$stageCible || !peutVoirStage($stageCible)) {
            flash('Stage invalide.', 'error');
        } elseif (!in_array($statut, PRESENCE_STATUTS, true)) {
            flash('Statut de présence invalide.', 'error');
        } elseif ($entree !== '' && $sortie !== '' && $sortie < $entree) {
            flash('L\'heure de sortie doit être postérieure à l\'heure d\'entrée.', 'error');
        } else {
            // Une seule ligne par jour et par stage (clé unique) : on met à jour si elle existe.
            query(
                'INSERT INTO presence (id_stage, date_presence, heure_entree, heure_sortie, statut)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE heure_entree = VALUES(heure_entree),
                                         heure_sortie = VALUES(heure_sortie),
                                         statut       = VALUES(statut)',
                [$stageCible, $date, $entree ?: null, $sortie ?: null, $statut]
            );
            flash('Pointage enregistré pour le ' . dateFr($date) . '.');
        }
        redirect('presence.php' . ($idStage ? '?stage=' . $idStage : ''));
    }

    if ($action === 'supprimer') {
        exigerRole(['admin', 'encadrant']);
        $idP = intOrNull(post('id_presence'));
        $p   = $idP ? fetchOne('SELECT * FROM presence WHERE id_presence = ?', [$idP]) : null;

        if ($p && peutVoirStage((int) $p['id_stage'])) {
            query('DELETE FROM presence WHERE id_presence = ?', [$idP]);
            flash('Pointage supprimé.');
        }
        redirect('presence.php' . ($idStage ? '?stage=' . $idStage : ''));
    }
}

// ---------------------------------------------------------------- Données
$mois = get('mois') ?: date('Y-m');

$params = [];
$sql = "SELECT p.*, s.sujet, st.nom, st.prenom,
               TIMEDIFF(p.heure_sortie, p.heure_entree) AS duree
        FROM presence p
        JOIN stage s      ON s.id_stage = p.id_stage
        JOIN stagiaire st ON st.id_stagiaire = s.id_stagiaire
        WHERE 1=1";
$sql .= filtreStagesParRole($params);

if ($idStage) { $sql .= ' AND p.id_stage = ?'; $params[] = $idStage; }
if (preg_match('/^\d{4}-\d{2}$/', $mois)) {
    $sql .= " AND DATE_FORMAT(p.date_presence, '%Y-%m') = ?";
    $params[] = $mois;
}

$sql .= ' ORDER BY p.date_presence DESC';
$presences = fetchAll($sql, $params);

// Compteurs du mois affiché.
$stats = ['Present' => 0, 'Absent' => 0, 'Retard' => 0, 'Congé' => 0];
foreach ($presences as $p) {
    if (isset($stats[$p['statut']])) {
        $stats[$p['statut']]++;
    }
}

$p2 = [];
$stagesDispo = fetchAll(
    "SELECT s.id_stage, s.sujet, st.nom, st.prenom
     FROM stage s JOIN stagiaire st ON st.id_stagiaire = s.id_stagiaire
     WHERE s.statut = 'En cours' " . filtreStagesParRole($p2) . ' ORDER BY st.nom',
    $p2
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h2 class="mb-0">Présences</h2>
        <p>Pointage quotidien des stagiaires</p>
    </div>
    <?php if ($idStage): ?>
        <a class="btn btn--secondary" href="<?= url('stage_details.php?id=' . $idStage) ?>">← Fiche du stage</a>
    <?php endif; ?>
</div>

<div class="stats">
    <div class="stat">
        <div class="stat__icon stat__icon--success">✓</div>
        <div>
            <div class="stat__value"><?= $stats['Present'] ?></div>
            <div class="stat__label">Présences</div>
        </div>
    </div>
    <div class="stat">
        <div class="stat__icon stat__icon--danger">✕</div>
        <div>
            <div class="stat__value"><?= $stats['Absent'] ?></div>
            <div class="stat__label">Absences</div>
        </div>
    </div>
    <div class="stat">
        <div class="stat__icon stat__icon--warning">⏱</div>
        <div>
            <div class="stat__value"><?= $stats['Retard'] ?></div>
            <div class="stat__label">Retards</div>
        </div>
    </div>
    <div class="stat">
        <div class="stat__icon stat__icon--info">🌴</div>
        <div>
            <div class="stat__value"><?= $stats['Congé'] ?></div>
            <div class="stat__label">Congés</div>
        </div>
    </div>
</div>

<!-- ==================== Pointage ==================== -->
<?php if ($stagesDispo): ?>
<section class="card">
    <div class="card__head">
        <div>
            <h3>Enregistrer un pointage</h3>
            <p>Un seul pointage par jour et par stage — un nouvel envoi met à jour la journée.</p>
        </div>
    </div>
    <div class="card__body">
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="pointer">

            <div class="form-grid">
                <div class="field">
                    <label for="id_stage">Stage <span class="req">*</span></label>
                    <select class="select" id="id_stage" name="id_stage" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($stagesDispo as $sd): ?>
                            <option value="<?= (int) $sd['id_stage'] ?>" <?= $idStage === (int) $sd['id_stage'] ? 'selected' : '' ?>>
                                <?= e($sd['prenom'] . ' ' . $sd['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="date_presence">Date <span class="req">*</span></label>
                    <input class="input" type="date" id="date_presence" name="date_presence" required
                           value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                </div>

                <div class="field">
                    <label for="statut">Statut</label>
                    <select class="select" id="statut" name="statut">
                        <?php foreach (PRESENCE_STATUTS as $s): ?>
                            <option value="<?= e($s) ?>"><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="heure_entree">Heure d'entrée</label>
                    <input class="input" type="time" id="heure_entree" name="heure_entree" value="08:30">
                </div>

                <div class="field">
                    <label for="heure_sortie">Heure de sortie</label>
                    <input class="input" type="time" id="heure_sortie" name="heure_sortie" value="17:00">
                </div>
            </div>

            <div class="form-actions">
                <button class="btn" type="submit">Enregistrer le pointage</button>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<!-- ==================== Registre ==================== -->
<section class="card">
    <div class="card__head">
        <h3>Registre des présences</h3>
        <form method="get" class="toolbar" style="margin:0">
            <?php if ($idStage): ?><input type="hidden" name="stage" value="<?= $idStage ?>"><?php endif; ?>
            <div class="field">
                <label for="mois">Mois</label>
                <input class="input" type="month" id="mois" name="mois" value="<?= e($mois) ?>" data-auto-submit>
            </div>
            <button class="btn btn--secondary" type="submit">Afficher</button>
        </form>
    </div>
    <div class="card__body card__body--flush">
        <?php if (!$presences): ?>
            <div class="empty">
                <span class="empty__icon">🕘</span>
                <p>Aucun pointage enregistré pour cette période.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <?php if (!estStagiaire()): ?><th>Stagiaire</th><?php endif; ?>
                        <th>Entrée</th>
                        <th>Sortie</th>
                        <th>Durée</th>
                        <th>Statut</th>
                        <?php if (estAdmin() || estEncadrant()): ?><th class="text-right">Actions</th><?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($presences as $p): ?>
                        <tr>
                            <td class="nowrap"><?= dateFr($p['date_presence']) ?></td>
                            <?php if (!estStagiaire()): ?>
                                <td><strong><?= e($p['prenom'] . ' ' . $p['nom']) ?></strong></td>
                            <?php endif; ?>
                            <td class="muted"><?= $p['heure_entree'] ? substr($p['heure_entree'], 0, 5) : '—' ?></td>
                            <td class="muted"><?= $p['heure_sortie'] ? substr($p['heure_sortie'], 0, 5) : '—' ?></td>
                            <td class="muted"><?= $p['duree'] ? substr($p['duree'], 0, 5) : '—' ?></td>
                            <td>
                                <?php $cls = match ($p['statut']) {
                                    'Present' => 'badge badge--success',
                                    'Absent'  => 'badge badge--danger',
                                    'Retard'  => 'badge badge--warning',
                                    default   => 'badge badge--info',
                                }; ?>
                                <span class="<?= $cls ?>"><?= e($p['statut']) ?></span>
                            </td>
                            <?php if (estAdmin() || estEncadrant()): ?>
                                <td>
                                    <div class="table__actions" style="justify-content:flex-end">
                                        <form method="post" style="display:inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="supprimer">
                                            <input type="hidden" name="id_presence" value="<?= (int) $p['id_presence'] ?>">
                                            <button class="btn btn--danger btn--sm" type="submit"
                                                    data-confirm="Supprimer ce pointage ?">Suppr.</button>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
