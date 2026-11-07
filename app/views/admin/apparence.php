<?php
/**
 * Apparence du journal : textes de la une, cases à cocher, numéro d'édition
 * et couleurs. Un aperçu vivant montre le résultat pendant la saisie.
 *
 * @var array  $valeurs, $couleurs, $variables, $rapports
 * @var array  $erreurs, $old
 * @var string $numeroAuto, $dateExemple
 * @var bool   $policePresente
 */
$v = static function (string $cle, string $defaut = '') use ($old, $valeurs): string {
    $valeur = old_value($old, $cle, $valeurs[$cle] ?? $defaut);
    return is_string($valeur) ? $valeur : $defaut;
};
$coche = static function (string $cle) use ($old, $valeurs): bool {
    $valeur = old_value($old, $cle, $valeurs[$cle] ?? '1');
    return (string) $valeur === '1';
};
$couleur = static function (string $cle) use ($old, $couleurs): string {
    return (string) old_value($old, 'couleur_' . $cle, $couleurs[$cle] ?? '#000000');
};
$modes    = APPARENCE_COULEURS_MODES;
$modeCoul = (string) old_value($old, 'couleurs_mode', $valeurs['couleurs_mode'] ?? 'papier');
$modeNum  = (string) old_value($old, 'numero_mode', $valeurs['numero_mode'] ?? 'auto');
$correction = [];
foreach (['fond', 'texte', 'accent', 'bordure'] as $cle) {
    $valeur = old_value($old, 'correction_' . $cle, '');
    if (is_string($valeur) && $valeur !== '') {
        $correction[$cle] = $valeur;
    }
}
$libelles = [
    'bandeau_milieu' => ['Bandeau du haut, au milieu', 'Aujourd’hui : « Édition des familles réunies ». 80 caractères maximum.'],
    'bandeau_droite' => ['Bandeau du haut, à droite', 'Aujourd’hui : « Prix : un sourire ». 80 caractères maximum.'],
    'numero_texte'   => ['Texte du numéro d’édition', 'Par exemple « N° 12 » ou « Hiver 2026 ». 40 caractères maximum.'],
    'pied'           => ['Pied de page', 'Une phrase en bas de chaque page. Vide : le texte d’aujourd’hui. 200 caractères maximum.'],
    'accueil'        => ['Phrase d’accueil (page publique)', 'Affichée sous le titre de la page d’accueil. Vide : la description du site.'],
    'titre_annonces' => ['Petites annonces', 'Titre de l’encart.'],
    'titre_courrier' => ['Courrier des lecteurs', 'Titre de l’encart.'],
    'titre_editions' => ['Les éditions', 'Titre de l’encart.'],
    'titre_acces'    => ['Accès rapides', 'Titre de l’encart.'],
    'titre_chiffres' => ['En chiffres', 'Titre de l’encart.'],
    'titre_une'      => ['À la une', 'Titre de la rubrique des articles, page publique.'],
    'titre_lire'     => ['À lire aussi', 'Titre de la rubrique des pages d’information.'],
    'titre_gazette_articles'    => ['Gazette : les articles', 'Encart de la gazette du mois.'],
    'titre_gazette_photos'      => ['Gazette : photos et albums', 'Encart de la gazette du mois.'],
    'titre_gazette_videos'      => ['Gazette : vidéos', 'Encart de la gazette du mois.'],
    'titre_gazette_temoignages' => ['Gazette : témoignages', 'Encart de la gazette du mois.'],
    'titre_gazette_recettes'    => ['Gazette : recettes', 'Encart de la gazette du mois.'],
    'titre_gazette_personnes'   => ['Gazette : nouvelles personnes', 'Encart de la gazette du mois.'],
];
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/admin">Administration</a>
    <span aria-hidden="true">/</span>
    <span>Apparence du journal</span>
</nav>

<h1>Apparence du journal</h1>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur" role="alert">
        <p><strong>Ces réglages n’ont pas été enregistrés :</strong></p>
        <ul>
            <?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
        </ul>
        <?php if ($correction !== []): ?>
            <form method="post" action="/admin/apparence">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="corriger">
                <?php foreach (['fond', 'texte', 'accent', 'bordure'] as $cle): ?>
                    <input type="hidden" name="couleur_<?= e($cle) ?>" value="<?= e($correction[$cle] ?? '') ?>">
                <?php endforeach; ?>
                <p>
                    <button type="submit" class="bouton">Corriger pour moi</button>
                    <span class="aide">
                        Le site rapproche juste ce qu’il faut le texte et l’accent de vos couleurs :
                        papier <?= e($correction['fond'] ?? '') ?>,
                        texte <?= e($correction['texte'] ?? '') ?>,
                        accent <?= e($correction['accent'] ?? '') ?>.
                    </span>
                </p>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php /* Aperçu : un vrai bandeau, mis à jour pendant la saisie (app.js). */ ?>
<section class="apercu-journal<?= $coche('gothique') ? ' apercu-journal--gothique' : ' apercu-journal--romain' ?>"
         id="apercu-journal" aria-label="Aperçu de l’en-tête du journal">
    <p class="apercu-journal__barre">
        <span data-apercu="date"><?= e($dateExemple) ?></span>
        <span data-apercu="bandeau"><?= e($v('bandeau_milieu') !== '' ? $v('bandeau_milieu') : APPARENCE_TEXTES['bandeau_milieu']) ?></span>
        <span data-apercu="prix"><?= e($v('bandeau_droite') !== '' ? $v('bandeau_droite') : APPARENCE_TEXTES['bandeau_droite']) ?></span>
    </p>
    <p class="apercu-journal__titre" data-apercu="titre"><?= e($v('nom', site_setting('name'))) ?></p>
    <p class="apercu-journal__ligne">
        <span data-apercu="fondee"><?= $v('fondee') !== '' ? 'Fondée en ' . e($v('fondee')) : '' ?></span>
        <span data-apercu="devise"><?= $v('devise') !== '' ? '« ' . e($v('devise')) . ' »' : '' ?></span>
        <span data-apercu="numero"><?= e(apparence_numero()) ?></span>
    </p>
    <div class="apercu-journal__exemple">
        <span class="bouton" data-apercu="bouton">Bouton</span>
        <span data-apercu="doux">Date, mentions légales, textes secondaires</span>
    </div>
</section>

<form method="post" action="/admin/apparence" class="formulaire" id="formulaire-apparence"
      data-apparence data-numero-auto="<?= e($numeroAuto) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="enregistrer">

    <section class="apparence-groupe">
        <h2>Titre et ligne du haut
            <small>Le titre vient des réglages du site : le changer ici le change aussi dans « Réglages ».</small>
        </h2>

        <div class="ligne-champs">
            <div class="champ">
                <label for="nom">Titre du site</label>
                <input type="text" id="nom" name="nom" maxlength="100" required value="<?= e($v('nom', site_setting('name'))) ?>">
            </div>
            <div class="champ">
                <label for="devise">Devise (sous le titre)</label>
                <input type="text" id="devise" name="devise" maxlength="120" value="<?= e($v('devise')) ?>"
                       placeholder="<?= e(site_setting('subtitle')) ?>">
                <p class="aide">Vide : le sous-titre du site.</p>
            </div>
        </div>

        <div class="ligne-champs">
            <div class="champ">
                <label for="bandeau_milieu">Bandeau du haut, au milieu</label>
                <input type="text" id="bandeau_milieu" name="bandeau_milieu" maxlength="80"
                       value="<?= e($v('bandeau_milieu')) ?>" placeholder="<?= e(APPARENCE_TEXTES['bandeau_milieu']) ?>">
                <p class="aide">Vide : « <?= e(APPARENCE_TEXTES['bandeau_milieu']) ?> ».</p>
            </div>
            <div class="champ">
                <label for="bandeau_droite">Bandeau du haut, à droite</label>
                <input type="text" id="bandeau_droite" name="bandeau_droite" maxlength="80"
                       value="<?= e($v('bandeau_droite')) ?>" placeholder="<?= e(APPARENCE_TEXTES['bandeau_droite']) ?>">
                <p class="aide">Vide : « <?= e(APPARENCE_TEXTES['bandeau_droite']) ?> ».</p>
            </div>
        </div>

        <fieldset class="champ">
            <legend>À afficher dans l’en-tête</legend>
            <label class="case"><input type="checkbox" name="montrer_bandeau" value="1" data-apercu-case="bandeau" <?= checked($coche('montrer_bandeau')) ?>> Le bandeau du haut (date, édition, prix)</label>
            <label class="case"><input type="checkbox" name="montrer_devise" value="1" data-apercu-case="devise" <?= checked($coche('montrer_devise')) ?>> La devise</label>
            <label class="case"><input type="checkbox" name="montrer_fondee" value="1" data-apercu-case="fondee" <?= checked($coche('montrer_fondee')) ?>> « Fondée en … »</label>
            <label class="case"><input type="checkbox" name="montrer_numero" value="1" data-apercu-case="numero" <?= checked($coche('montrer_numero')) ?>> Le numéro d’édition</label>
        </fieldset>

        <div class="champ champ--petit">
            <label for="fondee">Fondée en (année)</label>
            <input type="text" id="fondee" name="fondee" maxlength="4" inputmode="numeric" pattern="\d{4}"
                   value="<?= e($v('fondee')) ?>" placeholder="1889">
            <p class="aide">Quatre chiffres, entre 1500 et <?= (int) date('Y') ?>. Vide : rien n’est affiché.</p>
        </div>
    </section>

    <section class="apparence-groupe">
        <h2>Numéro d’édition
            <small>Il s’affiche à droite, sous le titre, après la devise.</small>
        </h2>

        <?php foreach (APPARENCE_NUMERO_MODES as $valeur => $libelle): ?>
            <label class="radio">
                <input type="radio" name="numero_mode" value="<?= e($valeur) ?>"
                       data-apercu-numero="<?= e($valeur) ?>" <?= checked($modeNum === $valeur) ?>>
                <?= e($libelle) ?>
                <?php if ($valeur === 'auto'): ?>
                    <span class="discret">(aujourd’hui : <?= e($numeroAuto) ?>)</span>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>

        <div class="champ champ--petit">
            <label for="numero_texte">Texte libre du numéro</label>
            <input type="text" id="numero_texte" name="numero_texte" maxlength="40" data-apercu-numero-texte
                   value="<?= e($v('numero_texte')) ?>" placeholder="N° 12">
            <p class="aide">Utilisé seulement avec « Texte libre ».</p>
        </div>
    </section>

    <section class="apparence-groupe">
        <h2>Pied de page et accueil</h2>

        <div class="champ">
            <label for="pied">Pied de page</label>
            <input type="text" id="pied" name="pied" maxlength="200" value="<?= e($v('pied')) ?>"
                   placeholder="<?= e(APPARENCE_PIED_DEFAUT) ?>">
            <p class="aide">Vide : le texte d’aujourd’hui.</p>
        </div>

        <div class="champ">
            <label for="accueil">Phrase d’accueil de la page publique</label>
            <input type="text" id="accueil" name="accueil" maxlength="200" value="<?= e($v('accueil')) ?>"
                   placeholder="<?= e(apparence_description_site()) ?>">
            <p class="aide">Vide : la description du site (réglages).</p>
        </div>
    </section>

    <section class="apparence-groupe">
        <h2>Titres des encarts
            <small>Un champ vide garde le nom d’aujourd’hui. 40 caractères maximum.</small>
        </h2>

        <div class="ligne-champs">
            <?php foreach ($libelles as $cle => [$libelle, $aide]): ?>
                <?php if (!str_starts_with($cle, 'titre_')) { continue; } ?>
                <div class="champ">
                    <label for="<?= e($cle) ?>"><?= e($libelle) ?></label>
                    <input type="text" id="<?= e($cle) ?>" name="<?= e($cle) ?>" maxlength="40"
                           value="<?= e($v($cle)) ?>" placeholder="<?= e(APPARENCE_TEXTES[$cle] ?? '') ?>">
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="apparence-groupe">
        <h2>Titre en gothique</h2>
        <label class="case">
            <input type="checkbox" name="gothique" value="1" data-apercu-gothique <?= checked($coche('gothique')) ?>>
            Afficher le titre en lettres gothiques
        </label>
        <p class="aide">
            Décoché, le titre s’écrit en Old Standard TT gras, en petites capitales.
            <?php if (!$policePresente): ?>
                <strong>Le fichier de la police gothique n’est pas présent sur le serveur :</strong>
                le titre s’affiche déjà en Old Standard TT, même si la case est cochée.
            <?php endif; ?>
        </p>
    </section>

    <section class="apparence-groupe" id="aspect-photo">
        <h2>Aspect « photo ancienne »
            <small>Un filtre d’affichage : le fichier d’origine n’est jamais modifié, les
            téléchargements et l’impression restent en couleurs.</small>
        </h2>

        <?php
        $aspectCourant = (string) old_value($old, 'aspect_photo', $valeurs['aspect_photo'] ?? 'aucun');
        if (!array_key_exists($aspectCourant, APPARENCE_ASPECTS)) {
            $aspectCourant = 'aucun';
        }
        ?>
        <?php foreach (APPARENCE_ASPECTS as $cle => $libelle): ?>
            <label class="radio">
                <input type="radio" name="aspect_photo" value="<?= e($cle) ?>"
                       data-apercu-aspect-choix="<?= e($cle) ?>" <?= checked($aspectCourant === $cle) ?>>
                <?= e($libelle) ?>
            </label>
        <?php endforeach; ?>
        <p class="aide">
            « Aucun » laisse les photos en couleurs (réglage d’origine). « Doux » et « Marqué »
            donnent un rendu sépia, de plus en plus appuyé. Sur la fiche d’une photo, vous pouvez
            forcer ou désactiver ce rendu.
        </p>

        <?php /* Aperçu vivant : la même image que le site, filtrée par la classe CSS. */ ?>
        <div class="apercu-aspect" id="apercu-aspect" aria-label="Aperçu de l’aspect des photos">
            <?php if ($photoApercu !== null): ?>
                <img data-apercu-aspect-image
                     src="<?= e(thumbnail_url($photoApercu)) ?>"
                     class="apercu-aspect__image <?= e(aspect_photo_classe($photoApercu)) ?>"
                     alt="Aperçu de l’aspect des photos" width="480" height="360">
            <?php else: ?>
                <div data-apercu-aspect-image
                     class="apercu-aspect__image apercu-aspect__echantillon <?= e(aspect_photo_classe(null)) ?>"
                     aria-hidden="true"></div>
            <?php endif; ?>
            <p class="apercu-aspect__legende">
                Aperçu — <span data-apercu-aspect-libelle><?= e(APPARENCE_ASPECTS[$aspectCourant]) ?></span>
            </p>
        </div>
    </section>

    <section class="apparence-groupe" id="couleurs">
        <h2>Couleurs
            <small>Trois possibilités. Le site vérifie les contrastes : un texte gris sur un papier clair est refusé.</small>
        </h2>

        <?php foreach ($modes as $valeur => $libelle): ?>
            <label class="radio">
                <input type="radio" name="couleurs_mode" value="<?= e($valeur) ?>"
                       data-apercu-mode="<?= e($valeur) ?>" <?= checked($modeCoul === $valeur) ?>>
                <?= e($libelle) ?>
            </label>
        <?php endforeach; ?>

        <div class="ligne-champs" data-apercu-couleurs>
            <?php
            $noms = [
                'fond'    => ['Papier (fond des pages)', '#f3e7ce'],
                'texte'   => ['Texte', '#1d140d'],
                'accent'  => ['Accent (liens, boutons, titres)', '#8f3126'],
                'bordure' => ['Encre (bordures et filets)', '#362c24'],
            ];
            foreach ($noms as $cle => [$libelle, $exemple]):
            ?>
                <div class="champ">
                    <label for="couleur_<?= e($cle) ?>"><?= e($libelle) ?></label>
                    <div class="couleur-champ">
                        <input type="color" id="couleur_<?= e($cle) ?>_selecteur" value="<?= e($couleur($cle)) ?>"
                               data-apercu-pipette="<?= e($cle) ?>" aria-label="Choisir la couleur : <?= e($libelle) ?>">
                        <input type="text" id="couleur_<?= e($cle) ?>" name="couleur_<?= e($cle) ?>"
                               maxlength="7" pattern="#[0-9a-fA-F]{6}" placeholder="<?= e($exemple) ?>"
                               value="<?= e($couleur($cle)) ?>" data-apercu-couleur="<?= e($cle) ?>">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="aide">
            Les couleurs se donnent en #RRGGBB (par exemple <code>#8f3126</code>). Le site calcule
            tout le reste (panneaux, texte gris, boutons, ombres) à partir de ces quatre couleurs.
        </p>

        <table class="contrastes" data-apercu-contrastes>
            <caption class="sr-only">Contrastes calculés</caption>
            <thead>
                <tr>
                    <th scope="col">Contraste</th>
                    <th scope="col">Calculé</th>
                    <th scope="col">Minimum</th>
                    <th scope="col">Verdict</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rapports as $rapport): ?>
                    <tr class="<?= $rapport['ok'] ? 'contraste-ok' : 'contraste-ko' ?>"
                        data-contraste="<?= e($rapport['cle']) ?>">
                        <td><?= e($rapport['libelle']) ?></td>
                        <td class="contraste-valeur" data-contraste-ratio><?= e(number_format($rapport['ratio'], 2, ',', ' ')) ?>:1</td>
                        <td class="contraste-valeur"><?= e(number_format($rapport['minimum'], 1, ',', ' ')) ?>:1</td>
                        <td class="contraste-etat" data-contraste-etat><?= $rapport['ok'] ? 'suffisant' : 'insuffisant' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="contraste-note" data-contraste-message>
            Ces valeurs sont recalculées pendant la saisie. Le site refuse d’enregistrer une
            combinaison trop peu contrastée : il propose alors « Corriger pour moi ».
        </p>

        <p class="actions">
            <button type="submit" class="bouton">Enregistrer</button>
            <a class="bouton bouton--secondaire" href="/admin/reglages">Réglages du site</a>
            <a class="bouton bouton--secondaire" href="/" target="_blank" rel="noopener">Voir le site</a>
        </p>
    </section>
</form>

<?php /* Deux actions séparées : elles ne doivent pas partir avec le grand formulaire. */ ?>
<form method="post" action="/admin/apparence" class="formulaire panneau"
      data-confirmation="Tout remettre comme à l’origine ? Les textes, les cases et les couleurs du journal reviendront aux valeurs d’aujourd’hui.">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="retablir">
    <h2>Revenir en arrière</h2>
    <p class="aide">
        Tout revient comme au premier jour, y compris le titre du site.
    </p>
    <p class="actions">
        <button type="submit" class="bouton bouton--danger">Rétablir les valeurs d’origine</button>
    </p>
</form>
