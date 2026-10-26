<?php
/**
 * Espace famille.
 *
 * @var array $account, $documents, $personnes, $pages
 * @var int   $nbDocuments, $nbPersonnes, $nbPages
 */
$isBranchAccount = ($account['role'] === 'famille');
?>
<section class="section">
    <h1>Bonjour<?= !empty($account['display_name']) ? ' ' . e($account['display_name']) : '' ?></h1>
    <p class="note">
        Vous êtes connecté<?= $isBranchAccount && !empty($account['branch_name']) ? ' avec le compte de la branche <strong>' . e($account['branch_name']) . '</strong>' : '' ?>.
        <?php if ($isBranchAccount && (int) ($account['branch_see_all'] ?? 0) === 1): ?>
            Ce compte voit les contenus de toutes les branches.
        <?php elseif ($isBranchAccount): ?>
            Vous voyez les contenus publics, ceux ouverts à toutes les familles et ceux
            réservés à votre branche.
        <?php endif; ?>
    </p>

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
