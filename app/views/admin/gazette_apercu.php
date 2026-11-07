<?php
/**
 * Aperçu d'un numéro, avec les gestes de l'éditeur.
 *
 * L'aperçu reprend exactement le contenu qui partira : les mêmes encarts, les
 * mêmes éléments, dans le même ordre. Seuls les boutons « Retirer » sont en
 * plus, et les éléments retirés sont listés à part.
 *
 * @var array  $gazette, $memes, $retenus
 * @var int    $motMax
 */
$contenu = $gazette['apercu'];
$periode = (string) $gazette['periode'];
$ouvert  = $gazette['status'] === 'a_valider';
$sections = [];
foreach ((array) $contenu['sections'] as $section) {
    if (($section['items'] ?? []) !== []) {
        $sections[] = $section;
    }
}
$retires = [];
foreach ($gazette['contenu_array']['sections'] as $section) {
    foreach ((array) $section['items'] as $item) {
        if (isset($retenus[(string) $item['cle']])) {
            $retires[] = $item;
        }
    }
}
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/admin">Administration</a>
    <span aria-hidden="true">/</span>
    <a href="/admin/gazette">Gazette du mois</a>
    <span aria-hidden="true">/</span>
    <span><?= e(gazette_mois_libelle($periode)) ?></span>
</nav>

<div class="section__entete">
    <div>
        <h1>Gazette de <?= e(gazette_mois_libelle($periode)) ?></h1>
        <p class="note">
            Destinataires : <strong><?= e(gazette_groupe_libelle((string) $gazette['groupe'])) ?></strong>.
            État : <strong><?= $ouvert ? 'à valider' : ($gazette['status'] === 'envoye' ? 'envoyé' : 'passé') ?></strong>.
        </p>
    </div>
</div>

<?php if ($memes !== []): ?>
    <p class="note">
        Autres numéros du même mois :
        <?php foreach ($memes as $autre): ?>
            <a class="lien" href="/admin/gazette/<?= (int) $autre['id'] ?>">
                <?= e(gazette_groupe_libelle((string) $autre['groupe'])) ?>
            </a>
        <?php endforeach; ?>
    </p>
<?php endif; ?>

<?php if ($ouvert): ?>
    <section class="panneau">
        <h2>Le mot de l’éditeur</h2>
        <form method="post" action="/admin/gazette/<?= (int) $gazette['id'] ?>/mot" class="formulaire">
            <?= csrf_field() ?>
            <div class="champ">
                <label for="mot">Quelques mots pour ouvrir le numéro (facultatif)</label>
                <textarea id="mot" name="mot" rows="4" maxlength="<?= (int) $motMax ?>"><?= e((string) $gazette['mot']) ?></textarea>
                <p class="aide"><?= (int) $motMax ?> caractères au plus. Vide : le numéro commence directement par les encarts.</p>
            </div>
            <p class="actions">
                <button type="submit" class="bouton bouton--secondaire">Enregistrer le mot</button>
            </p>
        </form>
    </section>
<?php elseif (trim((string) $gazette['mot']) !== ''): ?>
    <section class="panneau">
        <h2>Le mot de l’éditeur</h2>
        <p><?= nl2br(e((string) $gazette['mot'])) ?></p>
    </section>
<?php endif; ?>

<?php if ($sections === []): ?>
    <div class="message message--alerte" role="alert">
        <strong>Ce numéro est vide.</strong>
        Tous ses éléments ont été retirés : il ne peut pas être envoyé.
    </div>
<?php endif; ?>

<?php foreach ($sections as $section): ?>
    <section class="panneau gazette__encart" id="encart-<?= e((string) $section['cle']) ?>">
        <h2><?= e(gazette_section_titre((string) $section['cle'])) ?></h2>
        <ul class="gazette__liste">
            <?php foreach ((array) $section['items'] as $item): ?>
                <li class="gazette__element">
                    <a class="gazette__titre" href="<?= e((string) $item['url']) ?>"><?= e((string) $item['titre']) ?></a>
                    <?php if (trim((string) $item['meta']) !== ''): ?>
                        <span class="gazette__meta"><?= e((string) $item['meta']) ?></span>
                    <?php endif; ?>
                    <?php if (trim((string) $item['date']) !== ''): ?>
                        <span class="gazette__date"><?= e((string) $item['date']) ?></span>
                    <?php endif; ?>
                    <?php if (trim((string) ($item['resume'] ?? '')) !== ''): ?>
                        <p class="gazette__resume"><?= e((string) $item['resume']) ?></p>
                    <?php endif; ?>
                    <?php if ($ouvert): ?>
                        <form method="post" action="/admin/gazette/<?= (int) $gazette['id'] ?>/retirer" class="gazette__geste">
                            <?= csrf_field() ?>
                            <input type="hidden" name="cle" value="<?= e((string) $item['cle']) ?>">
                            <button type="submit" class="bouton bouton--secondaire">Retirer du numéro</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endforeach; ?>

<?php if ($retires !== []): ?>
    <section class="panneau">
        <h2>Éléments retirés</h2>
        <p class="aide">
            Ils ne figureront ni dans l’aperçu ci-dessus<?= site_courriels_actifs() ? ', ni dans le courriel' : '' ?>.
        </p>
        <ul class="gazette__liste">
            <?php foreach ($retires as $item): ?>
                <li class="gazette__element gazette__element--retire">
                    <span class="gazette__titre"><?= e((string) $item['titre']) ?></span>
                    <?php if ($ouvert): ?>
                        <form method="post" action="/admin/gazette/<?= (int) $gazette['id'] ?>/retirer" class="gazette__geste">
                            <?= csrf_field() ?>
                            <input type="hidden" name="cle" value="<?= e((string) $item['cle']) ?>">
                            <input type="hidden" name="geste" value="remettre">
                            <button type="submit" class="bouton bouton--secondaire">Remettre</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ((array) $contenu['chiffres'] !== []): ?>
    <section class="panneau">
        <h2><?= e(apparence_titre_encart('chiffres')) ?></h2>
        <div class="grille-compteurs">
            <?php foreach ((array) $contenu['chiffres'] as $chiffre): ?>
                <div class="compteur">
                    <span class="compteur__valeur"><?= (int) $chiffre['valeur'] ?></span>
                    <span class="compteur__libelle"><?= e((string) $chiffre['libelle']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($ouvert): ?>
    <section class="panneau">
        <h2>Envoyer, ou passer</h2>
        <?php if (site_courriels_actifs()): ?>
            <p>
                « Envoyer » met un message en file pour chaque personne de ce groupe qui a coché
                « Je veux recevoir la gazette ». Les messages partent dans les cinq minutes, par
                la tâche automatique — jamais pendant une visite du site.
            </p>
        <?php else: ?>
            <p>
                <strong>Les courriels sont désactivés :</strong> ce numéro ne part à personne. Il
                reste consultable <a class="lien" href="/gazette">en ligne</a>, et
                « Passer ce mois-ci » le classe sans rien envoyer.
            </p>
        <?php endif; ?>
        <div class="actions">
            <?php if (site_courriels_actifs()): ?>
            <form method="post" action="/admin/gazette/<?= (int) $gazette['id'] ?>/envoyer">
                <?= csrf_field() ?>
                <button type="submit" class="bouton" <?= $sections === [] ? 'disabled' : '' ?>>
                    Envoyer la gazette
                </button>
            </form>
            <?php endif; ?>
            <form method="post" action="/admin/gazette/<?= (int) $gazette['id'] ?>/passer">
                <?= csrf_field() ?>
                <button type="submit" class="bouton bouton--secondaire">Passer ce mois-ci</button>
            </form>
        </div>
    </section>
<?php endif; ?>
