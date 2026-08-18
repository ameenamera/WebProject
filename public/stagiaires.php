<?php
/**
 * Gestion des stagiaires — liste, recherche, suppression.
 */

require_once __DIR__ . '/../includes/auth.php';
exigerRole(['admin']);

$pageTitle  = 'Gestion des stagiaires';
$activeMenu = 'stagiaires';

// --- Suppression ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'supprimer') {
    checkCsrf();
    $id = intOrNull(post('id_stagiaire'));

    if ($id) {
        $nbStages = (int) fetchValue('SELECT COUNT(*) FROM stage WHERE id_stagiaire = ?', [$id]);
        query('DELETE FROM stagiaire WHERE id_stagiaire = ?', [$id]);
        flash($nbStages > 0
            ? "Stagiaire supprimé, ainsi que ses $nbStages stage(s) et données associées."
            : 'Stagiaire supprimé avec succès.');
    }
    redirect('stagiaires.php');
}

// --- Recherche & filtres -------------------------------------------------
$recherche = get('q');
$institut  = intOrNull(get('institut'));
$niveau    = get('niveau');

$sql = "SELECT st.*, i.nom_institut,
               (SELECT COUNT(*) FROM stage s WHERE s.id_stagiaire = st.id_stagiaire) AS nb_stages,
               (SELECT s2.statut FROM stage s2 WHERE s2.id_stagiaire = st.id_stagiaire
                 ORDER BY s2.date_debut DESC LIMIT 1) AS dernier_statut
        FROM stagiaire st
        LEFT JOIN institut i ON i.id_institut = st.id_institut
        WHERE 1=1";
$params = [];

if ($recherche !== '') {
    $sql .= ' AND (st.nom LIKE ? OR st.prenom LIKE ? OR st.cin LIKE ? OR st.email LIKE ?
                   OR st.etablissement LIKE ? OR st.specialite LIKE ?)';
    $like = '%' . $recherche . '%';
    $params = array_merge($params, array_fill(0, 6, $like));
}
if ($institut) {
    $sql .= ' AND st.id_institut = ?';
    $params[] = $institut;
}
if ($niveau !== '') {
    $sql .= ' AND st.niveau = ?';
    $params[] = $niveau;
}

$sql .= ' ORDER BY st.nom, st.prenom';
$stagiaires = fetchAll($sql, $params);

$niveaux = fetchAll("SELECT DISTINCT niveau FROM stagiaire WHERE niveau <> '' ORDER BY niveau");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h2 class="mb-0">Stagiaires</h2>
        <p><?= count($stagiaires) ?> stagiaire(s) trouvé(s)</p>
    </div>
    <a class="btn" href="<?= url('stagiaire_form.php') ?>">+ Ajouter un stagiaire</a>
</div>

<!-- ============================ Recherche ============================ -->
<section class="card">
    <div class="card__body">
        <form class="toolbar" method="get">
            <div class="field" style="flex:1;min-width:240px">
                <label for="q">Rechercher</label>
                <input class="input" type="search" id="q" name="q" value="<?= e($recherche) ?>"
                       placeholder="Nom, prénom, CIN, email, établissement…">
            </div>

            <div class="field">
                <label for="institut">Institut</label>
                <select class="select" id="institut" name="institut" data-auto-submit>
                    <option value="">Tous</option>
                    <?php foreach (listeInstituts() as $i): ?>
                        <option value="<?= (int) $i['id_institut'] ?>" <?= $institut === (int) $i['id_institut'] ? 'selected' : '' ?>>
                            <?= e($i['nom_institut']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="niveau">Niveau</label>
                <select class="select" id="niveau" name="niveau" data-auto-submit>
                    <option value="">Tous</option>
                    <?php foreach ($niveaux as $n): ?>
                        <option value="<?= e($n['niveau']) ?>" <?= $niveau === $n['niveau'] ? 'selected' : '' ?>>
                            <?= e($n['niveau']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="btn" type="submit">Rechercher</button>
            <?php if ($recherche !== '' || $institut || $niveau !== ''): ?>
                <a class="btn btn--secondary" href="<?= url('stagiaires.php') ?>">Réinitialiser</a>
            <?php endif; ?>
        </form>
    </div>
</section>

<!-- ============================ Liste ============================ -->
<section class="card">
    <div class="card__body card__body--flush">
        <?php if (!$stagiaires): ?>
            <div class="empty">
                <span class="empty__icon">👥</span>
                <p>Aucun stagiaire ne correspond à votre recherche.</p>
                <a class="btn" href="<?= url('stagiaire_form.php') ?>" style="margin-top:12px">Ajouter le premier stagiaire</a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Stagiaire</th>
                        <th>CIN</th>
                        <th>Contact</th>
                        <th>Établissement</th>
                        <th>Spécialité / Niveau</th>
                        <th>Stages</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($stagiaires as $s): ?>
                        <tr>
                            <td>
                                <div class="person">
                                    <span class="person__avatar"><?= e(initials($s['nom'], $s['prenom'])) ?></span>
                                    <div>
                                        <div class="person__name"><?= e($s['prenom'] . ' ' . $s['nom']) ?></div>
                                        <?php if ($s['dernier_statut']): ?>
                                            <span class="<?= statutBadge($s['dernier_statut']) ?>"><?= e($s['dernier_statut']) ?></span>
                                        <?php else: ?>
                                            <span class="person__sub">Aucun stage</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="muted"><?= e($s['cin']) ?></td>
                            <td>
                                <div><?= e($s['email']) ?></div>
                                <div class="person__sub"><?= e($s['telephone'] ?: '—') ?></div>
                            </td>
                            <td><?= e($s['nom_institut'] ?: $s['etablissement'] ?: '—') ?></td>
                            <td>
                                <div><?= e($s['specialite'] ?: '—') ?></div>
                                <div class="person__sub"><?= e($s['niveau'] ?: '—') ?></div>
                            </td>
                            <td><span class="badge"><?= (int) $s['nb_stages'] ?></span></td>
                            <td>
                                <div class="table__actions" style="justify-content:flex-end">
                                    <a class="btn btn--secondary btn--sm"
                                       href="<?= url('stagiaire_form.php?id=' . (int) $s['id_stagiaire']) ?>">Modifier</a>
                                    <form method="post" style="display:inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="supprimer">
                                        <input type="hidden" name="id_stagiaire" value="<?= (int) $s['id_stagiaire'] ?>">
                                        <button class="btn btn--danger btn--sm" type="submit"
                                                data-confirm="Supprimer <?= e($s['prenom'] . ' ' . $s['nom']) ?> ? Ses stages, tâches et documents seront également supprimés.">
                                            Supprimer
                                        </button>
                                    </form>
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
