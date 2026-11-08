<?php
/**
 * Une ligne de résultat de la recherche générale.
 *
 * @var array  $ligne résultat (document, article, page ou personne)
 * @var string $cle   thème : documents, articles, pages, personnes
 */
$lien    = '';
$titre   = '';
$meta    = [];
$extrait = '';
$visibilite = '';

switch ($cle) {
    case 'documents':
        $lien  = document_url($ligne);
        $titre = (string) $ligne['title'];
        $meta[] = document_kind_label((string) $ligne['kind'], (string) $ligne['ext']);
        if (trim((string) $ligne['doc_date']) !== '') {
            $meta[] = (string) $ligne['doc_date'];
        }
        $extrait = text_summary((string) ($ligne['description'] ?? ''), 160);
        $visibilite = (string) $ligne['visibility'];
        break;

    case 'articles':
        $lien  = '/articles/' . $ligne['slug'];
        $titre = (string) $ligne['title'];
        if (trim((string) $ligne['category']) !== '') {
            $meta[] = (string) $ligne['category'];
        }
        $meta[] = french_long_date((int) $ligne['published_at'], false);
        $extrait = trim((string) $ligne['summary']) !== ''
            ? text_to_plain((string) $ligne['summary'])
            : text_summary((string) $ligne['body'], 160);
        $visibilite = (string) $ligne['visibility'];
        break;

    case 'pages':
        $lien  = '/pages/' . $ligne['slug'];
        $titre = (string) $ligne['title'];
        $extrait = text_summary((string) $ligne['body'], 160);
        $visibilite = (string) $ligne['visibility'];
        break;

    case 'personnes':
        $lien  = '/arbre/' . (int) $ligne['id'];
        $titre = person_full_name($ligne);
        $dates = person_dates($ligne);
        if ($dates !== '') {
            $meta[] = $dates;
        }
        $lieux = array_filter([
            trim((string) $ligne['birth_place']),
            trim((string) $ligne['death_place']),
        ]);
        if ($lieux !== []) {
            $extrait = implode(' · ', $lieux);
        }
        $visibilite = (string) $ligne['visibility'];
        break;
}
?>
<li class="resultat">
    <?php if ($meta !== []): ?>
        <p class="resultat__meta"><?= e(implode(' · ', $meta)) ?></p>
    <?php endif; ?>
    <h3 class="resultat__titre"><a href="<?= e($lien) ?>"><?= e($titre) ?></a></h3>
    <?php if ($extrait !== ''): ?>
        <p class="resultat__extrait"><?= e($extrait) ?></p>
    <?php endif; ?>
    <?php if (is_logged_in() && $visibilite !== 'public'): ?>
        <p class="resultat__visibilite">
            <span class="<?= e(visibility_class($visibilite)) ?>"><?= e(visibility_short($visibilite)) ?></span>
        </p>
    <?php endif; ?>
</li>
