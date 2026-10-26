<?php
/**
 * Comptes administrateurs.
 *
 * @var array      $admins, $erreurs, $old
 * @var int        $nbActive
 * @var array|null $newPassword
 * @var string     $generated
 */
$me = current_account();
?>
<h1>Comptes administrateurs</h1>

<?php if ($newPassword !== null): ?>
    <div class="message message--succes">
        <p><strong>Mot de passe généré</strong> — <?= e($newPassword['context']) ?></p>
        <p class="mot-de-passe-genere">
            Identifiant : <code><?= e($newPassword['username']) ?></code><br>
            Mot de passe : <code><?= e($newPassword['password']) ?></code>
        </p>
        <p><strong>Transmettez-le à la personne concernée :</strong> il ne sera plus affiché.</p>
    </div>
<?php endif; ?>

<p class="note">
    Chaque administrateur dispose de son propre compte : ne partagez jamais un
    identifiant entre plusieurs personnes. Un administrateur voit et gère tout
    (documents, branches, comptes, arbre, pages).
</p>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="/admin/admins/nouveau" class="formulaire panneau">
    <?= csrf_field() ?>
    <h2>Ajouter un administrateur</h2>

    <div class="ligne-champs">
        <div class="champ">
            <label for="username">Identifiant</label>
            <input type="text" id="username" name="username" required maxlength="30"
                   pattern="[a-z][a-z0-9._-]{2,29}" autocapitalize="off"
                   value="<?= e((string) old_value($old, 'username', '')) ?>" placeholder="prenom">
            <p class="aide">Minuscules, chiffres, point, tiret. 3 caractères minimum.</p>
        </div>

        <div class="champ">
            <label for="display_name">Nom affiché</label>
            <input type="text" id="display_name" name="display_name" maxlength="100"
                   value="<?= e((string) old_value($old, 'display_name', '')) ?>" placeholder="Prénom Nom">
        </div>
    </div>

    <div class="champ">
        <label for="password">Mot de passe</label>
        <input type="text" id="password" name="password" minlength="10" autocomplete="new-password">
        <p class="aide">
            10 caractères minimum. Laissez vide et cochez « générer » pour un mot de passe
            aléatoire (suggestion : <code><?= e($generated) ?></code>).
        </p>
        <label class="case">
            <input type="checkbox" name="generate" value="1">
            Générer un mot de passe aléatoire
        </label>
    </div>

    <p class="actions">
        <button type="submit" class="bouton">Créer le compte</button>
    </p>
</form>

<section class="section">
    <h2>Comptes existants (<?= (int) $nbActive ?> actif(s))</h2>
    <div class="tableau-defilant">
        <table class="tableau">
            <thead>
                <tr>
                    <th scope="col">Identifiant</th>
                    <th scope="col">Nom</th>
                    <th scope="col">État</th>
                    <th scope="col">Dernière connexion</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $admin): ?>
                    <tr>
                        <td>
                            <code><?= e($admin['username']) ?></code>
                            <?php if ((int) $admin['id'] === (int) $me['id']): ?>
                                <span class="etiquette">vous</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) $admin['display_name']) ?></td>
                        <td>
                            <?php if ((int) $admin['is_active'] === 1): ?>
                                <span class="badge badge-public">actif</span>
                            <?php else: ?>
                                <span class="badge badge-prive">désactivé</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $admin['last_login_at'] ? e(format_date((int) $admin['last_login_at'], true)) : '—' ?></td>
                        <td class="colonne-actions">
                            <form method="post" action="/admin/admins/<?= (int) $admin['id'] ?>/mot-de-passe"
                                  class="formulaire-en-ligne"
                                  data-confirmation="Générer un nouveau mot de passe pour ce compte ?">
                                <?= csrf_field() ?>
                                <button type="submit" class="lien-bouton">Nouveau mot de passe</button>
                            </form>

                            <?php if ((int) $admin['id'] !== (int) $me['id']): ?>
                                <form method="post" action="/admin/admins/<?= (int) $admin['id'] ?>/activer"
                                      class="formulaire-en-ligne">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="lien-bouton">
                                        <?= (int) $admin['is_active'] === 1 ? 'Désactiver' : 'Réactiver' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
