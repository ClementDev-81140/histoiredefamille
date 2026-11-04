<?php
/**
 * Liste des vidéos (administration).
 *
 * @var array  $videos, $pagination, $enAttente
 * @var string $q, $visibility
 * @var int    $total, $maxStreams
 */
$filtreActif = $q !== '' || $visibility !== '';
?>
<div class="section__entete">
    <h1>Vidéos</h1>
    <span class="actions">
        <a class="bouton" href="/admin/videos/nouvelle">Ajouter une vidéo</a>
        <a class="bouton bouton--secondaire" href="/videos">Voir l’onglet public</a>
    </span>
</div>

<p class="note">
    <?= (int) $total ?> vidéo<?= (int) $total > 1 ? 's' : '' ?> enregistrée<?= (int) $total > 1 ? 's' : '' ?>.
    Le Raspberry Pi ne diffuse pas plus de <strong><?= (int) $maxStreams ?></strong> vidéos
    en même temps ; au-delà, les visiteurs voient un message d’attente, pas une erreur.
    Un fichier qui n’est pas en MP4 (H.264 + AAC) est converti en arrière-plan
    (<a href="/admin/conversions">Conversions</a>) : la famille ne le voit qu’une
    fois le travail terminé.
</p>

<form method="get" action="/admin/videos" class="filtres">
    <div class="champ">
        <label for="q">Recherche</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>">
    </div>
    <div class="champ">
        <label for="visibilite">Visibilité</label>
        <select id="visibilite" name="visibilite">
            <option value="">Toutes</option>
            <?php foreach (visibility_levels() as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= selected($visibility === $value) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="champ champ--bouton">
        <button type="submit" class="bouton">Filtrer</button>
        <?php if ($filtreActif): ?>
            <a class="lien" href="/admin/videos">Réinitialiser</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($videos === []): ?>
    <div class="panneau panneau--centre">
        <p><?= $filtreActif ? 'Aucune vidéo ne correspond à ce filtre.' : 'Aucune vidéo pour le moment.' ?></p>
        <p><a class="bouton" href="/admin/videos/nouvelle">Ajouter une première vidéo</a></p>
    </div>
<?php else: ?>
    <div class="tableau-defilant">
        <table class="tableau">
            <thead>
                <tr>
                    <th scope="col">Vidéo</th>
                    <th scope="col">Date</th>
                    <th scope="col">Visibilité</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($videos as $video): ?>
                    <?php
                    $enAvant = (int) $video['is_featured'] === 1;
                    $duree   = video_duration_short(isset($video['duration_seconds']) ? (int) $video['duration_seconds'] : null);
                    ?>
                    <tr>
                        <td>
                            <a href="/videos/<?= (int) $video['id'] ?>"><?= e($video['title']) ?></a>
                            <?php if ($enAvant): ?>
                                <span class="badge badge-branches">en avant</span>
                            <?php endif; ?>
                            <?php $conv = conversion_etat_document((int) $video['id']); ?>
                            <?php if (in_array($conv['state'], ['attente', 'en_cours', 'echec'], true)): ?>
                                <span class="badge badge-<?= $conv['state'] === 'echec' ? 'prive' : 'branches' ?>">
                                    <?= e($conv['state'] === 'en_cours' ? 'conversion ' . (int) $conv['progress'] . ' %' : conversion_libelle_etat($conv['state'])) ?>
                                </span>
                            <?php endif; ?>
                            <span class="discret discret--bloc">
                                <?php if ((int) $video['display_order'] > 0): ?>
                                    ordre <?= (int) $video['display_order'] ?> ·
                                <?php endif; ?>
                                <?php if ($duree !== ''): ?><?= e($duree) ?> · <?php endif; ?>
                                <?= (int) $video['allow_download'] === 1 ? 'téléchargement autorisé' : 'lecture seule' ?>
                            </span>
                        </td>
                        <td><?= e(trim((string) $video['doc_date']) !== '' ? (string) $video['doc_date'] : format_date((int) $video['created_at'])) ?></td>
                        <td><span class="<?= e(visibility_class((string) $video['visibility'])) ?>">
                            <?= e(visibility_short((string) $video['visibility'])) ?></span></td>
                        <td class="colonne-actions">
                            <form method="post" action="/admin/videos/<?= (int) $video['id'] ?>/mise-en-avant"
                                  class="formulaire-en-ligne">
                                <?= csrf_field() ?>
                                <button type="submit" class="lien-bouton"><?= $enAvant ? 'Retirer' : 'Mettre en avant' ?></button>
                            </form>
                            <span aria-hidden="true"> · </span>
                            <a href="/admin/videos/<?= (int) $video['id'] ?>">Modifier</a>
                            <span aria-hidden="true"> · </span>
                            <form method="post" action="/admin/videos/<?= (int) $video['id'] ?>/supprimer"
                                  class="formulaire-en-ligne"
                                  data-confirmation="Supprimer définitivement cette vidéo et son fichier ?">
                                <?= csrf_field() ?>
                                <button type="submit" class="lien-bouton lien-bouton--danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php require __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>

<section class="section">
    <div class="section__entete">
        <h2>Depuis le dossier arrivée</h2>
        <a class="lien" href="/admin/importer">Tous les fichiers en attente</a>
    </div>

    <?php if ($enAttente === []): ?>
        <p class="discret">
            Aucun fichier vidéo en attente. Déposez vos films (MP4) dans le dossier
            « arrivée » du Pi par SFTP ou partage réseau, ils apparaîtront ici.
        </p>
    <?php else: ?>
        <p class="aide">
            Les fichiers sont déplacés dans le stockage au moment de l’import
            (aucune copie en mémoire) : un film de plusieurs centaines de Mo passe
            sans problème.
        </p>

        <?php foreach ($enAttente as $fichier): ?>
            <form method="post" action="/admin/videos/importer" class="formulaire panneau">
                <?= csrf_field() ?>
                <input type="hidden" name="fichier" value="<?= e($fichier['name']) ?>">

                <p class="note">
                    <strong><?= e($fichier['name']) ?></strong>
                    <span class="discret">— <?= e(format_size((int) $fichier['size'])) ?>,
                    déposé le <?= e(format_date((int) $fichier['modified'], true)) ?></span>
                </p>

                <div class="ligne-champs">
                    <div class="champ">
                        <label for="title-<?= e($fichier['name']) ?>">Titre</label>
                        <input type="text" id="title-<?= e($fichier['name']) ?>" name="title"
                               maxlength="200" value="<?= e(pathinfo($fichier['name'], PATHINFO_FILENAME)) ?>">
                    </div>
                    <div class="champ">
                        <label for="duration-<?= e($fichier['name']) ?>">Durée (facultatif)</label>
                        <input type="text" id="duration-<?= e($fichier['name']) ?>" name="duration"
                               maxlength="20" placeholder="ex. 12:30">
                    </div>
                </div>

                <fieldset class="champ">
                    <legend>Visibilité</legend>
                    <?php foreach (visibility_levels() as $value => $label): ?>
                        <label class="radio">
                            <input type="radio" name="visibility" value="<?= e($value) ?>"
                                <?= checked($value === 'prive') ?>>
                            <?= e($label) ?>
                        </label>
                    <?php endforeach; ?>
                    <div class="branches" data-bloc-branches hidden>
                        <p class="aide">Cochez les branches autorisées.</p>
                        <?php foreach (branches_list(true) as $branch): ?>
                            <label class="case">
                                <input type="checkbox" name="branches[]" value="<?= (int) $branch['id'] ?>">
                                <?= e($branch['name']) ?>
                                <span class="discret">(<?= e($branch['code']) ?>)</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <p class="actions">
                    <button type="submit" class="bouton">Importer cette vidéo</button>
                </p>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
