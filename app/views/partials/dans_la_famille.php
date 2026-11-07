<?php
/**
 * Bloc « Dans la famille » de l'accueil d'un compte connecté (1.29.0).
 *
 * Depuis la 1.29.0, ce bloc passe **après** la une : sur grand écran il tient
 * dans une colonne de droite, sur téléphone il suit l'article. Il ne montre que
 * ce que la personne a le droit de voir — les quatre listes viennent de
 * `dans_la_famille()`, qui réutilise le calendrier et les conditions de
 * visibilité. Rien n'est filtré ici.
 *
 * Cinq lignes au plus, réparties entre les sections non vides (un tour chacune,
 * dans l'ordre habituel : anniversaires, événements, personnes, nouveautés),
 * puis un lien « Tout voir » vers l'espace famille, qui montre tout.
 *
 * Au-delà de trois lignes, le bloc se replie sur téléphone dans un `<details>`
 * (aucun JavaScript) ; sur grand écran, il reste ouvert. Les deux versions sont
 * écrites avec le même contenu : l'une est masquée par la feuille de style
 * selon la largeur (`.accueil-famille--repliable`).
 *
 * @var array $dansLaFamille  (anniversaires, evenements, personnes, nouveautes)
 * @var int   $familleMax     nombre de lignes visibles (5 par défaut)
 */
$limite = max(1, (int) ($familleMax ?? 5));

// Les quatre listes, dans l'ordre de lecture du bloc.
$listes = [
    'anniversaires' => array_values((array) ($dansLaFamille['anniversaires'] ?? [])),
    'evenements'    => array_values((array) ($dansLaFamille['evenements'] ?? [])),
    'personnes'     => array_values((array) ($dansLaFamille['personnes'] ?? [])),
    'nouveautes'    => array_values((array) ($dansLaFamille['nouveautes'] ?? [])),
];

// Répartition « un tour chacune » : une section qui a beaucoup d'entrées ne
// prend pas toute la place, et les cinq lignes sont toujours utilisées.
$gardees = array_fill_keys(array_keys($listes), []);
$restant = $limite;
$tour    = 0;
while ($restant > 0) {
    $ajout = false;
    foreach ($listes as $cle => $liste) {
        if ($restant <= 0) {
            break;
        }
        if (array_key_exists($tour, $liste)) {
            $gardees[$cle][] = $liste[$tour];
            $restant--;
            $ajout = true;
        }
    }
    if (!$ajout) {
        break;
    }
    $tour++;
}

$total     = $limite - $restant;
$repliable = $total > 3;

// Le contenu, écrit une seule fois puis posé deux fois (téléphone, grand écran).
ob_start();
?>
<div class="grille-pages">
    <?php if ($gardees['anniversaires'] !== []): ?>
        <section class="panneau panneau--famille">
            <h3>Anniversaires</h3>
            <ul class="liste-personnes">
                <?php foreach ($gardees['anniversaires'] as $evenement): ?>
                    <li>
                        <a href="<?= e($evenement['lien']) ?>"><?= e($evenement['nom']) ?></a>
                        <span class="discret"><?= e(calendrier_jour_lisible($evenement)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($gardees['evenements'] !== []): ?>
        <section class="panneau panneau--famille">
            <h3>Événements à venir</h3>
            <ul class="liste-personnes">
                <?php foreach ($gardees['evenements'] as $evenement): ?>
                    <li>
                        <a href="<?= e($evenement['lien']) ?>"><?= e($evenement['nom']) ?></a>
                        <span class="discret">
                            <?= e(calendrier_jour_lisible($evenement)) ?> —
                            <?= $evenement['genre'] === 'mariage'
                                ? 'mariage, ' . (int) $evenement['ans'] . ' an' . ((int) $evenement['ans'] > 1 ? 's' : '')
                                : 'souvenir, ' . (int) $evenement['ans'] . ' an' . ((int) $evenement['ans'] > 1 ? 's' : '') ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($gardees['personnes'] !== []): ?>
        <section class="panneau panneau--famille">
            <h3>Personnes ajoutées</h3>
            <ul class="liste-personnes">
                <?php foreach ($gardees['personnes'] as $personne): ?>
                    <li>
                        <a href="/arbre/<?= (int) $personne['id'] ?>"><?= e(person_full_name($personne)) ?></a>
                        <span class="discret"><?= e(format_date((int) $personne['created_at'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($gardees['nouveautes'] !== []): ?>
        <section class="panneau panneau--famille">
            <h3>Depuis votre dernière visite</h3>
            <ul class="liste-personnes">
                <?php foreach ($gardees['nouveautes'] as $nouveaute): ?>
                    <li>
                        <span class="nouveautes__type"><?= e($nouveaute['type']) ?></span>
                        <a href="<?= e($nouveaute['lien']) ?>"><?= e($nouveaute['titre']) ?></a>
                        <span class="discret"><?= e(format_date((int) $nouveaute['date'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
<?php
$contenu = (string) ob_get_clean();
?>
<div class="dans-la-famille accueil-famille<?= $repliable ? ' accueil-famille--repliable' : '' ?>">
    <?php if ($repliable): ?>
        <?php /* Téléphone : replié, une ligne de résumé ; le contenu s'ouvre au
                 clic. Le grand écran, lui, affiche le bloc entier (plus bas). */ ?>
        <details class="accueil-famille__repli">
            <summary>Dans la famille</summary>
            <?= $contenu ?>
            <p class="accueil-famille__tout"><a href="/famille">Tout voir →</a></p>
        </details>
    <?php endif; ?>

    <div class="accueil-famille__bloc">
        <div class="section__entete">
            <h2>Dans la famille</h2>
            <a href="/famille">Tout voir →</a>
        </div>
        <?= $contenu ?>
    </div>
</div>
