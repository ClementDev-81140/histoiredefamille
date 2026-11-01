<?php
/**
 * Liste des articles.
 *
 * @var array  $articles, $categories, $pagination
 * @var string $categorie
 * @var int    $total
 */
?>
<section class="section">
    <div class="section__entete">
        <h1>Articles</h1>
        <?php if (is_admin()): ?>
            <a class="bouton" href="/admin/articles/nouveau">Écrire un article</a>
        <?php endif; ?>
    </div>

    <?php if ($categories !== []): ?>
        <p class="rubriques" aria-label="Rubriques">
            <a href="/articles"<?= $categorie === '' ? ' class="actif" aria-current="page"' : '' ?>>Tout</a>
            <?php foreach ($categories as $cat): ?>
                <a href="/articles?categorie=<?= rawurlencode((string) $cat['name']) ?>"<?= $categorie === $cat['name'] ? ' class="actif" aria-current="page"' : '' ?>>
                    <?= e($cat['name']) ?> (<?= (int) $cat['nb'] ?>)
                </a>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>

    <?php if ($articles === []): ?>
        <div class="panneau panneau--centre">
            <p>Aucun article pour le moment.</p>
            <?php if (!is_logged_in()): ?>
                <p><a href="/connexion?next=%2Farticles">Se connecter</a> pour voir les articles réservés aux familles.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php if (!is_logged_in()): ?>
            <p class="discret">
                Seuls les articles publics sont affichés.
                <a href="/connexion?next=%2Farticles">Se connecter</a> pour voir les autres.
            </p>
        <?php endif; ?>
        <div class="articles-liste">
            <?php foreach ($articles as $i => $article): ?>
                <?php $grand = ($i === 0 && (int) $pagination['page'] === 1 && $categorie === ''); ?>
                <?php require __DIR__ . '/../partials/carte_article.php'; ?>
            <?php endforeach; ?>
        </div>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</section>
