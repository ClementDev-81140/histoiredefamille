<?php
/**
 * Page « Conversions » : la file d'attente des vidéos.
 *
 * @var array $lignes, $pagination, $resume, $reglages
 * @var int   $total, $aPlanifier
 * @var string $filtre
 * @var array|null $derniere
 */
$filtreActif = $filtre !== '';
$derniereDate = isset($derniere['t']) && (int) $derniere['t'] > 0
    ? format_date((int) $derniere['t'], true)
    : '';
?>
<div class="section__entete">
    <h1>Conversions</h1>
    <span class="actions">
        <a class="bouton bouton--secondaire" href="/admin/videos">Vidéos</a>
        <a class="bouton bouton--secondaire" href="/admin/etat">État du site</a>
    </span>
</div>

<p class="note">
    Une conversion à la fois, en arrière-plan.
</p>

<?php if (!$reglages['active']): ?>
    <div class="message message--attention">
        <strong>La conversion est désactivée.</strong>
        Les vidéos envoyées restent telles quelles : elles ne sont lisibles que si
        le navigateur connaît déjà leur format. Le réglage se trouve dans
        <a href="/admin/reglages">Réglages</a>.
    </div>
<?php elseif (!$reglages['disponible']): ?>
    <div class="message message--erreur">
        <strong>ffmpeg est introuvable sur ce serveur.</strong>
        La file d’attente fonctionne, mais rien ne sera converti tant que le
        programme n’est pas installé. Sur le Raspberry Pi :
        <code>sudo apt-get install -y ffmpeg</code>, puis
        <code>sudo bash deploy/install.sh</code>.
    </div>
<?php endif; ?>

<?php if ($outils['ffmpeg'] === null): ?>
    <div class="message message--attention">
        <strong>Les vidéos n’ont pas d’image d’aperçu.</strong>
        Le programme <code>ffmpeg</code> n’est pas installé sur ce serveur : le site ne peut pas
        extraire une image des vidéos. En attendant, chaque vidéo affiche une icône de lecture
        sur le papier (jamais un carré gris). Sur le Raspberry Pi :
        <code>sudo apt-get install -y ffmpeg</code>, puis
        <code>php app/cli/miniatures-videos.php --executer</code> pour rattraper les vidéos
        existantes, une par une et sans ralentir le site.
        <?php if ((int) $sansImage > 0): ?>
            <br>Vidéos sans image pour l’instant : <strong><?= (int) $sansImage ?></strong>.
        <?php endif; ?>
    </div>
<?php elseif ((int) $sansImage > 0): ?>
    <p class="note">
        <strong><?= (int) $sansImage ?></strong> vidéo(s) prête(s) n’ont pas encore d’image
        d’aperçu. La commande <code>php app/cli/miniatures-videos.php --executer</code> les crée
        une par une (elle est relançable sans risque).
    </p>
<?php endif; ?>

<div class="ligne-champs envoi-etat">
    <p><strong><?= (int) $resume['a_faire'] ?></strong> fichier(s) à convertir
        (<?= (int) $resume['attente'] ?> en attente, <?= (int) $resume['en_cours'] ?> en cours).</p>
    <p class="discret">
        <?= (int) $resume['terminee'] ?> terminée(s), <?= (int) $resume['echec'] ?> en échec,
        <?= (int) $resume['ignoree'] ?> annulée(s).
        <?php if ($derniereDate !== ''): ?>Dernière réussite : <?= e($derniereDate) ?>.<?php endif; ?>
    </p>
</div>

<?php if ($aPlanifier > 0): ?>
    <form method="post" action="/admin/conversions/planifier" class="formulaire-en-ligne">
        <?= csrf_field() ?>
        <p class="note">
            <strong><?= (int) $aPlanifier ?></strong> vidéo(s) de ce site ne sont pas au
            format MP4 et ne sont pas encore dans la file.
            <button type="submit" class="bouton">Les ajouter à la file</button>
        </p>
    </form>
<?php endif; ?>

<form method="get" action="/admin/conversions" class="filtres">
    <div class="champ">
        <label for="etat">État</label>
        <select id="etat" name="etat">
            <option value="">Tous</option>
            <?php foreach (conversion_etats() as $etat): ?>
                <option value="<?= e($etat) ?>"<?= selected($filtre === $etat) ?>>
                    <?= e(conversion_libelle_etat($etat)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="champ champ--bouton">
        <button type="submit" class="bouton">Filtrer</button>
        <?php if ($filtreActif): ?>
            <a class="lien" href="/admin/conversions">Réinitialiser</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($lignes === []): ?>
    <div class="panneau panneau--centre">
        <p><?= $filtreActif ? 'Aucune conversion ne correspond à ce filtre.' : 'Aucune conversion dans la file.' ?></p>
        <p class="discret">
            Une vidéo entre dans la file toute seule quand son format n’est pas
            lisible par les navigateurs (MOV, AVI, MKV, WebM, HEVC…).
        </p>
    </div>
<?php else: ?>
    <div class="tableau-defilant">
        <table class="tableau">
            <thead>
                <tr>
                    <th scope="col">Vidéo</th>
                    <th scope="col">État</th>
                    <th scope="col">Fichier d’origine</th>
                    <th scope="col">Depuis</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lignes as $ligne): ?>
                    <?php
                    $etat     = (string) $ligne['state'];
                    $enCours  = $etat === 'en_cours';
                    $terminee = $etat === 'terminee';
                    ?>
                    <tr>
                        <td>
                            <a href="/videos/<?= (int) $ligne['document_id'] ?>"><?= e((string) $ligne['title']) ?></a>
                            <?php if ((int) ($ligne['doc_duration'] ?? 0) > 0): ?>
                                <span class="discret discret--bloc"><?= e(conversion_duree_lisible((int) $ligne['doc_duration'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $etat === 'terminee' ? 'public' : ($etat === 'echec' ? 'prive' : 'branches') ?>">
                                <?= e(conversion_libelle_etat($etat)) ?>
                            </span>
                            <?php if ($enCours): ?>
                                <progress max="100" value="<?= (int) $ligne['progress'] ?>">
                                    <?= (int) $ligne['progress'] ?> %
                                </progress>
                                <span class="discret"><?= (int) $ligne['progress'] ?> %</span>
                            <?php endif; ?>
                            <?php if ((string) $ligne['message'] !== ''): ?>
                                <span class="discret discret--bloc"><?= e((string) $ligne['message']) ?></span>
                            <?php endif; ?>
                            <?php if ((int) $ligne['attempts'] > 0 && !$terminee): ?>
                                <span class="discret discret--bloc"><?= (int) $ligne['attempts'] ?> essai(s)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e((string) $ligne['original_name']) ?>
                            <span class="discret discret--bloc">
                                <?= e(format_size((int) $ligne['source_size'])) ?>
                                <?php if ($terminee && (string) ($ligne['target_path'] ?? '') !== ''): ?>
                                    → <?= e(format_size((int) @filesize(storage_absolute((string) $ligne['target_path'])))) ?>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td><?= e(format_date((int) $ligne['updated_at'], true)) ?></td>
                        <td class="colonne-actions">
                            <?php if (!$enCours && !$terminee): ?>
                                <form method="post" action="/admin/conversions/<?= (int) $ligne['id'] ?>/relancer"
                                      class="formulaire-en-ligne">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="lien-bouton">Relancer</button>
                                </form>
                                <span aria-hidden="true"> · </span>
                            <?php endif; ?>
                            <?php if (!$terminee): ?>
                                <form method="post" action="/admin/conversions/<?= (int) $ligne['id'] ?>/annuler"
                                      class="formulaire-en-ligne"
                                      data-confirmation="Annuler cette conversion ? La vidéo restera dans son format d’origine.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="lien-bouton lien-bouton--danger">Annuler</button>
                                </form>
                            <?php else: ?>
                                <span class="discret">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php require __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>

<section class="panneau">
    <h2>Ce qu’il faut savoir</h2>
    <ul class="liste-simple">
        <li><strong>Un fichier à la fois</strong> : la conversion est faite par la commande
            <code>php app/cli/convertir.php</code>, lancée par un minuteur systemd toutes
            les 10 minutes. Une seule conversion tourne à la fois : le site reste
            utilisable pendant ce temps.</li>
        <li><strong>Rien n’est jamais perdu</strong> : le fichier d’origine n’est effacé
            qu’après une conversion réussie <em>et</em> seulement si le réglage
            « garder les originaux » est désactivé — et jamais avant sept jours.</li>
        <li><strong>La vidéo attend</strong> : tant qu’une conversion n’est pas terminée,
            la famille voit « préparation en cours » à la place du lecteur. Vous, vous
            pouvez toujours la prévisualiser.</li>
        <li><strong>Qualité</strong> : <?= e((string) $reglages['qualite']) ?> —
            H.264, CRF <?= (int) $reglages['crf'] ?>,
            <?= (int) $reglages['hauteur_max'] > 0 ? 'hauteur limitée à ' . (int) $reglages['hauteur_max'] . ' px' : 'hauteur d’origine' ?>,
            audio AAC <?= (int) $reglages['audio_kbps'] ?> kb/s.
            Réglable dans <a href="/admin/reglages">Réglages</a>.</li>
        <li><strong>En cas d’échec</strong> : trois essais, puis le fichier d’origine
            reste servi tel quel. « Relancer » remet le travail dans la file.</li>
        <li>Les conversions terminées depuis plus de 90 jours sont effacées de cette
            liste (les fichiers, eux, restent dans les fiches).</li>
    </ul>
</section>
