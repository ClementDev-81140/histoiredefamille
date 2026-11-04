<?php
/**
 * Administration → Courriels : état, réglages, essai, derniers envois.
 *
 * @var bool  $configure
 * @var string $raison, $reponse, $monAdresse, $expediteur, $serveur
 * @var int   $plafond
 * @var array $compteurs, $derniers
 */
$libelles = [
    'article' => 'Nouvel article',
    'gazette' => 'Gazette du mois',
    'essai'   => 'Essai',
];
$etats = [
    'attente' => 'En attente',
    'envoye'  => 'Envoyé',
    'echec'   => 'Échec',
    'annule'  => 'Annulé',
];
?>
<div class="section__entete">
    <div>
        <h1>Courriels</h1>
    </div>
</div>

<?php if (!$configure): ?>
    <div class="message message--alerte" role="alert">
        <strong>L’envoi de courriels n’est pas configuré.</strong>
        <?= e($raison) ?>
    </div>
    <section class="panneau">
        <h2>Comment le configurer</h2>
        <p>
            Les réglages du serveur d’envoi ne se font pas ici : ils vivent dans le fichier
            <code>config.local.php</code> du Raspberry Pi, jamais dans la base ni dans cette page.
            Rien à installer : le site parle directement au serveur d’envoi de votre fournisseur.
        </p>
        <ol class="liste-etapes">
            <li>Ouvrez <code>/var/www/histoiredefamille/config.local.php</code> sur le Pi.</li>
            <li>Remplissez le bloc <code>'mail'</code> : serveur, port, chiffrement, identifiant,
                mot de passe, adresse d’expéditeur.</li>
            <li>Rechargez Apache, puis revenez ici et cliquez sur « Envoyer un essai ».</li>
        </ol>
        <p class="aide">
            Le README, section « Envoyer des courriels », donne les paramètres des fournisseurs
            courants et la marche à suivre pas à pas.
        </p>
    </section>
<?php else: ?>
    <div class="message message--succes">
        L’envoi est configuré : serveur <code><?= e($serveur) ?></code>,
        expéditeur <code><?= e($expediteur) ?></code>.
    </div>
<?php endif; ?>

<div class="grille-compteurs">
    <div class="compteur">
        <span class="compteur__valeur"><?= (int) $compteurs['attente'] ?></span>
        <span class="compteur__libelle">En attente</span>
    </div>
    <div class="compteur">
        <span class="compteur__valeur"><?= (int) $compteurs['envoyes'] ?></span>
        <span class="compteur__libelle">Envoyés aujourd’hui</span>
    </div>
    <div class="compteur">
        <span class="compteur__valeur"><?= (int) $compteurs['echecs'] ?></span>
        <span class="compteur__libelle">Échecs (3 essais)</span>
    </div>
    <div class="compteur">
        <span class="compteur__valeur"><?= (int) $compteurs['abonnes'] ?></span>
        <span class="compteur__libelle">Personnes inscrites</span>
    </div>
</div>

<div class="deux-colonnes">
    <section class="panneau">
        <h2>Réglages d’envoi</h2>
        <form method="post" action="/admin/courriels/reglages" class="formulaire">
            <?= csrf_field() ?>

            <div class="champ">
                <label for="plafond">Plafond quotidien</label>
                <input type="number" id="plafond" name="plafond" min="1" max="500"
                       value="<?= (int) $plafond ?>" required>
                <p class="aide">
                    Nombre maximum de courriels envoyés par jour (50 par défaut). Les messages en
                    trop attendent le lendemain : rien n’est perdu.
                </p>
            </div>

            <div class="champ">
                <label for="reponse">Adresse de réponse (facultatif)</label>
                <input type="email" id="reponse" name="reponse" maxlength="190" value="<?= e($reponse) ?>">
                <p class="aide">L’adresse qui recevra les réponses de la famille. Vide = pas de réponse possible.</p>
            </div>

            <p class="actions">
                <button type="submit" class="bouton">Enregistrer</button>
            </p>
        </form>
    </section>

    <section class="panneau">
        <h2>Essai d’envoi</h2>
        <p>
            Envoie un seul message, à vous-même, pour vérifier que tout fonctionne.
            Votre adresse : <code><?= $monAdresse !== '' ? e(courriel_masquer($monAdresse)) : 'non renseignée' ?></code>.
        </p>
        <?php if ($monAdresse === ''): ?>
            <p class="aide">
                Ajoutez votre adresse dans <a href="/mon-compte">Mon compte</a> pour pouvoir
                recevoir l’essai.
            </p>
        <?php endif; ?>
        <form method="post" action="/admin/courriels/essai" class="formulaire">
            <?= csrf_field() ?>
            <p class="actions">
                <button type="submit" class="bouton bouton--secondaire" <?= ($configure && $monAdresse !== '') ? '' : 'disabled' ?>>
                    Envoyer un courriel d’essai à moi-même
                </button>
            </p>
        </form>
        <p class="aide">
            Aucun courriel ne part pendant une visite du site : les messages sont mis en attente
            et envoyés par la tâche automatique, toutes les 5 minutes.
        </p>
    </section>
</div>

<section class="panneau">
    <h2>50 derniers envois</h2>
    <?php if ($derniers === []): ?>
        <p class="discret">Aucun envoi pour l’instant.</p>
    <?php else: ?>
        <?php /* Tableau large : sur un grand écran, les colonnes se replient
                 et l'erreur passe à la ligne (aucun défilement horizontal) ;
                 sur un téléphone, il défile latéralement. */ ?>
        <div class="tableau-defilant">
            <table class="tableau tableau--courriels">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Type</th>
                        <th scope="col" class="colonne-destinataire">Destinataire</th>
                        <th scope="col">Objet</th>
                        <th scope="col">État</th>
                        <th scope="col" class="colonne-nombre">Essais</th>
                        <th scope="col" class="colonne-erreur">Dernière erreur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($derniers as $envoi): ?>
                        <tr>
                            <td><?= e(format_date((int) ($envoi['sent_at'] ?: $envoi['created_at']), true)) ?></td>
                            <td><?= e($libelles[(string) $envoi['kind']] ?? (string) $envoi['kind']) ?></td>
                            <td class="colonne-destinataire"><?= e(courriel_masquer((string) $envoi['recipient'])) ?></td>
                            <td><?= e(mb_substr((string) $envoi['subject'], 0, 60)) ?></td>
                            <td><?= e($etats[(string) $envoi['status']] ?? (string) $envoi['status']) ?></td>
                            <td class="colonne-nombre"><?= (int) $envoi['attempts'] ?></td>
                            <td class="colonne-erreur"><?= e(mb_substr((string) $envoi['last_error'], 0, 80)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="aide">Les adresses sont masquées : le site n’affiche jamais l’adresse complète de quelqu’un d’autre.</p>
    <?php endif; ?>
</section>
