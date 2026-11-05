<?php
/**
 * Témoignages sonores.
 *
 * @var array $documents, $personnes, $pagination
 * @var int   $total
 */
?>
<section class="section">
    <div class="section__entete">
        <h1>Témoignages</h1>
        <?php if (is_admin()): ?>
            <a class="bouton" href="/admin/documents/nouveau">Envoyer un enregistrement</a>
        <?php endif; ?>
    </div>

    <p class="discret">
        <?= (int) $total ?> enregistrement<?= (int) $total > 1 ? 's' : '' ?>
        <?php if (!is_logged_in()): ?>
            — les enregistrements réservés aux familles ne sont pas affichés.
            <a href="/connexion">Se connecter</a>
        <?php endif; ?>
    </p>

    <?php if ($documents === []): ?>
        <div class="panneau panneau--centre">
            <p>Aucun enregistrement pour le moment.</p>
            <?php if (is_admin()): ?>
                <p>
                    Envoyer un fichier MP3, M4A ou OGG depuis
                    <a href="/admin/documents/nouveau">Ajouter un document</a>.
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="temoignages">
            <?php foreach ($documents as $document): ?>
                <li class="temoignage">
                    <h2 class="temoignage__titre">
                        <a href="<?= e(document_url($document)) ?>"><?= e($document['title']) ?></a>
                    </h2>

                    <p class="temoignage__meta">
                        <?php if (trim((string) $document['doc_date']) !== ''): ?>
                            <span><?= e($document['doc_date']) ?></span>
                        <?php endif; ?>
                        <?php
                        $idents = $personnes[(int) $document['id']] ?? [];
                        if ($idents !== []):
                            ?>
                            <span><?= e(album_people_line($idents)) ?></span>
                        <?php endif; ?>
                    </p>

                    <audio controls preload="none" src="<?= e(file_url($document)) ?>">
                        Votre navigateur ne permet pas la lecture de ce fichier audio.
                        <a href="<?= e(download_url($document)) ?>">Télécharger l’enregistrement</a>.
                    </audio>

                    <p class="temoignage__lien">
                        <a class="lien" href="<?= e(document_url($document)) ?>">Voir la fiche</a>
                    </p>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</section>
