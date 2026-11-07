<?php
/**
 * Ajouter un compte : le profil d'abord, puis les champs qui vont avec.
 *
 *   - Lecteur (proposé par défaut) : la branche et son compte partagé ;
 *   - Journaliste : les personnes (une seule, ou une liste collée), qui
 *     passent par l'écran d'aperçu avant toute création.
 *
 * @var array  $erreurs, $old, $branches
 * @var int    $maximum
 * @var string $profil  « lecteur » ou « journaliste »
 */
$val = static fn (string $cle, string $defaut = ''): string => (string) old_value($old, $cle, $defaut);
?>
<div class="section__entete">
    <div>
        <h1>Ajouter un compte</h1>
    </div>
    <a class="lien" href="/admin/comptes">← Retour aux comptes</a>
</div>

<p class="recherche__onglets">
    <a class="<?= $profil === 'lecteur' ? 'actif' : '' ?>" href="/admin/comptes/nouvelle?profil=lecteur">Lecteur</a>
    <a class="<?= $profil === 'journaliste' ? 'actif' : '' ?>" href="/admin/comptes/nouvelle?profil=journaliste">Journaliste</a>
</p>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul>
            <?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($profil === 'lecteur'): ?>
<section class="panneau comptes-bloc">
    <h2>Un lecteur (la branche et son compte)</h2>
    <p class="aide">
        Un lecteur est le compte partagé d’une branche : il lit ce que sa branche a le droit
        de voir et ne peut rien écrire ni proposer. Créer un lecteur crée donc aussi sa branche.
        Un seul lecteur par branche ; pour une personne de la famille qui souhaite proposer des
        contenus, choisissez le profil « Journaliste ».
    </p>

    <form method="post" action="/admin/comptes/lecteur" class="formulaire">
        <?= csrf_field() ?>

        <div class="ligne-champs">
            <div class="champ">
                <label for="code">Identifiant de connexion</label>
                <input type="text" id="code" name="code" maxlength="30" required
                       pattern="[A-Z][A-Z0-9_]{1,29}" placeholder="MARTIN"
                       autocapitalize="characters" spellcheck="false" value="<?= e($val('code')) ?>">
            </div>
            <div class="champ">
                <label for="name">Nom de la branche</label>
                <input type="text" id="name" name="name" maxlength="100" required
                       placeholder="Famille Martin" value="<?= e($val('name')) ?>">
            </div>
        </div>

        <label class="case">
            <input type="checkbox" name="see_all" value="1" <?= checked($val('see_all') === '1') ?>>
            Cette branche voit toutes les branches
        </label>

        <p class="actions">
            <button type="submit" class="bouton">Créer le lecteur</button>
        </p>
    </form>
</section>
<?php else: ?>
<section class="panneau comptes-bloc">
    <h2>Un journaliste</h2>

    <form method="post" action="/admin/comptes/apercu" class="formulaire">
        <?= csrf_field() ?>
        <input type="hidden" name="mode" value="une">

        <div class="ligne-champs">
            <div class="champ">
                <label for="nom">Nom</label>
                <input type="text" id="nom" name="nom" maxlength="40" required
                       autocomplete="family-name" value="<?= e($val('nom')) ?>">
            </div>
            <div class="champ">
                <label for="prenom">Prénom</label>
                <input type="text" id="prenom" name="prenom" maxlength="40" required
                       autocomplete="given-name" value="<?= e($val('prenom')) ?>">
            </div>
        </div>

        <?php if (site_courriels_actifs()): ?>
        <div class="champ">
            <label for="email">Adresse électronique (facultatif)</label>
            <input type="email" id="email" name="email" maxlength="190"
                   autocomplete="email" value="<?= e($val('email')) ?>">
            <p class="aide">
                Sert uniquement si la personne demande à être prévenue des nouveaux articles ou
                à recevoir la gazette. Une adresse saisie ici ne vaut pas accord : c’est la
                personne qui coche ses cases dans « Mon compte ».
            </p>
        </div>
        <?php endif; ?>

        <div class="champ">
            <label for="branche_une">Branche</label>
            <select id="branche_une" name="branche" required>
                <option value="">Choisir une branche…</option>
                <?php foreach ($branches as $branche): ?>
                    <option value="<?= (int) $branche['id'] ?>"<?= selected((int) $val('branche') === (int) $branche['id']) ?>>
                        <?= e($branche['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="aide">
                La personne verra les contenus publics, ceux ouverts à toutes les familles,
                et ceux réservés à cette branche.
            </p>
        </div>

        <label class="case">
            <input type="checkbox" name="see_all" value="1" <?= checked($val('see_all') === '1') ?>>
            Voit toutes les branches
        </label>
        <p class="aide">
            À cocher au cas par cas : un compte de branche qui voit tout ne transmet pas ce
            droit aux personnes.
        </p>

        <p class="actions">
            <button type="submit" class="bouton">Voir l’aperçu</button>
        </p>
    </form>
</section>

<section class="panneau comptes-bloc">
    <h2>Plusieurs personnes</h2>

    <form method="post" action="/admin/comptes/apercu" class="formulaire">
        <?= csrf_field() ?>
        <input type="hidden" name="mode" value="liste">

        <div class="champ">
            <label for="liste">La liste, une personne par ligne</label>
            <textarea id="liste" name="liste" rows="10" required spellcheck="false"
                      placeholder="Martin;Élise&#10;Durand;Paul&#10;Le Gall;Jean-Paul;jean-paul@exemple.fr"><?= e($val('liste')) ?></textarea>
            <p class="aide">
                Formats acceptés : « Nom;Prénom » (le plus sûr), « Nom;Prénom;adresse » pour
                ajouter l’adresse électronique, ou « Prénom Nom » sur deux mots.
                Dès qu’un nom ou un prénom compte plusieurs mots, écrivez « Nom;Prénom » :
                par exemple <code>Le Gall;Jean-Paul</code>.
                <?= (int) $maximum ?> personnes au maximum par envoi.
            </p>
        </div>

        <div class="champ">
            <label for="branche_liste">Branche commune</label>
            <select id="branche_liste" name="branche" required>
                <option value="">Choisir une branche…</option>
                <?php foreach ($branches as $branche): ?>
                    <option value="<?= (int) $branche['id'] ?>"<?= selected((int) $val('branche') === (int) $branche['id']) ?>>
                        <?= e($branche['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="aide">Toutes les personnes de la liste iront dans cette branche.</p>
        </div>

        <label class="case">
            <input type="checkbox" name="see_all" value="1" <?= checked($val('see_all') === '1') ?>>
            Toutes ces personnes voient toutes les branches
        </label>

        <p class="actions">
            <button type="submit" class="bouton">Voir l’aperçu</button>
        </p>
    </form>
</section>
<?php endif; ?>
