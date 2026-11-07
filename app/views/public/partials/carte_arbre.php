<?php
/**
 * Une carte de l'arbre « chêne gravé » (1.33.0).
 *
 * Toujours un lien vers la fiche : la carte se parcourt au clavier, et son nom
 * est le texte du lien. Aucune donnée n'est écrite hors de `$noeud`, qui vient
 * d'arbre_chene_noeud() — donc d'une personne que le compte a le droit de voir.
 *
 * @var array  $noeud  nom, dates, url
 * @var string $classe classe de plus (« arbre-chene__noeud--tronc »)
 */
$classe = $classe ?? '';
?>
<li class="arbre-chene__noeud<?= $classe !== '' ? ' ' . e($classe) : '' ?>">
    <a class="arbre-chene__lien" href="<?= e($noeud['url']) ?>">
        <span class="arbre-chene__nom"><?= e($noeud['nom']) ?></span>
        <?php if ($noeud['dates'] !== ''): ?>
            <span class="arbre-chene__dates"><?= e($noeud['dates']) ?></span>
        <?php endif; ?>
    </a>
</li>
