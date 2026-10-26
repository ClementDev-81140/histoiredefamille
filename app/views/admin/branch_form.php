<?php
/**
 * Formulaire de branche.
 *
 * @var array|null $branch
 * @var array|null $account
 * @var array      $erreurs, $old
 * @var string     $generated
 */
$isEdit = $branch !== null;
$v = static function (string $key, mixed $default = '') use ($old, $branch, $isEdit) {
    $base = $isEdit && isset($branch[$key]) ? $branch[$key] : $default;
    return old_value($old, $key, $base);
};
$seeAll   = (bool) old_value($old, 'see_all', $isEdit ? (int) $branch['see_all'] === 1 : false);
$isActive = (bool) old_value($old, 'is_active', $isEdit ? (int) $branch['is_active'] === 1 : true);
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/admin/branches">Branches</a>
    <span aria-hidden="true">/</span>
    <span><?= $isEdit ? 'Modifier' : 'Nouvelle branche' ?></span>
</nav>

<h1><?= e($titre) ?></h1>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post"
      action="<?= $isEdit ? '/admin/branches/' . (int) $branch['id'] : '/admin/branches/nouvelle' ?>"
      class="formulaire panneau">
    <?= csrf_field() ?>

    <div class="ligne-champs">
        <div class="champ">
            <label for="code">Identifiant de connexion <span class="obligatoire">*</span></label>
            <input type="text" id="code" name="code" required maxlength="30"
                   pattern="[A-Z][A-Z0-9_]{1,29}"
                   value="<?= e((string) $v('code')) ?>"
                   autocapitalize="characters" placeholder="RAVIART">
            <p class="aide">Majuscules uniquement (lettres, chiffres, tiret bas). C’est l’identifiant du compte.</p>
        </div>

        <div class="champ">
            <label for="name">Nom de la branche <span class="obligatoire">*</span></label>
            <input type="text" id="name" name="name" required maxlength="100" value="<?= e((string) $v('name')) ?>"
                   placeholder="Famille Raviart">
        </div>
    </div>

    <div class="champ">
        <label for="description">Description (interne)</label>
        <textarea id="description" name="description" rows="2"><?= e((string) $v('description')) ?></textarea>
    </div>

    <div class="champ">
        <label for="password">
            <?= $isEdit ? 'Nouveau mot de passe (facultatif)' : 'Mot de passe (laisser vide pour en générer un)' ?>
        </label>
        <input type="text" id="password" name="password" minlength="10" autocomplete="new-password"
               value="<?= e((string) old_value($old, 'password', '')) ?>"
               placeholder="10 caractères minimum, ou vide pour générer">
        <p class="aide">
            Suggestion générée aléatoirement : <code><?= e($generated) ?></code>
            (recopiez-la ici si elle vous convient).
            <?php if ($isEdit): ?>Laisser vide conserve le mot de passe actuel.<?php endif; ?>
        </p>
    </div>

    <fieldset class="champ">
        <legend>Droits et état</legend>

        <label class="case">
            <input type="checkbox" name="see_all" value="1"<?= checked($seeAll) ?>>
            Voit <strong>toutes les branches</strong> (tous les contenus ouverts aux familles)
        </label>
        <p class="aide">
            Sans cette case, la branche ne voit que : les contenus publics, ceux ouverts à
            toutes les familles et ceux où elle est cochée.
        </p>

        <label class="case">
            <input type="checkbox" name="is_active" value="1"<?= checked($isActive) ?>>
            Compte actif (décochez pour empêcher la connexion)
        </label>
    </fieldset>

    <p class="actions">
        <button type="submit" class="bouton"><?= $isEdit ? 'Enregistrer' : 'Créer la branche' ?></button>
        <a class="bouton bouton--secondaire" href="/admin/branches">Annuler</a>
    </p>
</form>

<?php if ($isEdit && $account !== null): ?>
    <section class="panneau">
        <h2>Compte associé</h2>
        <dl class="fiche">
            <dt>Identifiant</dt><dd><code><?= e((string) $account['username']) ?></code></dd>
            <dt>Dernière connexion</dt><dd><?= e(format_date((int) ($account['last_login_at'] ?? 0), true)) ?></dd>
            <dt>État</dt><dd><?= (int) $account['is_active'] === 1 ? 'actif' : 'désactivé' ?></dd>
        </dl>
        <form method="post" action="/admin/branches/<?= (int) $branch['id'] ?>/mot-de-passe"
              class="formulaire-en-ligne"
              data-confirmation="Générer un nouveau mot de passe ? L’ancien ne fonctionnera plus.">
            <?= csrf_field() ?>
            <button type="submit" class="bouton bouton--secondaire">Générer un nouveau mot de passe</button>
        </form>
    </section>
<?php endif; ?>
