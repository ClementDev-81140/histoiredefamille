<?php
/**
 * Carte des lieux : dessin SVG engendré par le serveur, sans JavaScript.
 *
 * @var array  $filtres, $donnees, $tableau, $branches, $decennies
 * @var string $svg, $source
 * @var int    $aPlacer
 */
$lienFiltre = static function (array $modifs) use ($filtres): string {
    $params = array_merge($filtres, $modifs);
    $pairs  = [];
    foreach ($params as $cle => $valeur) {
        if ($valeur === '' || $valeur === 0 || $valeur === null) {
            continue;
        }
        $pairs[] = rawurlencode((string) $cle) . '=' . rawurlencode((string) $valeur);
    }

    return '/carte' . ($pairs !== [] ? '?' . implode('&amp;', $pairs) : '');
};
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/">Accueil</a>
    <span aria-hidden="true">/</span>
    <span>Carte des lieux</span>
</nav>

<div class="section__entete">
    <div>
        <h1>Carte des lieux</h1>
        <p class="note">
            Les lieux de naissance, de décès et de mariage de la famille, sur une carte
            dessinée par le site. Chaque point mène à la liste des personnes qui y sont
            rattachées.
        </p>
    </div>
</div>

<?php if ((int) $aPlacer > 0): ?>
    <div class="message message--info">
        <strong><?= (int) $aPlacer ?> lieu(x) attendent d’être placés.</strong>
        <a class="lien" href="/admin/lieux">Administration → Lieux à placer</a>.
    </div>
<?php endif; ?>

<form method="get" action="/carte#carte" class="carte-lieux__filtres formulaire">
    <?php if ($filtres['lieu'] !== ''): ?>
        <input type="hidden" name="lieu" value="<?= e($filtres['lieu']) ?>">
    <?php endif; ?>
    <div class="champ champ--petit">
        <label for="filtre-vue">Vue</label>
        <select id="filtre-vue" name="vue">
            <?php foreach (carte_vues() as $cle => $libelle): ?>
                <option value="<?= e($cle) ?>" <?= $filtres['vue'] === $cle ? 'selected' : '' ?>>
                    <?= e($libelle) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="champ champ--petit">
        <label for="filtre-branche">Branche</label>
        <select id="filtre-branche" name="branche">
            <option value="0">Toutes mes branches</option>
            <?php foreach ($branches as $id => $nom): ?>
                <option value="<?= (int) $id ?>" <?= $filtres['branche'] === (int) $id ? 'selected' : '' ?>>
                    <?= e($nom) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="champ champ--petit">
        <label for="filtre-decennie">Décennie de naissance</label>
        <select id="filtre-decennie" name="decennie">
            <option value="0">Toutes les décennies</option>
            <?php foreach ($decennies as $decennie): ?>
                <option value="<?= (int) $decennie ?>" <?= $filtres['decennie'] === (int) $decennie ? 'selected' : '' ?>>
                    <?= (int) $decennie ?>–<?= (int) $decennie + 9 ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <p class="actions">
        <button type="submit" class="bouton bouton--secondaire">Afficher</button>
        <a class="lien" href="/carte">Tout effacer</a>
    </p>
</form>

<?php if ($donnees['points'] === []): ?>
    <div class="message message--info">
        <?php if ($filtres['branche'] > 0 || $filtres['decennie'] > 0): ?>
            Aucun lieu ne correspond à ces filtres. <a class="lien" href="/carte">Voir toute la carte</a>.
        <?php else: ?>
            Aucun lieu n’est encore placé, ou aucune personne visible n’y est rattachée.
            <?php if (is_admin()): ?>
                <a class="lien" href="/admin/lieux">Placer les lieux</a>.
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <figure class="carte-lieux__figure" id="carte">
        <div class="carte-lieux__cadre">
            <?= $svg ?>
        </div>
        <figcaption>
            <?php if ($filtres['lieu'] !== ''): ?>
                Le lieu « <?= e($filtres['lieu']) ?> » est entouré. <a class="lien" href="/carte#carte">Retirer</a>.
                <br>
            <?php endif; ?>
            <?= count($donnees['points']) ?> lieu(x) — <?= (int) $donnees['total'] ?> personne(s).
            La taille d’un point suit le nombre de personnes qui y sont rattachées.
            <?php if ((int) $donnees['sans_lieu'] > 0): ?>
                <br>
                <?= (int) $donnees['sans_lieu'] ?> personne(s) sont rattachées à un lieu
                qui n’est pas encore placé : elles ne figurent pas sur la carte.
                <?php if (is_admin()): ?>
                    <a class="lien" href="/admin/lieux">Les placer</a>.
                <?php endif; ?>
            <?php endif; ?>
        </figcaption>
    </figure>

    <div class="message message--info carte-lieux__note">
        Astuce : posez le doigt ou la souris sur un point pour lire son nom.
        Le tableau ci-dessous donne exactement la même information.
    </div>

    <section class="panneau">
        <h2>Les lieux, un par un</h2>
        <div class="tableau-defilant">
            <table class="tableau">
                <thead>
                    <tr>
                        <th scope="col">Lieu</th>
                        <th scope="col">Pays</th>
                        <th scope="col" class="colonne-nombre">Personnes</th>
                        <th scope="col">Fiche</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tableau as $ligne): ?>
                        <tr>
                            <td><?= e($ligne['nom']) ?></td>
                            <td><?= e($ligne['pays']) ?></td>
                            <td class="colonne-nombre"><?= (int) $ligne['personnes'] ?></td>
                            <td><a class="lien" href="<?= e($ligne['url']) ?>">Voir</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="aide">
            Fond de carte : <?= e($source) ?>. Aucune donnée n’est demandée à un service
            extérieur : la carte fonctionne sans Internet et ne vous suit pas.
        </p>
    </section>
<?php endif; ?>
