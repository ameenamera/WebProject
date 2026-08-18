<?php
/**
 * Tableau de bord — statistiques et graphiques, adaptés au rôle.
 */

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion();

$pageTitle  = 'Tableau de bord';
$activeMenu = 'dashboard';

// Filtre appliqué selon le rôle (admin = tout, encadrant = ses stages, stagiaire = le sien).
$p       = [];
$filtre  = filtreStagesParRole($p);

// --- Compteurs -----------------------------------------------------------
$totalStagiaires = (int) fetchValue(
    "SELECT COUNT(DISTINCT s.id_stagiaire) FROM stage s WHERE 1=1 $filtre", $p
);
if (estAdmin()) {
    // Un stagiaire peut exister sans stage : on compte la table complète.
    $totalStagiaires = (int) fetchValue('SELECT COUNT(*) FROM stagiaire');
}

$enCours = (int) fetchValue("SELECT COUNT(*) FROM stage s WHERE s.statut = 'En cours' $filtre", $p);
$termine = (int) fetchValue("SELECT COUNT(*) FROM stage s WHERE s.statut = 'Terminé' $filtre", $p);
$totalStages = (int) fetchValue("SELECT COUNT(*) FROM stage s WHERE 1=1 $filtre", $p);

$tachesAttente = (int) fetchValue(
    "SELECT COUNT(*) FROM tache t
     JOIN stage s ON s.id_stage = t.id_stage
     WHERE t.etat = 'En attente' $filtre", $p
);

// --- Répartition par service --------------------------------------------
$parService = fetchAll(
    "SELECT COALESCE(sv.nom_service, 'Non affecté') AS nom_service, COUNT(*) AS total
     FROM stage s
     LEFT JOIN service sv ON sv.id_service = s.id_service
     WHERE 1=1 $filtre
     GROUP BY sv.id_service, sv.nom_service
     ORDER BY total DESC", $p
);
$maxService = max(array_column($parService, 'total') ?: [1]);

// --- Répartition par type de stage --------------------------------------
$parType = fetchAll(
    "SELECT COALESCE(ts.libelle, s.type_stage, 'Non précisé') AS libelle, COUNT(*) AS total
     FROM stage s
     LEFT JOIN type_stage ts ON ts.id_type = s.id_type
     WHERE 1=1 $filtre
     GROUP BY libelle
     ORDER BY total DESC", $p
);
$maxType = max(array_column($parType, 'total') ?: [1]);

// --- Stages récents ------------------------------------------------------
$stagesRecents = fetchAll(
    "SELECT s.*, st.nom, st.prenom, sv.nom_service
     FROM stage s
     JOIN stagiaire st ON st.id_stagiaire = s.id_stagiaire
     LEFT JOIN service sv ON sv.id_service = s.id_service
     WHERE 1=1 $filtre
     ORDER BY s.date_debut DESC
     LIMIT 6", $p
);

// --- Dernières tâches ----------------------------------------------------
$tachesRecentes = fetchAll(
    "SELECT t.*, st.nom, st.prenom
     FROM tache t
     JOIN stage s ON s.id_stage = t.id_stage
     JOIN stagiaire st ON st.id_stagiaire = s.id_stagiaire
     WHERE 1=1 $filtre
     ORDER BY t.date_tache DESC, t.id_tache DESC
     LIMIT 7", $p
);

// --- Anneau : taux de validation global des tâches ----------------------
$g = fetchOne(
    "SELECT COUNT(*) AS total, 
            SUM(t.etat = 'Validée') AS validees,
            SUM(t.etat = 'En attente') AS attente,
            SUM(t.etat = 'Rejetée') AS rejetees
     FROM tache t JOIN stage s ON s.id_stage = t.id_stage
     WHERE 1=1 $filtre", $p
);
$tauxGlobal = ((int) $g['total'] > 0) ? (int) round(((int) $g['validees'] / (int) $g['total']) * 100) : 0;

// --- Évaluations avec observations ----------------------------------------
$evaluationsAvecObs = fetchAll(
    "SELECT s.id_stage, s.sujet, st.nom, st.prenom, ev.observations, 
            ev.note_technique, ev.note_comportement, ev.decision
     FROM evaluation ev
     JOIN stage s ON s.id_stage = ev.id_stage
     JOIN stagiaire st ON st.id_stagiaire = s.id_stagiaire
     WHERE ev.observations IS NOT NULL AND ev.observations != '' AND 1=1 $filtre
     ORDER BY ev.date_evaluation DESC
     LIMIT 5", $p
);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ============================= Taux d'avancement des stages ============================= -->
<?php if ($g && $g['total'] > 0): ?>
    <section class="card">
        <div class="card__body">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:10px">
                <strong>Taux d'avancement du stage</strong>
                <span class="muted"><?= (int) $g['validees'] ?> validée(s) sur <?= (int) $g['total'] ?> tâche(s)</span>
            </div>
            <div class="progress" style="height:11px">
                <div class="progress__bar<?= $tauxGlobal >= 100 ? ' progress__bar--success' : '' ?>"
                     style="width:<?= $tauxGlobal ?>%"></div>
            </div>
            <div style="display:flex;gap:14px;margin-top:12px;flex-wrap:wrap">
                <span class="badge badge--success"><?= (int) $g['validees'] ?> validée(s)</span>
                <span class="badge badge--warning"><?= (int) $g['attente'] ?> en attente</span>
                <span class="badge badge--danger"><?= (int) $g['rejetees'] ?> rejetée(s)</span>
                <span class="badge"><?= $tauxGlobal ?>% d'avancement</span>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- ============================= Compteurs ============================= -->


<div class="grid-2">

    <!-- ==================== Stages récents ==================== -->
    <section class="card">
        <div class="card__head">
            <h2>Stages récents</h2>
            <a class="btn btn--secondary btn--sm" href="<?= url('stages.php') ?>">Tout voir</a>
        </div>
        <div class="card__body card__body--flush">
            <?php if (!$stagesRecents): ?>
                <div class="empty">
                    <span class="empty__icon">📋</span>
                    <p>Aucun stage enregistré</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Stagiaire</th>
                            <th>Service</th>
                            <th>Période</th>
                            <th>Statut</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stagesRecents as $s): ?>
                            <tr>
                                <td>
                                    <a href="<?= url('stage_details.php?id=' . (int) $s['id_stage']) ?>">
                                        <strong><?= e($s['prenom'] . ' ' . $s['nom']) ?></strong>
                                    </a>
                                    <div class="person__sub"><?= e($s['sujet']) ?></div>
                                </td>
                                <td class="muted"><?= e($s['nom_service'] ?? '—') ?></td>
                                <td class="nowrap"><?= dateFr($s['date_debut'], 'd/m/y') ?> → <?= dateFr($s['date_fin'], 'd/m/y') ?></td>
                                <td><span class="<?= statutBadge($s['statut']) ?>"><?= e($s['statut']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ==================== Répartition par type ==================== -->
    <section class="card">
        <div class="card__head">
            <h2>Répartition par type de stage</h2>
        </div>
        <div class="card__body">
            <?php if (!$parType): ?>
                <div class="empty">
                    <span class="empty__icon">🏷️</span>
                    <p>Aucune donnée à afficher</p>
                </div>
            <?php else: ?>
                <div class="dist">
                    <?php foreach ($parType as $row): ?>
                        <div class="dist__row">
                            <span class="dist__name"><?= e($row['libelle']) ?></span>
                            <span class="dist__count"><?= (int) $row['total'] ?> stage(s)</span>
                            <div class="dist__track">
                                <div class="dist__fill" style="width: <?= round($row['total'] / $maxType * 100) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- ==================== Dernières tâches ==================== -->
<section class="card">
    <div class="card__head">
        <h2>Dernières tâches saisies</h2>
        <a class="btn btn--secondary btn--sm" href="<?= url('taches.php') ?>">Tout voir</a>
    </div>
    <div class="card__body">
        <?php if (!$tachesRecentes): ?>
            <div class="empty">
                <span class="empty__icon">✓</span>
                <p>Aucune tâche saisie pour le moment</p>
            </div>
        <?php else: ?>
            <div class="timeline">
                <?php foreach ($tachesRecentes as $t): ?>
                    <?php $mod = match ($t['etat']) {
                        'Validée' => ' timeline__item--success',
                        'Rejetée' => ' timeline__item--danger',
                        default   => ' timeline__item--warning',
                    }; ?>
                    <div class="timeline__item<?= $mod ?>">
                        <div class="timeline__date">
                            <?= dateFr($t['date_tache']) ?> — <?= e($t['prenom'] . ' ' . $t['nom']) ?>
                            <span class="<?= etatBadge($t['etat']) ?>" style="margin-left:6px"><?= e($t['etat']) ?></span>
                        </div>
                        <div class="timeline__title"><?= e($t['titre']) ?></div>
                        <?php if ($t['description']): ?>
                            <div class="timeline__text"><?= e(mb_strimwidth($t['description'], 0, 140, '…')) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ==================== Évaluations avec observations ==================== -->
<?php if ($evaluationsAvecObs): ?>
<section class="card">
    <div class="card__head">
        <h2>Évaluations avec commentaires</h2>
        <a class="btn btn--secondary btn--sm" href="<?= url('evaluations.php') ?>">Tout voir</a>
    </div>
    <div class="card__body card__body--flush">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Stagiaire</th>
                    <th>Sujet</th>
                    <th>Décision</th>
                    <th style="width:80px">Observations</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($evaluationsAvecObs as $e): ?>
                    <tr>
                        <td>
                            <strong><?= e($e['prenom'] . ' ' . $e['nom']) ?></strong>
                        </td>
                        <td style="max-width:250px"><?= e(mb_strimwidth($e['sujet'], 0, 55, '…')) ?></td>
                        <td>
                            <?php if ($e['decision'] === 'Validé'): ?>
                                <span class="badge badge--success">Validé</span>
                            <?php elseif ($e['decision'] === 'Non validé'): ?>
                                <span class="badge badge--danger">Non validé</span>
                            <?php else: ?>
                                <span class="badge badge--warning">Non évalué</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge--info" title="<?= e($e['observations']) ?>" style="cursor:help; display:inline-block">
                                <strong>💬</strong> Obs.
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
