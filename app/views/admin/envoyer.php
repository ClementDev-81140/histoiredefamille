<?php
/**
 * Envoyer des fichiers (page unique de l'administration).
 *
 * @var array $reglages, $recents, $prets, $erreurs
 * @var int   $enCours, $maxBytes
 * @var int|null $libre
 */
$reprendre = null;
$jeton = query('reprendre');
if ($jeton !== '') {
    $candidat = envoi_par_token($jeton);
    if ($candidat && envoi_appartient($candidat, (int) current_account()['id']) && envoi_reprisable($candidat)) {
        $reprendre = $candidat;
    }
}
?>
<div class="section__entete">
    <h1>Envoyer des fichiers</h1>
    <span class="actions">
        <a class="bouton bouton--secondaire" href="/admin/envois">Mes envois</a>
        <a class="bouton bouton--secondaire" href="/admin/importer">Dossier arrivée</a>
    </span>
</div>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul>
            <?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<p class="note">
    Si la connexion tombe, redéposez le même fichier : l’envoi reprend où il en était.
</p>

<div class="ligne-champs envoi-etat">
    <p><strong><?= (int) $enCours ?></strong> envoi(s) en cours sur
        <strong><?= (int) $reglages['simultanes'] ?></strong> possibles.</p>
    <?php if ($libre !== null): ?>
        <p class="discret">Espace libre sur le disque du site :
            <?= e(format_size($libre)) ?>.</p>
    <?php endif; ?>
</div>

<?php
$envoiOptions = [
    'creer'     => true,
    'titre'     => 'Déposer des fichiers',
    'aide'      => 'Chaque fichier arrive en privé : personne ne le voit tant que vous ne l’avez pas complété.',
    'reprendre' => $reprendre,
];
require __DIR__ . '/partials/envoi.php';
?>

<?php if ($prets !== []): ?>
    <section class="section">
        <div class="section__entete">
            <h2>Fichiers reçus, en attente de formulaire</h2>
        </div>
        <p class="aide">
            Ces fichiers sont arrivés entiers : ils ont été envoyés depuis un formulaire
            (document ou vidéo) qui n’a pas été validé. Ils restent privés et invisibles
            pour la famille. <strong>« Créer le document »</strong> en fait tout de suite
            une fiche privée, que vous compléterez ensuite à votre rythme.
            <strong>« Supprimer »</strong> efface le fichier et les morceaux reçus.
            Chaque fichier ne peut devenir un document qu’une seule fois, et vous seul
            pouvez agir sur les fichiers que vous avez envoyés.
        </p>
        <div class="tableau-defilant">
            <table class="tableau">
                <thead>
                    <tr>
                        <th scope="col">Fichier</th>
                        <th scope="col">Taille</th>
                        <th scope="col">Reçu le</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prets as $pret): ?>
                        <?php $nom = (string) $pret['original_name']; ?>
                        <tr>
                            <td><?= e($nom) ?></td>
                            <td><?= e(format_size((int) $pret['size'])) ?></td>
                            <td><?= e(format_date((int) $pret['updated_at'], true)) ?></td>
                            <td class="colonne-actions">
                                <?php $categoriesPret = categories_pour_kinds([(string) $pret['kind']]); ?>
                                <form method="post" action="/admin/envoyer/creer-document" class="formulaire-en-ligne">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="token" value="<?= e((string) $pret['token']) ?>">
                                    <?php if ($categoriesPret !== []): ?>
                                        <fieldset class="cases-categories">
                                            <legend>Catégories</legend>
                                            <?php foreach ($categoriesPret as $categoriePret): ?>
                                                <label class="case">
                                                    <input type="checkbox" name="categories[]" value="<?= (int) $categoriePret['id'] ?>">
                                                    <?= e((string) $categoriePret['name']) ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </fieldset>
                                    <?php endif; ?>
                                    <button type="submit" class="lien-bouton">Créer le document</button>
                                </form>
                                <span aria-hidden="true"> · </span>
                                <form method="post" action="/admin/envoyer/supprimer" class="formulaire-en-ligne"
                                      data-confirmation="Supprimer définitivement « <?= e($nom) ?> » et ses morceaux reçus ? Cette action est irréversible.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="token" value="<?= e((string) $pret['token']) ?>">
                                    <button type="submit" class="lien-bouton lien-bouton--danger">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($recents !== []): ?>
    <section class="section">
        <div class="section__entete">
            <h2>Reçus depuis hier</h2>
            <a class="lien" href="/admin/envoyer/lot">Compléter un lot d’un coup</a>
        </div>
        <div class="tableau-defilant">
            <table class="tableau">
                <thead>
                    <tr>
                        <th scope="col">Fichier</th>
                        <th scope="col">Taille</th>
                        <th scope="col">Fiche créée</th>
                        <th scope="col">Visibilité</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recents as $envoi): ?>
                        <?php
                        $docId = (int) ($envoi['document_id'] ?? 0);
                        $doc   = $docId > 0 ? db_one('SELECT id, title, visibility FROM documents WHERE id = ?', [$docId]) : null;
                        ?>
                        <tr>
                            <td><?= e((string) $envoi['original_name']) ?></td>
                            <td><?= e(format_size((int) $envoi['size'])) ?></td>
                            <td>
                                <?php if ($doc): ?>
                                    <a href="/admin/documents/<?= (int) $doc['id'] ?>/modifier"><?= e((string) $doc['title']) ?></a>
                                <?php else: ?>
                                    <span class="discret">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($doc): ?>
                                    <span class="<?= e(visibility_class((string) $doc['visibility'])) ?>">
                                        <?= e(visibility_short((string) $doc['visibility'])) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<section class="panneau">
    <h2>Ce qu’il faut savoir</h2>
    <ul class="liste-simple">
        <li>Un fichier envoyé est <strong>toujours privé</strong> au départ : vous choisissez
            ensuite sa visibilité, sa catégorie et son album. « Créer le document » fait
            naître la fiche tout de suite, en privé ; elle reste invisible pour la famille
            jusqu’à ce que vous la complétiez et choisissiez sa visibilité.</li>
        <li>Si le même fichier existe déjà sur le site, un message vous le dit avant
            d’ajouter quoi que ce soit : vous pouvez le garder quand même ou l’ignorer.
            Rien n’est jamais supprimé automatiquement.</li>
        <li>Une vidéo dans un format que les navigateurs ne lisent pas partout (.mov,
            .avi, .mkv…) est acceptée : le site la <strong>convertit en MP4 (H.264 + AAC)</strong>
            en tâche de fond, une vidéo à la fois. Elle reste invisible à la famille tant
            que la conversion n’est pas finie. Un MP4 déjà prêt part en ligne tout de suite.</li>
        <li>Le dossier « arrivée » reste la solution de secours si l’envoi par le
            navigateur pose problème.</li>
    </ul>
</section>
