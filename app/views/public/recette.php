<?php
/**
 * Une recette de famille.
 *
 * @var array      $recette, $ingredients
 * @var array|null $photo
 */
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/recettes">Recettes</a>
    <span aria-hidden="true">/</span>
    <span><?= e($recette['title']) ?></span>
</nav>

<article class="section recette">
    <div class="section__entete">
        <h1><?= e($recette['title']) ?></h1>
        <div class="actions">
            <button type="button" class="bouton bouton--secondaire" data-imprimer>Imprimer</button>
            <?php if (is_admin()): ?>
                <a class="bouton bouton--secondaire"
                   href="/admin/recettes/<?= (int) $recette['id'] ?>">Modifier</a>
            <?php endif; ?>
        </div>
    </div>

    <p class="recette__meta">
        <?php if (trim((string) ($recette['origin'] ?? '')) !== ''): ?>
            <span><?= e((string) $recette['origin']) ?></span>
        <?php endif; ?>
        <?php if (trim((string) ($recette['servings'] ?? '')) !== ''): ?>
            <span><?= e((string) $recette['servings']) ?></span>
        <?php endif; ?>
        <?php if (is_logged_in()): ?>
            <span class="<?= e(visibility_class((string) $recette['visibility'])) ?>">
                <?= e(visibility_short((string) $recette['visibility'])) ?>
            </span>
        <?php endif; ?>
    </p>

    <?php if ($photo !== null): ?>
        <figure class="recette__photo">
            <img class="<?= e(aspect_photo_classe($photo)) ?>" src="<?= e(file_url($photo)) ?>" alt="<?= e($photo['title']) ?>">
            <figcaption class="discret"><?= e($photo['title']) ?></figcaption>
        </figure>
    <?php endif; ?>

    <div class="recette__corps">
        <section class="recette__ingredients">
            <h2>Ingrédients</h2>
            <?php if ($ingredients === []): ?>
                <p class="discret">Aucun ingrédient indiqué.</p>
            <?php else: ?>
                <ul class="recette__liste">
                    <?php foreach ($ingredients as $ingredient): ?>
                        <li><?= e($ingredient) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="recette__preparation">
            <h2>Préparation</h2>
            <?php if (trim((string) $recette['preparation']) === ''): ?>
                <p class="discret">La préparation n’a pas encore été écrite.</p>
            <?php else: ?>
                <div class="texte"><?= render_text((string) $recette['preparation']) ?></div>
            <?php endif; ?>

            <?php if (trim((string) ($recette['notes'] ?? '')) !== ''): ?>
                <h2>Astuce et souvenir</h2>
                <div class="texte recette__notes"><?= render_text((string) $recette['notes']) ?></div>
            <?php endif; ?>
        </section>
    </div>
</article>
