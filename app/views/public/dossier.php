<?php
/**
 * Page d'un dossier d'archives (1.36.0).
 *
 * Elle n'est rendue que si le dossier a au moins un document visible pour
 * le compte courant (le contrôleur répond 404 sinon) : ni le nom ni le
 * nombre d'un dossier vide pour ce compte ne peuvent fuir.
 *
 * @var array $dossier, $documents, $personne
 */
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/documents?archives=1">Archives</a>
    <span aria-hidden="true">/</span>
    <span><?= e((string) $dossier['name']) ?></span>
</nav>

<section class="section">
    <div class="section__entete">
        <div>
            <h1><?= e((string) $dossier['name']) ?></h1>
            <p class="aide">Dossier d’archives : <?= (int) count($documents) ?> document<?= count($documents) > 1 ? 's' : '' ?> que votre compte peut voir.</p>
        </div>
    </div>

    <?php if (trim((string) ($dossier['description'] ?? '')) !== ''): ?>
        <div class="texte">
            <?= render_text((string) $dossier['description']) ?>
        </div>
    <?php endif; ?>

    <?php if (($personne ?? null) !== null): ?>
        <p>
            Personne liée :
            <a href="/arbre/<?= (int) $personne['id'] ?>"><?= e(person_full_name($personne)) ?></a>
        </p>
    <?php endif; ?>

    <?php if ($documents === []): ?>
        <div class="panneau panneau--centre">
            <p>Aucun document de ce dossier n’est visible avec votre compte.</p>
        </div>
    <?php else: ?>
        <div class="grille-cartes">
            <?php foreach ($documents as $doc): ?>
                <?php require __DIR__ . '/../partials/carte_document.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
