<?php
/**
 * Lecture d'un numéro de la gazette du mois.
 *
 * @var string $titre, $periode
 * @var array  $gazette  (ligne + contenu_array + retires + apercu)
 */
$contenu  = $gazette['apercu'];
$libelle  = gazette_mois_libelle($periode);
$envoye   = $gazette['status'] === 'envoye';
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/">Accueil</a>
    <span aria-hidden="true">/</span>
    <span>La gazette de <?= e($libelle) ?></span>
</nav>

<article class="gazette" data-periode="<?= e($periode) ?>">
    <header class="gazette__entete">
        <p class="gazette__surtitre">Numéro du mois</p>
        <h1>La gazette de <?= e($libelle) ?></h1>
        <?php if (!$envoye): ?>
            <p class="note">Aperçu : ce numéro n’a pas encore été envoyé.</p>
        <?php endif; ?>
    </header>

    <?php if (trim((string) $gazette['mot']) !== ''): ?>
        <section class="panneau gazette__mot">
            <h2>Le mot de l’éditeur</h2>
            <p><?= nl2br(e((string) $gazette['mot'])) ?></p>
        </section>
    <?php endif; ?>

    <?php
    $sections = [];
    foreach ((array) $contenu['sections'] as $section) {
        if (($section['items'] ?? []) !== []) {
            $sections[] = $section;
        }
    }
    ?>

    <div class="gazette__sommaire">
        <?php foreach ($sections as $section): ?>
            <a class="lien" href="#encart-<?= e((string) $section['cle']) ?>">
                <?= e(gazette_section_titre((string) $section['cle'])) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php foreach ($sections as $section): ?>
        <section class="panneau gazette__encart" id="encart-<?= e((string) $section['cle']) ?>">
            <h2><?= e(gazette_section_titre((string) $section['cle'])) ?></h2>
            <ul class="gazette__liste">
                <?php foreach ((array) $section['items'] as $item): ?>
                    <li class="gazette__element">
                        <a class="gazette__titre" href="<?= e((string) $item['url']) ?>">
                            <?= e((string) $item['titre']) ?>
                        </a>
                        <?php if (trim((string) $item['meta']) !== ''): ?>
                            <span class="gazette__meta"><?= e((string) $item['meta']) ?></span>
                        <?php endif; ?>
                        <?php if (trim((string) $item['date']) !== ''): ?>
                            <span class="gazette__date"><?= e((string) $item['date']) ?></span>
                        <?php endif; ?>
                        <?php if (trim((string) ($item['resume'] ?? '')) !== ''): ?>
                            <p class="gazette__resume"><?= e((string) $item['resume']) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>

    <?php if ((array) $contenu['chiffres'] !== []): ?>
        <section class="panneau gazette__chiffres">
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

    <p class="note">
        <a class="lien" href="/mon-compte">Gérer mes courriels</a>
    </p>
</article>
