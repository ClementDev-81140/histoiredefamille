<?php
/**
 * Liste des articles : la « une » de la gazette.
 *
 * @var array  $articles, $categories, $pagination, $decennies, $branches
 * @var string $categorie
 * @var int    $total, $branche, $decennie
 * @var bool   $filtreVide
 */
$page = (int) $pagination['page'];
$aLaUne = $articles !== [] && $page === 1 && $categorie === '';
?><section class="section">
    <div class="section__entete section__entete--gazette">
        <div>
            <h1>Articles</h1>
            <p class="gazette-sous-titre">Le journal de la famille, numéro après numéro</p>
        </div>
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

    <?php /* Filtre « édition » par branche et frise par décennie : de simples
             liens GET, sans JavaScript. Seules les branches et les décennies
             qui ont réellement un article visible apparaissent. */ ?>
    <?php if ($branches !== []): ?>
        <nav class="editions" aria-label="Édition par branche">
            <p class="editions__titre">Édition</p>
            <ul class="editions__branches">
                <li>
                    <a href="<?= e(url_with(['branche' => null, 'page' => null])) ?>"
                       class="<?= $branche === 0 ? 'actif' : '' ?>"<?= $branche === 0 ? ' aria-current="page"' : '' ?>>Toutes</a>
                </li>
                <?php foreach ($branches as $b): ?>
                    <li>
                        <a href="<?= e(url_with(['branche' => $b['id'], 'page' => null])) ?>"
                           class="<?= $branche === (int) $b['id'] ? 'actif' : '' ?>"<?= $branche === (int) $b['id'] ? ' aria-current="page"' : '' ?>><?= e($b['nom']) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <?php if ($decennies !== []): ?>
        <nav class="editions__frise" aria-label="Frise par décennie">
            <p class="editions__titre">Décennies</p>
            <ul class="editions__decennies">
                <li>
                    <a class="editions__point<?= $decennie === 0 ? ' actif' : '' ?>"
                       href="<?= e(url_with(['decennie' => null, 'page' => null])) ?>"
                       <?= $decennie === 0 ? 'aria-current="page"' : '' ?>>
                        <span class="editions__rond" aria-hidden="true"></span>
                        <span class="editions__libelle">Tout</span>
                    </a>
                </li>
                <?php foreach ($decennies as $valeur => $nb): ?>
                    <li>
                        <a class="editions__point<?= $decennie === (int) $valeur ? ' actif' : '' ?>"
                           href="<?= e(url_with(['decennie' => $valeur, 'page' => null])) ?>"
                           <?= $decennie === (int) $valeur ? 'aria-current="page"' : '' ?>>
                            <span class="editions__rond" aria-hidden="true"></span>
                            <span class="editions__libelle"><?= e(decennie_libelle((int) $valeur)) ?></span>
                            <span class="editions__nombre"><?= (int) $nb ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <?php if ($articles === []): ?>
        <div class="panneau panneau--centre">
            <?php if (!empty($filtreVide)): ?>
                <p class="vide-edition">Aucune nouvelle pour cette édition. La rédaction attend vos plis !</p>
                <p><a href="/articles">Revenir à toutes les éditions</a></p>
            <?php else: ?>
                <p>Aucun article pour le moment.</p>
                <?php if (!is_logged_in()): ?>
                    <p><a href="/connexion?next=%2Farticles">Se connecter</a> pour voir les articles réservés aux familles.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php if (!is_logged_in()): ?>
            <p class="discret">
                Seuls les articles publics sont affichés.
                <a href="/connexion?next=%2Farticles">Se connecter</a> pour voir les autres.
            </p>
        <?php endif; ?>

        <?php if ($aLaUne): ?>
            <?php $article = $articles[0]; $grand = true; require __DIR__ . '/../partials/carte_article.php'; ?>
            <?php $suite = array_slice($articles, 1); ?>
        <?php else: ?>
            <?php $suite = $articles; ?>
        <?php endif; ?>

        <?php if ($suite !== []): ?>
            <div class="articles-colonnes">
                <?php foreach ($suite as $article): ?>
                    <?php $grand = false; require __DIR__ . '/../partials/carte_article.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php require __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</section>
