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
    Cette page envoie les <strong>gros fichiers</strong> (vidéos, scans, lots de photos)
    depuis n’importe où, sans rien installer : le navigateur découpe le fichier en
    morceaux et les envoie un par un. Si la connexion tombe, l’envoi reprend où il
    en était — même après avoir fermé la page, en redéposant le même fichier.
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
            Ces fichiers ont été envoyés depuis un formulaire (document ou vidéo) qui
            n’a pas été validé. Ils restent privés et invisibles ; complétez leur fiche
            depuis la liste ci-dessous.
        </p>
        <div class="tableau-defilant">
            <table class="tableau">
                <thead>
                    <tr>
                        <th scope="col">Fichier</th>
                        <th scope="col">Taille</th>
                        <th scope="col">Reçu le</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prets as $pret): ?>
                        <tr>
                            <td><?= e((string) $pret['original_name']) ?></td>
                            <td><?= e(format_size((int) $pret['size'])) ?></td>
                            <td><?= e(format_date((int) $pret['updated_at'], true)) ?></td>
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
            ensuite sa visibilité, sa catégorie et son album.</li>
        <li>Si le même fichier existe déjà sur le site, un message vous le dit avant
            d’ajouter quoi que ce soit : vous pouvez le garder quand même ou l’ignorer.
            Rien n’est jamais supprimé automatiquement.</li>
        <li>Une vidéo envoyée reste telle quelle : le site ne la reconvertit pas. Le
            <strong>MP4 (H.264 + AAC)</strong> est le seul format que tous les navigateurs
            savent lire — choisissez-le quand c’est possible.</li>
        <li>Le dossier « arrivée » reste la solution de secours si l’envoi par le
            navigateur pose problème.</li>
    </ul>
</section>
