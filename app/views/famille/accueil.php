<?php
/**
 * Espace famille.
 *
 * @var array $account, $documents, $personnes, $pages, $articles, $annonces, $courrier
 * @var int   $nbDocuments, $nbPersonnes, $nbPages
 */
$isBranchAccount = ($account['role'] === 'famille');
?>
<section class="section une-intro">
    <h1 class="une-intro__titre">Bonjour<?= !empty($account['display_name']) ? ' ' . e($account['display_name']) : '' ?></h1>
    <p class="note">
        Vous êtes connecté<?= $isBranchAccount && !empty($account['branch_name']) ? ' avec le compte de la branche <strong>' . e($account['branch_name']) . '</strong>' : '' ?>.
        <?php if ($isBranchAccount && (int) ($account['branch_see_all'] ?? 0) === 1): ?>
            Ce compte voit les contenus de toutes les branches.
        <?php elseif ($isBranchAccount): ?>
            Vous voyez les contenus publics, ceux ouverts à toutes les familles et ceux
            réservés à votre branche.
        <?php endif; ?>
    </p>
</section>

<div class="une">
    <div class="une__principal">
        <?php if ($articles !== []): ?>
            <?php $article = $articles[0]; $grand = true; require __DIR__ . '/../partials/carte_article.php'; ?>
            <?php if (count($articles) > 1): ?>
                <div class="une__colonnes">
                    <?php foreach (array_slice($articles, 1) as $article): $grand = false; ?>
                        <?php require __DIR__ . '/../partials/carte_article.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <p class="une__suite"><a href="/articles">Tous les articles →</a></p>
        <?php else: ?>
            <p class="discret">Aucun article publié pour le moment.</p>
        <?php endif; ?>
    </div>

    <aside class="une__lateral" aria-label="Dans ce numéro">
        <section class="encart">
            <h2 class="encart__titre">Petites annonces</h2>
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

        <?php if ($courrier !== []): ?>
            <section class="encart">
                <h2 class="encart__titre">Courrier des lecteurs</h2>
                <?php foreach ($courrier as $message): ?>
                    <blockquote class="encart__lettre">
                        <p><?= e(text_summary((string) $message['body'], 140)) ?></p>
                        <footer>— <?= e((string) $message['author_label']) ?></footer>
                    </blockquote>
                <?php endforeach; ?>
                <p class="encart__suite"><a href="/tchat">Lire la suite →</a></p>
            </section>
        <?php endif; ?>

        <section class="encart">
            <h2 class="encart__titre">En chiffres</h2>
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
    </aside>
</div>

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
