<?php
/**
 * Ajouter des personnes : une seule, ou toute une liste collée.
 *
 * Les deux blocs envoient vers l'écran d'aperçu (aucune création directe).
 *
 * @var array $erreurs, $old, $branches
 * @var int   $maximum
 */
$val = static fn (string $cle, string $defaut = ''): string => (string) old_value($old, $cle, $defaut);
?>
<div class="section__entete">
    <div>
        <h1>Ajouter des personnes</h1>
        <p class="note">
            Chaque personne reçoit un identifiant (prénom.nom) et un mot de passe provisoire
            que vous lui transmettrez. Rien n’est créé avant l’écran d’aperçu.
        </p>
    </div>
    <a class="lien" href="/admin/comptes">← Retour aux comptes</a>
</div>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul>
            <?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<section class="panneau comptes-bloc">
    <h2>Une personne</h2>

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
                      placeholder="Martin;Élise&#10;Durand;Paul&#10;Le Gall;Jean-Paul"><?= e($val('liste')) ?></textarea>
            <p class="aide">
                Formats acceptés : « Nom;Prénom » (le plus sûr), ou « Prénom Nom » sur deux mots.
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
