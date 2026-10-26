<?php
/**
 * Fiche d'une personne de l'arbre.
 *
 * @var array      $person, $parents, $siblings, $unions, $branches
 * @var array|null $photo
 */
$dates = person_dates($person);
$age   = person_age($person);
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/arbre">Arbre généalogique</a>
    <span aria-hidden="true">/</span>
    <span><?= e(person_full_name($person)) ?></span>
</nav>

<section class="section fiche-personne">
    <div class="fiche-personne__entete">
        <?php if ($photo !== null): ?>
            <img class="fiche-personne__photo" src="<?= e(file_url($photo)) ?>"
                 alt="Portrait de <?= e(person_full_name($person)) ?>">
        <?php endif; ?>

        <div>
            <h1><?= e(person_full_name($person)) ?></h1>
            <?php if ($dates !== ''): ?>
                <p class="fiche-personne__dates"><?= e($dates) ?><?= $age !== null ? ' — ' . $age . ' ans' : '' ?></p>
            <?php endif; ?>
            <p class="actions">
                <a class="bouton bouton--secondaire" href="/arbre?racine=<?= (int) $person['id'] ?>">Voir dans l’arbre</a>
                <?php if (is_admin()): ?>
                    <a class="bouton bouton--secondaire" href="/admin/arbre/personne/<?= (int) $person['id'] ?>">Modifier</a>
                    <a class="bouton bouton--secondaire" href="/admin/arbre/couple/nouveau">Ajouter un couple</a>
                <?php endif; ?>
            </p>
            <?php if (is_admin()): ?>
                <p>
                    <span class="<?= e(visibility_class((string) $person['visibility'])) ?>">
                        <?= e(visibility_label((string) $person['visibility'])) ?>
                    </span>
                    <?php foreach ($branches as $branchId): ?>
                        <?php $branch = find_branch($branchId); ?>
                        <?php if ($branch): ?>
                            <span class="etiquette"><?= e($branch['name']) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="fiche-personne__corps">
        <div>
            <h2>Repères</h2>
            <dl class="fiche">
                <dt>Naissance</dt>
                <dd>
                    <?= trim((string) $person['birth_date']) !== '' ? e($person['birth_date']) : '—' ?>
                    <?php if (trim((string) $person['birth_place']) !== ''): ?>
                        <span class="discret">à <?= e($person['birth_place']) ?></span>
                    <?php endif; ?>
                </dd>
                <dt>Décès</dt>
                <dd>
                    <?= trim((string) $person['death_date']) !== '' ? e($person['death_date']) : '—' ?>
                    <?php if (trim((string) $person['death_place']) !== ''): ?>
                        <span class="discret">à <?= e($person['death_place']) ?></span>
                    <?php endif; ?>
                </dd>
            </dl>

            <h2>Parents</h2>
            <?php if ($parents === []): ?>
                <p class="discret">Aucun parent renseigné.</p>
            <?php else: ?>
                <ul class="liste-personnes">
                    <?php foreach ($parents as $parent): ?>
                        <li>
                            <a href="/arbre/<?= (int) $parent['id'] ?>"><?= e(person_full_name($parent)) ?></a>
                            <span class="discret"><?= e(person_dates($parent)) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($siblings !== []): ?>
                <h2>Frères et sœurs</h2>
                <ul class="liste-personnes">
                    <?php foreach ($siblings as $sibling): ?>
                        <li>
                            <a href="/arbre/<?= (int) $sibling['id'] ?>"><?= e(person_full_name($sibling)) ?></a>
                            <span class="discret"><?= e(person_dates($sibling)) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div>
            <h2>Union<?= count($unions) > 1 ? 's' : '' ?> et descendance</h2>
            <?php if ($unions === []): ?>
                <p class="discret">Aucun conjoint ni enfant renseigné.</p>
            <?php endif; ?>

            <?php foreach ($unions as $union): ?>
                <div class="panneau union">
                    <?php if ($union['spouse'] !== null): ?>
                        <p>
                            Avec
                            <a href="/arbre/<?= (int) $union['spouse']['id'] ?>"><?= e(person_full_name($union['spouse'])) ?></a>
                            <span class="discret"><?= e(person_dates($union['spouse'])) ?></span>
                        </p>
                    <?php else: ?>
                        <p class="discret">Sans conjoint renseigné</p>
                    <?php endif; ?>

                    <?php if (trim((string) $union['family']['marriage_date']) !== ''
                        || trim((string) $union['family']['marriage_place']) !== ''): ?>
                        <p class="discret">
                            Mariage :
                            <?= e($union['family']['marriage_date'] ?? '') ?>
                            <?php if (trim((string) $union['family']['marriage_place']) !== ''): ?>
                                à <?= e($union['family']['marriage_place']) ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($union['children'] === []): ?>
                        <p class="discret">Aucun enfant renseigné.</p>
                    <?php else: ?>
                        <ul class="liste-personnes">
                            <?php foreach ($union['children'] as $child): ?>
                                <li>
                                    <a href="/arbre/<?= (int) $child['id'] ?>"><?= e(person_full_name($child)) ?></a>
                                    <span class="discret"><?= e(person_dates($child)) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (trim((string) $person['notes']) !== ''): ?>
        <h2>Notes</h2>
        <div class="texte"><?= render_text($person['notes']) ?></div>
    <?php endif; ?>
</section>
