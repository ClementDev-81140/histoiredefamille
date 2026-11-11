<?php
/**
 * Une carte du schéma généalogique (1.33.0, photo et navigation 1.40.0).
 *
 * La carte entière centre l'arbre sur la personne (lien vers
 * `/arbre?racine=…`, rendu serveur complet, sans JavaScript). Les liens
 * vers la fiche sont hors du dessin pour rester utilisables à tout zoom.
 * La relation avec la personne sélectionnée est écrite en petit (1.35.0).
 * La photo ronde est le portrait de la fiche (persons.photo_document_id)
 * quand le compte a le droit de le voir, sinon la silhouette du site —
 * jamais une image interdite, jamais son existence. Les dates ne sont
 * plus sur la carte : elles restent sur la fiche.
 *
 * @var array  $noeud  nom, url, relation, relation_libelle, trait_couple, photo
 * @var string $classe classe de plus (« arbre-chene__noeud--tronc »)
 */
$classe = $classe ?? '';
if (!empty($noeud['trait_couple']) && !str_contains($classe, 'arbre-chene__noeud--couple')) {
    $classe .= ($classe !== '' ? ' ' : '') . 'arbre-chene__noeud--couple';
}
if (!empty($noeud['trait_descente'])) {
    $classe .= ' arbre-chene__noeud--descente';
}
$nomAccessible = ($noeud['relation_libelle'] ?? '') !== ''
    ? $noeud['nom'] . ', ' . mb_strtolower($noeud['relation_libelle'])
    : $noeud['nom'];
?>
<li class="arbre-chene__noeud<?= $classe !== '' ? ' ' . e($classe) : '' ?>"
    <?= ($noeud['relation'] ?? '') !== '' ? 'data-relation="' . e($noeud['relation']) . '"' : '' ?><?= !empty($noeud['centre']) ? ' data-explo-centre="1"' : '' ?>>
    <a class="arbre-chene__lien" href="/arbre?racine=<?= (int) $noeud['id'] ?>#chene"
       aria-label="<?= e($nomAccessible . ' — centrer l’arbre sur cette personne') ?>">
        <?php if (($noeud['relation_libelle'] ?? '') !== ''): ?>
            <span class="arbre-chene__relation"><?= e($noeud['relation_libelle']) ?></span>
        <?php endif; ?>
        <?php if (($noeud['photo'] ?? null) !== null): ?>
            <img class="arbre-chene__photo" src="<?= e($noeud['photo']) ?>"
                 alt="Portrait de <?= e($noeud['nom']) ?>" loading="lazy" decoding="async">
        <?php else: ?>
            <img class="arbre-chene__photo arbre-chene__photo--silhouette"
                 src="<?= e(asset_url('/assets/silhouette.svg')) ?>" alt="" loading="lazy" decoding="async">
        <?php endif; ?>
        <span class="arbre-chene__nom"><?= e($noeud['nom']) ?></span>
    </a>
</li>
<?php // Les inclusions se partagent la portée de la vue : sans cette
      // réinitialisation, la classe « tronc » d'une carte suivrait toutes
      // les cartes incluses après elle (les descendants, notamment).
      $classe = ''; ?>
