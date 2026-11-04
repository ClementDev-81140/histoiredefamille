<?php
/**
 * Administration → Lieux à placer.
 *
 * @var array  $aPlacer, $places, $suggestions
 * @var string $titre, $recherche, $source
 */
$val = static fn (string $cle, string $defaut = ''): string => (string) old_value(form_old(), $cle, $defaut);
?>
<div class="section__entete">
    <div>
        <h1>Lieux à placer</h1>
        <p class="note">
            La carte ne devine aucun lieu : elle a besoin de coordonnées. Un texte de lieu
            (« Toulouse », « Saint-Étienne ») reste écrit tel quel dans les fiches ; cette page
            lui donne seulement une position, une fois pour toutes.
        </p>
    </div>
</div>

<?php if (query_int('q', 0) === 0 && $aPlacer === [] && $places !== []): ?>
    <div class="message message--succes">
        Tous les lieux connus du site sont placés. La carte est complète.
    </div>
<?php endif; ?>

<section class="panneau">
    <h2>À placer (<?= count($aPlacer) ?>)</h2>
    <?php if ($aPlacer === []): ?>
        <p class="discret">
            Aucun texte de lieu en attente : tous ceux qui apparaissent dans les fiches des
            personnes et des couples ont déjà une position.
        </p>
    <?php else: ?>
        <p class="aide">
            Le nombre entre parenthèses indique combien de fois ce lieu apparaît dans les fiches.
            Cherchez la commune, puis cliquez sur « Placer » : c'est tout.
        </p>
        <div class="tableau-defilant">
            <table class="tableau">
                <thead>
                    <tr>
                        <th scope="col">Lieu écrit dans les fiches</th>
                        <th scope="col" class="colonne-nombre">Fiches</th>
                        <th scope="col">Le placer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aPlacer as $attente): ?>
                        <tr>
                            <td><strong><?= e($attente['nom']) ?></strong></td>
                            <td class="colonne-nombre"><?= (int) $attente['personnes'] ?></td>
                            <td>
                                <a class="bouton bouton--secondaire"
                                   href="/admin/lieux?q=<?= e(rawurlencode($attente['nom'])) ?>#placer">
                                    Chercher
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panneau" id="placer">
    <h2>Placer un lieu</h2>

    <form method="get" action="/admin/lieux" class="formulaire">
        <div class="champ">
            <label for="q">Chercher une commune de France</label>
            <input type="search" id="q" name="q" value="<?= e($recherche) ?>" maxlength="80"
                   placeholder="Toulouse, Saint-Étienne, Montauban…">
            <p class="aide">Deux lettres suffisent. La liste des communes est dans le site :
                aucune connexion à Internet n'est nécessaire.</p>
        </div>
        <p class="actions">
            <button type="submit" class="bouton bouton--secondaire">Chercher</button>
        </p>
    </form>

    <?php if ($recherche !== '' && $suggestions === []): ?>
        <p class="discret">Aucune commune ne correspond à « <?= e($recherche) ?> ».
            Vous pouvez saisir la latitude et la longitude à la main, plus bas.</p>
    <?php endif; ?>

    <?php foreach ($suggestions as $suggestion): ?>
        <form method="post" action="/admin/lieux/placer" class="formulaire formulaire--suggestion">
            <?= csrf_field() ?>
            <input type="hidden" name="nom" value="<?= e($suggestion['nom']) ?>">
            <input type="hidden" name="pays" value="France">
            <input type="hidden" name="lat" value="<?= e((string) $suggestion['lat']) ?>">
            <input type="hidden" name="lon" value="<?= e((string) $suggestion['lon']) ?>">
            <span class="suggestion__nom"><?= e($suggestion['nom']) ?></span>
            <span class="discret">
                <?= e((string) $suggestion['departement']) ?> —
                <?= e((string) $suggestion['lat']) ?>, <?= e((string) $suggestion['lon']) ?>
            </span>
            <button type="submit" class="bouton bouton--secondaire">Placer</button>
        </form>
    <?php endforeach; ?>
</section>

<section class="panneau">
    <h2>À la main</h2>
    <p class="aide">
        Pour un lieu qui n'est pas une commune (un hameau, un lieu-dit, un village à
        l'étranger), indiquez sa position. Écrivez le nom <strong>exactement comme dans les
        fiches</strong> : c'est ce qui les relie.
    </p>
    <form method="post" action="/admin/lieux/placer" class="formulaire">
        <?= csrf_field() ?>
        <div class="ligne-champs">
            <div class="champ">
                <label for="nom">Nom du lieu</label>
                <input type="text" id="nom" name="nom" maxlength="120" required
                       value="<?= e($val('nom', $recherche)) ?>" placeholder="Toulouse">
            </div>
            <div class="champ">
                <label for="pays">Pays</label>
                <input type="text" id="pays" name="pays" maxlength="80" value="<?= e($val('pays', 'France')) ?>">
            </div>
        </div>
        <div class="ligne-champs">
            <div class="champ">
                <label for="lat">Latitude</label>
                <input type="text" id="lat" name="lat" required inputmode="decimal"
                       value="<?= e($val('lat')) ?>" placeholder="43.6047">
                <p class="aide">Entre -90 et 90. Positive au nord.</p>
            </div>
            <div class="champ">
                <label for="lon">Longitude</label>
                <input type="text" id="lon" name="lon" required inputmode="decimal"
                       value="<?= e($val('lon')) ?>" placeholder="1.4442">
                <p class="aide">Entre -180 et 180. Positive à l'est.</p>
            </div>
        </div>
        <p class="actions">
            <button type="submit" class="bouton">Placer ce lieu</button>
        </p>
    </form>
    <p class="aide">
        Pour trouver une position : sur une carte en ligne, un clic droit sur le lieu donne
        souvent « 43.6047, 1.4442 ». Recopiez ces deux nombres, dans l'ordre.
    </p>
</section>

<section class="panneau">
    <h2>Lieux déjà placés (<?= count($places) ?>)</h2>
    <?php if ($places === []): ?>
        <p class="discret">Aucun lieu placé pour l’instant.</p>
    <?php else: ?>
        <div class="tableau-defilant">
            <table class="tableau">
                <thead>
                    <tr>
                        <th scope="col">Lieu</th>
                        <th scope="col">Pays</th>
                        <th scope="col">Position</th>
                        <th scope="col">Fiche</th>
                        <th scope="col">Retirer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($places as $place): ?>
                        <tr>
                            <td><?= e((string) $place['nom']) ?></td>
                            <td><?= e((string) $place['pays']) ?></td>
                            <td class="colonne-position">
                                <?= e((string) $place['lat']) ?>, <?= e((string) $place['lon']) ?>
                            </td>
                            <td><a class="lien" href="/lieu/<?= (int) $place['id'] ?>">Voir</a></td>
                            <td>
                                <form method="post" action="/admin/lieux/supprimer">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $place['id'] ?>">
                                    <button type="submit" class="bouton bouton--secondaire">Retirer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="aide">
            Retirer un lieu ne modifie aucun texte de fiche : il disparaît simplement de la
            carte, et revient dans « À placer ».
        </p>
    <?php endif; ?>
</section>

<p class="aide">
    Fond de carte : <?= e($source) ?>. Il est livré avec le site : la carte ne fait aucun
    appel à un service extérieur et fonctionne sans Internet.
</p>
