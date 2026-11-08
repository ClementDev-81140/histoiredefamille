<?php
/**
 * Envoyer un ou plusieurs fichiers par morceaux (composant réutilisable).
 *
 * Utilisé par la page « Envoyer des fichiers » (plusieurs fichiers, lot) et
 * par les formulaires existants (documents, vidéos) : un seul code, une seule
 * page de script (public/assets/envoi.js).
 *
 * @var array $envoiOptions {
 *     creer:  bool   créer la fiche tout de suite (true) ou la laisser au
 *                    formulaire qui entoure le composant (false) ;
 *     titre:  string titre du bloc ;
 *     aide:   string phrase d'explication ;
 *     reprendre: array|null envoi à reprendre (nom, taille, token) ;
 *     base:   string préfixe des adresses du protocole (/admin/envoyer par
 *             défaut ; /propositions/fichier pour un journaliste) ;
 *     album:  int   album de destination (les photos y entrent tout de suite) ;
 *     versAlbum: string adresse publique de l'album (« Voir l'album ») ;
 *     types:  string[] extensions acceptées (par défaut, toutes celles du site).
 * }
 */
$envoiOptions = $envoiOptions ?? [];
$creerFiche   = (bool) ($envoiOptions['creer'] ?? true);
$titreBloc    = (string) ($envoiOptions['titre'] ?? 'Envoyer des fichiers');
$aideBloc     = (string) ($envoiOptions['aide'] ?? '');
$aReprendre   = $envoiOptions['reprendre'] ?? null;
$baseEnvoi    = rtrim((string) ($envoiOptions['base'] ?? '/admin/envoyer'), '/');
$afficherLot  = (bool) ($envoiOptions['lot'] ?? true);
$albumBloc    = (int) ($envoiOptions['album'] ?? 0);
$versAlbum    = (string) ($envoiOptions['versAlbum'] ?? '');
$archiveBloc  = (bool) ($envoiOptions['archive'] ?? false);
$typesBloc    = array_values(array_filter(array_map('strval', (array) ($envoiOptions['types'] ?? []))));
if ($typesBloc === []) {
    $typesBloc = allowed_extensions();
}
$acceptBloc   = '.' . implode(',.', $typesBloc);
$reglages     = envoi_reglages();
?>
<section class="envoi" data-envoi
         data-envoi-init="<?= e($baseEnvoi) ?>/init"
         data-envoi-morceau="<?= e($baseEnvoi) ?>/morceau"
         data-envoi-etat="<?= e($baseEnvoi) ?>/etat"
         data-envoi-terminer="<?= e($baseEnvoi) ?>/terminer"
         data-envoi-decision="<?= e($baseEnvoi) ?>/decision"
         data-envoi-annuler="<?= e($baseEnvoi) ?>/annuler"
         data-envoi-lot="<?= e($baseEnvoi) ?>/lot"
         data-envoi-csrf="<?= e(csrf_token()) ?>"
         data-envoi-creer="<?= $creerFiche ? '1' : '0' ?>"
         data-envoi-morceau-octets="<?= (int) $reglages['morceau_octets'] ?>"
         data-envoi-max-octets="<?= (int) $reglages['max_octets'] ?>"
         data-envoi-accept="<?= e($acceptBloc) ?>"
         <?php if ($albumBloc > 0): ?>
         data-envoi-album="<?= (int) $albumBloc ?>"
         data-envoi-vers-album="<?= e($versAlbum) ?>"
         <?php endif; ?>
         <?php if (is_array($aReprendre)): ?>
         data-envoi-reprendre="<?= e((string) $aReprendre['token']) ?>"
         data-envoi-reprendre-nom="<?= e((string) $aReprendre['original_name']) ?>"
         data-envoi-reprendre-taille="<?= (int) $aReprendre['size'] ?>"
         <?php endif; ?>>

    <h2 class="envoi__titre"><?= e($titreBloc) ?></h2>

    <p class="aide envoi__aide">
        <?php if ($aideBloc !== ''): ?><?= e($aideBloc) ?><?php endif; ?>
        Morceaux de <?= e(format_size($reglages['morceau_octets'])) ?>,
        <?= e(format_size($reglages['max_octets'])) ?> au maximum par fichier.
        L’envoi reprend tout seul après une coupure.
    </p>

    <?php if (is_array($aReprendre)): ?>
        <div class="message message--info" role="status">
            Reprise de <strong><?= e((string) $aReprendre['original_name']) ?></strong>
            (<?= e(format_size((int) $aReprendre['size'])) ?>) :
            déposez à nouveau ce même fichier, l’envoi continuera où il s’est arrêté.
        </div>
    <?php endif; ?>

    <?php if ($archiveBloc): ?>
        <div class="champ">
            <label class="case">
                <input type="checkbox" data-envoi-archive>
                Classer en archive
            </label>
            <p class="aide"><?= e('Un document d’archive n’apparaît pas à la une et ne prévient personne. On le retrouve dans les dossiers d’archives et par la recherche.') ?></p>
        </div>
    <?php endif; ?>

    <div class="envoi__zone" data-envoi-zone>
        <p class="envoi__consigne">
            Glissez vos fichiers ici, ou
        </p>
        <p>
            <label class="bouton" for="envoi-fichiers">
                Choisir des fichiers
                <input type="file" id="envoi-fichiers" class="sr-only"
                       data-envoi-champ multiple
                       accept="<?= e($acceptBloc) ?>">
            </label>
        </p>
        <p class="discret">
            <?php if ($albumBloc > 0): ?>
                Des photos : <?= e(implode(', ', $typesBloc)) ?>. Plusieurs à la fois,
                chacune avec sa propre barre de progression.
            <?php else: ?>
                Photos, PDF, vidéos, sons. Plusieurs fichiers à la fois : chacun a sa
                propre barre de progression.
            <?php endif; ?>
            <?php if (!$creerFiche): ?>
                Une fois l’envoi terminé, un lien vous amène au bouton
                d’enregistrement, tout en bas du formulaire.
            <?php endif; ?>
        </p>
    </div>

    <noscript>
        <div class="message message--attention">
            <strong>JavaScript est désactivé.</strong>
            L’envoi par morceaux en a besoin pour découper les gros fichiers.
            L’envoi simple reste possible, jusqu’à
            <?= e(format_size(upload_max_bytes())) ?> par fichier :
            <?php if ($creerFiche): ?>
                <a href="/admin/documents/nouveau">ajouter un document</a> ou
                <a href="/admin/videos/nouvelle">ajouter une vidéo</a>.
            <?php else: ?>
                utilisez le champ « Fichier » du formulaire ci-dessous.
            <?php endif; ?>
        </div>
    </noscript>

    <p class="sr-only" role="status" aria-live="polite" data-envoi-annonce></p>

    <ul class="envoi__liste" data-envoi-liste hidden></ul>

    <p class="envoi__actions" data-envoi-actions hidden>
        <button type="button" class="bouton bouton--secondaire" data-envoi-tout-pause>
            Tout mettre en pause
        </button>
        <?php if ($afficherLot): ?>
        <a class="bouton bouton--secondaire" href="/admin/envoyer/lot" data-envoi-vers-lot hidden>
            Compléter ce lot d’un coup
        </a>
        <?php endif; ?>
        <?php if ($albumBloc > 0 && $versAlbum !== ''): ?>
        <a class="bouton bouton--secondaire" href="<?= e($versAlbum) ?>" data-envoi-vers-album-lien hidden>
            Voir l’album
        </a>
        <?php endif; ?>
    </p>
</section>
