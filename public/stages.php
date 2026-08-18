<?php
/**
 * Gestion des stages — liste filtrable avec taux d'avancement.
 */

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion();

$pageTitle  = estStagiaire() ? 'Mon stage' : 'Gestion des stages';
$activeMenu = 'stages';

// --- Suppression (admin) -------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'supprimer') {
    checkCsrf();
    exigerRole(['admin']);
    $id = intOrNull(post('id_stage'));
    if ($id) {
        query('DELETE FROM stage WHERE id_stage = ?', [$id]);
        flash('Stage supprimé, avec ses tâches, documents et évaluation.');
    }
    redirect('stages.php');
}

// --- Changement rapide de statut (admin / encadrant) --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'statut') {
    checkCsrf();
    exigerRole(['admin', 'encadrant']);
    $id     = intOrNull(post('id_stage'));
    $statut = post('statut');
    if ($id && in_array($statut, STAGE_STATUTS, true) && peutVoirStage($id)) {
        query('UPDATE stage SET statut = ? WHERE id_stage = ?', [$statut, $id]);
        flash("Statut du stage mis à jour : $statut.");
    }
    redirect('stages.php');
}

// --- Filtres -------------------------------------------------------------
$recherche = get('q');
$statut    = get('statut');
$idService = intOrNull(get('service'));
$idType    = intOrNull(get('type'));

$params = [];
$sql = "SELECT s.*, st.nom, st.prenom, st.cin,
               sv.nom_service,
               ts.libelle AS type_libelle,
               e.nom AS enc_nom, e.prenom AS enc_prenom,
               (SELECT COUNT(*) FROM tache t WHERE t.id_stage = s.id_stage) AS nb_taches,
               (SELECT COUNT(*) FROM tache t WHERE t.id_stage = s.id_stage AND t.etat = 'Validée') AS nb_validees
        FROM stage s
        JOIN stagiaire st ON st.id_stagiaire = s.id_stagiaire
        LEFT JOIN service sv    ON sv.id_service = s.id_service
        LEFT JOIN type_stage ts ON ts.id_type    = s.id_type
        LEFT JOIN encadrant e   ON e.id_encadrant = s.id_encadrant
        WHERE 1=1";

$sql .= filtreStagesParRole($params);

if ($recherche !== '') {
    $sql .= ' AND (st.nom LIKE ? OR st.prenom LIKE ? OR s.sujet LIKE ? OR st.cin LIKE ?)';
    $like = '%' . $recherche . '%';
    $params = array_merge($params, array_fill(0, 4, $like));
}
if ($statut !== '' && in_array($statut, STAGE_STATUTS, true)) {
    $sql .= ' AND s.statut = ?';
    $params[] = $statut;
}
if ($idService) { $sql .= ' AND s.id_service = ?'; $params[] = $idService; }
if ($idType)    { $sql .= ' AND s.id_type = ?';    $params[] = $idType; }

$sql .= ' ORDER BY s.date_debut DESC';
$stages = fetchAll($sql, $params);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h2 class="mb-0"><?= estStagiaire() ? 'Mon stage' : 'Stages' ?></h2>
        <p><?= count($stages) ?> stage(s) affiché(s)</p>
    </div>
    <?php if (estAdmin()): ?>
        <a class="btn" href="<?= url('stage_form.php') ?>">+ Nouveau stage</a>
    <?php endif; ?>
</div>

<?php if (!estStagiaire()): ?>
<section class="card">
    <div class="card__body">
        <form class="toolbar" method="get">
            <div class="field" style="flex:1;min-width:220px">
                <label for="q">Rechercher</label>
                <input class="input" type="search" id="q" name="q" value="<?= e($recherche) ?>"
                       placeholder="Stagiaire, sujet, CIN…">
            </div>

            <div class="field">
                <label for="statut">Statut</label>
                <select class="select" id="statut" name="statut" data-auto-submit>
                    <option value="">Tous</option>
                    <?php foreach (STAGE_STATUTS as $st): ?>
                        <option value="<?= e($st) ?>" <?= $statut === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="service">Service</label>
                <select class="select" id="service" name="service" data-auto-submit>
                    <option value="">Tous</option>
                    <?php foreach (listeServices() as $sv): ?>
                        <option value="<?= (int) $sv['id_service'] ?>" <?= $idService === (int) $sv['id_service'] ? 'selected' : '' ?>>
                            <?= e($sv['nom_service']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="type">Type</label>
                <select class="select" id="type" name="type" data-auto-submit>
                    <option value="">Tous</option>
                    <?php foreach (listeTypes() as $t): ?>
                        <option value="<?= (int) $t['id_type'] ?>" <?= $idType === (int) $t['id_type'] ? 'selected' : '' ?>>
                            <?= e($t['libelle']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="btn" type="submit">Filtrer</button>
            <a class="btn btn--secondary" href="<?= url('stages.php') ?>">Réinitialiser</a>
        </form>
    </div>
</section>
<?php endif; ?>

<section class="card">
    <div class="card__body card__body--flush">
        <?php if (!$stages): ?>
            <div class="empty">
                <span class="empty__icon">📋</span>
                <p>Aucun stage ne correspond aux critères.</p>
                <?php if (estAdmin()): ?>
                    <a class="btn" href="<?= url('stage_form.php') ?>" style="margin-top:12px">Créer un stage</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Stagiaire</th>
                        <th>Sujet</th>
                        <th>Service</th>
                        <th>Encadrant</th>
                        <th>Type</th>
                        <th>Période</th>
                        <th>Avancement</th>
                        <th>Statut</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($stages as $s):
                        $taux = (int) $s['nb_taches'] > 0
                            ? (int) round((int) $s['nb_validees'] / (int) $s['nb_taches'] * 100)
                            : 0;
                        $barre = $taux >= 100 ? ' progress__bar--success' : ($taux < 34 ? ' progress__bar--warning' : '');
                    ?>
                        <tr>
                            <td>
                                <div class="person">
                                    <span class="person__avatar"><?= e(initials($s['nom'], $s['prenom'])) ?></span>
                                    <div>
                                        <div class="person__name"><?= e($s['prenom'] . ' ' . $s['nom']) ?></div>
                                        <div class="person__sub"><?= e($s['cin']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="max-width:230px"><?= e($s['sujet']) ?></td>
                            <td class="muted"><?= e($s['nom_service'] ?: '—') ?></td>
                            <td class="muted">
                                <?= $s['enc_nom'] ? e($s['enc_prenom'] . ' ' . $s['enc_nom']) : '—' ?>
                            </td>
                            <td><span class="badge"><?= e($s['type_libelle'] ?: $s['type_stage'] ?: '—') ?></span></td>
                            <td class="nowrap">
                                <?= dateFr($s['date_debut'], 'd/m/y') ?><br>
                                <span class="muted">→ <?= dateFr($s['date_fin'], 'd/m/y') ?></span>
                            </td>
                            <td>
                                <div class="progress-cell">
                                    <div class="progress">
                                        <div class="progress__bar<?= $barre ?>" style="width:<?= $taux ?>%"></div>
                                    </div>
                                    <span><?= $taux ?>%</span>
                                </div>
                                <div class="person__sub"><?= (int) $s['nb_validees'] ?>/<?= (int) $s['nb_taches'] ?> tâches</div>
                            </td>
                            <td>
                                <?php if (estStagiaire()): ?>
                                    <span class="<?= statutBadge($s['statut']) ?>"><?= e($s['statut']) ?></span>
                                <?php else: ?>
                                    <form method="post" style="display:inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="statut">
                                        <input type="hidden" name="id_stage" value="<?= (int) $s['id_stage'] ?>">
                                        <select class="select" name="statut" data-auto-submit style="padding:5px 8px;font-size:13px">
                                            <?php foreach (STAGE_STATUTS as $st): ?>
                                                <option value="<?= e($st) ?>" <?= $s['statut'] === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table__actions" style="justify-content:flex-end">
                                    <a class="btn btn--secondary btn--sm"
                                       href="<?= url('stage_details.php?id=' . (int) $s['id_stage']) ?>">Détails</a>
                                    <?php if (estAdmin()): ?>
                                        <a class="btn btn--secondary btn--sm"
                                           href="<?= url('stage_form.php?id=' . (int) $s['id_stage']) ?>">Modifier</a>
                                        <form method="post" style="display:inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="supprimer">
                                            <input type="hidden" name="id_stage" value="<?= (int) $s['id_stage'] ?>">
                                            <button class="btn btn--danger btn--sm" type="submit"
                                                    data-confirm="Supprimer ce stage et toutes ses données (tâches, documents, évaluation) ?">
                                                Supprimer
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
