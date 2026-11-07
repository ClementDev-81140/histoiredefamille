<?php
/**
 * Une vignette de la Une publique (1.31.0) : vignette, titre, date, type.
 *
 * Attend `$item` (voir une_publique_items()). Aucun lien vers autre chose que
 * la page publique du contenu.
 *
 * @var array $item
 */
?>
<li class="une-carte">
    <a class="une-carte__lien" href="<?= e($item['url']) ?>">
        <span class="une-carte__vignette">
            <?php if (($item['image'] ?? null) !== null): ?>
                <img src="<?= e($item['image']) ?>" alt="" width="320" height="240" loading="lazy">
            <?php else: ?>
                <span class="une-carte__vide"><?= e($item['rubrique']) ?></span>
            <?php endif; ?>
        </span>
        <span class="une-carte__type"><?= e($item['rubrique']) ?></span>
        <span class="une-carte__titre"><?= e($item['titre']) ?></span>
        <span class="une-carte__meta">
            <span class="discret"><?= e($item['date_texte']) ?></span>
            <span class="discret"><?= e($item['genre']) ?></span>
        </span>
    </a>
</li>
