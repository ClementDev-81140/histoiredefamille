<?php
/**
 * Branches familiales et comptes associés.
 *
 * @var array      $branches
 * @var array|null $newPassword
 */
?>
<div class="section__entete">
    <h1>Branches &amp; comptes de famille</h1>
    <a class="bouton" href="/admin/branches/nouvelle">Nouvelle branche</a>
</div>

<?php if ($newPassword !== null): ?>
    <div class="message message--succes">
        <p><strong>Mot de passe généré</strong> — <?= e($newPassword['context']) ?></p>
        <p class="mot-de-passe-genere">
            Identifiant : <code><?= e($newPassword['username']) ?></code><br>
            Mot de passe : <code><?= e($newPassword['password']) ?></code>
        </p>
        <p>
            <strong>Notez-le maintenant :</strong> il ne sera plus affiché.
            En cas d’oubli, générez-en un nouveau.
        </p>
    </div>
<?php endif; ?>

<p class="note">
    Chaque branche possède un compte unique, partagé par la famille, dont l’identifiant
    est en majuscules. La case « voit toutes les branches » donne accès à l’ensemble
    des contenus (hors contenus privés réservés aux administrateurs).
</p>

<?php if ($branches === []): ?>
    <div class="panneau panneau--centre">
        <p>Aucune branche pour le moment.</p>
        <p><a class="bouton" href="/admin/branches/nouvelle">Créer la première branche</a></p>
    </div>
<?php else: ?>
    <div class="tableau-defilant">
        <table class="tableau">
            <thead>
                <tr>
                    <th scope="col">Branche</th>
                    <th scope="col">Identifiant</th>
                    <th scope="col">Droits</th>
                    <th scope="col">État</th>
                    <th scope="col">Contenus liés</th>
                    <th scope="col">Dernière connexion</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($branches as $branch): ?>
                    <tr>
                        <td><?= e($branch['name']) ?></td>
                        <td><code><?= e($branch['code']) ?></code></td>
                        <td>
                            <?php if ((int) $branch['see_all'] === 1): ?>
                                <span class="badge badge-famille">voit tout</span>
                            <?php else: ?>
                                <span class="badge badge-branches">sa branche</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $branch['is_active'] === 1): ?>
                                <span class="badge badge-public">active</span>
                            <?php else: ?>
                                <span class="badge badge-prive">désactivée</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= (int) $branch['nb_documents'] ?> doc.
                            · <?= (int) $branch['nb_personnes'] ?> pers.
                            · <?= (int) $branch['nb_pages'] ?> page(s)
                        </td>
                        <td>
                            <?= $branch['last_login_at'] ? e(format_date((int) $branch['last_login_at'], true)) : '—' ?>
                        </td>
                        <td class="colonne-actions">
                            <a href="/admin/branches/<?= (int) $branch['id'] ?>">Modifier</a>

                            <form method="post" action="/admin/branches/<?= (int) $branch['id'] ?>/mot-de-passe"
                                  class="formulaire-en-ligne"
                                  data-confirmation="Générer un nouveau mot de passe ? L’ancien ne fonctionnera plus.">
                                <?= csrf_field() ?>
                                <button type="submit" class="lien-bouton">Nouveau mot de passe</button>
                            </form>

                            <form method="post" action="/admin/branches/<?= (int) $branch['id'] ?>/activer"
                                  class="formulaire-en-ligne">
                                <?= csrf_field() ?>
                                <button type="submit" class="lien-bouton">
                                    <?= (int) $branch['is_active'] === 1 ? 'Désactiver' : 'Réactiver' ?>
                                </button>
                            </form>

                            <?php $liens = (int) $branch['nb_documents'] + (int) $branch['nb_personnes'] + (int) $branch['nb_pages']; ?>
                            <form method="post" action="/admin/branches/<?= (int) $branch['id'] ?>/supprimer"
                                  class="formulaire-en-ligne"
                                  data-confirmation="<?= $liens > 0
                                      ? 'Cette branche est liée à des contenus : ils deviendront invisibles pour les familles. Confirmer ?'
                                      : 'Supprimer définitivement cette branche et son compte ?' ?>">
                                <?= csrf_field() ?>
                                <?php if ($liens > 0): ?>
                                    <input type="hidden" name="force" value="1">
                                <?php endif; ?>
                                <button type="submit" class="lien-bouton lien-bouton--danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
