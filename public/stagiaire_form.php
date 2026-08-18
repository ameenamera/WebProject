<?php
/**
 * Ajout / modification d'un stagiaire.
 * Crée aussi, à la demande, le compte de connexion associé.
 */

require_once __DIR__ . '/../includes/auth.php';
exigerRole(['admin']);

$id       = intOrNull(get('id'));
$edition  = $id !== null;
$activeMenu = 'stagiaires';
$pageTitle  = $edition ? 'Modifier un stagiaire' : 'Ajouter un stagiaire';

// Valeurs par défaut du formulaire.
$data = [
    'nom' => '', 'prenom' => '', 'cin' => '', 'email' => '', 'telephone' => '',
    'etablissement' => '', 'specialite' => '', 'niveau' => '', 'id_institut' => '',
];
$erreurs = [];
$compte  = null;

if ($edition) {
    $existant = fetchOne('SELECT * FROM stagiaire WHERE id_stagiaire = ?', [$id]);
    if (!$existant) {
        flash('Stagiaire introuvable.', 'error');
        redirect('stagiaires.php');
    }
    $data   = array_merge($data, $existant);
    $compte = fetchOne('SELECT * FROM utilisateur WHERE id_stagiaire = ?', [$id]);
}

// --- Enregistrement ------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    foreach (array_keys($data) as $champ) {
        $data[$champ] = post($champ);
    }

    // Validation
    if ($data['nom'] === '')    $erreurs['nom'] = 'Le nom est obligatoire.';
    if ($data['prenom'] === '') $erreurs['prenom'] = 'Le prénom est obligatoire.';
    if ($data['cin'] === '')    $erreurs['cin'] = 'Le CIN est obligatoire.';

    if ($data['email'] === '') {
        $erreurs['email'] = 'L\'email est obligatoire.';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $erreurs['email'] = 'Format d\'email invalide.';
    }

    if ($data['telephone'] !== '' && !preg_match('/^[0-9 +().-]{6,20}$/', $data['telephone'])) {
        $erreurs['telephone'] = 'Numéro de téléphone invalide.';
    }

    // Unicité CIN / email
    $doublonCin = fetchValue(
        'SELECT id_stagiaire FROM stagiaire WHERE cin = ? AND id_stagiaire <> ?',
        [$data['cin'], $id ?? 0]
    );
    if ($doublonCin) $erreurs['cin'] = 'Ce CIN est déjà enregistré.';

    $doublonMail = fetchValue(
        'SELECT id_stagiaire FROM stagiaire WHERE email = ? AND id_stagiaire <> ?',
        [$data['email'], $id ?? 0]
    );
    if ($doublonMail) $erreurs['email'] = 'Cet email est déjà utilisé.';

    // Compte de connexion (facultatif)
    $creerCompte = isset($_POST['creer_compte']);
    $loginCompte = post('login_compte');
    $mdpCompte   = $_POST['mdp_compte'] ?? '';

    if ($creerCompte) {
        if ($loginCompte === '') {
            $erreurs['login_compte'] = 'Identifiant obligatoire pour créer un compte.';
        } elseif (fetchValue('SELECT id_utilisateur FROM utilisateur WHERE login = ? AND id_utilisateur <> ?',
                             [$loginCompte, $compte['id_utilisateur'] ?? 0])) {
            $erreurs['login_compte'] = 'Cet identifiant est déjà pris.';
        }
        if (!$compte && strlen($mdpCompte) < 6) {
            $erreurs['mdp_compte'] = 'Le mot de passe doit contenir au moins 6 caractères.';
        } elseif ($mdpCompte !== '' && strlen($mdpCompte) < 6) {
            $erreurs['mdp_compte'] = 'Le mot de passe doit contenir au moins 6 caractères.';
        }
    }

    if (!$erreurs) {
        $champs = [
            $data['nom'], $data['prenom'], $data['cin'], $data['email'],
            $data['telephone'] ?: null, $data['etablissement'] ?: null,
            $data['specialite'] ?: null, $data['niveau'] ?: null,
            intOrNull($data['id_institut']),
        ];

        if ($edition) {
            query(
                'UPDATE stagiaire SET nom=?, prenom=?, cin=?, email=?, telephone=?,
                        etablissement=?, specialite=?, niveau=?, id_institut=?
                 WHERE id_stagiaire=?',
                array_merge($champs, [$id])
            );
            $idStagiaire = $id;
            flash('Les informations du stagiaire ont été mises à jour.');
        } else {
            query(
                'INSERT INTO stagiaire (nom, prenom, cin, email, telephone,
                        etablissement, specialite, niveau, id_institut)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                $champs
            );
            $idStagiaire = (int) db()->lastInsertId();
            flash('Stagiaire ajouté avec succès.');
        }

        // Création / mise à jour du compte
        if ($creerCompte) {
            if ($compte) {
                if ($mdpCompte !== '') {
                    query('UPDATE utilisateur SET login=?, mot_de_passe=? WHERE id_utilisateur=?',
                        [$loginCompte, password_hash($mdpCompte, PASSWORD_DEFAULT), $compte['id_utilisateur']]);
                } else {
                    query('UPDATE utilisateur SET login=? WHERE id_utilisateur=?',
                        [$loginCompte, $compte['id_utilisateur']]);
                }
            } else {
                query(
                    'INSERT INTO utilisateur (login, mot_de_passe, role, id_stagiaire) VALUES (?,?,?,?)',
                    [$loginCompte, password_hash($mdpCompte, PASSWORD_DEFAULT), 'stagiaire', $idStagiaire]
                );
                flash('Compte de connexion créé pour le stagiaire.', 'info');
            }
        }

        redirect('stagiaires.php');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h2 class="mb-0"><?= e($pageTitle) ?></h2>
        <p><?= $edition ? 'Modifiez la fiche du stagiaire.' : 'Renseignez les informations du nouveau stagiaire.' ?></p>
    </div>
    <a class="btn btn--secondary" href="<?= url('stagiaires.php') ?>">← Retour à la liste</a>
</div>

<?php if ($erreurs): ?>
    <div class="alert alert--error">
        <span>Le formulaire contient <?= count($erreurs) ?> erreur(s). Veuillez corriger les champs signalés.</span>
    </div>
<?php endif; ?>

<form method="post">
    <?= csrfField() ?>

    <section class="card">
        <div class="card__head"><h3>Identité</h3></div>
        <div class="card__body">
            <div class="form-grid">
                <div class="field">
                    <label for="nom">Nom <span class="req">*</span></label>
                    <input class="input" type="text" id="nom" name="nom" required maxlength="50"
                           value="<?= e($data['nom']) ?>">
                    <?php if (isset($erreurs['nom'])): ?><span class="hint" style="color:var(--danger)"><?= e($erreurs['nom']) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="prenom">Prénom <span class="req">*</span></label>
                    <input class="input" type="text" id="prenom" name="prenom" required maxlength="50"
                           value="<?= e($data['prenom']) ?>">
                    <?php if (isset($erreurs['prenom'])): ?><span class="hint" style="color:var(--danger)"><?= e($erreurs['prenom']) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="cin">CIN <span class="req">*</span></label>
                    <input class="input" type="text" id="cin" name="cin" required maxlength="20"
                           value="<?= e($data['cin']) ?>">
                    <?php if (isset($erreurs['cin'])): ?><span class="hint" style="color:var(--danger)"><?= e($erreurs['cin']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card__head"><h3>Coordonnées</h3></div>
        <div class="card__body">
            <div class="form-grid">
                <div class="field">
                    <label for="email">Email <span class="req">*</span></label>
                    <input class="input" type="email" id="email" name="email" required maxlength="100"
                           value="<?= e($data['email']) ?>">
                    <?php if (isset($erreurs['email'])): ?><span class="hint" style="color:var(--danger)"><?= e($erreurs['email']) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="telephone">Téléphone</label>
                    <input class="input" type="tel" id="telephone" name="telephone" maxlength="20"
                           value="<?= e($data['telephone']) ?>" placeholder="ex. 20 123 456">
                    <?php if (isset($erreurs['telephone'])): ?><span class="hint" style="color:var(--danger)"><?= e($erreurs['telephone']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card__head"><h3>Parcours académique</h3></div>
        <div class="card__body">
            <div class="form-grid">
                <div class="field">
                    <label for="id_institut">Institut</label>
                    <select class="select" id="id_institut" name="id_institut">
                        <option value="">— Aucun —</option>
                        <?php foreach (listeInstituts() as $i): ?>
                            <option value="<?= (int) $i['id_institut'] ?>"
                                <?= (string) $data['id_institut'] === (string) $i['id_institut'] ? 'selected' : '' ?>>
                                <?= e($i['nom_institut']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">Gérez la liste depuis « Instituts ».</span>
                </div>

                <div class="field">
                    <label for="etablissement">Établissement (libre)</label>
                    <input class="input" type="text" id="etablissement" name="etablissement" maxlength="100"
                           value="<?= e($data['etablissement']) ?>">
                </div>

                <div class="field">
                    <label for="specialite">Spécialité</label>
                    <input class="input" type="text" id="specialite" name="specialite" maxlength="100"
                           value="<?= e($data['specialite']) ?>" placeholder="ex. Génie logiciel">
                </div>

                <div class="field">
                    <label for="niveau">Niveau</label>
                    <input class="input" type="text" id="niveau" name="niveau" maxlength="50"
                           value="<?= e($data['niveau']) ?>" placeholder="ex. 3ème année" list="niveaux">
                    <datalist id="niveaux">
                        <option value="1ère année"><option value="2ème année"><option value="3ème année">
                        <option value="Licence"><option value="Master 1"><option value="Master 2">
                        <option value="Ingénieur">
                    </datalist>
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card__head">
            <div>
                <h3>Compte de connexion</h3>
                <p>Permet au stagiaire de saisir ses tâches et de suivre son stage.</p>
            </div>
        </div>
        <div class="card__body">
            <div class="field" style="margin-bottom:14px">
                <label style="display:flex;align-items:center;gap:9px;font-weight:550;cursor:pointer">
                    <input type="checkbox" name="creer_compte" value="1"
                           <?= ($compte || isset($_POST['creer_compte'])) ? 'checked' : '' ?>
                           onclick="document.getElementById('bloc-compte').style.display = this.checked ? 'grid' : 'none'">
                    <?= $compte ? 'Modifier le compte existant' : 'Créer un compte de connexion' ?>
                </label>
            </div>

            <div class="form-grid" id="bloc-compte"
                 style="display: <?= ($compte || isset($_POST['creer_compte'])) ? 'grid' : 'none' ?>">
                <div class="field">
                    <label for="login_compte">Identifiant</label>
                    <input class="input" type="text" id="login_compte" name="login_compte" maxlength="50"
                           value="<?= e(post('login_compte') ?: ($compte['login'] ?? '')) ?>">
                    <?php if (isset($erreurs['login_compte'])): ?><span class="hint" style="color:var(--danger)"><?= e($erreurs['login_compte']) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="mdp_compte">Mot de passe</label>
                    <input class="input" type="password" id="mdp_compte" name="mdp_compte" autocomplete="new-password">
                    <span class="hint"><?= $compte ? 'Laissez vide pour conserver le mot de passe actuel.' : '6 caractères minimum.' ?></span>
                    <?php if (isset($erreurs['mdp_compte'])): ?><span class="hint" style="color:var(--danger)"><?= e($erreurs['mdp_compte']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <button class="btn" type="submit"><?= $edition ? 'Enregistrer les modifications' : 'Ajouter le stagiaire' ?></button>
        <a class="btn btn--secondary" href="<?= url('stagiaires.php') ?>">Annuler</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
