<?php
/**
 * Pagination simple.
 *
 * @var array $pagination  page, pages, total, perPage
 */
if (($pagination['pages'] ?? 1) <= 1) {
    return;
}
$page = (int) $pagination['page'];
?>
<nav class="pagination" aria-label="Pagination">
    <?php if ($page > 1): ?>
        <a class="bouton bouton--secondaire" href="<?= e(url_with(['page' => $page - 1])) ?>">← Précédent</a>
    <?php endif; ?>

    <span class="pagination__info">
        Page <?= $page ?> sur <?= (int) $pagination['pages'] ?>
        <?php if (!empty($pagination['total'])): ?>
            — <?= (int) $pagination['total'] ?> résultat<?= (int) $pagination['total'] > 1 ? 's' : '' ?>
        <?php endif; ?>
    </span>

    <?php if ($page < (int) $pagination['pages']): ?>
        <a class="bouton bouton--secondaire" href="<?= e(url_with(['page' => $page + 1])) ?>">Suivant →</a>
    <?php endif; ?>
</nav>
