<?php
/**
 * Gestion des documents : dépôt de la convention, du rapport,
 * de l'attestation, et téléchargement.
 */

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion();

$pageTitle  = 'Gestion des documents';
$activeMenu = 'documents';

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

// ---------------------------------------------------------------- Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = post('action');

    // -------------------------------------------------- Dépôt
    if ($action === 'deposer') {
        $stageCible = intOrNull(post('id_stage'));
        $type       = post('type_document');
        $fichier    = $_FILES['fichier'] ?? null;

        if (!$stageCible || !peutVoirStage($stageCible)) {
            flash('Stage invalide.', 'error');
        } elseif (!in_array($type, DOCUMENT_TYPES, true)) {
            flash('Type de document invalide.', 'error');
        } elseif (!$fichier || $fichier['error'] !== UPLOAD_ERR_OK) {
            $msg = match ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille autorisée.',
                UPLOAD_ERR_NO_FILE  => 'Aucun fichier sélectionné.',
                UPLOAD_ERR_PARTIAL  => 'Le transfert du fichier a été interrompu.',
                default             => 'Erreur lors du transfert du fichier.',
            };
            flash($msg, 'error');
        } elseif ($fichier['size'] > MAX_UPLOAD_SIZE) {
            flash('Le fichier dépasse ' . formatSize(MAX_UPLOAD_SIZE) . '.', 'error');
        } else {
            $ext = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
                flash('Format non autorisé. Formats acceptés : ' . implode(', ', ALLOWED_EXTENSIONS) . '.', 'error');
            } else {
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0775, true);
                }

                // Nom de stockage unique : impossible d'écraser un fichier existant
                // ou d'injecter un chemin via le nom d'origine.
                $nomStocke = sprintf('stage%d_%s_%s.%s', $stageCible, strtolower($type), bin2hex(random_bytes(8)), $ext);

                if (move_uploaded_file($fichier['tmp_name'], UPLOAD_DIR . '/' . $nomStocke)) {
                    query(
                        'INSERT INTO document (id_stage, type_document, nom_fichier, chemin, taille, depose_par)
                         VALUES (?,?,?,?,?,?)',
                        [$stageCible, $type, basename($fichier['name']), $nomStocke,
                         (int) $fichier['size'], utilisateur()['id']]
                    );
                    flash("Document « $type » déposé avec succès.");
                } else {
                    flash('Impossible d\'enregistrer le fichier sur le serveur.', 'error');
                }
            }
        }
        redirect('documents.php' . ($idStage ? '?stage=' . $idStage : ''));
    }

    // -------------------------------------------------- Suppression
    if ($action === 'supprimer') {
        $idDoc = intOrNull(post('id_document'));
        $doc   = $idDoc ? fetchOne('SELECT * FROM document WHERE id_document = ?', [$idDoc]) : null;

        if (!$doc || !peutVoirStage((int) $doc['id_stage'])) {
            flash('Document introuvable.', 'error');
        } else {
            $chemin = UPLOAD_DIR . '/' . $doc['chemin'];
            if (is_file($chemin)) {
                unlink($chemin);
            }
            query('DELETE FROM document WHERE id_document = ?', [$idDoc]);
            flash('Document supprimé.');
        }
        redirect('documents.php' . ($idStage ? '?stage=' . $idStage : ''));
    }
}

// ---------------------------------------------------------------- Données
$filtreType = get('type');

$params = [];
$sql = "SELECT d.*, s.sujet, st.nom, st.prenom, u.login AS deposant
        FROM document d
        JOIN stage s      ON s.id_stage = d.id_stage
        JOIN stagiaire st ON st.id_stagiaire = s.id_stagiaire
        LEFT JOIN utilisateur u ON u.id_utilisateur = d.depose_par
        WHERE 1=1";
$sql .= filtreStagesParRole($params);

if ($idStage)     { $sql .= ' AND d.id_stage = ?';      $params[] = $idStage; }
if ($filtreType !== '' && in_array($filtreType, DOCUMENT_TYPES, true)) {
    $sql .= ' AND d.type_document = ?';
    $params[] = $filtreType;
}

$sql .= ' ORDER BY d.date_depot DESC';
$documents = fetchAll($sql, $params);

$p2 = [];
$stagesDispo = fetchAll(
    'SELECT s.id_stage, s.sujet, st.nom, st.prenom
     FROM stage s JOIN stagiaire st ON st.id_stagiaire = s.id_stagiaire
     WHERE 1=1 ' . filtreStagesParRole($p2) . ' ORDER BY st.nom',
    $p2
);

// Documents déjà présents pour le stage courant (checklist).
$presents = [];
if ($idStage) {
    foreach (fetchAll('SELECT DISTINCT type_document FROM document WHERE id_stage = ?', [$idStage]) as $r) {
        $presents[] = $r['type_document'];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h2 class="mb-0">Documents</h2>
        <p><?= count($documents) ?> document(s) — convention, rapport, attestation</p>
    </div>
    <?php if ($idStage): ?>
        <a class="btn btn--secondary" href="<?= url('stage_details.php?id=' . $idStage) ?>">← Fiche du stage</a>
    <?php endif; ?>
</div>

<?php if ($idStage): ?>
    <section class="card">
        <div class="card__head"><h3>Pièces du dossier</h3></div>
        <div class="card__body">
            <div class="grid-3">
                <?php foreach (['Convention', 'Rapport', 'Attestation'] as $type):
                    $ok = in_array($type, $presents, true); ?>
                    <div class="stat">
                        <div class="stat__icon <?= $ok ? 'stat__icon--success' : 'stat__icon--warning' ?>">
                            <?= $ok ? '✓' : '!' ?>
                        </div>
                        <div>
                            <div class="stat__value" style="font-size:16px"><?= e($type) ?></div>
                            <div class="stat__label"><?= $ok ? 'Déposé' : 'Manquant' ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- ==================== Dépôt ==================== -->
<?php if ($stagesDispo): ?>
<section class="card">
    <div class="card__head">
        <div>
            <h3>Déposer un document</h3>
            <p>Formats acceptés : <?= e(implode(', ', ALLOWED_EXTENSIONS)) ?> — <?= e(formatSize(MAX_UPLOAD_SIZE)) ?> maximum.</p>
        </div>
    </div>
    <div class="card__body">
        <form method="post" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="deposer">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_UPLOAD_SIZE ?>">

            <div class="form-grid">
                <div class="field">
                    <label for="id_stage">Stage <span class="req">*</span></label>
                    <select class="select" id="id_stage" name="id_stage" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($stagesDispo as $sd): ?>
                            <option value="<?= (int) $sd['id_stage'] ?>" <?= $idStage === (int) $sd['id_stage'] ? 'selected' : '' ?>>
                                <?= e($sd['prenom'] . ' ' . $sd['nom'] . ' — ' . mb_strimwidth($sd['sujet'], 0, 40, '…')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="type_document">Type de document <span class="req">*</span></label>
                    <select class="select" id="type_document" name="type_document" required>
                        <?php foreach (DOCUMENT_TYPES as $t): ?>
                            <option value="<?= e($t) ?>"><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="fichier">Fichier <span class="req">*</span></label>
                    <input class="input" type="file" id="fichier" name="fichier" required
                           accept=".<?= implode(',.', ALLOWED_EXTENSIONS) ?>">
                </div>
            </div>

            <div class="form-actions">
                <button class="btn" type="submit">Déposer le document</button>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<!-- ==================== Liste ==================== -->
<section class="card">
    <div class="card__head">
        <h3>Documents déposés</h3>
        <form method="get" class="toolbar" style="margin:0">
            <?php if ($idStage): ?><input type="hidden" name="stage" value="<?= $idStage ?>"><?php endif; ?>
            <div class="field">
                <select class="select" name="type" data-auto-submit>
                    <option value="">Tous les types</option>
                    <?php foreach (DOCUMENT_TYPES as $t): ?>
                        <option value="<?= e($t) ?>" <?= $filtreType === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
    <div class="card__body card__body--flush">
        <?php if (!$documents): ?>
            <div class="empty">
                <span class="empty__icon">📎</span>
                <p>Aucun document déposé pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Type</th>
                        <th>Fichier</th>
                        <?php if (!estStagiaire()): ?><th>Stagiaire</th><?php endif; ?>
                        <th>Taille</th>
                        <th>Déposé le</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($documents as $d): ?>
                        <tr>
                            <td><span class="badge badge--info"><?= e($d['type_document']) ?></span></td>
                            <td>
                                <div style="font-weight:600"><?= e($d['nom_fichier']) ?></div>
                                <?php if ($d['deposant']): ?>
                                    <div class="person__sub">par <?= e($d['deposant']) ?></div>
                                <?php endif; ?>
                            </td>
                            <?php if (!estStagiaire()): ?>
                                <td><?= e($d['prenom'] . ' ' . $d['nom']) ?></td>
                            <?php endif; ?>
                            <td class="muted"><?= formatSize($d['taille'] !== null ? (int) $d['taille'] : null) ?></td>
                            <td class="muted nowrap"><?= dateFr($d['date_depot'], 'd/m/Y H:i') ?></td>
                            <td>
                                <div class="table__actions" style="justify-content:flex-end">
                                    <a class="btn btn--secondary btn--sm"
                                       href="<?= url('telecharger.php?id=' . (int) $d['id_document']) ?>">Télécharger</a>
                                    <?php if (estAdmin() || estEncadrant() || (int) $d['depose_par'] === utilisateur()['id']): ?>
                                        <form method="post" style="display:inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="supprimer">
                                            <input type="hidden" name="id_document" value="<?= (int) $d['id_document'] ?>">
                                            <button class="btn btn--danger btn--sm" type="submit"
                                                    data-confirm="Supprimer définitivement ce document ?">Suppr.</button>
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
