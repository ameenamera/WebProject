<?php
/**
 * Gestion des instituts de stage (établissements d'origine).
 */

require_once __DIR__ . '/../includes/auth.php';
exigerRole(['admin']);

$pageTitle  = 'Instituts de stage';
$activeMenu = 'instituts';

$erreur = '';
$edit   = intOrNull(get('edit'))
    ? fetchOne('SELECT * FROM institut WHERE id_institut = ?', [intOrNull(get('edit'))])
    : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    if (post('action') === 'supprimer') {
        $id = intOrNull(post('id_institut'));
        if ($id) {
            query('DELETE FROM institut WHERE id_institut = ?', [$id]);
            flash('Institut supprimé.');
        }
        redirect('instituts.php');
    }

    $id    = intOrNull(post('id_institut'));
    $nom   = post('nom_institut');
    $ville = post('ville');
    $tel   = post('telephone');
    $mail  = post('email');

    if ($nom === '') {
        $erreur = 'Le nom de l\'institut est obligatoire.';
    } elseif ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'Format d\'email invalide.';
    } elseif (fetchValue('SELECT id_institut FROM institut WHERE nom_institut = ? AND id_institut <> ?', [$nom, $id ?? 0])) {
        $erreur = 'Cet institut existe déjà.';
    } else {
        $champs = [$nom, $ville ?: null, $tel ?: null, $mail ?: null];
        if ($id) {
            query('UPDATE institut SET nom_institut=?, ville=?, telephone=?, email=? WHERE id_institut=?',
                array_merge($champs, [$id]));
            flash('Institut mis à jour.');
        } else {
            query('INSERT INTO institut (nom_institut, ville, telephone, email) VALUES (?,?,?,?)', $champs);
            flash('Institut ajouté avec succès.');
        }
        redirect('instituts.php');
    }
}

$instituts = fetchAll(
    'SELECT i.*, (SELECT COUNT(*) FROM stagiaire st WHERE st.id_institut = i.id_institut) AS nb_stagiaires
     FROM institut i ORDER BY i.nom_institut'
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h2 class="mb-0">Instituts</h2>
        <p>Établissements d'origine des stagiaires — <?= count($instituts) ?> enregistré(s)</p>
    </div>
</div>

<div class="grid-2" style="align-items:start">

    <section class="card">
        <div class="card__head">
            <h3><?= $edit ? 'Modifier l\'institut' : 'Nouvel institut' ?></h3>
            <?php if ($edit): ?>
                <a class="btn btn--secondary btn--sm" href="<?= url('instituts.php') ?>">Annuler</a>
            <?php endif; ?>
        </div>
        <div class="card__body">
            <?php if ($erreur): ?>
                <div class="alert alert--error"><span><?= e($erreur) ?></span></div>
            <?php endif; ?>

            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="id_institut" value="<?= (int) ($edit['id_institut'] ?? 0) ?>">

                <div class="form-grid">
                    <div class="field field--full">
                        <label for="nom_institut">Nom de l'institut <span class="req">*</span></label>
                        <input class="input" type="text" id="nom_institut" name="nom_institut" required maxlength="150"
                               value="<?= e($edit['nom_institut'] ?? '') ?>"
                               placeholder="ex. ISET de Tunis">
                    </div>

                    <div class="field">
                        <label for="ville">Ville</label>
                        <input class="input" type="text" id="ville" name="ville" maxlength="100"
                               value="<?= e($edit['ville'] ?? '') ?>">
                    </div>

                    <div class="field">
                        <label for="telephone">Téléphone</label>
                        <input class="input" type="tel" id="telephone" name="telephone" maxlength="20"
                               value="<?= e($edit['telephone'] ?? '') ?>">
                    </div>

                    <div class="field field--full">
                        <label for="email">Email</label>
                        <input class="input" type="email" id="email" name="email" maxlength="100"
                               value="<?= e($edit['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn" type="submit"><?= $edit ? 'Enregistrer' : 'Ajouter l\'institut' ?></button>
                </div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card__head"><h3>Liste des instituts</h3></div>
        <div class="card__body card__body--flush">
            <?php if (!$instituts): ?>
                <div class="empty">
                    <span class="empty__icon">🎓</span>
                    <p>Aucun institut enregistré.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr><th>Institut</th><th>Contact</th><th>Stagiaires</th><th class="text-right">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($instituts as $i): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600"><?= e($i['nom_institut']) ?></div>
                                    <div class="person__sub"><?= e($i['ville'] ?: '—') ?></div>
                                </td>
                                <td class="muted">
                                    <div><?= e($i['email'] ?: '—') ?></div>
                                    <div class="person__sub"><?= e($i['telephone'] ?: '') ?></div>
                                </td>
                                <td><span class="badge badge--info"><?= (int) $i['nb_stagiaires'] ?></span></td>
                                <td>
                                    <div class="table__actions" style="justify-content:flex-end">
                                        <a class="btn btn--secondary btn--sm"
                                           href="<?= url('instituts.php?edit=' . (int) $i['id_institut']) ?>">Modifier</a>
                                        <form method="post" style="display:inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="supprimer">
                                            <input type="hidden" name="id_institut" value="<?= (int) $i['id_institut'] ?>">
                                            <button class="btn btn--danger btn--sm" type="submit"
                                                    data-confirm="Supprimer cet institut ?">Suppr.</button>
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
