<?php
/**
 * Évaluation des stages : note technique, note de comportement,
 * observations et décision finale.
 */

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion();

$pageTitle  = 'Évaluations';
$activeMenu = 'evaluations';

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

$erreurs = [];

// ---------------------------------------------------------------- Enregistrement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    exigerRole(['admin', 'encadrant']);

    $cible    = intOrNull(post('id_stage'));
    $noteTech = post('note_technique');
    $noteComp = post('note_comportement');
    $obs      = post('observations');
    $decision = post('decision');

    if (!$cible || !peutVoirStage($cible)) {
        $erreurs[] = 'Stage invalide ou inaccessible.';
    }
    foreach ([['note_technique', $noteTech, 'technique'], ['note_comportement', $noteComp, 'de comportement']] as [$k, $v, $lib]) {
        if ($v !== '' && (!is_numeric($v) || (float) $v < 0 || (float) $v > 20)) {
            $erreurs[] = "La note $lib doit être comprise entre 0 et 20.";
        }
    }
    if ($decision !== '' && !in_array($decision, ['Validé', 'Non validé'], true)) {
        $erreurs[] = 'Décision invalide.';
    }

    if (!$erreurs) {
        $idEncadrant = estEncadrant() ? utilisateur()['id_encadrant']
                                      : intOrNull(fetchValue('SELECT id_encadrant FROM stage WHERE id_stage = ?', [$cible]));

        // Une évaluation unique par stage (clé unique sur id_stage).
        query(
            'INSERT INTO evaluation (id_stage, note_technique, note_comportement, observations, decision, id_encadrant)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE note_technique    = VALUES(note_technique),
                                     note_comportement = VALUES(note_comportement),
                                     observations      = VALUES(observations),
                                     decision          = VALUES(decision),
                                     id_encadrant      = VALUES(id_encadrant),
                                     date_evaluation   = NOW()',
            [$cible, $noteTech !== '' ? $noteTech : null, $noteComp !== '' ? $noteComp : null,
             $obs ?: null, $decision ?: null, $idEncadrant]
        );

        // Une décision finale clôture le stage.
        if ($decision !== '') {
            query("UPDATE stage SET statut = 'Terminé' WHERE id_stage = ? AND statut = 'En cours'", [$cible]);
        }

        flash('Évaluation enregistrée avec succès.');
        redirect('evaluations.php' . ($idStage ? '?stage=' . $idStage : ''));
    }
}

// ---------------------------------------------------------------- Données
$params = [];
$sql = "SELECT s.id_stage, s.sujet, s.statut, s.date_fin,
               st.nom, st.prenom, st.cin,
               sv.nom_service,
               ev.note_technique, ev.note_comportement, ev.observations,
               ev.decision, ev.date_evaluation
        FROM stage s
        JOIN stagiaire st    ON st.id_stagiaire = s.id_stagiaire
        LEFT JOIN service sv ON sv.id_service   = s.id_service
        LEFT JOIN evaluation ev ON ev.id_stage  = s.id_stage
        WHERE 1=1";
$sql .= filtreStagesParRole($params);

if ($idStage) { $sql .= ' AND s.id_stage = ?'; $params[] = $idStage; }

$sql .= ' ORDER BY (ev.id_evaluation IS NULL) DESC, s.date_fin DESC';
$lignes = fetchAll($sql, $params);

// Formulaire : stage courant, ou premier stage évaluable.
$stageForm = null;
if ($idStage) {
    $stageForm = fetchOne(
        'SELECT s.*, st.nom, st.prenom FROM stage s
         JOIN stagiaire st ON st.id_stagiaire = s.id_stagiaire WHERE s.id_stage = ?',
        [$idStage]
    );
    $evalCourante = fetchOne('SELECT * FROM evaluation WHERE id_stage = ?', [$idStage]);
}

$p2 = [];
$stagesDispo = fetchAll(
    'SELECT s.id_stage, s.sujet, st.nom, st.prenom
     FROM stage s JOIN stagiaire st ON st.id_stagiaire = s.id_stagiaire
     WHERE 1=1 ' . filtreStagesParRole($p2) . ' ORDER BY st.nom',
    $p2
);

// Statistiques d'ensemble.
$evalues = array_filter($lignes, fn($l) => $l['date_evaluation'] !== null);
$valides = array_filter($evalues, fn($l) => $l['decision'] === 'Validé');
$moyennes = array_map(
    fn($l) => ((float) $l['note_technique'] + (float) $l['note_comportement']) / 2,
    array_filter($evalues, fn($l) => $l['note_technique'] !== null && $l['note_comportement'] !== null)
);
$moyenneGenerale = $moyennes ? round(array_sum($moyennes) / count($moyennes), 2) : null;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h2 class="mb-0">Évaluations</h2>
        <p>Notes, observations et décision finale de chaque stage</p>
    </div>
    <?php if ($idStage): ?>
        <a class="btn btn--secondary" href="<?= url('stage_details.php?id=' . $idStage) ?>">← Fiche du stage</a>
    <?php endif; ?>
</div>

<div class="stats">
    <div class="stat">
        <div class="stat__icon">★</div>
        <div>
            <div class="stat__value"><?= count($evalues) ?>/<?= count($lignes) ?></div>
            <div class="stat__label">Stages évalués</div>
        </div>
    </div>
    <div class="stat">
        <div class="stat__icon stat__icon--success">✓</div>
        <div>
            <div class="stat__value"><?= count($valides) ?></div>
            <div class="stat__label">Stages validés</div>
        </div>
    </div>
    <div class="stat">
        <div class="stat__icon stat__icon--danger">✕</div>
        <div>
            <div class="stat__value"><?= count($evalues) - count($valides) ?></div>
            <div class="stat__label">Non validés</div>
        </div>
    </div>
    <div class="stat">
        <div class="stat__icon stat__icon--info">Σ</div>
        <div>
            <div class="stat__value"><?= $moyenneGenerale !== null ? number_format($moyenneGenerale, 2, ',', '') : '—' ?></div>
            <div class="stat__label">Moyenne générale /20</div>
        </div>
    </div>
</div>

<?php if ($erreurs): ?>
    <div class="alert alert--error"><span><?= e(implode(' ', $erreurs)) ?></span></div>
<?php endif; ?>

<!-- ==================== Formulaire d'évaluation ==================== -->
<?php if ((estAdmin() || estEncadrant()) && $stagesDispo): ?>
<section class="card">
    <div class="card__head">
        <div>
            <h3><?= !empty($evalCourante) ? 'Modifier l\'évaluation' : 'Évaluer un stage' ?></h3>
            <p>
                <?php if ($stageForm): ?>
                    <?= e($stageForm['prenom'] . ' ' . $stageForm['nom']) ?> — <?= e($stageForm['sujet']) ?>
                <?php else: ?>
                    Sélectionnez le stage à évaluer.
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="card__body">
        <form method="post">
            <?= csrfField() ?>

            <div class="form-grid">
                <div class="field field--full">
                    <label for="id_stage">Stage <span class="req">*</span></label>
                    <select class="select" id="id_stage" name="id_stage" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($stagesDispo as $sd): ?>
                            <option value="<?= (int) $sd['id_stage'] ?>" <?= $idStage === (int) $sd['id_stage'] ? 'selected' : '' ?>>
                                <?= e($sd['prenom'] . ' ' . $sd['nom'] . ' — ' . mb_strimwidth($sd['sujet'], 0, 50, '…')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="note_technique">Note technique (/20)</label>
                    <input class="input" type="number" id="note_technique" name="note_technique"
                           min="0" max="20" step="0.25"
                           value="<?= e($evalCourante['note_technique'] ?? '') ?>">
                    <span class="hint">Maîtrise technique, qualité du travail rendu.</span>
                </div>

                <div class="field">
                    <label for="note_comportement">Note de comportement (/20)</label>
                    <input class="input" type="number" id="note_comportement" name="note_comportement"
                           min="0" max="20" step="0.25"
                           value="<?= e($evalCourante['note_comportement'] ?? '') ?>">
                    <span class="hint">Assiduité, intégration, autonomie.</span>
                </div>

                <div class="field">
                    <label for="decision">Décision finale</label>
                    <select class="select" id="decision" name="decision">
                        <option value="">— À décider —</option>
                        <option value="Validé"     <?= ($evalCourante['decision'] ?? '') === 'Validé' ? 'selected' : '' ?>>Validé</option>
                        <option value="Non validé" <?= ($evalCourante['decision'] ?? '') === 'Non validé' ? 'selected' : '' ?>>Non validé</option>
                    </select>
                    <span class="hint">Une décision passe automatiquement le stage à « Terminé ».</span>
                </div>

                <div class="field field--full">
                    <label for="observations">Observations</label>
                    <textarea class="textarea" id="observations" name="observations"
                              placeholder="Appréciation générale, points forts, axes d'amélioration…"><?= e($evalCourante['observations'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn" type="submit">Enregistrer l'évaluation</button>
                <?php if ($idStage): ?>
                    <a class="btn btn--secondary" href="<?= url('evaluations.php') ?>">Voir toutes les évaluations</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<!-- ==================== Tableau récapitulatif ==================== -->
<section class="card">
    <div class="card__head"><h3>Récapitulatif</h3></div>
    <div class="card__body card__body--flush">
        <?php if (!$lignes): ?>
            <div class="empty">
                <span class="empty__icon">★</span>
                <p>Aucun stage à évaluer.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Stagiaire</th>
                        <th>Sujet</th>
                        <th>Service</th>
                        <th>Technique</th>
                        <th>Comportement</th>
                        <th>Moyenne</th>
                        <th>Décision</th>
                        <th style="width:60px">Notes</th>
                        <?php if (estAdmin() || estEncadrant()): ?><th class="text-right">Actions</th><?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lignes as $l):
                        $nt = $l['note_technique'] !== null ? (float) $l['note_technique'] : null;
                        $nc = $l['note_comportement'] !== null ? (float) $l['note_comportement'] : null;
                        $moy = ($nt !== null && $nc !== null) ? ($nt + $nc) / 2 : null;
                    ?>
                        <tr>
                            <td>
                                <div class="person">
                                    <span class="person__avatar"><?= e(initials($l['nom'], $l['prenom'])) ?></span>
                                    <div>
                                        <div class="person__name"><?= e($l['prenom'] . ' ' . $l['nom']) ?></div>
                                        <div class="person__sub"><?= e($l['cin']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="max-width:220px"><?= e(mb_strimwidth($l['sujet'], 0, 55, '…')) ?></td>
                            <td class="muted"><?= e($l['nom_service'] ?: '—') ?></td>
                            <td><?= $nt !== null ? number_format($nt, 2, ',', '') : '<span class="muted">—</span>' ?></td>
                            <td><?= $nc !== null ? number_format($nc, 2, ',', '') : '<span class="muted">—</span>' ?></td>
                            <td>
                                <?php if ($moy !== null): ?>
                                    <strong style="color:<?= $moy >= 10 ? 'var(--success)' : 'var(--danger)' ?>">
                                        <?= number_format($moy, 2, ',', '') ?>
                                    </strong>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($l['decision'] === 'Validé'): ?>
                                    <span class="badge badge--success">Validé</span>
                                <?php elseif ($l['decision'] === 'Non validé'): ?>
                                    <span class="badge badge--danger">Non validé</span>
                                <?php else: ?>
                                    <span class="badge badge--warning">Non évalué</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($l['observations']): ?>
                                    <span class="badge badge--info" title="<?= e($l['observations']) ?>" style="cursor:help; display:inline-block">
                                        <strong>💬</strong> Obs.
                                    </span>
                                <?php else: ?>
                                    <span class="muted" style="font-size:0.85em">—</span>
                                <?php endif; ?>
                            </td>
                            <?php if (estAdmin() || estEncadrant()): ?>
                                <td>
                                    <div class="table__actions" style="justify-content:flex-end">
                                        <a class="btn btn--secondary btn--sm"
                                           href="<?= url('evaluations.php?stage=' . (int) $l['id_stage']) ?>">
                                            <?= $l['date_evaluation'] ? 'Modifier' : 'Évaluer' ?>
                                        </a>
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
