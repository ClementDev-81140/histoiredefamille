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
 * Depuis la 1.30.0, la page propose aussi de **verser dans l'album** des
 * photos ou des documents qui sont déjà dans « Documents & photos » : une
 * grille de vignettes à cocher, des filtres ordinaires (GET), et un ajout en
 * fin de liste. La visibilité des documents n'est jamais modifiée par l'ajout ;
 * si l'un d'eux reste plus fermé que l'album, la page le dit après l'ajout et
 * propose une action séparée pour lui donner la visibilité de l'album.
 *
 * @var array $album, $ajoutees, $erreurs
 * @var array $candidats, $pagination, $dansAlbum, $filtres, $categories
 * @var array $moinsVisibles, $tousMoinsVisibles
 * @var int   $totalCandidats
 * @var list<int> $ajoutesRecents
 */
$nb = count($ajoutees);

// Adresse des filtres et de l'ajout : la même chaîne de requête, construite
// ici à partir des filtres validés par le serveur.
$requeteFiltres = http_build_query(array_filter([
    'q'          => (string) $filtres['q'],
    'type'       => (string) $filtres['type'],
    'visibilite' => (string) $filtres['visibilite'],
    'categorie'  => $filtres['categorie'] > 0 ? (string) $filtres['categorie'] : '',
    'pas_album'  => $filtres['pas_album'] ? '' : '0',
], static fn (string $valeur): bool => $valeur !== ''));
$adressePage  = '/admin/albums/' . (int) $album['id'] . '/photos';
$adresseAjout = $adressePage . '/ajouter' . ($requeteFiltres !== '' ? '?' . $requeteFiltres : '');
$dedans       = array_flip(array_map('intval', (array) $dansAlbum));
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

<section class="section" id="ajouter-documents">
    <div class="section__entete">
        <h2>Ajouter depuis les documents du site</h2>
        <span class="discret"><?= (int) $totalCandidats ?> élément<?= (int) $totalCandidats > 1 ? 's' : '' ?></span>
    </div>

    <p class="note">
        Cochez des photos ou des documents <strong>déjà présents</strong> sur le site : ils entrent
        dans l’album à la fin, dans l’ordre de la sélection, sans être renvoyés.
        Leur visibilité ne change pas — un document privé reste privé, même dans un album
        <span class="<?= e(visibility_class((string) $album['visibility'])) ?>"><?= e(visibility_short((string) $album['visibility'])) ?></span>.
    </p>

    <?php if ($moinsVisibles !== []): ?>
        <?php $avertissement = album_message_moins_visibles($moinsVisibles, $album); ?>
        <div class="message message--attention" id="visibilite-a-corriger">
            <p><strong><?= e($avertissement) ?></strong></p>
            <form method="post" class="formulaire-en-ligne"
                  action="/admin/albums/<?= (int) $album['id'] ?>/photos/visibilite"
                  data-confirmation="Donner à ces documents la visibilité de l’album ? Cela change qui peut les voir, partout sur le site.">
                <?= csrf_field() ?>
                <?php foreach ($moinsVisibles as $document): ?>
                    <input type="hidden" name="documents[]" value="<?= (int) $document['id'] ?>">
                <?php endforeach; ?>
                <button type="submit" class="bouton bouton--secondaire">
                    Leur donner la visibilité de l’album
                </button>
            </form>
            <p class="discret">
                Rien n’est fait automatiquement : c’est vous qui décidez, et cela se voit
                partout sur le site, pas seulement dans cet album.
            </p>
        </div>
    <?php endif; ?>

    <form method="get" action="<?= e($adressePage) ?>" class="filtres" id="filtres-documents">
        <div class="champ">
            <label for="f-q">Recherche</label>
            <input type="search" id="f-q" name="q" value="<?= e((string) $filtres['q']) ?>"
                   placeholder="Titre ou étiquette">
        </div>
        <div class="champ">
            <label for="f-type">Type</label>
            <select id="f-type" name="type">
                <option value="photo"<?= selected($filtres['type'] === 'photo') ?>>Photos</option>
                <option value=""<?= selected($filtres['type'] === '') ?>>Tous les documents</option>
            </select>
        </div>
        <div class="champ">
            <label for="f-visibilite">Visibilité</label>
            <select id="f-visibilite" name="visibilite">
                <option value="">Toutes</option>
                <?php foreach (visibility_levels() as $valeur => $libelle): ?>
                    <option value="<?= e($valeur) ?>"<?= selected($filtres['visibilite'] === $valeur) ?>><?= e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="champ">
            <label for="f-categorie">Catégorie</label>
            <select id="f-categorie" name="categorie">
                <option value="">Toutes</option>
                <?php foreach ($categories as $categorie): ?>
                    <option value="<?= (int) $categorie['id'] ?>"
                        <?= selected((int) $filtres['categorie'] === (int) $categorie['id']) ?>><?= e((string) $categorie['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="champ champ--case">
            <?php /* Case cochée par défaut : le champ caché qui la précède porte
                     « 0 » quand elle est décochée. */ ?>
            <input type="hidden" name="pas_album" value="0">
            <label for="f-pas-album">
                <input type="checkbox" id="f-pas-album" name="pas_album" value="1"
                    <?= checked($filtres['pas_album']) ?>>
                Pas encore dans cet album
            </label>
        </div>
        <div class="champ champ--bouton">
            <button type="submit" class="bouton">Filtrer</button>
            <a class="lien" href="<?= e($adressePage) ?>">Réinitialiser</a>
        </div>
    </form>

    <?php if ($candidats === []): ?>
        <p class="aide">
            <?php if ((int) $totalCandidats === 0): ?>
                Aucun document ne correspond à ces filtres.
                <?php if ($filtres['pas_album']): ?>
                    Décochez « Pas encore dans cet album » pour revoir ceux qui y sont déjà.
                <?php endif; ?>
            <?php endif; ?>
        </p>
    <?php else: ?>
        <form method="post" action="<?= e($adresseAjout) ?>" class="formulaire" id="selection-documents">
            <?= csrf_field() ?>
            <ul class="grille-selection">
                <?php foreach ($candidats as $document): ?>
                    <?php
                    $idDocument = (int) $document['id'];
                    $dedansAlbum = isset($dedans[$idDocument]);
                    $vignette    = thumbnail_url($document);
                    ?>
                    <li class="choix-document<?= $dedansAlbum ? ' choix-document--dedans' : '' ?>">
                        <label class="choix-document__case">
                            <?php if ($dedansAlbum): ?>
                                <input type="checkbox" disabled>
                            <?php else: ?>
                                <input type="checkbox" name="documents[]" value="<?= $idDocument ?>">
                            <?php endif; ?>
                            <span class="choix-document__vignette">
                                <?php if ($vignette !== null): ?>
                                    <img src="<?= e($vignette) ?>" alt="" loading="lazy" width="240" height="180">
                                <?php else: ?>
                                    <span class="choix-document__vide"><?= e(document_kind_label((string) $document['kind'], (string) $document['ext'])) ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="choix-document__titre"><?= e((string) $document['title']) ?></span>
                            <span class="choix-document__meta">
                                <span class="discret"><?= e(trim((string) $document['doc_date']) !== ''
                                    ? (string) $document['doc_date']
                                    : format_date((int) $document['created_at'])) ?></span>
                                <span class="discret"><?= e(document_kind_label((string) $document['kind'], (string) $document['ext'])) ?></span>
                                <span class="<?= e(visibility_class((string) $document['visibility'])) ?>"><?= e(visibility_short((string) $document['visibility'])) ?></span>
                            </span>
                            <?php if ($dedansAlbum): ?>
                                <span class="etiquette">Déjà dans l’album</span>
                            <?php endif; ?>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>

            <p class="actions">
                <button type="submit" class="bouton">Ajouter la sélection à l’album</button>
                <button type="submit" class="bouton bouton--secondaire" name="tout" value="1">
                    Tout ajouter sur cette page
                </button>
                <button type="button" class="lien-bouton" data-tout-cocher="selection-documents">
                    Tout cocher sur cette page
                </button>
            </p>
            <p class="discret">
                « Tout cocher » coche les cases de cette page (il faut JavaScript) ; « Tout ajouter
                sur cette page » fait le même travail sans JavaScript, côté serveur.
            </p>
        </form>

        <?php require __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</section>

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
