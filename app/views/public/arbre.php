<?php
/**
 * Arbre généalogique : le chêne gravé (1.33.0), puis le répertoire des
 * personnes.
 *
 * Le dessin — ancêtres dans les branches, personne (et conjoint) sur le tronc,
 * descendants dans les racines — est écrit ici, en HTML, par le serveur :
 * aucun JavaScript n'est nécessaire pour le voir, et jamais un style en ligne
 * (les largeurs de rangée sont des classes de la feuille de style).
 *
 * @var array       $persons, $pagination, $letters, $arbre, $choix
 * @var string      $q, $lettre
 * @var array|null  $root
 * @var bool        $tout
 * @var int         $total, $defautId
 */
$alphabet = range('A', 'Z');
?>
<section class="section arbre-chene" id="chene">
    <div class="section__entete">
        <h1 id="arbre-titre">Arbre généalogique</h1>
        <?php if (is_admin()): ?>
            <span class="actions">
                <a class="bouton" href="/admin/arbre/personne/nouvelle">Ajouter une personne</a>
                <a class="bouton bouton--secondaire" href="/admin/arbre/couples">Couples</a>
                <a class="bouton bouton--secondaire" href="/admin/arbre/import">Importer GEDCOM</a>
            </span>
        <?php endif; ?>
    </div>

    <?php if ($arbre === null): ?>
        <div class="panneau panneau--centre">
            <p>
                Aucune personne de l’arbre n’est encore visible avec votre compte.
                Les fiches apparaissent ici dès qu’une personne a été ajoutée et que
                vous avez le droit de la voir.
            </p>
        </div>
    <?php else: ?>
        <form method="get" action="/arbre" class="filtres arbre-chene__selecteur">
            <div class="champ">
                <label for="racine">Personne au centre de l’arbre</label>
                <input type="search" class="arbre-chene__filtre"
                       data-filtre-liste="racine"
                       placeholder="Filtrer la liste" aria-label="Filtrer la liste des personnes"
                       autocomplete="off">
                <select id="racine" name="racine">
                    <?php foreach ($choix['personnes'] as $personne): ?>
                        <option value="<?= (int) $personne['id'] ?>"
                            <?= (int) $personne['id'] === (int) $arbre['racine']['id'] ? 'selected' : '' ?>>
                            <?= e($personne['nom']) ?><?= $personne['dates'] !== '' ? ' (' . e($personne['dates']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="champ champ--bouton">
                <button type="submit" class="bouton">Voir cet arbre</button>
            </div>
        </form>
        <?php if ((int) $arbre['racine']['id'] !== (int) ($defautId ?? 0)): ?>
            <p class="arbre-chene__defaut">
                <a class="lien" href="/arbre#chene">Revenir à l’arbre par défaut</a>
            </p>
        <?php endif; ?>

        <?php if ($choix['tronquee']): ?>
            <p class="note">
                La liste propose les <?= ARBRE_CHENE_MAX_CHOIX ?> premières personnes.
                Pour en choisir une autre, servez-vous de la recherche ou du
                <a href="#liste-personnes">répertoire</a> ci-dessous.
            </p>
        <?php endif; ?>

        <figure class="arbre-chene__gravure">
            <picture>
                <source srcset="<?= e(asset_url('/assets/arbre-chene.webp')) ?>" type="image/webp">
                <img src="<?= e(asset_url('/assets/arbre-chene.jpg')) ?>"
                     alt="" width="860" height="1290" loading="lazy" decoding="async">
            </picture>
            <figcaption>
                Le chêne de la famille : les ancêtres dans les branches, la personne
                au centre sur le tronc, les descendants dans les racines.
            </figcaption>
        </figure>

        <div class="arbre-explo" data-arbre-exploration>
            <div class="arbre-explo__barre" role="toolbar" aria-label="Exploration de l’arbre"
                 data-explo-barre hidden>
                <button type="button" class="arbre-explo__bouton" data-explo="zoom-moins"
                        aria-label="Dézoomer" title="Dézoomer">−</button>
                <span class="arbre-explo__niveau" data-explo-niveau aria-live="polite">100 %</span>
                <button type="button" class="arbre-explo__bouton" data-explo="zoom-plus"
                        aria-label="Zoomer" title="Zoomer">+</button>
                <button type="button" class="arbre-explo__bouton" data-explo="gauche"
                        aria-label="Voir à gauche" title="Voir à gauche">←</button>
                <button type="button" class="arbre-explo__bouton" data-explo="haut"
                        aria-label="Voir en haut" title="Voir en haut">↑</button>
                <button type="button" class="arbre-explo__bouton" data-explo="bas"
                        aria-label="Voir en bas" title="Voir en bas">↓</button>
                <button type="button" class="arbre-explo__bouton" data-explo="droite"
                        aria-label="Voir à droite" title="Voir à droite">→</button>
                <button type="button" class="arbre-explo__bouton arbre-explo__bouton--texte"
                        data-explo="centrer"
                        aria-label="Centrer la personne sélectionnée"
                        title="Centrer la personne sélectionnée">Centrer</button>
                <button type="button" class="arbre-explo__bouton arbre-explo__bouton--texte"
                        data-explo="tout"
                        aria-label="Voir tout l’arbre" title="Voir tout l’arbre">Voir tout</button>
            </div>
            <p class="arbre-explo__aide" data-explo-aide hidden>
                Glissez pour déplacer l’arbre, molette ou pincement pour zoomer.
                Au clavier : Tab pour entrer dans le cadre et en sortir,
                flèches pour déplacer, + et − pour zoomer.
            </p>
            <div class="arbre-explo__cadre" data-explo-cadre tabindex="0" role="region"
                 aria-label="Cadre d’exploration de l’arbre. Tab pour en sortir, flèches pour déplacer l’arbre.">
        <div class="arbre-chene__dessin arbre-explo__dessin" data-explo-dessin>
            <section class="arbre-chene__partie arbre-chene__partie--branches" aria-labelledby="chene-ancetres">
                <h2 id="chene-ancetres">Les ancêtres</h2>
                <?php if ($arbre['ancetres'] === []): ?>
                    <p class="arbre-chene__vide">Aucun parent connu pour l’instant.</p>
                <?php else: ?>
                    <?php foreach ($arbre['ancetres'] as $generation): ?>
                        <?php $idLegende = 'chene-ascendants-' . (int) $generation['niveau']; ?>
                        <p class="arbre-chene__legende" id="<?= e($idLegende) ?>">
                            <?= e($generation['libelle']) ?>
                            <span class="arbre-chene__compte"><?= (int) $generation['total'] ?></span>
                        </p>
                        <?php foreach ($generation['rangees'] as $numeroRangee => $rangee): ?>
                            <ul class="arbre-chene__rangee arbre-chene__rangee--<?= count($rangee) ?>"
                                aria-labelledby="<?= e($idLegende) ?>">
                                <?php foreach ($rangee as $noeud): ?>
                                    <?php require __DIR__ . '/partials/carte_arbre.php'; ?>
                                <?php endforeach; ?>
                            </ul>
                            <?php if ($numeroRangee === 0): ?>
                                <?php $traits = $generation['traits']; require __DIR__ . '/partials/traits_arbre.php'; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="arbre-chene__partie arbre-chene__partie--tronc" aria-labelledby="chene-tronc">
                <h2 id="chene-tronc">Le tronc</h2>
                <ul class="arbre-chene__rangee arbre-chene__rangee--<?= 1 + count($arbre['conjoints']) ?>">
                    <?php $noeud = $arbre['racine']; ?>
                    <li class="arbre-chene__noeud arbre-chene__noeud--tronc"
                        data-relation="selection" data-explo-centre="1">
                        <a class="arbre-chene__lien" href="<?= e($noeud['url']) ?>"
                           aria-label="<?= e($noeud['nom'] . ', personne sélectionnée') ?>">
                            <span class="arbre-chene__relation"><?= e($noeud['relation_libelle']) ?></span>
                            <span class="arbre-chene__nom"><?= e($noeud['nom']) ?></span>
                            <?php if ($noeud['dates'] !== ''): ?>
                                <span class="arbre-chene__dates"><?= e($noeud['dates']) ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php foreach ($arbre['conjoints'] as $noeud): ?>
                        <?php $classe = 'arbre-chene__noeud--tronc'; require __DIR__ . '/partials/carte_arbre.php'; ?>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="arbre-chene__partie arbre-chene__partie--racines" aria-labelledby="chene-descendants">
                <h2 id="chene-descendants">Les descendants</h2>
                <?php if ($arbre['descendants'] === []): ?>
                    <p class="arbre-chene__vide">Aucun enfant connu pour l’instant.</p>
                <?php else: ?>
                    <?php foreach ($arbre['descendants'] as $generation): ?>
                        <?php $idLegende = 'chene-descendants-' . (int) $generation['niveau']; ?>
                        <p class="arbre-chene__legende" id="<?= e($idLegende) ?>">
                            <?= e($generation['libelle']) ?>
                            <span class="arbre-chene__compte"><?= (int) $generation['total'] ?></span>
                        </p>
                        <?php foreach ($generation['rangees'] as $numeroRangee => $rangee): ?>
                            <?php if ($numeroRangee === 0): ?>
                                <?php $traits = $generation['traits_avant']; require __DIR__ . '/partials/traits_arbre.php'; ?>
                            <?php endif; ?>
                            <ul class="arbre-chene__rangee arbre-chene__rangee--<?= count($rangee) ?>"
                                aria-labelledby="<?= e($idLegende) ?>">
                                <?php foreach ($rangee as $noeud): ?>
                                    <?php require __DIR__ . '/partials/carte_arbre.php'; ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>
            </div>
        </div>
        <script src="<?= e(asset_url('/assets/arbre-exploration.js')) ?>" defer></script>

        <?php if ($arbre['reste'] > 0 || $tout): ?>
            <p class="arbre-chene__plus">
                <?php if ($arbre['reste'] > 0): ?>
                    <a href="/arbre?racine=<?= (int) $arbre['racine']['id'] ?>&amp;plus=1#chene">
                        <?php if ($arbre['reste'] === 1): ?>
                            Voir l’autre personne de la famille
                        <?php else: ?>
                            Voir les <?= (int) $arbre['reste'] ?> autres personnes de la famille
                        <?php endif; ?>
                    </a>
                <?php else: ?>
                    <a href="/arbre?racine=<?= (int) $arbre['racine']['id'] ?>#chene">Voir moins</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <p class="note arbre-chene__note">
            Trois générations d’ancêtres et deux de descendants, avec les seules
            personnes que votre compte a le droit de voir. Chaque nom mène à la
            fiche de la personne.
        </p>
    <?php endif; ?>

    <form method="get" action="/arbre" class="filtres" id="liste-personnes">
        <div class="champ">
            <label for="q">Rechercher une personne</label>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Nom ou prénom">
        </div>
        <div class="champ champ--bouton">
            <button type="submit" class="bouton">Rechercher</button>
            <?php if ($q !== '' || $lettre !== ''): ?>
                <a class="lien" href="/arbre">Tout afficher</a>
            <?php endif; ?>
        </div>
    </form>

    <nav class="lettres" aria-label="Parcourir par initiale">
        <a href="/arbre<?= $q !== '' ? '?q=' . rawurlencode($q) : '' ?>"
           class="<?= $lettre === '' ? 'actif' : '' ?>">Toutes</a>
        <?php foreach ($alphabet as $letter): ?>
            <?php if (in_array($letter, $letters, true)): ?>
                <a href="/arbre?lettre=<?= $letter ?><?= $q !== '' ? '&q=' . rawurlencode($q) : '' ?>"
                   class="<?= $lettre === $letter ? 'actif' : '' ?>"><?= $letter ?></a>
            <?php else: ?>
                <span class="inactif"><?= $letter ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <p class="discret"><?= (int) $total ?> personne<?= (int) $total > 1 ? 's' : '' ?></p>

    <?php if ($persons === []): ?>
        <div class="panneau panneau--centre">
            <p>Aucune personne ne correspond à cette recherche.</p>
        </div>
    <?php else: ?>
        <table class="tableau">
            <caption class="sr-only">Liste des personnes</caption>
            <thead>
                <tr>
                    <th scope="col">Nom</th>
                    <th scope="col">Prénom</th>
                    <th scope="col">Dates</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($persons as $person): ?>
                    <tr>
                        <td><?= e((string) $person['last_name']) ?></td>
                        <td><?= e((string) $person['first_name']) ?></td>
                        <td><?= e(person_dates($person)) ?></td>
                        <td class="colonne-actions">
                            <a href="/arbre/<?= (int) $person['id'] ?>">Fiche</a>
                            <a href="/arbre?racine=<?= (int) $person['id'] ?>#chene">Arbre</a>
                            <?php if (is_admin()): ?>
                                <a href="/admin/arbre/personne/<?= (int) $person['id'] ?>">Modifier</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php require __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</section>
