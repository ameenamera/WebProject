<?php
/**
 * Gestion des types de stage (ouvrier, technicien, PFE…).
 */

require_once __DIR__ . '/../includes/auth.php';
exigerRole(['admin']);

$pageTitle  = 'Types de stage';
$activeMenu = 'types';

$erreur = '';
$edit   = intOrNull(get('edit'))
    ? fetchOne('SELECT * FROM type_stage WHERE id_type = ?', [intOrNull(get('edit'))])
    : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    if (post('action') === 'supprimer') {
        $id = intOrNull(post('id_type'));
        if ($id) {
            query('DELETE FROM type_stage WHERE id_type = ?', [$id]);
            flash('Type de stage supprimé.');
        }
        redirect('types_stage.php');
    }

    $id      = intOrNull(post('id_type'));
    $libelle = post('libelle');
    $duree   = intOrNull(post('duree_min'));

    if ($libelle === '') {
        $erreur = 'Le libellé est obligatoire.';
    } elseif (fetchValue('SELECT id_type FROM type_stage WHERE libelle = ? AND id_type <> ?', [$libelle, $id ?? 0])) {
        $erreur = 'Ce type de stage existe déjà.';
    } else {
        if ($id) {
            query('UPDATE type_stage SET libelle = ?, duree_min = ? WHERE id_type = ?', [$libelle, $duree, $id]);
            flash('Type de stage mis à jour.');
        } else {
            query('INSERT INTO type_stage (libelle, duree_min) VALUES (?,?)', [$libelle, $duree]);
            flash('Type de stage ajouté.');
        }
        redirect('types_stage.php');
    }
}

$types = fetchAll(
    'SELECT t.*, (SELECT COUNT(*) FROM stage s WHERE s.id_type = t.id_type) AS nb_stages
     FROM type_stage t ORDER BY t.libelle'
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h2 class="mb-0">Types de stage</h2>
        <p>Ouvrier, technicien, PFE… — <?= count($types) ?> type(s) enregistré(s)</p>
    </div>
</div>

<div class="grid-2" style="align-items:start">

    <section class="card">
        <div class="card__head">
            <h3><?= $edit ? 'Modifier le type' : 'Nouveau type de stage' ?></h3>
            <?php if ($edit): ?>
                <a class="btn btn--secondary btn--sm" href="<?= url('types_stage.php') ?>">Annuler</a>
            <?php endif; ?>
        </div>
        <div class="card__body">
            <?php if ($erreur): ?>
                <div class="alert alert--error"><span><?= e($erreur) ?></span></div>
            <?php endif; ?>

            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="id_type" value="<?= (int) ($edit['id_type'] ?? 0) ?>">

                <div class="form-grid">
                    <div class="field">
                        <label for="libelle">Libellé <span class="req">*</span></label>
                        <input class="input" type="text" id="libelle" name="libelle" required maxlength="50"
                               value="<?= e($edit['libelle'] ?? '') ?>" placeholder="ex. Stage PFE">
                    </div>

                    <div class="field">
                        <label for="duree_min">Durée conseillée (jours)</label>
                        <input class="input" type="number" id="duree_min" name="duree_min" min="1" max="500"
                               value="<?= e($edit['duree_min'] ?? '') ?>" placeholder="ex. 120">
                        <span class="hint">Indicatif, à titre de référence.</span>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn" type="submit"><?= $edit ? 'Enregistrer' : 'Ajouter le type' ?></button>
                </div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card__head"><h3>Liste des types</h3></div>
        <div class="card__body card__body--flush">
            <?php if (!$types): ?>
                <div class="empty">
                    <span class="empty__icon">🏷️</span>
                    <p>Aucun type de stage enregistré.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr><th>Libellé</th><th>Durée conseillée</th><th>Stages</th><th class="text-right">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($types as $t): ?>
                            <tr>
                                <td><strong><?= e($t['libelle']) ?></strong></td>
                                <td class="muted"><?= $t['duree_min'] ? (int) $t['duree_min'] . ' jours' : '—' ?></td>
                                <td><span class="badge badge--info"><?= (int) $t['nb_stages'] ?></span></td>
                                <td>
                                    <div class="table__actions" style="justify-content:flex-end">
                                        <a class="btn btn--secondary btn--sm"
                                           href="<?= url('types_stage.php?edit=' . (int) $t['id_type']) ?>">Modifier</a>
                                        <form method="post" style="display:inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="supprimer">
                                            <input type="hidden" name="id_type" value="<?= (int) $t['id_type'] ?>">
                                            <button class="btn btn--danger btn--sm" type="submit"
                                                    data-confirm="Supprimer ce type de stage ?">Suppr.</button>
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
