<?php
/**
 * Fiche complète d'un stage : informations, avancement, tâches,
 * documents, présence et évaluation.
 */

require_once __DIR__ . '/../includes/auth.php';

$id = intOrNull(get('id'));
if (!$id) {
    flash('Stage introuvable.', 'error');
    redirect('stages.php');
}
exigerAccesStage($id);

$stage = fetchOne(
    'SELECT s.*, st.nom, st.prenom, st.cin, st.email, st.telephone,
            st.etablissement, st.specialite, st.niveau,
            i.nom_institut, sv.nom_service, ts.libelle AS type_libelle,
            e.nom AS enc_nom, e.prenom AS enc_prenom, e.email AS enc_email, e.fonction
     FROM stage s
     JOIN stagiaire st       ON st.id_stagiaire = s.id_stagiaire
     LEFT JOIN institut i    ON i.id_institut   = st.id_institut
     LEFT JOIN service sv    ON sv.id_service   = s.id_service
     LEFT JOIN type_stage ts ON ts.id_type      = s.id_type
     LEFT JOIN encadrant e   ON e.id_encadrant  = s.id_encadrant
     WHERE s.id_stage = ?',
    [$id]
);

if (!$stage) {
    flash('Stage introuvable.', 'error');
    redirect('stages.php');
}

$pageTitle  = 'Stage — ' . $stage['prenom'] . ' ' . $stage['nom'];
$activeMenu = 'stages';

$prog       = progressionStage($id);
$taches     = fetchAll('SELECT * FROM tache WHERE id_stage = ? ORDER BY date_tache DESC, id_tache DESC', [$id]);
$documents  = fetchAll('SELECT * FROM document WHERE id_stage = ? ORDER BY date_depot DESC', [$id]);
$evaluation = fetchOne('SELECT * FROM evaluation WHERE id_stage = ?', [$id]);

$presenceStats = fetchOne(
    "SELECT COUNT(*) AS total,
            SUM(statut = 'Present') AS presents,
            SUM(statut = 'Absent')  AS absents
     FROM presence WHERE id_stage = ?",
    [$id]
);

// Durée et progression temporelle du stage.
$debut  = strtotime($stage['date_debut']);
$fin    = strtotime($stage['date_fin']);
$duree  = max(1, (int) round(($fin - $debut) / 86400) + 1);
$ecoule = min($duree, max(0, (int) round((time() - $debut) / 86400) + 1));
$tempsPct = (int) round($ecoule / $duree * 100);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h2 class="mb-0"><?= e($stage['sujet']) ?></h2>
        <p>
            <?= e($stage['prenom'] . ' ' . $stage['nom']) ?> ·
            <?= e($stage['nom_service'] ?: 'Service non affecté') ?> ·
            <span class="<?= statutBadge($stage['statut']) ?>"><?= e($stage['statut']) ?></span>
        </p>
    </div>
    <div class="table__actions">
        <a class="btn btn--secondary" href="<?= url('stages.php') ?>">← Retour</a>
        <?php if (estAdmin()): ?>
            <a class="btn" href="<?= url('stage_form.php?id=' . $id) ?>">Modifier</a>
        <?php endif; ?>
        <button class="btn btn--secondary" type="button" onclick="window.print()">Imprimer</button>
    </div>
</div>

<!-- ==================== Indicateurs ==================== -->
<div class="stats">
    <div class="stat">
        <div class="stat__icon">📅</div>
        <div>
            <div class="stat__value"><?= $duree ?></div>
            <div class="stat__label">Jours de stage</div>
        </div>
    </div>
    <div class="stat">
        <div class="stat__icon stat__icon--info">✓</div>
        <div>
            <div class="stat__value"><?= $prog['validees'] ?>/<?= $prog['total'] ?></div>
            <div class="stat__label">Tâches validées</div>
        </div>
    </div>
    <div class="stat">
        <div class="stat__icon stat__icon--warning">⏱</div>
        <div>
            <div class="stat__value"><?= $prog['attente'] ?></div>
            <div class="stat__label">Tâches en attente</div>
        </div>
    </div>
    <div class="stat">
        <div class="stat__icon stat__icon--success">📎</div>
        <div>
            <div class="stat__value"><?= count($documents) ?></div>
            <div class="stat__label">Documents déposés</div>
        </div>
    </div>
</div>

<div class="grid-2" style="align-items:start">

    <!-- ==================== Informations ==================== -->
    <section class="card">
        <div class="card__head"><h3>Informations du stage</h3></div>
        <div class="card__body">
            <div class="info-list">
                <div class="info-list__row">
                    <span class="info-list__key">Type de stage</span>
                    <span class="info-list__val"><?= e($stage['type_libelle'] ?: $stage['type_stage'] ?: '—') ?></span>
                </div>
                <div class="info-list__row">
                    <span class="info-list__key">Service d'affectation</span>
                    <span class="info-list__val"><?= e($stage['nom_service'] ?: '—') ?></span>
                </div>
                <div class="info-list__row">
                    <span class="info-list__key">Date de début</span>
                    <span class="info-list__val"><?= dateFr($stage['date_debut']) ?></span>
                </div>
                <div class="info-list__row">
                    <span class="info-list__key">Date de fin</span>
                    <span class="info-list__val"><?= dateFr($stage['date_fin']) ?></span>
                </div>
                <div class="info-list__row">
                    <span class="info-list__key">Encadrant</span>
                    <span class="info-list__val">
                        <?= $stage['enc_nom'] ? e($stage['enc_prenom'] . ' ' . $stage['enc_nom']) : '—' ?>
                        <?php if ($stage['fonction']): ?>
                            <br><span class="person__sub"><?= e($stage['fonction']) ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-list__row">
                    <span class="info-list__key">État</span>
                    <span class="info-list__val"><span class="<?= statutBadge($stage['statut']) ?>"><?= e($stage['statut']) ?></span></span>
                </div>
            </div>

            <?php if ($stage['description']): ?>
                <div style="margin-top:18px;padding-top:16px;border-top:1px dashed var(--border)">
                    <strong style="font-size:13.5px;color:var(--ink-soft)">Description</strong>
                    <p style="margin:6px 0 0;color:var(--ink-soft);font-size:14px;white-space:pre-line"><?= e($stage['description']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ==================== Stagiaire ==================== -->
    <section class="card">
        <div class="card__head"><h3>Le stagiaire</h3></div>
        <div class="card__body">
            <div class="person" style="margin-bottom:16px">
                <span class="person__avatar" style="width:48px;height:48px;flex-basis:48px;font-size:17px">
                    <?= e(initials($stage['nom'], $stage['prenom'])) ?>
                </span>
                <div>
                    <div class="person__name" style="font-size:16px"><?= e($stage['prenom'] . ' ' . $stage['nom']) ?></div>
                    <div class="person__sub">CIN <?= e($stage['cin']) ?></div>
                </div>
            </div>

            <div class="info-list">
                <div class="info-list__row">
                    <span class="info-list__key">Email</span>
                    <span class="info-list__val"><?= e($stage['email']) ?></span>
                </div>
                <div class="info-list__row">
                    <span class="info-list__key">Téléphone</span>
                    <span class="info-list__val"><?= e($stage['telephone'] ?: '—') ?></span>
                </div>
                <div class="info-list__row">
                    <span class="info-list__key">Établissement</span>
                    <span class="info-list__val"><?= e($stage['nom_institut'] ?: $stage['etablissement'] ?: '—') ?></span>
                </div>
                <div class="info-list__row">
                    <span class="info-list__key">Spécialité</span>
                    <span class="info-list__val"><?= e($stage['specialite'] ?: '—') ?></span>
                </div>
                <div class="info-list__row">
                    <span class="info-list__key">Niveau</span>
                    <span class="info-list__val"><?= e($stage['niveau'] ?: '—') ?></span>
                </div>
                <div class="info-list__row">
                    <span class="info-list__key">Présences enregistrées</span>
                    <span class="info-list__val">
                        <?= (int) $presenceStats['presents'] ?> présent(s) /
                        <?= (int) $presenceStats['total'] ?> jour(s)
                    </span>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- ==================== Avancement ==================== -->
<section class="card">
    <div class="card__head">
        <div>
            <h3>Suivi de l'avancement</h3>
            <p>Tâches validées par l'encadrant et progression dans le temps</p>
        </div>
        <a class="btn btn--secondary btn--sm" href="<?= url('taches.php?stage=' . $id) ?>">Gérer les tâches</a>
    </div>
    <div class="card__body">
        <div class="grid-2">
            <div class="ring">
                <?php $r = 48; $c = 2 * M_PI * $r; $off = $c - ($prog['taux'] / 100) * $c; ?>
                <svg class="ring__svg" width="118" height="118" viewBox="0 0 118 118">
                    <circle class="ring__track" cx="59" cy="59" r="<?= $r ?>" stroke-width="11"></circle>
                    <circle class="ring__value" cx="59" cy="59" r="<?= $r ?>" stroke-width="11"
                            stroke-dasharray="<?= round($c, 2) ?>" stroke-dashoffset="<?= round($off, 2) ?>"></circle>
                </svg>
                <div>
                    <div class="ring__pct"><?= $prog['taux'] ?>%</div>
                    <div class="ring__sub">Taux d'avancement<br>
                        <?= $prog['validees'] ?> validée(s), <?= $prog['attente'] ?> en attente,
                        <?= $prog['rejetees'] ?> rejetée(s)
                    </div>
                </div>
            </div>

            <div>
                <div style="margin-bottom:18px">
                    <div style="display:flex;justify-content:space-between;font-size:13.5px;margin-bottom:6px">
                        <span style="font-weight:600">Progression dans le temps</span>
                        <span class="muted"><?= $ecoule ?> / <?= $duree ?> jours</span>
                    </div>
                    <div class="progress">
                        <div class="progress__bar<?= $tempsPct >= 100 ? ' progress__bar--success' : '' ?>"
                             style="width:<?= min(100, $tempsPct) ?>%"></div>
                    </div>
                </div>

                <div>
                    <div style="display:flex;justify-content:space-between;font-size:13.5px;margin-bottom:6px">
                        <span style="font-weight:600">Tâches accomplies</span>
                        <span class="muted"><?= $prog['validees'] ?> / <?= $prog['total'] ?></span>
                    </div>
                    <div class="progress">
                        <div class="progress__bar progress__bar--success" style="width:<?= $prog['taux'] ?>%"></div>
                    </div>
                </div>

                <?php if ($tempsPct > $prog['taux'] + 20 && $stage['statut'] === 'En cours'): ?>
                    <div class="alert alert--warning" style="margin-top:18px">
                        <span>Le stage avance plus vite que les tâches validées. Un rattrapage est conseillé.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<div class="grid-2" style="align-items:start">

    <!-- ==================== Tâches ==================== -->
    <section class="card">
        <div class="card__head">
            <h3>Tâches quotidiennes</h3>
            <a class="btn btn--secondary btn--sm" href="<?= url('taches.php?stage=' . $id) ?>">Tout voir</a>
        </div>
        <div class="card__body">
            <?php if (!$taches): ?>
                <div class="empty">
                    <span class="empty__icon">✓</span>
                    <p>Aucune tâche saisie.</p>
                </div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach (array_slice($taches, 0, 8) as $t): ?>
                        <?php $mod = match ($t['etat']) {
                            'Validée' => ' timeline__item--success',
                            'Rejetée' => ' timeline__item--danger',
                            default   => ' timeline__item--warning',
                        }; ?>
                        <div class="timeline__item<?= $mod ?>">
                            <div class="timeline__date">
                                <?= dateFr($t['date_tache']) ?>
                                <span class="<?= etatBadge($t['etat']) ?>" style="margin-left:6px"><?= e($t['etat']) ?></span>
                            </div>
                            <div class="timeline__title"><?= e($t['titre']) ?></div>
                            <?php if ($t['description']): ?>
                                <div class="timeline__text"><?= e($t['description']) ?></div>
                            <?php endif; ?>
                            <?php if ($t['commentaire']): ?>
                                <div class="timeline__text" style="margin-top:4px;font-style:italic">
                                    💬 <?= e($t['commentaire']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ==================== Documents ==================== -->
    <section class="card">
        <div class="card__head">
            <h3>Documents</h3>
            <a class="btn btn--secondary btn--sm" href="<?= url('documents.php?stage=' . $id) ?>">Gérer</a>
        </div>
        <div class="card__body card__body--flush">
            <?php if (!$documents): ?>
                <div class="empty">
                    <span class="empty__icon">📎</span>
                    <p>Aucun document déposé.</p>
                    <a class="btn btn--sm" href="<?= url('documents.php?stage=' . $id) ?>" style="margin-top:12px">Déposer un document</a>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr><th>Type</th><th>Fichier</th><th>Déposé le</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($documents as $d): ?>
                            <tr>
                                <td><span class="badge badge--info"><?= e($d['type_document']) ?></span></td>
                                <td>
                                    <div><?= e(mb_strimwidth($d['nom_fichier'], 0, 30, '…')) ?></div>
                                    <div class="person__sub"><?= formatSize($d['taille'] !== null ? (int) $d['taille'] : null) ?></div>
                                </td>
                                <td class="muted nowrap"><?= dateFr($d['date_depot'], 'd/m/y') ?></td>
                                <td class="text-right">
                                    <a class="btn btn--secondary btn--sm"
                                       href="<?= url('telecharger.php?id=' . (int) $d['id_document']) ?>">↓</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- ==================== Évaluation ==================== -->
<section class="card">
    <div class="card__head">
        <div>
            <h3>Évaluation finale</h3>
            <p>Notes, observations et décision de l'encadrant</p>
        </div>
        <?php if (estAdmin() || estEncadrant()): ?>
            <a class="btn btn--secondary btn--sm" href="<?= url('evaluations.php?stage=' . $id) ?>">
                <?= $evaluation ? 'Modifier l\'évaluation' : 'Évaluer ce stage' ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card__body">
        <?php if (!$evaluation): ?>
            <div class="empty">
                <span class="empty__icon">★</span>
                <p>Ce stage n'a pas encore été évalué.</p>
            </div>
        <?php else: ?>
            <?php
                $nt = $evaluation['note_technique'] !== null ? (float) $evaluation['note_technique'] : null;
                $nc = $evaluation['note_comportement'] !== null ? (float) $evaluation['note_comportement'] : null;
                $moy = ($nt !== null && $nc !== null) ? round(($nt + $nc) / 2, 2) : ($nt ?? $nc);
            ?>
            <div class="stats" style="margin-bottom:20px">
                <div class="stat">
                    <div class="stat__icon">⚙️</div>
                    <div>
                        <div class="stat__value"><?= $nt !== null ? number_format($nt, 2, ',', '') : '—' ?><small style="font-size:14px;color:var(--ink-muted)">/20</small></div>
                        <div class="stat__label">Note technique</div>
                    </div>
                </div>
                <div class="stat">
                    <div class="stat__icon stat__icon--info">🤝</div>
                    <div>
                        <div class="stat__value"><?= $nc !== null ? number_format($nc, 2, ',', '') : '—' ?><small style="font-size:14px;color:var(--ink-muted)">/20</small></div>
                        <div class="stat__label">Note de comportement</div>
                    </div>
                </div>
                <div class="stat">
                    <div class="stat__icon stat__icon--success">Σ</div>
                    <div>
                        <div class="stat__value"><?= $moy !== null ? number_format($moy, 2, ',', '') : '—' ?><small style="font-size:14px;color:var(--ink-muted)">/20</small></div>
                        <div class="stat__label">Moyenne générale</div>
                    </div>
                </div>
                <div class="stat">
                    <div class="stat__icon <?= $evaluation['decision'] === 'Validé' ? 'stat__icon--success' : 'stat__icon--danger' ?>">
                        <?= $evaluation['decision'] === 'Validé' ? '✓' : '✕' ?>
                    </div>
                    <div>
                        <div class="stat__value" style="font-size:19px"><?= e($evaluation['decision'] ?: '—') ?></div>
                        <div class="stat__label">Décision finale</div>
                    </div>
                </div>
            </div>

            <?php if ($evaluation['observations']): ?>
                <div>
                    <strong style="font-size:13.5px;color:var(--ink-soft)">Observations</strong>
                    <p style="margin:6px 0 0;color:var(--ink-soft);white-space:pre-line"><?= e($evaluation['observations']) ?></p>
                </div>
            <?php endif; ?>
            <p class="muted" style="margin-top:14px;font-size:13px">
                Évaluation enregistrée le <?= dateFr($evaluation['date_evaluation'], 'd/m/Y à H:i') ?>
            </p>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
