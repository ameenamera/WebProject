<?php
/**
 * Gestion des services de l'entreprise.
 */

require_once __DIR__ . '/../includes/auth.php';
exigerRole(['admin']);

$pageTitle  = 'Gestion des services';
$activeMenu = 'services';

$erreur = '';
$edit   = intOrNull(get('edit'))
    ? fetchOne('SELECT * FROM service WHERE id_service = ?', [intOrNull(get('edit'))])
    : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = post('action');

    if ($action === 'supprimer') {
        $id = intOrNull(post('id_service'));
        if ($id) {
            $nb = (int) fetchValue('SELECT COUNT(*) FROM stage WHERE id_service = ?', [$id]);
            query('DELETE FROM service WHERE id_service = ?', [$id]);
            flash($nb > 0
                ? "Service supprimé. $nb stage(s) n'ont plus de service affecté."
                : 'Service supprimé.');
        }
        redirect('services.php');
    }

    $id  = intOrNull(post('id_service'));
    $nom = post('nom_service');
    $desc = post('description');

    if ($nom === '') {
        $erreur = 'Le nom du service est obligatoire.';
    } elseif (fetchValue('SELECT id_service FROM service WHERE nom_service = ? AND id_service <> ?', [$nom, $id ?? 0])) {
        $erreur = 'Ce service existe déjà.';
    } else {
        if ($id) {
            query('UPDATE service SET nom_service = ?, description = ? WHERE id_service = ?', [$nom, $desc ?: null, $id]);
            flash('Service mis à jour.');
        } else {
            query('INSERT INTO service (nom_service, description) VALUES (?,?)', [$nom, $desc ?: null]);
            flash('Service ajouté avec succès.');
        }
        redirect('services.php');
    }
}

$services = fetchAll(
    'SELECT sv.*,
            (SELECT COUNT(*) FROM stage s WHERE s.id_service = sv.id_service)     AS nb_stages,
            (SELECT COUNT(*) FROM encadrant e WHERE e.id_service = sv.id_service) AS nb_encadrants
     FROM service sv ORDER BY sv.nom_service'
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h2 class="mb-0">Services</h2>
        <p>Services d'affectation des stagiaires — <?= count($services) ?> enregistré(s)</p>
    </div>
</div>

<div class="grid-2" style="align-items:start">

    <section class="card">
        <div class="card__head">
            <h3><?= $edit ? 'Modifier le service' : 'Nouveau service' ?></h3>
            <?php if ($edit): ?>
                <a class="btn btn--secondary btn--sm" href="<?= url('services.php') ?>">Annuler</a>
            <?php endif; ?>
        </div>
        <div class="card__body">
            <?php if ($erreur): ?>
                <div class="alert alert--error"><span><?= e($erreur) ?></span></div>
            <?php endif; ?>

            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="id_service" value="<?= (int) ($edit['id_service'] ?? 0) ?>">

                <div class="field" style="margin-bottom:14px">
                    <label for="nom_service">Nom du service <span class="req">*</span></label>
                    <input class="input" type="text" id="nom_service" name="nom_service" required maxlength="100"
                           value="<?= e($edit['nom_service'] ?? '') ?>"
                           placeholder="ex. Service Informatique">
                </div>

                <div class="field">
                    <label for="description">Description</label>
                    <textarea class="textarea" id="description" name="description"
                              placeholder="Missions et périmètre du service…"><?= e($edit['description'] ?? '') ?></textarea>
                </div>

                <div class="form-actions">
                    <button class="btn" type="submit"><?= $edit ? 'Enregistrer' : 'Ajouter le service' ?></button>
                </div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card__head"><h3>Liste des services</h3></div>
        <div class="card__body card__body--flush">
            <?php if (!$services): ?>
                <div class="empty">
                    <span class="empty__icon">🏢</span>
                    <p>Aucun service enregistré.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr><th>Service</th><th>Stages</th><th>Encadrants</th><th class="text-right">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($services as $sv): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600"><?= e($sv['nom_service']) ?></div>
                                    <?php if ($sv['description']): ?>
                                        <div class="person__sub"><?= e(mb_strimwidth($sv['description'], 0, 60, '…')) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge--info"><?= (int) $sv['nb_stages'] ?></span></td>
                                <td><span class="badge"><?= (int) $sv['nb_encadrants'] ?></span></td>
                                <td>
                                    <div class="table__actions" style="justify-content:flex-end">
                                        <a class="btn btn--secondary btn--sm"
                                           href="<?= url('services.php?edit=' . (int) $sv['id_service']) ?>">Modifier</a>
                                        <form method="post" style="display:inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="supprimer">
                                            <input type="hidden" name="id_service" value="<?= (int) $sv['id_service'] ?>">
                                            <button class="btn btn--danger btn--sm" type="submit"
                                                    data-confirm="Supprimer ce service ?">Suppr.</button>
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
