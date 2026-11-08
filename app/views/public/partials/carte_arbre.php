<?php
/**
 * Une carte de l'arbre « chêne gravé » (1.33.0, relation ajoutée en 1.35.0).
 *
 * Toujours un lien vers la fiche : la carte se parcourt au clavier, et son nom
 * accessible dit aussi la relation (« Casimir CHENE, conjoint »). Aucune
 * donnée n'est écrite hors de `$noeud`, qui vient d'arbre_chene_noeud() —
 * donc d'une personne que le compte a le droit de voir.
 *
 * @var array  $noeud  nom, dates, url, relation, relation_libelle, trait_couple
 * @var string $classe classe de plus (« arbre-chene__noeud--tronc »)
 */
$classe = $classe ?? '';
if (!empty($noeud['trait_couple']) && !str_contains($classe, 'arbre-chene__noeud--couple')) {
    $classe .= ($classe !== '' ? ' ' : '') . 'arbre-chene__noeud--couple';
}
$nomAccessible = ($noeud['relation_libelle'] ?? '') !== ''
    ? $noeud['nom'] . ', ' . mb_strtolower($noeud['relation_libelle'])
    : $noeud['nom'];
?>
<li class="arbre-chene__noeud<?= $classe !== '' ? ' ' . e($classe) : '' ?>"
    <?= ($noeud['relation'] ?? '') !== '' ? 'data-relation="' . e($noeud['relation']) . '"' : '' ?>>
    <a class="arbre-chene__lien" href="<?= e($noeud['url']) ?>"
       aria-label="<?= e($nomAccessible) ?>">
        <?php if (($noeud['relation_libelle'] ?? '') !== ''): ?>
            <span class="arbre-chene__relation"><?= e($noeud['relation_libelle']) ?></span>
        <?php endif; ?>
        <span class="arbre-chene__nom"><?= e($noeud['nom']) ?></span>
        <?php if ($noeud['dates'] !== ''): ?>
            <span class="arbre-chene__dates"><?= e($noeud['dates']) ?></span>
        <?php endif; ?>
    </a>
</li>
