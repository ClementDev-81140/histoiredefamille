<?php
/**
 * Page d'informations.
 *
 * @var array $page
 */
?>
<article class="section page-info">
    <h1><?= e($page['title']) ?></h1>
    <p class="discret">
        Dernière mise à jour : <?= e(format_date((int) $page['updated_at'])) ?>
        <?php if (is_admin()): ?>
            — <a href="/admin/pages/<?= (int) $page['id'] ?>">modifier cette page</a>
        <?php endif; ?>
    </p>

    <div class="texte">
        <?= render_text($page['body']) ?>
    </div>
</article>
