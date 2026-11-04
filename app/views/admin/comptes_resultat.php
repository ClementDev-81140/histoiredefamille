<?php
/**
 * Écran de résultat : les mots de passe provisoires, affichés UNE SEULE FOIS.
 *
 * @var array $comptes
 * @var bool  $creation, $expire
 */
?>
<?php if ($expire): ?>
    <div class="section__entete">
        <div>
            <h1>Mots de passe non affichés</h1>
            <p class="note">Cet écran ne s’affiche qu’une seule fois.</p>
        </div>
    </div>

    <div class="message message--info">
        <p>
            Pour protéger la famille, les mots de passe provisoires ne sont plus disponibles :
            ils n’existent nulle part en clair, ni dans la base, ni dans le journal.
        </p>
        <p>
            Une personne a perdu son mot de passe ? Ouvrez sa fiche dans « Comptes de la famille »
            et utilisez « Nouveau mot de passe » : un mot de passe provisoire s’affichera de nouveau,
            une seule fois.
        </p>
    </div>

    <p class="actions">
        <a class="bouton" href="/admin/comptes">Retour aux comptes</a>
    </p>
<?php else: ?>
    <div class="section__entete">
        <div>
            <h1><?= $creation ? 'Comptes créés' : 'Nouveau mot de passe' ?></h1>
            <p class="note">Transmettez le message à chaque personne, par SMS, WhatsApp ou courriel.</p>
        </div>
        <a class="lien" href="/admin/comptes">← Retour aux comptes</a>
    </div>

    <div class="message comptes-avertissement">
        <p>
            <strong>Les mots de passe ne seront plus affichés après cet écran : copiez-les maintenant.</strong>
        </p>
        <p>
            Ils ne sont conservés nulle part en clair (ni dans la base, ni dans le journal).
            En cas d’oubli, il suffira d’en générer un nouveau depuis la fiche de la personne.
        </p>
    </div>

    <p class="actions">
        <button type="button" class="bouton" data-copier-tout=".message-acces__texte">
            Tout copier
        </button>
    </p>

    <div class="tableau-defilant">
        <table class="tableau comptes-resultat">
            <thead>
                <tr>
                    <th scope="col">Nom</th>
                    <th scope="col">Identifiant</th>
                    <th scope="col">Mot de passe provisoire</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($comptes as $index => $compte): ?>
                    <tr>
                        <td><?= e(compte_nom_complet(['first_name' => (string) ($compte['prenom'] ?? ''), 'last_name' => (string) ($compte['nom'] ?? '')])) ?></td>
                        <td><code><?= e((string) $compte['identifiant']) ?></code></td>
                        <td><code class="mot-de-passe-genere"><?= e((string) $compte['mot_de_passe']) ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <section class="comptes-messages">
        <h2>Messages à transmettre</h2>

        <?php foreach ($comptes as $index => $compte): ?>
            <div class="message-acces">
                <p class="message-acces__titre">
                    <?= e(compte_nom_complet(['first_name' => (string) ($compte['prenom'] ?? ''), 'last_name' => (string) ($compte['nom'] ?? '')])) ?>
                    — <code><?= e((string) $compte['identifiant']) ?></code>
                </p>
                <pre class="message-acces__texte" id="message-acces-<?= (int) $index ?>"><?= e((string) $compte['message']) ?></pre>
                <p class="actions">
                    <button type="button" class="bouton bouton--secondaire"
                            data-copier="#message-acces-<?= (int) $index ?>">
                        Copier le message
                    </button>
                </p>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
