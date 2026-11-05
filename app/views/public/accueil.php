<?php
/**
 * Page d'accueil publique.
 *
 * Comme l'espace famille, elle se compose dans
 * « Administration → Composition des pages » : les blocs de l'en-tête
 * (« également publié », chiffres) puis les rubriques, dans l'ordre choisi.
 * Les deux portes (« Consulter », « Se connecter ») ne s'affichent que pour
 * un visiteur ; un compte connecté trouve à leur place, plus bas, le bloc
 * « Dans la famille » — strictement dans ses droits.
 *
 * @var array $documents
 * @var array $photos
 * @var array $pages
 * @var array $personnes
 * @var array $dansLaFamille
 * @var int   $nbDocs, $nbPages, $nbPersonnes
 */
$siteName = site_setting('name');
$hero     = composition_colonne('public', 'hero');
$pleine   = composition_colonne('public', 'pleine');
?>
<section class="hero">
    <?php /* Le nom et le sous-titre figurent déjà dans l'en-tête du journal :
             on garde un h1 lisible par les lecteurs d'écran et les moteurs de
             recherche, mais invisible à l'écran (évite le doublon). */ ?>
    <h1 class="sr-only"><?= e($siteName) ?></h1>
    <?php /* Le sous-titre est déjà dans l'en-tête du journal : on ne
             l'affiche pas une deuxième fois ici. La phrase d'accueil est
             réglable dans « Apparence du journal » ; vide, elle reprend la
             description du site. */ ?>
    <?php if (apparence_accueil_phrase() !== ''): ?>
        <p class="hero__texte"><?= e(apparence_accueil_phrase()) ?></p>
    <?php endif; ?>

    <?php foreach ($hero as $bloc): ?>

        <?php if ($bloc === 'entrees'): ?>
            <?php /* Deux portes pour un visiteur : « Consulter » pour découvrir,
                     « Se connecter » pour entrer dans l'espace de la famille.
                     La seconde est mise en avant : c'est celle qu'on cherche.
                     Un compte connecté, lui, n'a plus besoin de ces portes :
                     le menu lui donne déjà toutes les rubriques. */ ?>
            <?php if (!is_logged_in()): ?>
                <p class="actions actions--portes">
                    <a class="bouton bouton--large" href="/documents">Consulter</a>
                    <a class="bouton bouton--accent bouton--large bouton--porte" href="/connexion">Se connecter</a>
                </p>
            <?php endif; ?>

        <?php elseif ($bloc === 'egalement'): ?>
            <?php
            // Rubriques supplémentaires : seulement celles qui ont du contenu
            // public (une seule fonction, mise en cache pour la requête). Pour
            // un compte connecté, le menu complet suffit : on n'ajoute rien.
            $autres = [];
            if (menu_public_reduit()) {
                $autres = [
                    'articles'   => ['/articles', 'Articles'],
                    'videos'     => ['/videos', 'Vidéos'],
                    'albums'     => ['/albums', 'Albums'],
                    'calendrier' => ['/calendrier', 'Calendrier'],
                    'recherche'  => ['/recherche', 'Recherche'],
                ];
                $autres = array_filter($autres, static fn (string $cle): bool => rubrique_publique($cle), ARRAY_FILTER_USE_KEY);
            }
            ?>
            <?php if ($autres !== []): ?>
                <nav class="hero__rubriques" aria-label="Autres rubriques publiques">
                    <p class="hero__rubriques-titre">Également publié</p>
                    <ul>
                        <?php foreach ($autres as $lien): ?>
                            <li><a href="<?= e($lien[0]) ?>"><?= e($lien[1]) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php elseif ($bloc === 'chiffres'): ?>
            <p class="hero__chiffres" aria-label="Le site en chiffres">
                <span><strong><?= (int) $nbDocs ?></strong> document<?= (int) $nbDocs > 1 ? 's' : '' ?> accessible<?= (int) $nbDocs > 1 ? 's' : '' ?></span>
                <span><strong><?= (int) $nbPages ?></strong> page<?= (int) $nbPages > 1 ? 's' : '' ?> d’information</span>
            </p>

        <?php endif; ?>

    <?php endforeach; ?>
</section>

<?php foreach ($pleine as $bloc): ?>

    <?php if ($bloc === 'famille'): ?>
        <?php if (!empty($dansLaFamille) && !dans_la_famille_vide($dansLaFamille)): ?>
            <?php /* Ce qui concerne la personne connectée, et rien d'autre :
                     les quatre listes sont déjà filtrées par ses droits
                     (calendrier.php, visibility.php). Vide, le bloc
                     disparaît : aucune section vide sur la page. */ ?>
            <section class="section dans-la-famille">
                <div class="section__entete">
                    <h2>Dans la famille</h2>
                    <a href="/famille">Votre espace famille →</a>
                </div>
                <div class="grille-pages">

                    <?php if ($dansLaFamille['anniversaires'] !== []): ?>
                        <section class="panneau panneau--famille">
                            <h3>Anniversaires</h3>
                            <ul class="liste-personnes">
                                <?php foreach ($dansLaFamille['anniversaires'] as $evenement): ?>
                                    <li>
                                        <a href="<?= e($evenement['lien']) ?>"><?= e($evenement['nom']) ?></a>
                                        <span class="discret"><?= e(calendrier_jour_lisible($evenement)) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endif; ?>

                    <?php if ($dansLaFamille['evenements'] !== []): ?>
                        <section class="panneau panneau--famille">
                            <h3>Événements à venir</h3>
                            <ul class="liste-personnes">
                                <?php foreach ($dansLaFamille['evenements'] as $evenement): ?>
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

                    <?php if ($dansLaFamille['personnes'] !== []): ?>
                        <section class="panneau panneau--famille">
                            <h3>Personnes ajoutées</h3>
                            <ul class="liste-personnes">
                                <?php foreach ($dansLaFamille['personnes'] as $personne): ?>
                                    <li>
                                        <a href="/arbre/<?= (int) $personne['id'] ?>"><?= e(person_full_name($personne)) ?></a>
                                        <span class="discret"><?= e(format_date((int) $personne['created_at'])) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endif; ?>

                    <?php if ($dansLaFamille['nouveautes'] !== []): ?>
                        <section class="panneau panneau--famille">
                            <h3>Depuis votre dernière visite</h3>
                            <ul class="liste-personnes">
                                <?php foreach ($dansLaFamille['nouveautes'] as $nouveaute): ?>
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
            </section>
        <?php endif; ?>

    <?php elseif ($bloc === 'une'): ?>
        <?php if ($articles !== []): ?>
            <section class="section">
                <div class="section__entete">
                    <h2><?= e(apparence_titre_encart('une')) ?></h2>
                    <a href="/articles">Tous les articles →</a>
                </div>
                <div class="articles-liste">
                    <?php foreach ($articles as $i => $article): ?>
                        <?php $grand = ($i === 0); ?>
                        <?php require __DIR__ . '/../partials/carte_article.php'; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

    <?php elseif ($bloc === 'documents'): ?>
        <?php if ($documents !== []): ?>
            <section class="section">
                <div class="section__entete">
                    <h2>Derniers documents</h2>
                    <a href="/documents">Tout voir →</a>
                </div>
                <div class="grille-cartes">
                    <?php foreach ($documents as $doc): ?>
                        <?php require __DIR__ . '/../partials/carte_document.php'; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

    <?php elseif ($bloc === 'lire'): ?>
        <?php if ($pages !== []): ?>
            <section class="section">
                <?php /* « À lire » (page d'accueil) et « À lire aussi » (bas
                         d'article) sont deux rubriques différentes : seul le
                         titre de la seconde est réglable. */ ?>
                <h2>À lire</h2>
                <div class="grille-pages">
                    <?php foreach ($pages as $page): ?>
                        <article class="panneau">
                            <h3><a href="/pages/<?= e($page['slug']) ?>"><?= e($page['title']) ?></a></h3>
                            <p><?= e(text_summary($page['body'], 200)) ?></p>
                            <p><a href="/pages/<?= e($page['slug']) ?>">Lire la suite →</a></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

    <?php endif; ?>

<?php endforeach; ?>
