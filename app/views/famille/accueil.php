<?php
/**
 * Espace famille : la « une » du journal.
 *
 * La composition (blocs affichés, ordre) se règle dans
 * « Administration → Composition des pages ». Chaque bloc reste dans sa
 * colonne : la principale (les articles), la latérale (les encarts) et la
 * pleine largeur (les rubriques, sous la une). Un bloc masqué n'est pas
 * calculé du tout par le contrôleur.
 *
 * @var array $account, $documents, $personnes, $pages, $articles, $annonces, $courrier
 * @var array $videos, $albums, $acces, $branchesEdition, $decenniesEdition
 * @var int   $nbDocuments, $nbPersonnes, $nbPages
 */
$principale      = composition_colonne('famille', 'principale');
$laterale        = composition_colonne('famille', 'laterale');
$pleine          = composition_colonne('famille', 'pleine');
$articlesVisibles = in_array('une', $principale, true) || in_array('colonnes', $principale, true);
?>
<?php
// Un journaliste retrouve ici son espace de propositions : c'est son travail
// en cours, avec le nombre de propositions qui attendent une décision.
$mesPropositions = 0;
if (est_journaliste()) {
    foreach (propositions_de((int) $account['id']) as $element) {
        if (($element['row']['moderation_state'] ?? '') === 'attente') {
            $mesPropositions++;
        }
    }
}
?>
<?php if (est_journaliste()): ?>
<section class="panneau panneau--famille">
    <h2>Vous êtes journaliste</h2>
    <p>
        Vous proposez des articles, des documents et des recettes. Chaque proposition
        est relue par un administrateur avant d’être publiée.
    </p>
    <p class="actions">
        <a class="bouton" href="/propositions/nouvelle/article">Proposer un article</a>
        <a class="bouton bouton--secondaire" href="/propositions">Mes propositions<?php if ($mesPropositions > 0): ?> <span class="pastille"><?= $mesPropositions ?></span><?php endif; ?></a>
    </p>
</section>
<?php endif; ?>

<?php if ($account['role'] === 'admin'): ?>
<section class="message message--info">
    <strong><?= (int) ($aValider ?? 0) ?></strong> proposition<?= (int) ($aValider ?? 0) > 1 ? 's' : '' ?>
    en attente de validation.
    <a href="/admin/a-valider">Examiner les propositions</a>
</section>
<?php endif; ?>

<section class="panneau panneau--famille">
    <h2>Nouveautés de votre famille</h2>
    <?php if (empty($nouveautes)): ?>
        <p class="discret">Rien de nouveau pour le moment : les derniers articles, documents et personnes ajoutés apparaîtront ici.</p>
    <?php else: ?>
        <ul class="nouveautes">
            <?php foreach ($nouveautes as $nouveaute): ?>
                <li>
                    <span class="nouveautes__type"><?= e($nouveaute['type']) ?></span>
                    <a href="<?= e($nouveaute['lien']) ?>"><?= e($nouveaute['titre']) ?></a>
                    <span class="discret"><?= e(format_date($nouveaute['date'])) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="section une-intro">
    <h1 class="une-intro__titre">Bonjour<?= !empty($account['display_name']) ? ' ' . e($account['display_name']) : '' ?></h1>
    <?php if (!empty($account['branch_name'])): ?>
        <p class="note">Branche <strong><?= e($account['branch_name']) ?></strong></p>
    <?php endif; ?>
</section>

<?php if ($principale !== [] || $laterale !== []): ?>
<div class="une">
    <?php if ($principale !== []): ?>
    <div class="une__principal">
        <?php foreach ($principale as $bloc): ?>
            <?php if ($bloc === 'une'): ?>
                <?php if ($articles !== []): ?>
                    <?php $article = $articles[0]; $grand = true; require __DIR__ . '/../partials/carte_article.php'; ?>
                <?php else: ?>
                    <p class="discret">Aucun article publié pour le moment.</p>
                <?php endif; ?>
            <?php elseif ($bloc === 'colonnes'): ?>
                <?php if (count($articles) > 1): ?>
                    <div class="une__colonnes">
                        <?php foreach (array_slice($articles, 1) as $article): $grand = false; ?>
                            <?php require __DIR__ . '/../partials/carte_article.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($articles !== [] && $articlesVisibles): ?>
            <p class="une__suite"><a href="/articles">Tous les articles →</a></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($laterale !== []): ?>
    <aside class="une__lateral" aria-label="Dans ce numéro">
        <?php foreach ($laterale as $bloc): ?>

            <?php if ($bloc === 'annonces'): ?>
                <section class="encart">
                    <h2 class="encart__titre"><?= e(apparence_titre_encart('annonces')) ?></h2>
                    <?php if ($annonces === []): ?>
                        <p class="discret">Rien à signaler dans les trente prochains jours.</p>
                    <?php else: ?>
                        <ul class="encart__liste">
                            <?php foreach ($annonces as $annonce): ?>
                                <li>
                                    <span class="encart__date"><?= (int) $annonce['jour'] ?>/<?= sprintf('%02d', (int) $annonce['mois']) ?></span>
                                    <a href="<?= e($annonce['lien']) ?>"><?= e($annonce['texte']) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <p class="encart__suite"><a href="/calendrier">Calendrier →</a></p>
                </section>

            <?php elseif ($bloc === 'editions'): ?>
                <?php if ($branchesEdition !== [] || $decenniesEdition !== []): ?>
                    <section class="encart">
                        <h2 class="encart__titre"><?= e(apparence_titre_encart('editions')) ?></h2>
                        <?php if ($branchesEdition !== []): ?>
                            <ul class="encart__liste encart__liste--editions">
                                <?php foreach ($branchesEdition as $brancheEdition): ?>
                                    <li>
                                        <a href="/articles?branche=<?= (int) $brancheEdition['id'] ?>"><?= e($brancheEdition['nom']) ?></a>
                                        <span class="encart__nombre"><?= (int) $brancheEdition['nb'] ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if ($decenniesEdition !== []): ?>
                            <p class="encart__suite"><a href="/articles">Toutes les décennies →</a></p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

            <?php elseif ($bloc === 'courrier'): ?>
                <?php if ($courrier !== []): ?>
                    <section class="encart">
                        <h2 class="encart__titre"><?= e(apparence_titre_encart('courrier')) ?></h2>
                        <?php foreach ($courrier as $message): ?>
                            <blockquote class="encart__lettre">
                                <p><?= e(text_summary((string) $message['body'], 140)) ?></p>
                                <footer>— <?= e((string) $message['author_label']) ?></footer>
                            </blockquote>
                        <?php endforeach; ?>
                        <p class="encart__suite"><a href="/tchat">Lire la suite →</a></p>
                    </section>
                <?php endif; ?>

            <?php elseif ($bloc === 'acces'): ?>
                <section class="encart">
                    <h2 class="encart__titre"><?= e(apparence_titre_encart('acces')) ?></h2>
                    <ul class="encart__liste encart__liste--liens">
                        <?php foreach ($acces as $lien): ?>
                            <li><a href="<?= e($lien[0]) ?>"><?= e($lien[1]) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </section>

            <?php elseif ($bloc === 'chiffres'): ?>
                <section class="encart">
                    <h2 class="encart__titre"><?= e(apparence_titre_encart('chiffres')) ?></h2>
                    <p class="hero__chiffres">
                        <span><strong><?= (int) $nbDocuments ?></strong> document<?= (int) $nbDocuments > 1 ? 's' : '' ?></span>
                        <span><strong><?= (int) $nbPersonnes ?></strong> personne<?= (int) $nbPersonnes > 1 ? 's' : '' ?> dans l’arbre</span>
                        <span><strong><?= (int) $nbPages ?></strong> page<?= (int) $nbPages > 1 ? 's' : '' ?> d’information</span>
                    </p>
                    <p class="actions">
                        <a class="bouton" href="/documents">Tous les documents</a>
                        <a class="bouton bouton--secondaire" href="/arbre">Arbre généalogique</a>
                    </p>
                </section>
            <?php endif; ?>

        <?php endforeach; ?>
    </aside>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php foreach ($pleine as $bloc): ?>

    <?php if ($bloc === 'videos'): ?>
        <?php if ($videos !== []): ?>
            <section class="section">
                <div class="section__entete">
                    <h2>Dernières vidéos</h2>
                    <a href="/videos">Toutes les vidéos →</a>
                </div>
                <ul class="liste-personnes">
                    <?php foreach ($videos as $video): ?>
                        <li>
                            <a href="/videos/<?= (int) $video['id'] ?>"><?= e((string) $video['title']) ?></a>
                            <span class="discret"><?= e((string) ($video['doc_date'] ?? '')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

    <?php elseif ($bloc === 'albums'): ?>
        <?php if ($albums !== []): ?>
            <section class="section">
                <div class="section__entete">
                    <h2>Albums photos</h2>
                    <a href="/albums">Tous les albums →</a>
                </div>
                <ul class="liste-personnes">
                    <?php foreach ($albums as $album): ?>
                        <li>
                            <a href="/albums/<?= e((string) $album['slug']) ?>"><?= e((string) $album['title']) ?></a>
                            <?php if (trim((string) ($album['description'] ?? '')) !== ''): ?>
                                <span class="discret"><?= e(text_summary((string) $album['description'], 80)) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

    <?php elseif ($bloc === 'pages'): ?>
        <?php if ($pages !== []): ?>
            <section class="section">
                <h2>Pages d’information</h2>
                <div class="grille-pages">
                    <?php foreach ($pages as $page): ?>
                        <article class="panneau">
                            <h3><a href="/pages/<?= e($page['slug']) ?>"><?= e($page['title']) ?></a></h3>
                            <p><?= e(text_summary($page['body'], 180)) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

    <?php elseif ($bloc === 'documents'): ?>
        <section class="section">
            <div class="section__entete">
                <h2>Derniers documents accessibles</h2>
                <a href="/documents">Tout voir →</a>
            </div>
            <?php if ($documents === []): ?>
                <p class="discret">Aucun document pour le moment.</p>
            <?php else: ?>
                <div class="grille-cartes">
                    <?php foreach ($documents as $doc): ?>
                        <?php require __DIR__ . '/../partials/carte_document.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    <?php elseif ($bloc === 'personnes'): ?>
        <?php if ($personnes !== []): ?>
            <section class="section">
                <h2>Personnes récemment mises à jour</h2>
                <ul class="liste-personnes">
                    <?php foreach ($personnes as $personne): ?>
                        <li>
                            <a href="/arbre/<?= (int) $personne['id'] ?>"><?= e(person_full_name($personne)) ?></a>
                            <span class="discret"><?= e(person_dates($personne)) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    <?php endif; ?>

<?php endforeach; ?>
