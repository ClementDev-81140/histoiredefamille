<?php
/**
 * Les traits de liaison entre deux générations de l'arbre (1.35.0).
 *
 * Un petit SVG écrit par le serveur : les centres des cartes sont des
 * pourcentages calculés dans arbre_chene_traits_liaison(), aucun style en
 * ligne, aucun JavaScript de mesure. Décoratif : le lien de parenté reste
 * écrit sur chaque carte (« Parent », « Enfant »…), le trait ne fait que le
 * montrer. Un trait ne part jamais vers une carte absente.
 *
 * @var array<int, array{x1:string, y1:int, x2:string, y2:int}> $traits
 */
?>
<?php if (($traits ?? []) !== []): ?>
    <svg class="arbre-chene__traits" viewBox="0 0 100 24" preserveAspectRatio="none"
         aria-hidden="true" focusable="false">
        <?php foreach ($traits as $segment): ?>
            <line x1="<?= e($segment['x1']) ?>" y1="<?= (int) $segment['y1'] ?>"
                  x2="<?= e($segment['x2']) ?>" y2="<?= (int) $segment['y2'] ?>"
                  vector-effect="non-scaling-stroke" />
        <?php endforeach; ?>
    </svg>
<?php endif; ?>
