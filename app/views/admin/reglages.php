<?php
/**
 * Réglages du site.
 *
 * @var array $erreurs, $old, $values
 */
$val = static fn (string $key, string $default = ''): string => (string) old_value($old, $key, $values[$key] ?? $default);
?>
<h1>Réglages du site</h1>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul>
            <?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="/admin/reglages" class="formulaire panneau">
    <?= csrf_field() ?>

    <div class="champ">
        <label for="name">Nom du site</label>
        <input type="text" id="name" name="name" maxlength="100" required value="<?= e($val('name')) ?>">
        <p class="aide">Affiché dans l’en-tête et le titre des pages.</p>
    </div>

    <div class="champ">
        <label for="subtitle">Sous-titre</label>
        <input type="text" id="subtitle" name="subtitle" maxlength="150" value="<?= e($val('subtitle')) ?>">
    </div>

    <div class="champ">
        <label for="devise">Devise du journal (facultatif)</label>
        <input type="text" id="devise" name="devise" maxlength="120" value="<?= e($val('devise')) ?>">
        <p class="aide">
            Affichée entre guillemets sous le titre, au centre (par exemple
            « Mémoire des Bertillon et des Vasseur »). Vide, elle reprend le sous-titre.
        </p>
    </div>

    <div class="champ">
        <label for="fondee">Année de fondation (facultatif)</label>
        <input type="text" id="fondee" name="fondee" maxlength="4" inputmode="numeric" value="<?= e($val('fondee')) ?>">
        <p class="aide">Quatre chiffres, affichés à gauche sous le titre : « Fondée en 1889 ».</p>
    </div>

    <div class="champ">
        <label for="description">Description (page d’accueil)</label>
        <textarea id="description" name="description" rows="3" maxlength="500"><?= e($val('description')) ?></textarea>
    </div>

    <div class="champ">
        <label for="adresse">Adresse publique du site</label>
        <input type="url" id="adresse" name="adresse" maxlength="190"
               placeholder="https://histoiredefamille.eu" value="<?= e($val('adresse')) ?>">
        <p class="aide">
            Elle sert dans les messages d’accès envoyés aux personnes
            (« Adresse : https://… »). Sans valeur, l’adresse du fichier
            <code>config.local.php</code> est utilisée.
        </p>
    </div>

    <fieldset class="champ">
        <legend>Adresses affichées</legend>

        <p class="aide">
            Le site <strong>n’envoie aucun courriel</strong> : ces deux adresses sont seulement
            écrites sur les pages, pour qu’on puisse vous joindre depuis l’extérieur. Un clic
            ouvre le logiciel de courriel de la personne (lien <code>mailto:</code> ordinaire).
        </p>

        <div class="ligne-champs">
            <div class="champ">
                <label for="contact">Adresse de contact</label>
                <input type="email" id="contact" name="contact" maxlength="190"
                       value="<?= e($val('contact')) ?>" placeholder="contact@histoiredefamille.eu">
                <p class="aide">
                    Affichée en bas de chaque page (« Une question ? … ») et sur la page
                    d’accueil (« Nous écrire : … »).
                </p>
            </div>

            <div class="champ">
                <label for="support">Adresse d’assistance</label>
                <input type="email" id="support" name="support" maxlength="190"
                       value="<?= e($val('support')) ?>" placeholder="support@histoiredefamille.eu">
                <p class="aide">
                    Affichée sur la page de connexion (« Mot de passe oublié ou besoin d’un
                    compte ? »), dans « Mon compte » et sur les pages d’erreur.
                </p>
            </div>
        </div>
    </fieldset>

    <fieldset class="champ">
        <legend>Courriels</legend>

        <label class="case">
            <input type="checkbox" id="courriels_actifs" name="courriels_actifs" value="1"
                   <?= checked($val('courriels_actifs') === '1') ?>>
            Le site envoie des courriels
        </label>
        <p class="aide">
            <strong>Décoché (par défaut)</strong>, le site n’envoie rien : aucune notification,
            aucune gazette par courriel, aucun lien de désinscription. La communication passe par
            le tchat et les écrans du site ; les comptes se transmettent à la main avec
            « Copier le message », et un administrateur redonne un mot de passe.
            Coché, l’envoi reprend — à condition de remplir aussi le bloc <code>'mail'</code> de
            <code>config.local.php</code>.
        </p>
    </fieldset>

    <fieldset class="champ">
        <legend>Tchat de la famille</legend>

        <div class="tchat-reglage">
            <label class="case">
                <input type="checkbox" id="tchat_actif" name="tchat_actif" value="1"
                       <?= checked($val('tchat_actif') === '1') ?>>
                Ouvrir le tchat pour les personnes connectées
            </label>
            <p class="aide">
                Quand le tchat est fermé, l’onglet disparaît de la navigation et personne
                ne peut lire ni écrire. Les messages déjà envoyés sont conservés.
            </p>
        </div>

        <div class="tchat-reglage">
            <label for="tchat_conservation_mois">Conservation des messages</label>
            <select id="tchat_conservation_mois" name="tchat_conservation_mois">
                <?php foreach ([0 => 'Jamais (conservation illimitée)', 12 => '1 an', 6 => '6 mois', 3 => '3 mois'] as $mois => $libelle): ?>
                    <option value="<?= (int) $mois ?>"<?= selected($val('tchat_conservation_mois') === (string) $mois) ?>><?= e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="aide">
                Un message plus ancien que cette durée est effacé automatiquement par la
                tâche d’entretien de la nuit du dimanche (<code>app/cli/maintenance.php</code>).
                Valeur par défaut : 1 an.
            </p>
        </div>
    </fieldset>

    <fieldset class="champ">
        <legend>Envoi de gros fichiers</legend>

        <div class="ligne-champs">
            <div class="champ">
                <label for="envoi_morceau_mo">Taille d’un morceau (Mo)</label>
                <input type="number" id="envoi_morceau_mo" name="envoi_morceau_mo"
                       min="1" max="20" step="1" value="<?= e($val('envoi_morceau_mo')) ?>">
                <p class="aide">
                    Un morceau est la plus petite unité envoyée : 5 Mo convient à
                    presque tout. <strong>20 Mo au maximum</strong> (au-delà, Cloudflare
                    couperait la requête).
                </p>
            </div>

            <div class="champ">
                <label for="envoi_max_mo">Taille maximale d’un fichier (Mo)</label>
                <input type="number" id="envoi_max_mo" name="envoi_max_mo"
                       min="50" max="16384" step="1" value="<?= e($val('envoi_max_mo')) ?>">
                <p class="aide">
                    Valeur par défaut : 4096 (4 Go). Un fichier plus gros est refusé
                    <em>avant</em> le premier octet. Le disque des données doit aussi
                    garder 2 Go libres.
                </p>
            </div>
        </div>

        <p class="aide">
            Ces réglages ne changent rien à l’envoi simple des formulaires, limité à
            <?= e(format_size(upload_max_bytes())) ?>.
        </p>
    </fieldset>

    <fieldset class="champ">
        <legend>Conversion des vidéos</legend>

        <div class="ligne-champs">
            <div class="champ">
                <label for="conversion_active">Convertir les vidéos en MP4</label>
                <select id="conversion_active" name="conversion_active">
                    <option value="1"<?= selected($val('conversion_active') === '1') ?>>Oui (conseillé)</option>
                    <option value="0"<?= selected($val('conversion_active') !== '1') ?>>Non</option>
                </select>
                <p class="aide">
                    Une vidéo dans un format que les navigateurs ne lisent pas (MOV,
                    AVI, MKV, WebM, HEVC, WAV, AMR…) est convertie en arrière-plan,
                    une seule à la fois, par la commande
                    <code>php app/cli/convertir.php</code>.
                </p>
            </div>

            <div class="champ">
                <label for="conversion_qualite">Qualité</label>
                <select id="conversion_qualite" name="conversion_qualite">
                    <option value="leger"<?= selected($val('conversion_qualite') === 'leger') ?>>Légère (720p) — la plus rapide</option>
                    <option value="equilibre"<?= selected($val('conversion_qualite') !== 'leger' && $val('conversion_qualite') !== 'haute') ?>>Équilibrée (1080p) — conseillée</option>
                    <option value="haute"<?= selected($val('conversion_qualite') === 'haute') ?>>Haute — plus lente, plus lourde</option>
                </select>
            </div>
        </div>

        <div class="champ">
            <label class="case">
                <input type="checkbox" id="conversion_supprimer_original" name="conversion_supprimer_original"
                       value="1"<?= checked($val('conversion_supprimer_original') === '1') ?>>
                Effacer le fichier d’origine sept jours après une conversion réussie
            </label>
            <p class="aide">
                Décoché (par défaut), l’original est conservé sur le disque : deux
                versions de la vidéo occupent alors la place. Coché, il n’est effacé
                qu’après sept jours, et jamais si la conversion a échoué. Il faut
                un peu de place libre : deux heures de film en 1080p pèsent 4 à 6 Go.
            </p>
        </div>
    </fieldset>

    <p class="actions">
        <button type="submit" class="bouton">Enregistrer</button>
        <a class="bouton bouton--secondaire" href="/">Voir le résultat</a>
    </p>
</form>

