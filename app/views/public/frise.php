<?php
/**
 * Frise chronologique : une période de cinquante ans.
 *
 * @var int        $debut, $fin, $total, $borneDebut, $borneFin
 * @var array      $parAnnee
 * @var int|null   $precedent, $suivant, $premiere
 */
?>
<section class="section frise">
    <div class="section__entete">
        <h1>Frise chronologique</h1>
        <p class="actions">
            <a class="bouton bouton--secondaire" href="/calendrier">Calendrier</a>
            <button type="button" class="bouton bouton--secondaire" data-imprimer>Imprimer</button>
        </p>
    </div>

    <p class="frise__intro">
        Naissances, mariages, décès et documents datés, du plus ancien au plus
        récent. Les dates sans jour ni mois figurent ici, à leur année.
    </p>

    <nav class="frise__nav" aria-label="Changer de période">
        <?php if ($premiere !== null): ?>
            <a class="bouton bouton--secondaire" href="/frise?debut=<?= (int) $premiere ?>">← Commencer</a>
        <?php endif; ?>
        <?php if ($precedent !== null): ?>
            <a class="bouton bouton--secondaire" href="/frise?debut=<?= (int) $precedent ?>">← <?= (int) $precedent ?> – <?= (int) $precedent + 49 ?></a>
        <?php endif; ?>

        <span class="frise__periode"><?= (int) $debut ?> – <?= (int) $fin ?></span>

        <?php if ($suivant !== null): ?>
            <a class="bouton bouton--secondaire" href="/frise?debut=<?= (int) $suivant ?>"><?= (int) $suivant ?> – <?= (int) $suivant + 49 ?> →</a>
        <?php endif; ?>
    </nav>

    <p class="discret">
        <?= (int) $total ?> événement<?= (int) $total > 1 ? 's' : '' ?>
        sur cette période (<?= (int) $borneDebut ?> – <?= (int) $borneFin ?> au total).
    </p>

    <?php if ($parAnnee === []): ?>
        <div class="panneau panneau--centre">
            <p>Aucun événement daté sur cette période.</p>
        </div>
    <?php else: ?>
        <ol class="frise__liste">
            <?php foreach ($parAnnee as $annee => $evenements): ?>
                <li class="frise__annee">
                    <h2 class="frise__titre-annee"><?= (int) $annee ?></h2>
                    <ul class="frise__evenements">
                        <?php foreach ($evenements as $evenement): ?>
                            <li class="frise__evenement frise__evenement--<?= e($evenement['genre']) ?>">
                                <?php if ($evenement['genre'] === 'document'): ?>
                                    <span class="frise__genre">Document</span>
                                    <a href="<?= e(document_url($evenement['document'])) ?>"><?= e($evenement['document']['title']) ?></a>
                                <?php elseif ($evenement['genre'] === 'mariage'): ?>
                                    <span class="frise__genre">Mariage</span>
                                    <a href="/arbre/<?= (int) $evenement['epoux']['id'] ?>"><?= e(person_full_name($evenement['epoux'])) ?></a>
                                    et
                                    <a href="/arbre/<?= (int) $evenement['epouse']['id'] ?>"><?= e(person_full_name($evenement['epouse'])) ?></a>
                                    <?php if ($evenement['lieu'] !== ''): ?>
                                        <span class="discret">à <?= e($evenement['lieu']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="frise__genre"><?= $evenement['genre'] === 'naissance' ? 'Naissance' : 'Décès' ?></span>
                                    <a href="/arbre/<?= (int) $evenement['personne']['id'] ?>"><?= e(person_full_name($evenement['personne'])) ?></a>
                                <?php endif; ?>
                                <?php if (trim((string) $evenement['date']) !== ''): ?>
                                    <span class="frise__date"><?= e($evenement['date']) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
