<?php
/**
 * Tchat de la famille.
 *
 * @var array  $account   compte connecté
 * @var string $signature signature actuelle (« MARTIN/Jean », « ADMIN/… »)
 * @var string $prenom    prénom choisi (vide au premier passage)
 * @var array  $bloc      html des 50 derniers messages + état
 * @var int    $premier   identifiant du plus ancien message affiché
 * @var bool   $resteAvant des messages plus anciens existent-ils ?
 */
$estAdmin = $account['role'] === 'admin';
?>
<section class="section tchat-page">
    <div class="section__entete">
        <h1>Tchat de la famille</h1>
        <a href="/famille">← Espace famille</a>
    </div>

    <p class="note">
        Ce salon est privé : seules les personnes connectées peuvent le lire.
        Vos messages sont signés <strong><?= $signature !== '' ? e($signature) : 'de votre prénom' ?></strong>.
    </p>

    <?php if ($signature === ''): ?>
        <?php /* Premier passage : on demande comment signer ses messages. */ ?>
        <form method="post" action="/tchat/prenom" class="formulaire panneau tchat-page__prenom">
            <?= csrf_field() ?>
            <h2>Comment voulez-vous signer vos messages ?</h2>
            <div class="champ">
                <label for="prenom">Votre prénom</label>
                <input type="text" id="prenom" name="prenom" maxlength="30" required
                       autocomplete="given-name" value="<?= e($prenom) ?>">
                <p class="aide">
                    Votre message apparaîtra sous la forme
                    « <?= e($account['branch_code'] ?: 'BRANCHE') ?>/Prénom ».
                    Vous pourrez le changer plus tard. Les autres personnes connectées
                    verront ce prénom.
                </p>
            </div>
            <p class="actions">
                <button type="submit" class="bouton">Commencer à discuter</button>
            </p>
        </form>
    <?php else: ?>
        <details class="tchat-page__reglages">
            <summary>Changer mon prénom (<?= e($signature) ?>)</summary>
            <form method="post" action="/tchat/prenom" class="formulaire">
                <?= csrf_field() ?>
                <div class="champ">
                    <label for="prenom">Nouveau prénom</label>
                    <input type="text" id="prenom" name="prenom" maxlength="30" required
                           autocomplete="given-name" value="<?= e($prenom) ?>">
                </div>
                <p class="actions">
                    <button type="submit" class="bouton bouton--secondaire">Enregistrer le prénom</button>
                </p>
            </form>
            <p class="aide">
                La signature des messages déjà envoyés ne change pas : l’historique
                reste compréhensible pour tout le monde.
            </p>
        </details>

        <div class="tchat" id="tchat"
             data-tchat="1"
             data-signature="<?= e($signature) ?>"
             data-apres="<?= (int) ($bloc['dernier'] ?? 0) ?>"
             data-premier="<?= (int) $premier ?>"
             data-jour="<?= e($bloc['jour'] ?? '') ?>"
             data-reste-avant="<?= $resteAvant ? '1' : '0' ?>">

            <p class="tchat__etat" id="tchat-etat" role="status" aria-live="polite"></p>

            <p class="tchat__plus-anciens" id="tchat-plus-anciens"<?= $resteAvant ? '' : ' hidden' ?>>
                <button type="button" class="lien-bouton" data-voir-anciens>Voir les messages plus anciens</button>
            </p>

            <div class="tchat__messages" id="tchat-messages" aria-live="polite" aria-label="Messages">
                <?php if ($bloc['nombre'] === 0): ?>
                    <p class="tchat__vide" id="tchat-vide">
                        Aucun message pour le moment. Écrivez le premier !
                    </p>
                <?php else: ?>
                    <?= $bloc['html'] ?>
                <?php endif; ?>
            </div>

            <p class="tchat__nouveaux" id="tchat-nouveaux" hidden>
                <button type="button" class="bouton bouton--secondaire" data-vers-bas>
                    De nouveaux messages sont arrivés ↓
                </button>
            </p>
        </div>

        <form method="post" action="/tchat/envoyer" class="tchat__envoi" id="tchat-envoi" data-tchat-envoi>
            <?= csrf_field() ?>
            <div class="champ">
                <label for="message" class="tchat__etiquette">Votre message</label>
                <textarea id="message" name="message" rows="3" maxlength="1000" required
                          placeholder="Écrivez ici, puis appuyez sur « Envoyer »."
                          data-tchat-texte></textarea>
                <p class="aide tchat__compteur" id="tchat-compteur" aria-live="polite">0 / 1000 caractères</p>
            </div>
            <p class="actions">
                <button type="submit" class="bouton">Envoyer</button>
                <span class="tchat__erreur" id="tchat-erreur" role="alert"></span>
            </p>
        </form>

        <p class="aide">
            Le salon se met à jour tout seul toutes les 5 secondes (toutes les 30 secondes
            quand il ne se passe rien, et il s’arrête quand vous quittez la page).
            Accepté : texte brut, 1000 caractères, une adresse web devient un lien.
            <?php if ($estAdmin): ?>
                En tant qu’administrateur, vous pouvez supprimer un message : la suppression
                est inscrite dans le journal d’activité.
            <?php endif; ?>
        </p>
    <?php endif; ?>
</section>
