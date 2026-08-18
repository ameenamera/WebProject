<?php
/**
 * Création / modification d'un stage.
 */

require_once __DIR__ . '/../includes/auth.php';
exigerRole(['admin']);

$id      = intOrNull(get('id'));
$edition = $id !== null;
$activeMenu = 'stages';
$pageTitle  = $edition ? 'Modifier le stage' : 'Nouveau stage';

$data = [
    'id_stagiaire' => '', 'id_service' => '', 'id_encadrant' => '', 'id_type' => '',
    'sujet' => '', 'description' => '', 'date_debut' => '', 'date_fin' => '',
    'statut' => 'En cours',
];
$erreurs = [];

if ($edition) {
    $existant = fetchOne('SELECT * FROM stage WHERE id_stage = ?', [$id]);
    if (!$existant) {
        flash('Stage introuvable.', 'error');
        redirect('stages.php');
    }
    $data = array_merge($data, $existant);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    foreach (array_keys($data) as $champ) {
        $data[$champ] = post($champ);
    }

    if (!intOrNull($data['id_stagiaire'])) $erreurs['id_stagiaire'] = 'Sélectionnez un stagiaire.';
    if ($data['sujet'] === '')             $erreurs['sujet'] = 'Le sujet du stage est obligatoire.';
    if ($data['date_debut'] === '')        $erreurs['date_debut'] = 'Date de début obligatoire.';
    if ($data['date_fin'] === '')          $erreurs['date_fin'] = 'Date de fin obligatoire.';

    if ($data['date_debut'] !== '' && $data['date_fin'] !== ''
        && $data['date_fin'] < $data['date_debut']) {
        $erreurs['date_fin'] = 'La date de fin doit être postérieure à la date de début.';
    }

    if (!in_array($data['statut'], STAGE_STATUTS, true)) {
        $data['statut'] = 'En cours';
    }

    if (!$erreurs) {
        // On conserve aussi le libellé du type dans stage.type_stage (champ du cahier des charges).
        $libelleType = null;
        if ($t = intOrNull($data['id_type'])) {
            $libelleType = fetchValue('SELECT libelle FROM type_stage WHERE id_type = ?', [$t]);
        }

        $champs = [
            intOrNull($data['id_stagiaire']),
            intOrNull($data['id_service']),
            intOrNull($data['id_encadrant']),
            intOrNull($data['id_type']),
            $data['sujet'],
            $data['description'] ?: null,
            $data['date_debut'],
            $data['date_fin'],
            $libelleType,
            $data['statut'],
        ];

        if ($edition) {
            query(
                'UPDATE stage SET id_stagiaire=?, id_service=?, id_encadrant=?, id_type=?,
                        sujet=?, description=?, date_debut=?, date_fin=?, type_stage=?, statut=?
                 WHERE id_stage=?',
                array_merge($champs, [$id])
            );
            flash('Stage mis à jour avec succès.');
            redirect('stage_details.php?id=' . $id);
        }

        query(
            'INSERT INTO stage (id_stagiaire, id_service, id_encadrant, id_type,
                    sujet, description, date_debut, date_fin, type_stage, statut)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            $champs
        );
        $nouvelId = (int) db()->lastInsertId();
        flash('Stage créé avec succès.');
        redirect('stage_details.php?id=' . $nouvelId);
    }
}

$stagiaires = fetchAll('SELECT id_stagiaire, nom, prenom, cin FROM stagiaire ORDER BY nom, prenom');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h2 class="mb-0"><?= e($pageTitle) ?></h2>
        <p>Affectation du stagiaire, période, sujet et suivi.</p>
    </div>
    <a class="btn btn--secondary" href="<?= url('stages.php') ?>">← Retour aux stages</a>
</div>

<?php if ($erreurs): ?>
    <div class="alert alert--error"><span>Veuillez corriger les erreurs signalées ci-dessous.</span></div>
<?php endif; ?>

<?php if (!$stagiaires): ?>
    <div class="alert alert--warning">
        <span>Aucun stagiaire n'est enregistré. Ajoutez d'abord un stagiaire avant de créer un stage.</span>
    </div>
<?php endif; ?>

<form method="post">
    <?= csrfField() ?>

    <section class="card">
        <div class="card__head"><h3>Affectation</h3></div>
        <div class="card__body">
            <div class="form-grid">
                <div class="field">
                    <label for="id_stagiaire">Stagiaire <span class="req">*</span></label>
                    <select class="select" id="id_stagiaire" name="id_stagiaire" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($stagiaires as $st): ?>
                            <option value="<?= (int) $st['id_stagiaire'] ?>"
                                <?= (string) $data['id_stagiaire'] === (string) $st['id_stagiaire'] ? 'selected' : '' ?>>
                                <?= e($st['prenom'] . ' ' . $st['nom'] . ' (' . $st['cin'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($erreurs['id_stagiaire'])): ?><span class="hint" style="color:var(--danger)"><?= e($erreurs['id_stagiaire']) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="id_service">Service d'affectation</label>
                    <select class="select" id="id_service" name="id_service">
                        <option value="">— Aucun —</option>
                        <?php foreach (listeServices() as $sv): ?>
                            <option value="<?= (int) $sv['id_service'] ?>"
                                <?= (string) $data['id_service'] === (string) $sv['id_service'] ? 'selected' : '' ?>>
                                <?= e($sv['nom_service']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="id_encadrant">Encadrant</label>
                    <select class="select" id="id_encadrant" name="id_encadrant">
                        <option value="">— Aucun —</option>
                        <?php foreach (listeEncadrants() as $en): ?>
                            <option value="<?= (int) $en['id_encadrant'] ?>"
                                <?= (string) $data['id_encadrant'] === (string) $en['id_encadrant'] ? 'selected' : '' ?>>
                                <?= e($en['prenom'] . ' ' . $en['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">L'encadrant valide les tâches et évalue le stage.</span>
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card__head"><h3>Détails du stage</h3></div>
        <div class="card__body">
            <div class="form-grid">
                <div class="field field--full">
                    <label for="sujet">Sujet du stage <span class="req">*</span></label>
                    <input class="input" type="text" id="sujet" name="sujet" required maxlength="255"
                           value="<?= e($data['sujet']) ?>"
                           placeholder="ex. Développement d'une application de gestion des stages">
                    <?php if (isset($erreurs['sujet'])): ?><span class="hint" style="color:var(--danger)"><?= e($erreurs['sujet']) ?></span><?php endif; ?>
                </div>

                <div class="field field--full">
                    <label for="description">Description / objectifs</label>
                    <textarea class="textarea" id="description" name="description"
                              placeholder="Objectifs, technologies, livrables attendus…"><?= e($data['description']) ?></textarea>
                </div>

                <div class="field">
                    <label for="id_type">Type de stage</label>
                    <select class="select" id="id_type" name="id_type">
                        <option value="">— Aucun —</option>
                        <?php foreach (listeTypes() as $t): ?>
                            <option value="<?= (int) $t['id_type'] ?>"
                                <?= (string) $data['id_type'] === (string) $t['id_type'] ? 'selected' : '' ?>>
                                <?= e($t['libelle']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="statut">État du stage</label>
                    <select class="select" id="statut" name="statut">
                        <?php foreach (STAGE_STATUTS as $st): ?>
                            <option value="<?= e($st) ?>" <?= $data['statut'] === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="date_debut">Date de début <span class="req">*</span></label>
                    <input class="input" type="date" id="date_debut" name="date_debut" required
                           value="<?= e($data['date_debut']) ?>">
                    <?php if (isset($erreurs['date_debut'])): ?><span class="hint" style="color:var(--danger)"><?= e($erreurs['date_debut']) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="date_fin">Date de fin <span class="req">*</span></label>
                    <input class="input" type="date" id="date_fin" name="date_fin" required
                           value="<?= e($data['date_fin']) ?>">
                    <?php if (isset($erreurs['date_fin'])): ?><span class="hint" style="color:var(--danger)"><?= e($erreurs['date_fin']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <button class="btn" type="submit" <?= $stagiaires ? '' : 'disabled' ?>>
            <?= $edition ? 'Enregistrer les modifications' : 'Créer le stage' ?>
        </button>
        <a class="btn btn--secondary" href="<?= url('stages.php') ?>">Annuler</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
