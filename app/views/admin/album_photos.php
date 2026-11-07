<?php
/**
 * Ajouter des photos à un album, sans quitter la page.
 *
 * Le bloc d'envoi par morceaux est celui de tout le site (glisser-déposer,
 * plusieurs fichiers, reprise). Chaque photo reçue entre dans l'album avec la
 * visibilité de l'album ; la liste du dessous permet de corriger le titre et
 * la date, puis de retirer une photo de l'album — sans jamais supprimer le
 * document.
 *
 * @var array $album, $ajoutees, $erreurs
 */
$nb = count($ajoutees);
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/admin/albums">Albums</a>
    <span aria-hidden="true">/</span>
    <a href="/admin/albums/<?= (int) $album['id'] ?>/modifier"><?= e($album['title']) ?></a>
    <span aria-hidden="true">/</span>
    <span>Ajouter des photos</span>
</nav>

<div class="section__entete">
    <h1>Ajouter des photos à « <?= e($album['title']) ?> »</h1>
    <span class="actions">
        <a class="bouton bouton--secondaire" href="/albums/<?= e($album['slug']) ?>">Voir l’album</a>
        <a class="bouton bouton--secondaire" href="/admin/albums/<?= (int) $album['id'] ?>/modifier">Modifier l’album</a>
    </span>
</div>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<p class="note">
    Les photos ajoutées ici entrent <strong>tout de suite</strong> dans cet album, à la fin,
    et prennent sa visibilité
    (<span class="<?= e(visibility_class((string) $album['visibility'])) ?>"><?= e(visibility_short((string) $album['visibility'])) ?></span>).
    Une photo garde la sienne : une photo privée reste invisible, même dans un album public.
</p>

<?php
$envoiOptions = [
    'creer'     => true,
    'lot'       => false,
    'album'     => (int) $album['id'],
    'versAlbum' => '/albums/' . (string) $album['slug'],
    'types'     => envoi_extensions_photo(),
    'titre'     => 'Déposer des photos',
    'aide'      => 'Chaque photo reçue devient une photo de cet album, sans autre formulaire.',
];
require __DIR__ . '/partials/envoi.php';
?>

<section class="section" id="photos-ajoutees">
    <div class="section__entete">
        <h2>Photos ajoutées à cet album</h2>
        <a class="lien" href="/albums/<?= e($album['slug']) ?>">Voir l’album</a>
    </div>

    <?php if ($nb === 0): ?>
        <p class="aide">
            Aucune photo ajoutée pour le moment depuis cette page. Dès qu’une photo est
            reçue, elle apparaît ici : vous pouvez y corriger son titre et sa date, ou la
            retirer de l’album (le document, lui, reste dans « Documents &amp; photos »).
        </p>
    <?php else: ?>
        <p class="aide">
            <?= $nb ?> photo<?= $nb > 1 ? 's' : '' ?> ajoutée<?= $nb > 1 ? 's' : '' ?>
            par vous à cet album. Corrigez le titre et la date, puis enregistrez :
            la page reste ici, et vous pouvez en envoyer d’autres.
        </p>

        <div class="tableau-defilant tableau-defilant--photos">
            <table class="tableau">
                <thead>
                    <tr>
                        <th scope="col">Photo</th>
                        <th scope="col">Titre et date</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ajoutees as $photo): ?>
                        <?php $idPhoto = (int) $photo['id']; ?>
                        <tr>
                            <td>
                                <?php if (thumbnail_url($photo) !== null): ?>
                                    <img src="<?= e(thumbnail_url($photo)) ?>" alt="" loading="lazy"
                                         width="120" height="90">
                                <?php else: ?>
                                    <span class="discret">Photo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" class="formulaire"
                                      action="/admin/albums/<?= (int) $album['id'] ?>/photos/titre">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="document_id" value="<?= $idPhoto ?>">
                                    <div class="ligne-champs">
                                        <div class="champ">
                                            <label for="titre-<?= $idPhoto ?>">Titre</label>
                                            <input type="text" id="titre-<?= $idPhoto ?>" name="title"
                                                   maxlength="200" value="<?= e((string) $photo['title']) ?>">
                                        </div>
                                        <div class="champ champ--petit">
                                            <label for="date-<?= $idPhoto ?>">Date</label>
                                            <input type="text" id="date-<?= $idPhoto ?>" name="doc_date"
                                                   maxlength="100" value="<?= e((string) $photo['doc_date']) ?>">
                                        </div>
                                    </div>
                                    <p>
                                        <button type="submit" class="bouton bouton--secondaire">
                                            Enregistrer le titre et la date
                                        </button>
                                    </p>
                                </form>
                            </td>
                            <td class="colonne-actions colonne-actions--pile">
                                <form method="post" class="formulaire-en-ligne"
                                      action="/admin/albums/<?= (int) $album['id'] ?>/photos/retirer"
                                      data-confirmation="Retirer cette photo de l’album ? Le document n’est pas supprimé.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="document_id" value="<?= $idPhoto ?>">
                                    <button type="submit" class="lien-bouton lien-bouton--danger">
                                        Retirer de l’album
                                    </button>
                                </form>
                                <a class="lien" href="/documents/<?= $idPhoto ?>">Ouvrir la fiche</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
