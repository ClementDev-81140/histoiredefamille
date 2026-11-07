<?php
/**
 * Liste des recettes de famille.
 *
 * @var array $recettes, $photos, $pagination
 * @var int   $total
 */
?>
<section class="section">
    <div class="section__entete">
        <h1>Recettes de famille</h1>
        <?php if (is_admin()): ?>
            <a class="bouton" href="/admin/recettes/nouvelle">Ajouter une recette</a>
        <?php endif; ?>
    </div>

    <p class="discret">
        <?= (int) $total ?> recette<?= (int) $total > 1 ? 's' : '' ?>
        <?php if (!is_logged_in()): ?>
            — les recettes réservées aux familles ne sont pas affichées.
            <a href="/connexion">Se connecter</a>
        <?php endif; ?>
    </p>

    <?php
    $filtreCategories = $categories ?? [];
    $filtreCategorie  = (int) ($categorie ?? 0);
    $filtreAction     = '/recettes';
    $filtreParam      = 'categorie';
    require __DIR__ . '/../partials/filtre_categories.php';
    ?>

    <?php if ($recettes === []): ?>
        <div class="panneau panneau--centre">
            <p>Aucune recette pour le moment.</p>
        </div>
    <?php else: ?>
        <div class="grille-albums">
            <?php foreach ($recettes as $recette): ?>
                <?php $photo = $photos[(int) $recette['id']] ?? null; ?>
                <article class="album-carte">
                    <a class="album-carte__lien" href="<?= e(recipe_url($recette)) ?>">
                        <div class="album-carte__apercu">
                            <?php if ($photo !== null && thumbnail_url($photo) !== null): ?>
                                <img class="<?= e(aspect_photo_classe($photo)) ?>" src="<?= e(thumbnail_url($photo)) ?>" alt="" loading="lazy"
                                     width="480" height="360">
                            <?php else: ?>
                                <span class="album-carte__vide">Recette</span>
                            <?php endif; ?>
                        </div>
                        <div class="album-carte__corps">
                            <h2 class="album-carte__titre"><?= e($recette['title']) ?></h2>
                            <p class="album-carte__meta">
                                <?= e(trim((string) ($recette['origin'] ?? '')) !== '' ? (string) $recette['origin'] : 'Recette de famille') ?>
                                <?php if (trim((string) ($recette['servings'] ?? '')) !== ''): ?>
                                    · <?= e((string) $recette['servings']) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</section>
