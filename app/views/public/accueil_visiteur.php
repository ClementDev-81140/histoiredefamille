<?php
/**
 * Accueil d'un **visiteur** : la Une du journal (1.31.0).
 *
 * Une seule page pour tout le contenu public du site, du plus récent au plus
 * ancien : le plus récent en grand (la une), les autres en grille de journal,
 * regroupés par rubrique dès qu'ils sont plus de six. Chaque contenu mène à sa
 * page publique ; le seul autre lien est « Se connecter ».
 *
 * Aucun style ni script en ligne : les classes sont dans
 * `public/assets/style.css` (bloc 1.31.0).
 *
 * @var array|null $unePublique      le contenu de la une (null : site vide)
 * @var bool  $uneChoisie            la une a été choisie par un administrateur
 * @var list<array> $itemsPubliques  les autres contenus de la page
 * @var int   $totalPublique
 * @var array $pagination
 * @var int   $pagePublique
 * @var int   $parPage
 * @var string $phraseAccueil
 */
$pagesTotal = (int) ($pagination['pages'] ?? 1);
$groupes    = count($itemsPubliques) > 6 ? une_publique_groupes($itemsPubliques) : [];
?>
<?php /* 1.33.1 : plus de phrase ni de gros bouton en tête de page. « Se connecter »
         est déjà dans le menu ; la page commence directement par la une. */ ?>

<?php if ($unePublique === null): ?>
    <?php /* Aucun contenu public : une Une sobre, sans bloc vide ni rubrique
             fantôme. Le bouton et les deux adresses suffisent. */ ?>
    <section class="section une-vide">
        <div class="section__entete">
            <h2>Le journal paraîtra bientôt</h2>
        </div>
        <p>
            Cette page ne montre que ce qui est public : pour l’instant, il n’y a
            rien à lire ici. Les familles, elles, ont déjà accès à tout le reste
            une fois connectées.
        </p>
        <p class="actions actions--portes">
            <a class="bouton bouton--accent bouton--large bouton--porte" href="/connexion">Se connecter</a>
        </p>
        <p class="une-vide__contact">
            Une question ?
            <a href="mailto:<?= e(site_adresse('contact')) ?>"><?= e(site_adresse('contact')) ?></a>
            · Besoin d’un compte ?
            <a href="mailto:<?= e(site_adresse('support')) ?>"><?= e(site_adresse('support')) ?></a>
        </p>
    </section>
<?php else: ?>
    <section class="section une-grande" aria-labelledby="une-publique-titre">
        <div class="section__entete">
            <h2 id="une-publique-titre">À la une</h2>
            <span class="discret"><?= $uneChoisie
                ? 'Choisi par la rédaction'
                : 'Le contenu public le plus récent' ?></span>
        </div>

        <?php /* Sans image, la colonne de texte prend toute la largeur : pas de
                 zone vide à droite (1.32.1). */ ?>
        <article class="une-grande__article<?= ($unePublique['image'] ?? null) === null ? ' une-grande__article--sans-image' : '' ?>">
            <?php if (($unePublique['image'] ?? null) !== null): ?>
                <a class="une-grande__image" href="<?= e($unePublique['url']) ?>" tabindex="-1" aria-hidden="true">
                    <img class="<?= e((string) ($unePublique['image_aspect'] ?? '')) ?>" src="<?= e($unePublique['image']) ?>" alt="" width="960" height="640" loading="eager">
                </a>
            <?php endif; ?>

            <div class="une-grande__texte">
                <p class="une-grande__meta">
                    <span class="une-grande__rubrique"><?= e($unePublique['rubrique']) ?></span>
                    <span class="discret"><?= e($unePublique['date_texte']) ?></span>
                    <span class="discret"><?= e($unePublique['genre']) ?></span>
                </p>
                <h3 class="une-grande__titre">
                    <a href="<?= e($unePublique['url']) ?>"><?= e($unePublique['titre']) ?></a>
                </h3>
                <?php if ($unePublique['resume'] !== ''): ?>
                    <p class="une-grande__resume"><?= e($unePublique['resume']) ?></p>
                <?php endif; ?>
                <p><a href="<?= e($unePublique['url']) ?>">Lire la suite →</a></p>
            </div>
        </article>
    </section>

    <?php if ($itemsPubliques !== []): ?>
        <?php if ($groupes !== []): ?>
            <?php /* Plus de six éléments : une rubrique par section, comme les
                     pages intérieures d'un journal. */ ?>
            <?php foreach ($groupes as $groupe): ?>
                <section class="section une-rubrique">
                    <div class="section__entete">
                        <h2><?= e($groupe['libelle']) ?></h2>
                        <span class="discret"><?= count($groupe['items']) ?></span>
                    </div>
                    <ul class="une-grille">
                        <?php foreach ($groupe['items'] as $item): ?>
                            <?php require __DIR__ . '/partials/carte_une.php'; ?>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        <?php else: ?>
            <section class="section une-rubrique">
                <div class="section__entete">
                    <h2>Dans le journal</h2>
                    <span class="discret"><?= (int) $totalPublique ?></span>
                </div>
                <ul class="une-grille">
                    <?php foreach ($itemsPubliques as $item): ?>
                        <?php require __DIR__ . '/partials/carte_une.php'; ?>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($pagePublique < $pagesTotal): ?>
            <p class="actions une-suite">
                <a class="bouton" href="/?page=<?= $pagePublique + 1 ?>">Voir plus</a>
                <span class="discret">
                    page <?= $pagePublique ?> sur <?= $pagesTotal ?> — <?= (int) $totalPublique ?> contenus publics
                </span>
            </p>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
