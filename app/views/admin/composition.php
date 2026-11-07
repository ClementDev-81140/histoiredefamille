<?php
/**
 * Administration → Composition des pages.
 *
 * Chaque bloc a trois petits formulaires (monter, descendre, afficher ou
 * masquer) : ce sont de vrais boutons POST, sans JavaScript, avec un jeton
 * CSRF. Un bloc reste dans sa colonne ; les flèches ne le font que glisser
 * parmi ses voisins.
 *
 * @var array $pages, $catalogue, $colonnes, $erreurs, $old, $modeles
 * @var array $uneCandidats  contenus publics proposés pour la une (1.31.0)
 * @var int   $colonnesUne
 * @var string $resume, $modeleDefaut, $unePublique
 * @var bool  $unePhoto
 */
$val = static function (string $cle, mixed $defaut = '') use ($old): mixed {
    return old_value($old, $cle, $defaut);
};
$resume = (string) $val('une_resume', $resume);
$modele = (string) $val('modele_defaut', $modeleDefaut);
$nbUne  = (int) $val('une_colonnes', $colonnesUne);
$photo  = (string) $val('une_photo', $unePhoto ? '1' : '0') === '1';
$une    = (string) $val('une_publique', $unePublique);
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/admin">Administration</a>
    <span aria-hidden="true">/</span>
    <span>Composition des pages</span>
</nav>

<h1>Composition des pages</h1>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur" role="alert">
        <p><strong>Ces réglages n’ont pas été enregistrés :</strong></p>
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php foreach ($pages as $clePage => $page): ?>
    <section class="section composition-page">
        <h2><?= e($page['nom']) ?></h2>
        <?php if ($clePage === 'public'): ?>
            <p class="aide">
                Cette composition règle l’accueil des <strong>comptes connectés</strong>.
                Un visiteur, lui, voit la <strong>Une du journal</strong> : tout le contenu
                public, du plus récent au plus ancien, avec la porte « Se connecter ».
                Le contenu présenté en grand se choisit plus bas
                (<a href="#une_publique">À la une du site public</a>).
            </p>
        <?php endif; ?>

        <?php foreach ($colonnes as $cleColonne => $nomColonne): ?>
            <?php $blocs = $page['colonnes'][$cleColonne] ?? []; ?>
            <?php if ($blocs === []) { continue; } ?>
            <h3 class="composition-colonne"><?= e($nomColonne) ?></h3>
            <ul class="composition-liste">
                <?php foreach ($blocs as $bloc): ?>
                    <li class="composition-bloc<?= $bloc['actif'] ? '' : ' composition-bloc--masque' ?>">
                        <div class="composition-bloc__texte">
                            <p class="composition-bloc__nom">
                                <?= e($bloc['nom']) ?>
                                <?php if ($bloc['fixe']): ?>
                                    <span class="badge badge-public">toujours affiché</span>
                                <?php elseif (!$bloc['actif']): ?>
                                    <span class="badge badge-prive">masqué</span>
                                <?php endif; ?>
                            </p>
                            <p class="aide"><?= e($bloc['aide']) ?></p>
                        </div>

                        <?php if (!$bloc['fixe']): ?>
                            <div class="composition-bloc__actions">
                                <?php foreach ([
                                    ['monter', '↑ Monter', 'Monter « ' . $bloc['nom'] . ' »'],
                                    ['descendre', '↓ Descendre', 'Descendre « ' . $bloc['nom'] . ' »'],
                                ] as [$action, $libelle, $titre]): ?>
                                    <form method="post" action="/admin/composition" class="formulaire-en-ligne">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="page" value="<?= e($clePage) ?>">
                                        <input type="hidden" name="bloc" value="<?= e($bloc['cle']) ?>">
                                        <input type="hidden" name="action" value="<?= e($action) ?>">
                                        <button type="submit" class="bouton bouton--secondaire bouton--petit"
                                                title="<?= e($titre) ?>"><?= e($libelle) ?></button>
                                    </form>
                                <?php endforeach; ?>

                                <form method="post" action="/admin/composition" class="formulaire-en-ligne">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="page" value="<?= e($clePage) ?>">
                                    <input type="hidden" name="bloc" value="<?= e($bloc['cle']) ?>">
                                    <input type="hidden" name="action" value="basculer">
                                    <button type="submit" class="bouton bouton--petit<?= $bloc['actif'] ? ' bouton--danger' : '' ?>">
                                        <?= $bloc['actif'] ? 'Masquer' : 'Afficher' ?>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </section>
<?php endforeach; ?>

<form method="post" action="/admin/composition/reglages" class="formulaire panneau">
    <?= csrf_field() ?>
    <h2>Réglages de la une</h2>

    <div class="ligne-champs">
        <div class="champ champ--petit">
            <label for="une_colonnes">Articles en colonnes</label>
            <input type="number" id="une_colonnes" name="une_colonnes" min="3" max="12" step="1"
                   value="<?= (int) $nbUne ?>" required>
            <p class="aide">
                De 3 à 12, sur l’espace famille. L’article le plus récent s’affiche en
                grand, les suivants en colonnes (aujourd’hui : 4).
            </p>
        </div>

        <div class="champ champ--petit">
            <label for="modele_defaut">Modèle d’article par défaut</label>
            <select id="modele_defaut" name="modele_defaut">
                <?php foreach ($modeles as $cle => $libelle): ?>
                    <?php if ($cle === '') { continue; } ?>
                    <option value="<?= e($cle) ?>"<?= selected($modele === $cle) ?>><?= e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="aide">
                Utilisé par les articles qui n’ont pas choisi leur modèle.
                « Journal » est la mise en page d’aujourd’hui.
            </p>
        </div>
    </div>

    <fieldset class="champ">
        <legend>Longueur du résumé</legend>
        <?php foreach (COMPOSITION_RESUMES as $cle => $libelle): ?>
            <label class="radio">
                <input type="radio" name="une_resume" value="<?= e($cle) ?>" <?= checked($resume === $cle) ?>>
                <?= e($libelle) ?>
            </label>
        <?php endforeach; ?>
        <p class="aide">S’applique aux résumés affichés dans les listes d’articles.</p>
    </fieldset>

    <label class="case">
        <input type="checkbox" name="une_photo" value="1" <?= checked($photo) ?>>
        Afficher la photo de l’article à la une
    </label>

    <?php /* La une du site public (1.31.0) : un seul contenu en grand sur
             l'accueil d'un visiteur. La liste ne contient que des contenus
             publics — le choix est donc toujours affichable. */ ?>
    <div class="champ">
        <label for="une_publique">À la une du site public</label>
        <select id="une_publique" name="une_publique">
            <option value="">Automatique — le contenu public le plus récent</option>
            <?php foreach ($uneCandidats as $candidat): ?>
                <option value="<?= e($candidat['cle']) ?>"<?= selected($une === $candidat['cle']) ?>>
                    <?= e($candidat['libelle']) ?> — <?= e($candidat['titre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="aide">
            L’accueil d’un visiteur présente <strong>tout le contenu public</strong> du site,
            du plus récent au plus ancien. Le contenu choisi ici s’affiche en grand ;
            « automatique » garde le plus récent. Si ce contenu cesse d’être public,
            la page reprend le plus récent toute seule.
        </p>
    </div>

    <p class="actions">
        <button type="submit" class="bouton">Enregistrer ces réglages</button>
        <a class="bouton bouton--secondaire" href="/" target="_blank" rel="noopener">Voir l’accueil</a>
        <a class="bouton bouton--secondaire" href="/famille">Voir l’espace famille</a>
    </p>
</form>

<form method="post" action="/admin/composition/retablir" class="formulaire panneau"
      data-confirmation="Tout remettre comme à l’origine ? Tous les blocs seront de nouveau affichés, dans leur ordre d’origine, et les réglages de la une reviendront à leurs valeurs d’aujourd’hui.">
    <?= csrf_field() ?>
    <h2>Revenir en arrière</h2>
    <p>
        « Rétablir la composition d’origine » réaffiche tous les blocs des deux
        pages, dans leur ordre d’origine, et remet les réglages de la une et le
        modèle d’article par défaut comme au premier jour. Les articles, les
        documents et les droits ne sont pas touchés.
    </p>
    <p class="actions">
        <button type="submit" class="bouton bouton--danger">Rétablir la composition d’origine</button>
    </p>
</form>
