<?php
/**
 * Administration → Gazette du mois : la liste des numéros.
 *
 * @var array $gazettes, $aValider
 * @var bool  $auto, $configure
 * @var string $prochain
 * @var int   $grace, $motMax
 */
$etats = [
    'a_valider' => 'À valider',
    'envoye'    => 'Envoyé',
    'passe'     => 'Passé',
];
?>
<div class="section__entete">
    <div>
        <h1>Gazette du mois</h1>
    </div>
</div>

<?php if (!site_courriels_actifs()): ?>
    <div class="message message--info" role="status">
        <strong>Les courriels sont désactivés.</strong>
        La gazette du mois est préparée et se lit <a class="lien" href="/gazette">en ligne</a> :
        aucun message n’est envoyé, et aucun réglage d’envoi ne sert ici.
        (Pour réactiver l’envoi : <a class="lien" href="/admin/reglages">Réglages</a>.)
    </div>
<?php elseif (!$configure): ?>
    <div class="message message--alerte" role="alert">
        <strong>L’envoi de courriels n’est pas configuré.</strong>
        La gazette sera préparée, mais aucun message ne pourra partir avant d’avoir rempli le
        bloc <code>'mail'</code> de <code>config.local.php</code>
        (<a class="lien" href="/admin/courriels">Administration → Courriels</a>).
    </div>
<?php endif; ?>

<?php if ($gazettes === []): ?>
    <section class="panneau">
        <h2>Aucun numéro pour l’instant</h2>
        <p>
            Le prochain numéro sera préparé automatiquement pour le mois de
            <strong><?= e(gazette_mois_libelle($prochain)) ?></strong>, à condition qu’au moins une
            personne ait coché « Je veux recevoir la gazette du mois » dans son compte.
        </p>
        <p class="aide">
            Un mois sans nouveauté ne produit aucun numéro : on ne vous envoie pas une page vide.
        </p>
    </section>
<?php else: ?>
    <section class="panneau">
        <h2>Les numéros</h2>
        <div class="tableau-defilant">
            <table class="tableau">
                <thead>
                    <tr>
                        <th scope="col">Mois</th>
                        <th scope="col">Destinataires</th>
                        <th scope="col">État</th>
                        <?php if (site_courriels_actifs()): ?>
                            <th scope="col">Envoyé le</th>
                        <?php endif; ?>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gazettes as $gazette): ?>
                        <tr>
                            <td><?= e(gazette_mois_libelle((string) $gazette['periode'])) ?></td>
                            <td><?= e(gazette_groupe_libelle((string) $gazette['groupe'])) ?></td>
                            <td><?= e($etats[(string) $gazette['status']] ?? (string) $gazette['status']) ?></td>
                            <?php if (site_courriels_actifs()): ?>
                                <td><?= $gazette['sent_at'] ? e(format_date((int) $gazette['sent_at'], true)) : '—' ?></td>
                            <?php endif; ?>
                            <td>
                                <a class="bouton bouton--secondaire" href="/admin/gazette/<?= (int) $gazette['id'] ?>">
                                    Relire
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if (site_courriels_actifs()): ?>
<section class="panneau">
    <h2>Envoi automatique</h2>
    <form method="post" action="/admin/gazette/auto" class="formulaire">
        <?= csrf_field() ?>
        <label class="case">
            <input type="checkbox" name="auto" value="1" <?= checked($auto) ?>>
            Envoyer sans validation, au-delà de <?= (int) $grace ?> jours
        </label>
        <p class="aide">
            Désactivé (par défaut), chaque numéro attend votre décision : vous pouvez le relire,
            retirer un élément, écrire un mot, puis cliquer sur « Envoyer » — ou sur
            « Passer ce mois-ci ». Activé, un numéro encore « à valider » <?= (int) $grace ?> jours
            après la fin du mois part tout seul.
        </p>
        <p class="actions">
            <button type="submit" class="bouton bouton--secondaire">Enregistrer ce réglage</button>
        </p>
    </form>
</section>
<?php endif; ?>

<section class="panneau">
    <h2>Comment ça se passe</h2>
    <ol class="liste-etapes">
        <li>Le <strong>1er du mois</strong>, la tâche automatique prépare un numéro par groupe de
            droits (la famille, chaque branche, et les personnes qui voient tout).</li>
        <li>Vous le relisez ici : l’aperçu est exactement ce que les personnes recevraient.</li>
        <li>Vous pouvez <strong>retirer un élément</strong> et écrire un <strong>mot de
        l’éditeur</strong> (<?= (int) $motMax ?> caractères).</li>
        <?php if (site_courriels_actifs()): ?>
            <li>« Envoyer » met les messages en file : ils partent dans les cinq minutes.
                « Passer ce mois-ci » n’envoie rien.</li>
            <li>Chaque personne ne reçoit que ce qu’elle a le droit de voir, et un seul message par
                mois. Personne ne reçoit deux fois le même numéro.</li>
        <?php else: ?>
            <li>Les courriels étant désactivés, le numéro se lit <a class="lien" href="/gazette">en
                ligne</a> : personne ne reçoit de message. « Passer ce mois-ci » le classe sans
                rien envoyer.</li>
        <?php endif; ?>
    </ol>
</section>
