<?php
/**
 * Liste des pages d'informations.
 *
 * @var array $pages
 */
?>
<div class="section__entete">
    <h1>Pages d’informations</h1>
    <a class="bouton" href="/admin/pages/nouvelle">Nouvelle page</a>
</div>

<p class="note">
    Les pages servent à raconter l’histoire de la famille, publier des annonces, etc.
    Une page peut être publique, réservée aux familles connectées, ou à certaines branches.
</p>

<?php if ($pages === []): ?>
    <div class="panneau panneau--centre">
        <p>Aucune page pour le moment.</p>
        <p><a class="bouton" href="/admin/pages/nouvelle">Créer une page</a></p>
    </div>
<?php else: ?>
    <div class="tableau-defilant">
        <table class="tableau">
            <thead>
                <tr>
                    <th scope="col">Titre</th>
                    <th scope="col">Adresse</th>
                    <th scope="col">Visibilité</th>
                    <th scope="col">État</th>
                    <th scope="col">Menu</th>
                    <th scope="col">Modifiée le</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $page): ?>
                    <tr>
                        <td><?= e($page['title']) ?></td>
                        <td><code>/pages/<?= e($page['slug']) ?></code></td>
                        <td><span class="<?= e(visibility_class((string) $page['visibility'])) ?>">
                            <?= e(visibility_short((string) $page['visibility'])) ?></span></td>
                        <td>
                            <?php if ((int) $page['is_published'] === 1): ?>
                                <span class="badge badge-public">publiée</span>
                            <?php else: ?>
                                <span class="badge badge-prive">brouillon</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $page['show_in_nav'] === 1 ? 'oui' : '—' ?></td>
                        <td><?= e(format_date((int) $page['updated_at'], true)) ?></td>
                        <td class="colonne-actions">
                            <a href="/admin/pages/<?= (int) $page['id'] ?>">Modifier</a>
                            <?php if ((int) $page['is_published'] === 1 && can_view((string) $page['visibility'], content_branch_ids('page', (int) $page['id']))): ?>
                                <a href="/pages/<?= e($page['slug']) ?>">Voir</a>
                            <?php endif; ?>
                            <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/supprimer"
                                  class="formulaire-en-ligne"
                                  data-confirmation="Supprimer définitivement cette page ?">
                                <?= csrf_field() ?>
                                <button type="submit" class="lien-bouton lien-bouton--danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
