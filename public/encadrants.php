<?php
/**
 * Gestion des encadrants — liste, ajout, modification, suppression,
 * et création du compte de connexion associé.
 */

require_once __DIR__ . '/../includes/auth.php';
exigerRole(['admin']);

$pageTitle  = 'Gestion des encadrants';
$activeMenu = 'encadrants';

$erreurs = [];
$edit    = null;
$idEdit  = intOrNull(get('edit'));

if ($idEdit) {
    $edit = fetchOne('SELECT * FROM encadrant WHERE id_encadrant = ?', [$idEdit]);
    if ($edit) {
        $edit['compte'] = fetchOne('SELECT * FROM utilisateur WHERE id_encadrant = ?', [$idEdit]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = post('action');

    // ------------------------------------------------------ Suppression
    if ($action === 'supprimer') {
        $id = intOrNull(post('id_encadrant'));
        if ($id) {
            query('DELETE FROM encadrant WHERE id_encadrant = ?', [$id]);
            flash('Encadrant supprimé. Les stages concernés n\'ont plus d\'encadrant affecté.', 'info');
        }
        redirect('encadrants.php');
    }

    // ------------------------------------------------ Ajout / modification
    $id        = intOrNull(post('id_encadrant'));
    $nom       = post('nom');
    $prenom    = post('prenom');
    $email     = post('email');
    $telephone = post('telephone');
    $fonction  = post('fonction');
    $idService = intOrNull(post('id_service'));

    $creerCompte = isset($_POST['creer_compte']);
    $loginCompte = post('login_compte');
    $mdpCompte   = $_POST['mdp_compte'] ?? '';
    $compteExist = $id ? fetchOne('SELECT * FROM utilisateur WHERE id_encadrant = ?', [$id]) : null;

    if ($nom === '')    $erreurs['nom'] = 'Nom obligatoire.';
    if ($prenom === '') $erreurs['prenom'] = 'Prénom obligatoire.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs['email'] = 'Email valide obligatoire.';
    } elseif (fetchValue('SELECT id_encadrant FROM encadrant WHERE email = ? AND id_encadrant <> ?', [$email, $id ?? 0])) {
        $erreurs['email'] = 'Cet email est déjà utilisé par un autre encadrant.';
    }

    if ($creerCompte) {
        if ($loginCompte === '') {
            $erreurs['login_compte'] = 'Identifiant obligatoire.';
        } elseif (fetchValue('SELECT id_utilisateur FROM utilisateur WHERE login = ? AND id_utilisateur <> ?',
                             [$loginCompte, $compteExist['id_utilisateur'] ?? 0])) {
            $erreurs['login_compte'] = 'Identifiant déjà pris.';
        }
        if (!$compteExist && strlen($mdpCompte) < 6) {
            $erreurs['mdp_compte'] = 'Mot de passe : 6 caractères minimum.';
        }
    }

    if (!$erreurs) {
        $champs = [$nom, $prenom, $email, $telephone ?: null, $fonction ?: null, $idService];

        if ($id) {
            query('UPDATE encadrant SET nom=?, prenom=?, email=?, telephone=?, fonction=?, id_service=?
                   WHERE id_encadrant=?', array_merge($champs, [$id]));
            $idEncadrant = $id;
            flash('Encadrant mis à jour.');
        } else {
            query('INSERT INTO encadrant (nom, prenom, email, telephone, fonction, id_service)
                   VALUES (?,?,?,?,?,?)', $champs);
            $idEncadrant = (int) db()->lastInsertId();
            flash('Encadrant ajouté avec succès.');
        }

        if ($creerCompte) {
            if ($compteExist) {
                $mdpCompte !== ''
                    ? query('UPDATE utilisateur SET login=?, mot_de_passe=? WHERE id_utilisateur=?',
                        [$loginCompte, password_hash($mdpCompte, PASSWORD_DEFAULT), $compteExist['id_utilisateur']])
                    : query('UPDATE utilisateur SET login=? WHERE id_utilisateur=?',
                        [$loginCompte, $compteExist['id_utilisateur']]);
            } else {
                query('INSERT INTO utilisateur (login, mot_de_passe, role, id_encadrant) VALUES (?,?,?,?)',
                    [$loginCompte, password_hash($mdpCompte, PASSWORD_DEFAULT), 'encadrant', $idEncadrant]);
                flash('Compte de connexion créé pour l\'encadrant.', 'info');
            }
        }

        redirect('encadrants.php');
    }
}

$encadrants = fetchAll(
    'SELECT e.*, sv.nom_service,
            (SELECT COUNT(*) FROM stage s WHERE s.id_encadrant = e.id_encadrant) AS nb_stages,
            (SELECT COUNT(*) FROM utilisateur u WHERE u.id_encadrant = e.id_encadrant) AS a_compte
     FROM encadrant e
     LEFT JOIN service sv ON sv.id_service = e.id_service
     ORDER BY e.nom, e.prenom'
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h2 class="mb-0">Encadrants</h2>
        <p><?= count($encadrants) ?> encadrant(s) enregistré(s)</p>
    </div>
</div>

<div class="grid-2" style="align-items:start">

    <!-- ============================ Formulaire ============================ -->
    <section class="card">
        <div class="card__head">
            <h3><?= $edit ? 'Modifier l\'encadrant' : 'Nouvel encadrant' ?></h3>
            <?php if ($edit): ?>
                <a class="btn btn--secondary btn--sm" href="<?= url('encadrants.php') ?>">Annuler</a>
            <?php endif; ?>
        </div>
        <div class="card__body">
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="id_encadrant" value="<?= (int) ($edit['id_encadrant'] ?? 0) ?>">

                <div class="form-grid">
                    <div class="field">
                        <label for="nom">Nom <span class="req">*</span></label>
                        <input class="input" type="text" id="nom" name="nom" required maxlength="50"
                               value="<?= e(post('nom') ?: ($edit['nom'] ?? '')) ?>">
                        <?php if (isset($erreurs['nom'])): ?><span class="hint" style="color:var(--danger)"><?= e($erreurs['nom']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="prenom">Prénom <span class="req">*</span></label>
                        <input class="input" type="text" id="prenom" name="prenom" required maxlength="50"
                               value="<?= e(post('prenom') ?: ($edit['prenom'] ?? '')) ?>">
                        <?php if (isset($erreurs['prenom'])): ?><span class="hint" style="color:var(--danger)"><?= e($erreurs['prenom']) ?></span><?php endif; ?>
                    </div>

                    <div class="field field--full">
                        <label for="email">Email <span class="req">*</span></label>
                        <input class="input" type="email" id="email" name="email" required maxlength="100"
                               value="<?= e(post('email') ?: ($edit['email'] ?? '')) ?>">
                        <?php if (isset($erreurs['email'])): ?><span class="hint" style="color:var(--danger)"><?= e($erreurs['email']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="telephone">Téléphone</label>
                        <input class="input" type="tel" id="telephone" name="telephone" maxlength="20"
                               value="<?= e(post('telephone') ?: ($edit['telephone'] ?? '')) ?>">
                    </div>

                    <div class="field">
                        <label for="fonction">Fonction</label>
                        <input class="input" type="text" id="fonction" name="fonction" maxlength="100"
                               value="<?= e(post('fonction') ?: ($edit['fonction'] ?? '')) ?>"
                               placeholder="ex. Ingénieur principal">
                    </div>

                    <div class="field field--full">
                        <label for="id_service">Service</label>
                        <select class="select" id="id_service" name="id_service">
                            <option value="">— Aucun —</option>
                            <?php foreach (listeServices() as $sv): ?>
                                <option value="<?= (int) $sv['id_service'] ?>"
                                    <?= (string) ($edit['id_service'] ?? post('id_service')) === (string) $sv['id_service'] ? 'selected' : '' ?>>
                                    <?= e($sv['nom_service']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="field" style="margin:18px 0 12px">
                    <label style="display:flex;align-items:center;gap:9px;cursor:pointer">
                        <input type="checkbox" name="creer_compte" value="1"
                               <?= !empty($edit['compte']) ? 'checked' : '' ?>
                               onclick="document.getElementById('bloc-compte').style.display = this.checked ? 'grid' : 'none'">
                        Compte de connexion (espace encadrant)
                    </label>
                </div>

                <div class="form-grid" id="bloc-compte" style="display:<?= !empty($edit['compte']) ? 'grid' : 'none' ?>">
                    <div class="field">
                        <label for="login_compte">Identifiant</label>
                        <input class="input" type="text" id="login_compte" name="login_compte" maxlength="50"
                               value="<?= e($edit['compte']['login'] ?? '') ?>">
                        <?php if (isset($erreurs['login_compte'])): ?><span class="hint" style="color:var(--danger)"><?= e($erreurs['login_compte']) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="mdp_compte">Mot de passe</label>
                        <input class="input" type="password" id="mdp_compte" name="mdp_compte" autocomplete="new-password">
                        <span class="hint"><?= !empty($edit['compte']) ? 'Vide = inchangé.' : '6 caractères min.' ?></span>
                        <?php if (isset($erreurs['mdp_compte'])): ?><span class="hint" style="color:var(--danger)"><?= e($erreurs['mdp_compte']) ?></span><?php endif; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn" type="submit"><?= $edit ? 'Enregistrer' : 'Ajouter l\'encadrant' ?></button>
                </div>
            </form>
        </div>
    </section>

    <!-- ============================ Liste ============================ -->
    <section class="card">
        <div class="card__head"><h3>Liste des encadrants</h3></div>
        <div class="card__body card__body--flush">
            <?php if (!$encadrants): ?>
                <div class="empty">
                    <span class="empty__icon">🧑‍🏫</span>
                    <p>Aucun encadrant enregistré.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Encadrant</th>
                            <th>Service</th>
                            <th>Stages</th>
                            <th class="text-right">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($encadrants as $en): ?>
                            <tr>
                                <td>
                                    <div class="person">
                                        <span class="person__avatar"><?= e(initials($en['nom'], $en['prenom'])) ?></span>
                                        <div>
                                            <div class="person__name"><?= e($en['prenom'] . ' ' . $en['nom']) ?></div>
                                            <div class="person__sub"><?= e($en['email']) ?></div>
                                            <?php if ($en['fonction']): ?>
                                                <div class="person__sub"><?= e($en['fonction']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="muted"><?= e($en['nom_service'] ?: '—') ?></td>
                                <td>
                                    <span class="badge"><?= (int) $en['nb_stages'] ?></span>
                                    <?php if ($en['a_compte']): ?>
                                        <span class="badge badge--success" title="Compte actif">✓</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table__actions" style="justify-content:flex-end">
                                        <a class="btn btn--secondary btn--sm"
                                           href="<?= url('encadrants.php?edit=' . (int) $en['id_encadrant']) ?>">Modifier</a>
                                        <form method="post" style="display:inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="supprimer">
                                            <input type="hidden" name="id_encadrant" value="<?= (int) $en['id_encadrant'] ?>">
                                            <button class="btn btn--danger btn--sm" type="submit"
                                                    data-confirm="Supprimer cet encadrant ? Son compte sera également supprimé.">
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
