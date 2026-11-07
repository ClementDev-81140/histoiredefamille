<?php
/**
 * Liste des articles (administration).
 *
 * @var array       $articles
 * @var string|null $visibility filtre « Visibilité » ('' = toutes)
 */
?>
<div class="section__entete">
    <h1>Articles</h1>
    <a class="bouton" href="/admin/articles/nouveau">Écrire un article</a>
</div>

<p class="note">
    Une date future <strong>programme</strong> la publication.
</p>

<?php
$action = '/admin/articles';
require __DIR__ . '/partials/filtre_visibilite.php';
$filtreAutres = ['visibilite' => $visibility];
require __DIR__ . '/partials/filtre_categories_liste.php';
?>

<?php if ($articles === []): ?>
    <div class="panneau panneau--centre">
        <p>Aucun article pour le moment.</p>
        <p><a class="bouton" href="/admin/articles/nouveau">Écrire le premier article</a></p>
    </div>
<?php else: ?>
    <div class="tableau-defilant">
        <table class="tableau">
            <thead>
                <tr>
                    <th scope="col">Titre</th>
                    <th scope="col">Rubrique</th>
                    <th scope="col">Visibilité</th>
                    <th scope="col">État</th>
                    <th scope="col">Date</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($articles as $article): ?>
                <?php
                $programme = $article['status'] === 'publie' && (int) $article['published_at'] > time();
                ?>
                <tr>
                    <td><?= e($article['title']) ?><br><span class="discret">par <?= e((string) ($article['auteur'] ?? '—')) ?></span></td>
                    <td><?= e($article['category'] !== '' ? $article['category'] : '—') ?></td>
                    <td><span class="<?= e(visibility_class((string) $article['visibility'])) ?>">
                        <?= e(visibility_short((string) $article['visibility'])) ?></span></td>
                    <td>
                        <?php if ($article['status'] === 'brouillon'): ?>
                            <span class="badge badge-prive">brouillon</span>
                        <?php elseif ($programme): ?>
                            <span class="badge badge-branches">programmé</span>
                        <?php else: ?>
                            <span class="badge badge-public">publié</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(format_date((int) $article['published_at'], true)) ?></td>
                    <td class="colonne-actions">
                        <a href="/admin/articles/<?= (int) $article['id'] ?>">Modifier</a>
                        <a href="/articles/<?= e($article['slug']) ?>">Voir</a>
                        <form method="post" action="/admin/articles/<?= (int) $article['id'] ?>/supprimer"
                              class="formulaire-en-ligne"
                              data-confirmation="Supprimer définitivement cet article ?">
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
