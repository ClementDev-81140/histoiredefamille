<?php
/**
 * Fiche d'un lieu : qui y est né, qui y est mort, qui s'y est marié.
 *
 * @var array $place, $personnes, $mariages
 * @var string $titre
 */
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/">Accueil</a>
    <span aria-hidden="true">/</span>
    <a href="/carte">Carte des lieux</a>
    <span aria-hidden="true">/</span>
    <span><?= e((string) $place['nom']) ?></span>
</nav>

<div class="section__entete">
    <div>
        <h1><?= e((string) $place['nom']) ?></h1>
        <p class="note">
            <?= e((string) $place['pays']) ?> —
            <?= e((string) $place['lat']) ?>, <?= e((string) $place['lon']) ?>
        </p>
    </div>
</div>

<p class="actions">
    <a class="bouton bouton--secondaire"
       href="/carte?lieu=<?= e(rawurlencode((string) $place['nom'])) ?>&amp;vue=<?= e(
           carte_projeter('france', (float) $place['lat'], (float) $place['lon'])['dedans'] ? 'france' : 'monde'
       ) ?>#carte">
        Voir sur la carte
    </a>
</p>

<section class="panneau">
    <h2>Personnes rattachées (<?= count($personnes) ?>)</h2>
    <?php if ($personnes === []): ?>
        <p class="discret">Aucune naissance ni aucun décès visibles ne sont rattachés à ce lieu.</p>
    <?php else: ?>
        <ul class="liste-personnes">
            <?php foreach ($personnes as $personne): ?>
                <li>
                    <a href="/arbre/<?= (int) $personne['id'] ?>"><?= e($personne['nom']) ?></a>
                    <span class="discret">
                        <?= e($personne['quoi']) ?><?= $personne['annee'] !== '' ? ' — ' . e($personne['annee']) : '' ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if ($mariages !== []): ?>
    <section class="panneau">
        <h2>Mariages célébrés ici (<?= count($mariages) ?>)</h2>
        <ul class="liste-personnes">
            <?php foreach ($mariages as $mariage): ?>
                <li>
                    <?php if ($mariage['epoux'] !== ''): ?><?= e($mariage['epoux']) ?><?php endif; ?>
                    <?php if ($mariage['epoux'] !== '' && $mariage['epouse'] !== ''): ?> et <?php endif; ?>
                    <?php if ($mariage['epouse'] !== ''): ?><?= e($mariage['epouse']) ?><?php endif; ?>
                    <?php if ($mariage['date'] !== ''): ?>
                        <span class="discret">— <?= e($mariage['date']) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<p class="note">
    <a class="lien" href="/carte">Revenir à la carte</a>
</p>
