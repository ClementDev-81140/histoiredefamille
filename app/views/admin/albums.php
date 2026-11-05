<?php
/**
 * Liste des albums (administration).
 *
 * @var array       $albums
 * @var string|null $visibility filtre « Visibilité » ('' = toutes)
 */
?>
<div class="section__entete">
    <h1>Albums photos</h1>
    <a class="bouton" href="/admin/albums/nouveau">Nouvel album</a>
</div>

<p class="discret">
    Un album range des photos déjà envoyées : les fichiers ne sont ni copiés ni
    déplacés. Supprimer un album ne supprime aucune photo.
</p>

<?php
$action = '/admin/albums';
require __DIR__ . '/partials/filtre_visibilite.php';
?>

<?php if ($albums === []): ?>
    <div class="panneau panneau--centre">
        <p>Aucun album pour le moment.</p>
        <p><a class="bouton" href="/admin/albums/nouveau">Créer le premier album</a></p>
    </div>
<?php else: ?>
    <div class="tableau-defilant">
        <table class="tableau">
            <thead>
                <tr>
                    <th scope="col">Titre</th>
                    <th scope="col">Photos</th>
                    <th scope="col">Visibilité</th>
                    <th scope="col">Adresse</th>
                    <th scope="col">Modifié le</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($albums as $album): ?>
                    <tr>
                        <td><a href="/albums/<?= e($album['slug']) ?>"><?= e($album['title']) ?></a></td>
                        <td><?= (int) $album['nb_photos'] ?></td>
                        <td>
                            <span class="<?= e(visibility_class((string) $album['visibility'])) ?>">
                                <?= e(visibility_short((string) $album['visibility'])) ?>
                            </span>
                        </td>
                        <td><code>/albums/<?= e($album['slug']) ?></code></td>
                        <td><?= e(format_date((int) $album['updated_at'])) ?></td>
                        <td class="colonne-actions">
                            <a class="bouton bouton--secondaire" href="/admin/albums/<?= (int) $album['id'] ?>/modifier">Modifier</a>
                            <form method="post" action="/admin/albums/<?= (int) $album['id'] ?>/supprimer"
                                  class="formulaire-en-ligne"
                                  data-confirmation="Supprimer cet album ? Les photos seront conservées.">
                                <?= csrf_field() ?>
                                <button type="submit" class="bouton bouton--danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
