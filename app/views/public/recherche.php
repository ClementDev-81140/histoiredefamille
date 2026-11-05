<?php
/**
 * Recherche générale.
 *
 * @var string     $q, $type
 * @var string[]   $mots
 * @var array      $types, $resultats, $totaux
 * @var int        $total
 * @var array|null $pagination
 */
$onglets = ['' => 'Tout'] + $types;
?>
<section class="section recherche">
    <div class="section__entete">
        <h1>Recherche</h1>
    </div>

    <form method="get" action="/recherche" class="filtres recherche__formulaire" role="search">
        <div class="champ">
            <label for="q">Que cherchez-vous ?</label>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" maxlength="100"
                   placeholder="Un nom, un lieu, un mot d’un titre…" autofocus>
            <p class="aide">
                La recherche ignore les accents et les majuscules : « begles »
                trouve « Bègles ». Elle porte sur les documents, les articles,
                les pages d’information et les personnes.
            </p>
        </div>
        <?php if ($type !== ''): ?>
            <input type="hidden" name="type" value="<?= e($type) ?>">
        <?php endif; ?>
        <div class="champ champ--bouton">
            <button type="submit" class="bouton">Chercher</button>
            <?php if ($q !== ''): ?>
                <a class="lien" href="/recherche">Effacer</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($q === ''): ?>
        <div class="panneau panneau--centre recherche__accueil">
            <p>Indiquez un mot ou un nom pour commencer.</p>
            <p class="discret">
                Exemples : un nom de famille, un village, une année, le titre
                d’un article ou d’une photo.
            </p>
        </div>
    <?php elseif ($mots === []): ?>
        <div class="panneau panneau--centre">
            <p>Cette recherche ne contient aucun mot utilisable.</p>
            <p class="discret">Essayez avec des lettres ou des chiffres.</p>
        </div>
    <?php else: ?>
        <p class="recherche__resume">
            <?php if ($total === 0): ?>
                Aucun résultat pour « <strong><?= e($q) ?></strong> ».
            <?php else: ?>
                <strong><?= (int) $total ?></strong> résultat<?= $total > 1 ? 's' : '' ?>
                pour « <strong><?= e($q) ?></strong> »
                <?php if (!is_logged_in()): ?>
                    <span class="discret">— seuls les contenus publics sont cherchés.
                        <a href="/connexion?next=%2Frecherche">Se connecter</a> pour chercher dans tout le site.</span>
                <?php endif; ?>
            <?php endif; ?>
        </p>

        <?php if ($total > 0): ?>
            <nav class="recherche__onglets" aria-label="Filtrer par catégorie">
                <?php foreach ($onglets as $cle => $libelle): ?>
                    <?php
                    $compte = $cle === '' ? $total : ($totaux[$cle] ?? 0);
                    $actif  = $type === $cle;
                    $lien   = '/recherche?q=' . rawurlencode($q) . ($cle !== '' ? '&type=' . $cle : '');
                    ?>
                    <a href="<?= e($lien) ?>"<?= $actif ? ' class="actif" aria-current="page"' : '' ?>>
                        <?= e($libelle) ?><?php if ($compte > 0): ?> (<?= (int) $compte ?>)<?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php foreach ($types as $cle => $libelle): ?>
                <?php
                $lignes = $resultats[$cle] ?? [];
                if ($lignes === []) {
                    continue;
                }
                $nombre  = (int) ($totaux[$cle] ?? count($lignes));
                $voirUrl = '/recherche?q=' . rawurlencode($q) . '&type=' . $cle;
                ?>
                <section class="recherche__bloc">
                    <div class="section__entete">
                        <h2><?= e($libelle) ?> <span class="discret">(<?= $nombre ?>)</span></h2>
                        <?php if ($type === '' && $nombre > count($lignes)): ?>
                            <a href="<?= e($voirUrl) ?>">Voir les <?= $nombre ?> résultats →</a>
                        <?php endif; ?>
                    </div>

                    <ul class="resultats">
                        <?php foreach ($lignes as $ligne): ?>
                            <?php require __DIR__ . '/../partials/resultat_recherche.php'; ?>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>

            <?php if ($type !== '' && $pagination !== null): ?>
                <?php require __DIR__ . '/../partials/pagination.php'; ?>
            <?php endif; ?>

            <?php if ($type !== '' && $resultats[$type] === []): ?>
                <p class="discret">Aucun résultat dans cette catégorie.</p>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
