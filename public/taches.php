<?php
/**
 * Suivi du stage : saisie des tâches quotidiennes (stagiaire),
 * validation et commentaires (encadrant / admin).
 */

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion();

$pageTitle  = 'Suivi des tâches';
$activeMenu = 'taches';

$idStage = intOrNull(get('stage'));

// Le stagiaire est toujours rattaché à son propre stage.
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

    // ------------------------------------------- Saisie d'une tâche
    if ($action === 'ajouter') {
        // L'encadrant valide les tâches, il ne les saisit pas.
        exigerRole(['admin', 'stagiaire']);
        $stageCible = intOrNull(post('id_stage'));
        $titre      = post('titre');
        $desc       = post('description');
        $date       = post('date_tache') ?: date('Y-m-d');

        if (!$stageCible || !peutVoirStage($stageCible)) {
            flash('Stage invalide.', 'error');
        } elseif ($titre === '') {
            flash('Le titre de la tâche est obligatoire.', 'error');
        } else {
            query(
                'INSERT INTO tache (id_stage, titre, description, date_tache, etat)
                 VALUES (?,?,?,?,?)',
                [$stageCible, $titre, $desc ?: null, $date, 'En attente']
            );
            flash('Tâche ajoutée. Elle est en attente de validation par l\'encadrant.');
        }
        redirect('taches.php' . ($idStage ? '?stage=' . $idStage : ''));
    }

    // ------------------------------------------- Validation / rejet
    if ($action === 'valider') {
        exigerRole(['admin', 'encadrant']);
        $idTache = intOrNull(post('id_tache'));
        $etat    = post('etat');
        $comm    = post('commentaire');

        $tache = $idTache ? fetchOne('SELECT * FROM tache WHERE id_tache = ?', [$idTache]) : null;

        if ($tache && peutVoirStage((int) $tache['id_stage']) && in_array($etat, TACHE_ETATS, true)) {
            query(
                'UPDATE tache SET etat = ?, commentaire = ?, date_validation = NOW() WHERE id_tache = ?',
                [$etat, $comm ?: null, $idTache]
            );
            flash("Tâche marquée « $etat ».");
        } else {
            flash('Action impossible sur cette tâche.', 'error');
        }
        redirect('taches.php' . ($idStage ? '?stage=' . $idStage : ''));
    }

    // ------------------------------------------- Suppression
    if ($action === 'supprimer') {
        $idTache = intOrNull(post('id_tache'));
        $tache   = $idTache ? fetchOne('SELECT * FROM tache WHERE id_tache = ?', [$idTache]) : null;

        if (!$tache || !peutVoirStage((int) $tache['id_stage'])) {
            flash('Tâche introuvable.', 'error');
        } elseif (estStagiaire() && $tache['etat'] === 'Validée') {
            flash('Une tâche déjà validée ne peut plus être supprimée.', 'error');
        } else {
            query('DELETE FROM tache WHERE id_tache = ?', [$idTache]);
            flash('Tâche supprimée.');
        }
        redirect('taches.php' . ($idStage ? '?stage=' . $idStage : ''));
    }
}

// ---------------------------------------------------------------- Données
$filtreEtat = get('etat');

$params = [];
$sql = "SELECT t.*, s.sujet, s.id_stage, st.nom, st.prenom
        FROM tache t
        JOIN stage s     ON s.id_stage = t.id_stage
        JOIN stagiaire st ON st.id_stagiaire = s.id_stagiaire
        WHERE 1=1";
$sql .= filtreStagesParRole($params);

if ($idStage) {
    $sql .= ' AND t.id_stage = ?';
    $params[] = $idStage;
}
if ($filtreEtat !== '' && in_array($filtreEtat, TACHE_ETATS, true)) {
    $sql .= ' AND t.etat = ?';
    $params[] = $filtreEtat;
}

$sql .= ' ORDER BY t.date_tache DESC, t.id_tache DESC';
$taches = fetchAll($sql, $params);

// Stages disponibles pour la saisie.
$p2 = [];
$stagesDispo = fetchAll(
    "SELECT s.id_stage, s.sujet, st.nom, st.prenom
     FROM stage s JOIN stagiaire st ON st.id_stagiaire = s.id_stagiaire
     WHERE s.statut = 'En cours' " . filtreStagesParRole($p2) . ' ORDER BY st.nom',
    $p2
);

$stageCourant = $idStage ? fetchOne(
    'SELECT s.*, st.nom, st.prenom FROM stage s
     JOIN stagiaire st ON st.id_stagiaire = s.id_stagiaire WHERE s.id_stage = ?',
    [$idStage]
) : null;

$prog = $idStage ? progressionStage($idStage) : null;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h2 class="mb-0">Suivi des tâches</h2>
        <p>
            <?php if ($stageCourant): ?>
                <?= e($stageCourant['prenom'] . ' ' . $stageCourant['nom']) ?> — <?= e($stageCourant['sujet']) ?>
            <?php else: ?>
                <?= count($taches) ?> tâche(s) · saisie quotidienne et validation par l'encadrant
            <?php endif; ?>
        </p>
    </div>
    <?php if ($idStage): ?>
        <a class="btn btn--secondary" href="<?= url('stage_details.php?id=' . $idStage) ?>">← Fiche du stage</a>
    <?php endif; ?>
</div>

<?php if ($prog && $prog['total'] > 0): ?>
    <section class="card">
        <div class="card__body">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:10px">
                <strong>Taux d'avancement du stage</strong>
                <span class="muted"><?= $prog['validees'] ?> validée(s) sur <?= $prog['total'] ?> tâche(s)</span>
            </div>
            <div class="progress" style="height:11px">
                <div class="progress__bar<?= $prog['taux'] >= 100 ? ' progress__bar--success' : '' ?>"
                     style="width:<?= $prog['taux'] ?>%"></div>
            </div>
            <div style="display:flex;gap:14px;margin-top:12px;flex-wrap:wrap">
                <span class="badge badge--success"><?= $prog['validees'] ?> validée(s)</span>
                <span class="badge badge--warning"><?= $prog['attente'] ?> en attente</span>
                <span class="badge badge--danger"><?= $prog['rejetees'] ?> rejetée(s)</span>
                <span class="badge"><?= $prog['taux'] ?>% d'avancement</span>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- ==================== Saisie d'une tâche ==================== -->
<?php // La saisie quotidienne revient au stagiaire ; l'administrateur y a
      // accès pour corriger. L'encadrant, lui, valide sans saisir. ?>
<?php if ($stagesDispo && !estEncadrant()): ?>
<section class="card">
    <div class="card__head">
        <div>
            <h3>Saisir une tâche quotidienne</h3>
            <p>Décrivez le travail réalisé ; l'encadrant la validera.</p>
        </div>
    </div>
    <div class="card__body">
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="ajouter">

            <div class="form-grid">
                <?php if (count($stagesDispo) > 1 || !$idStage): ?>
                    <div class="field">
                        <label for="id_stage">Stage <span class="req">*</span></label>
                        <select class="select" id="id_stage" name="id_stage" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($stagesDispo as $sd): ?>
                                <option value="<?= (int) $sd['id_stage'] ?>" <?= $idStage === (int) $sd['id_stage'] ? 'selected' : '' ?>>
                                    <?= e($sd['prenom'] . ' ' . $sd['nom'] . ' — ' . mb_strimwidth($sd['sujet'], 0, 45, '…')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="id_stage" value="<?= (int) ($idStage ?: $stagesDispo[0]['id_stage']) ?>">
                <?php endif; ?>

                <div class="field">
                    <label for="date_tache">Date <span class="req">*</span></label>
                    <input class="input" type="date" id="date_tache" name="date_tache" required
                           value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                </div>

                <div class="field field--full">
                    <label for="titre">Titre de la tâche <span class="req">*</span></label>
                    <input class="input" type="text" id="titre" name="titre" required maxlength="150"
                           placeholder="ex. Conception de la base de données">
                </div>

                <div class="field field--full">
                    <label for="description">Description</label>
                    <textarea class="textarea" id="description" name="description"
                              placeholder="Détaillez le travail effectué, les difficultés rencontrées…"></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn" type="submit">Enregistrer la tâche</button>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<!-- ==================== Liste des tâches ==================== -->
<section class="card">
    <div class="card__head">
        <h3>Historique des tâches</h3>
        <form method="get" class="toolbar" style="margin:0">
            <?php if ($idStage): ?><input type="hidden" name="stage" value="<?= $idStage ?>"><?php endif; ?>
            <div class="field">
                <select class="select" name="etat" data-auto-submit>
                    <option value="">Tous les états</option>
                    <?php foreach (TACHE_ETATS as $et): ?>
                        <option value="<?= e($et) ?>" <?= $filtreEtat === $et ? 'selected' : '' ?>><?= e($et) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
    <div class="card__body card__body--flush">
        <?php if (!$taches): ?>
            <div class="empty">
                <span class="empty__icon">✓</span>
                <p>Aucune tâche enregistrée pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <?php if (!estStagiaire()): ?><th>Stagiaire</th><?php endif; ?>
                        <th>Tâche</th>
                        <th>État</th>
                        <th>Commentaire de l'encadrant</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($taches as $t): ?>
                        <tr>
                            <td class="nowrap muted"><?= dateFr($t['date_tache']) ?></td>
                            <?php if (!estStagiaire()): ?>
                                <td><strong><?= e($t['prenom'] . ' ' . $t['nom']) ?></strong></td>
                            <?php endif; ?>
                            <td style="max-width:320px">
                                <div style="font-weight:600"><?= e($t['titre']) ?></div>
                                <?php if ($t['description']): ?>
                                    <div class="person__sub" style="white-space:pre-line"><?= e($t['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="<?= etatBadge($t['etat']) ?>"><?= e($t['etat']) ?></span></td>
                            <td style="max-width:220px" class="muted">
                                <?= $t['commentaire'] ? e($t['commentaire']) : '—' ?>
                            </td>
                            <td>
                                <div class="table__actions" style="justify-content:flex-end">
                                    <?php if (estAdmin() || estEncadrant()): ?>
                                        <button class="btn btn--secondary btn--sm" type="button"
                                                onclick="document.getElementById('val-<?= (int) $t['id_tache'] ?>').classList.toggle('is-hidden')">
                                            Valider
                                        </button>
                                    <?php endif; ?>
                                    <?php if (estAdmin() || estEncadrant() || $t['etat'] !== 'Validée'): ?>
                                        <form method="post" style="display:inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="supprimer">
                                            <input type="hidden" name="id_tache" value="<?= (int) $t['id_tache'] ?>">
                                            <button class="btn btn--danger btn--sm" type="submit"
                                                    data-confirm="Supprimer cette tâche ?">Suppr.</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <?php if (estAdmin() || estEncadrant()): ?>
                            <tr id="val-<?= (int) $t['id_tache'] ?>" class="is-hidden">
                                <td colspan="<?= estStagiaire() ? 5 : 6 ?>" style="background:#f8fafc">
                                    <form method="post">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="valider">
                                        <input type="hidden" name="id_tache" value="<?= (int) $t['id_tache'] ?>">
                                        <div class="toolbar">
                                            <div class="field">
                                                <label>Décision</label>
                                                <select class="select" name="etat">
                                                    <?php foreach (TACHE_ETATS as $et): ?>
                                                        <option value="<?= e($et) ?>" <?= $t['etat'] === $et ? 'selected' : '' ?>><?= e($et) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="field" style="flex:1;min-width:260px">
                                                <label>Commentaire</label>
                                                <input class="input" type="text" name="commentaire"
                                                       value="<?= e($t['commentaire']) ?>"
                                                       placeholder="Retour de l'encadrant sur cette tâche…">
                                            </div>
                                            <button class="btn btn--success" type="submit">Enregistrer</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>.is-hidden { display: none; }</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
