<?php
/**
 * Calendrier familial d'un mois, toutes années confondues.
 *
 * @var int   $annee, $mois
 * @var array $grille, $evenements
 * @var string $precedent, $suivant
 * @var bool  $moisCourant
 */
$moisNom = calendrier_mois_nom($mois);
?>
<section class="section calendrier">
    <div class="section__entete">
        <h1>Calendrier</h1>
        <p class="aide">Anniversaires, mariages et souvenirs, mois par mois.</p>
        <p class="actions">
            <?php if (is_admin()): ?>
                <a class="bouton" href="/calendrier/ajouter">Ajouter un événement</a>
            <?php elseif (est_journaliste()): ?>
                <a class="bouton" href="/calendrier/ajouter">Proposer un événement</a>
            <?php endif; ?>
            <a class="bouton bouton--secondaire" href="/frise">Frise chronologique</a>
        </p>
    </div>

    <p class="calendrier__intro">
        Les anniversaires et anniversaires de mariage du mois de
        <strong><?= e($moisNom) ?></strong>, toutes années confondues<?php if (calendrier_afficher_deces()): ?>,
        et les dates de décès (réglage de l'administration)<?php endif; ?>.
    </p>

    <nav class="calendrier__nav" aria-label="Changer de mois">
        <a class="bouton bouton--secondaire" href="/calendrier?mois=<?= e($precedent) ?>">← <?= e(calendrier_mois_nom((int) substr($precedent, 5))) ?></a>
        <span class="calendrier__mois">
            <?= e(ucfirst($moisNom)) ?> <?= $annee ?>
            <?php if (!$moisCourant): ?>
                <a class="lien" href="/calendrier">Ce mois-ci</a>
            <?php endif; ?>
        </span>
        <a class="bouton bouton--secondaire" href="/calendrier?mois=<?= e($suivant) ?>"><?= e(calendrier_mois_nom((int) substr($suivant, 5))) ?> →</a>
    </nav>

    <div class="calendrier__grille">
        <?php foreach (calendrier_jours_noms() as $jourNom): ?>
            <div class="calendrier__entete-jour" aria-hidden="true"><?= e($jourNom) ?></div>
        <?php endforeach; ?>

        <?php foreach ($grille as $semaine): ?>
            <?php foreach ($semaine as $jour): ?>
                <?php $liste = $jour > 0 ? ($evenements[$jour] ?? []) : []; ?>
                <div class="calendrier__case<?= $jour === 0 ? ' calendrier__case--vide' : '' ?><?= $liste !== [] ? ' calendrier__case--pleine' : '' ?>">
                    <?php if ($jour > 0): ?>
                        <span class="calendrier__jour"><?= (int) $jour ?></span>
                        <?php if ($liste !== []): ?>
                            <ul class="calendrier__points">
                                <?php foreach ($liste as $evenement): ?>
                                    <li class="calendrier__point calendrier__point--<?= e($evenement['genre']) ?>">
                                        <span class="sr-only">
                                            <?= e($evenement['annee']) ?> —
                                            <?= e(match ($evenement['genre']) {
                                                'naissance' => 'anniversaire',
                                                'deces'     => 'in memoriam',
                                                'evenement' => 'événement de famille',
                                                default     => 'anniversaire de mariage',
                                            }) ?>
                                        </span>
                                        <span aria-hidden="true"><?= e((string) $evenement['annee']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>

    <h2>Le détail</h2>

    <?php if ($evenements === []): ?>
        <div class="panneau panneau--centre">
            <p>
                Aucun événement en <?= e($moisNom) ?> pour le moment.
                Les dates sans jour ni mois (par exemple « 1901 ») ne peuvent pas
                figurer dans le calendrier : elles restent dans la
                <a href="/frise">frise chronologique</a>.
            </p>
        </div>
    <?php else: ?>
        <ol class="calendrier__liste">
            <?php foreach ($evenements as $jour => $liste): ?>
                <li class="calendrier__jour-liste">
                    <h3 class="calendrier__date"><?= (int) $jour ?> <?= e($moisNom) ?></h3>
                    <ul>
                        <?php foreach ($liste as $evenement): ?>
                            <li class="calendrier__evenement calendrier__evenement--<?= e($evenement['genre']) ?>">
                                <span class="calendrier__annee"><?= e((string) $evenement['annee']) ?></span>
                                <?php if ($evenement['genre'] === 'evenement'): ?>
                                    <?= $evenement['annuel'] ? 'Chaque année : ' : 'Événement : ' ?>
                                    <strong><?= e((string) $evenement['titre']) ?></strong>
                                    <?php $typeLibelle = evenement_type_libelle((string) ($evenement['type'] ?? '')); ?>
                                    <?php if ($typeLibelle !== ''): ?>
                                        <span class="discret"><?= e($typeLibelle) ?></span>
                                    <?php endif; ?>
                                    <?php if ((string) $evenement['heure'] !== ''): ?>
                                        <span class="discret">à <?= e((string) $evenement['heure']) ?></span>
                                    <?php endif; ?>
                                    <?php if ((string) $evenement['lieu'] !== ''): ?>
                                        <span class="discret">— <?= e((string) $evenement['lieu']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($evenement['peutModifier'])): ?>
                                        <a href="/calendrier/<?= (int) $evenement['id'] ?>/modifier">Modifier</a>
                                    <?php endif; ?>
                                <?php elseif ($evenement['genre'] === 'mariage'): ?>
                                    <?php $ansMariage = (int) $annee - (int) $evenement['annee']; ?>
                                    Anniversaire de mariage de
                                    <a href="/arbre/<?= (int) $evenement['epoux']['id'] ?>"><?= e(person_full_name($evenement['epoux'])) ?></a>
                                    et
                                    <a href="/arbre/<?= (int) $evenement['epouse']['id'] ?>"><?= e(person_full_name($evenement['epouse'])) ?></a><?php if ($ansMariage > 0): ?>
                                        <span class="discret">(<?= $ansMariage ?> an<?= $ansMariage > 1 ? 's' : '' ?>)</span>
                                    <?php endif; ?>
                                    <?php if ($evenement['lieu'] !== ''): ?>
                                        <span class="discret">à <?= e($evenement['lieu']) ?></span>
                                    <?php endif; ?>
                                <?php elseif ($evenement['genre'] === 'deces'): ?>
                                    In memoriam :
                                    <a href="/arbre/<?= (int) $evenement['personne']['id'] ?>"><?= e(person_full_name($evenement['personne'])) ?></a>
                                <?php else: ?>
                                    <?php $ans = (int) $annee - (int) $evenement['annee']; ?>
                                    Anniversaire de
                                    <a href="/arbre/<?= (int) $evenement['personne']['id'] ?>"><?= e(person_full_name($evenement['personne'])) ?></a><?php if ($ans > 0): ?>
                                        <span class="discret">(<?= $ans ?> an<?= $ans > 1 ? 's' : '' ?>)</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
