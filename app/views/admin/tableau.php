<?php
/**
 * Tableau de bord d'administration.
 *
 * @var array $stats, $documents, $activity
 */
?>
<div class="section__entete">
    <h1>Tableau de bord</h1>
    <span class="actions">
        <a class="bouton" href="/admin/documents/nouveau">Ajouter un document</a>
        <a class="bouton bouton--secondaire" href="/admin/pages/nouvelle">Nouvelle page</a>
    </span>
</div>

<?php require __DIR__ . '/../partials/alertes_connexion.php'; ?>

<?php
// Compteur « À valider » : ce que les journalistes ont proposé et qui attend
// une décision. Un défaut de lecture ne doit pas casser le tableau de bord.
try {
    $aValider = propositions_a_valider();
} catch (Throwable) {
    $aValider = 0;
}
?>
<?php if ($aValider > 0): ?>
    <div class="message message--info">
        <strong><?= $aValider ?></strong> proposition<?= $aValider > 1 ? 's' : '' ?>
        attend<?= $aValider > 1 ? 'ent' : '' ?> une décision de votre part.
        <a href="/admin/a-valider">Examiner les propositions</a>
    </div>
<?php endif; ?>

<?php /* 1.38.0 : pas d'encadré « sauvegarde » ici. Le bandeau de
         sauvegarde_alerte() (mise en page d'administration) s'affiche déjà
         en haut de toutes les pages de l'administration, avec le lien vers
         « État du site » : le répéter sur le tableau de bord disait deux
         fois la même chose. */ ?>

<?php /* Une seule carte : le contenu du site, et la part publique. Les deux
         anciennes cartes « Partie publique » / « Partie famille » obligeaient à
         additionner deux listes pour répondre à « combien ai-je de documents ? ».
         Les raccourcis mènent aux listes, filtre « Visibilité » déjà posé. */ ?>
<section class="panneau">
    <div class="section__entete">
        <h2>Contenus</h2>
    </div>

    <ul class="chiffres">
        <li><strong><?= (int) $stats['documents'] ?></strong> document<?= (int) $stats['documents'] > 1 ? 's' : '' ?></li>
        <li><strong><?= (int) $stats['articles'] ?></strong> article<?= (int) $stats['articles'] > 1 ? 's' : '' ?></li>
        <li><strong><?= (int) $stats['pages'] ?></strong> page<?= (int) $stats['pages'] > 1 ? 's' : '' ?> d’information</li>
        <li><strong><?= (int) $stats['videos'] ?></strong> vidéo<?= (int) $stats['videos'] > 1 ? 's' : '' ?></li>
        <li><strong><?= (int) $stats['persons'] ?></strong> personne<?= (int) $stats['persons'] > 1 ? 's' : '' ?></li>
        <li>
            <strong><?= (int) $stats['accounts'] ?></strong> compte<?= (int) $stats['accounts'] > 1 ? 's' : '' ?>
            <span class="discret">— <?= (int) $stats['branches_active'] ?> branche<?= (int) $stats['branches_active'] > 1 ? 's' : '' ?> active<?= (int) $stats['branches_active'] > 1 ? 's' : '' ?> sur <?= (int) $stats['branches'] ?></span>
        </li>
        <li>
            <a href="/admin/a-valider"><strong><?= (int) $stats['propositions'] ?></strong> proposition<?= (int) $stats['propositions'] > 1 ? 's' : '' ?> à valider</a>
        </li>
        <?php /* La part publique, en une ligne : ce qu'un visiteur voit sans
                 compte. Les personnes n'y sont pas : l'arbre est fermé au
                 visiteur, même pour une fiche marquée « Public ». */ ?>
        <li class="chiffres__dont">
            dont publics : <strong><?= (int) $stats['public_total'] ?></strong>
            <span class="discret">— documents, articles publiés et pages publiées</span>
        </li>
    </ul>

    <p class="actions">
        <a class="bouton bouton--secondaire" href="/admin/documents?visibilite=public">Documents publics</a>
        <a class="bouton bouton--secondaire" href="/admin/articles?visibilite=public">Articles publics</a>
        <a class="bouton bouton--secondaire" href="/admin/pages?visibilite=public">Pages publiques</a>
        <a class="lien" href="/" target="_blank" rel="noopener">Voir le site public</a>
    </p>
</section>

<?php /* Dossier arrivée : seulement s'il y a quelque chose à ranger. */ ?>
<?php if ((int) $stats['incoming'] > 0): ?>
    <div class="message message--info">
        <strong><?= (int) $stats['incoming'] ?></strong> fichier<?= (int) $stats['incoming'] > 1 ? 's' : '' ?>
        attend<?= (int) $stats['incoming'] > 1 ? 'ent' : '' ?> dans le dossier arrivée.
        <a href="/admin/importer">Ouvrir le dossier arrivée</a>
    </div>
<?php endif; ?>

<section class="section">
    <div class="section__entete">
        <h2>Derniers documents ajoutés</h2>
        <a href="/admin/documents">Tous les documents →</a>
    </div>

    <?php if ($documents === []): ?>
        <p class="discret">Aucun document pour le moment.</p>
    <?php else: ?>
        <table class="tableau">
            <thead>
                <tr>
                    <th scope="col">Titre</th>
                    <th scope="col">Type</th>
                    <th scope="col">Visibilité</th>
                    <th scope="col">Ajouté le</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td><?= e($doc['title']) ?></td>
                        <td><?= e(document_kind_label((string) $doc['kind'], (string) $doc['ext'])) ?></td>
                        <td><span class="<?= e(visibility_class((string) $doc['visibility'])) ?>">
                            <?= e(visibility_short((string) $doc['visibility'])) ?></span></td>
                        <td><?= e(format_date((int) $doc['created_at'])) ?></td>
                        <td class="colonne-actions">
                            <a href="/documents/<?= (int) $doc['id'] ?>">Voir</a>
                            <a href="/admin/documents/<?= (int) $doc['id'] ?>/modifier">Modifier</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="section">
    <h2>Espace disque et journal</h2>

    <?php /* L'alerte d'espace disque, visible dès le tableau de bord : orange
             sous 20 %, rouge sous 10 % ou sous 5 Go. Aucune classe renommée :
             on réutilise les deux boîtes de message existantes. */ ?>
    <?php $espace = $stats['storage_state']; ?>
    <?php if ($espace['niveau'] === 'rouge'): ?>
        <div class="message message--erreur" role="alert">
            <strong>Espace disque presque plein.</strong>
            <?= e($espace['message']) ?>. Libérez de la place (vidéos, anciennes
            sauvegardes) avant que le site ne puisse plus enregistrer.
        </div>
    <?php elseif ($espace['niveau'] === 'orange'): ?>
        <div class="message message--info">
            <strong>Espace disque qui baisse.</strong>
            <?= e($espace['message']) ?>. Pensez à faire de la place : au-delà,
            les envois et la conversion s’arrêteront.
        </div>
    <?php endif; ?>

    <p>
        Stockage : <code><?= e((string) config('storage.root')) ?></code> —
        <?= $stats['storage_free'] !== null ? e(format_size((int) $stats['storage_free'])) . ' libres' : 'espace inconnu' ?>
        <?php if ($espace['pourcentage'] !== null): ?>
            (<?= e(number_format((float) $espace['pourcentage'], 0, ',', ' ')) ?> % de la carte)
        <?php endif; ?>
    </p>

    <?php if ($activity === []): ?>
        <p class="discret">Aucune action enregistrée.</p>
    <?php else: ?>
        <ul class="journal">
            <?php foreach ($activity as $entry): ?>
                <li>
                    <span class="discret"><?= e(format_date((int) $entry['created_at'], true)) ?></span>
                    <strong><?= e((string) ($entry['username'] ?? 'système')) ?></strong>
                    <?= e((string) $entry['action']) ?>
                    <?php if (trim((string) $entry['details']) !== ''): ?>
                        — <?= e((string) $entry['details']) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
