<?php
/**
 * Essais « doublons » (1.38.0) : les pages clés ne doivent jamais afficher
 * deux fois le même bloc d'avertissement, le même titre ou le même bouton.
 *
 * Complète le contrôle navigateur (tools/navigateur/actions-doublons.mjs),
 * qui parcourt toutes les pages rendues aux deux largeurs et aux deux
 * thèmes. Ici, on vérifie la structure du HTML de cinq pages clés, rendues
 * avec les données fictives de la matrice d'accès (transaction annulée) :
 * accueil visiteur, accueil connecté, tableau de bord, page d'article et
 * formulaire d'envoi d'une proposition.
 */

declare(strict_types=1);

require_once HDF_APP . '/lib/matrice_acces.php';

/** Texte visible d'un fragment HTML, normalisé pour comparer. */
function doublons_texte(string $html): string
{
    $texte = strip_tags($html);
    $texte = html_entity_decode($texte, ENT_QUOTES, 'UTF-8');
    return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $texte)));
}

/**
 * Les doublons structurels d'une page rendue.
 *
 * @return list<string> descriptions des problèmes (vide = page propre)
 */
function doublons_problemes(string $html): array
{
    $problemes = [];

    // Un seul <h1>.
    $nbH1 = preg_match_all('/<h1[\s>]/i', $html);
    if ($nbH1 !== 1) {
        $problemes[] = "h1 : {$nbH1} trouvé(s), 1 attendu";
    }

    // Aucun identifiant HTML dupliqué.
    preg_match_all('/\sid="([^"]+)"/', $html, $m);
    foreach (array_count_values($m[1]) as $id => $n) {
        if ($n > 1) {
            $problemes[] = "id « {$id} » utilisé {$n} fois";
        }
    }

    // Aucun titre h2/h3 répété.
    preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/is', $html, $m);
    $titres = [];
    foreach ($m[1] as $titreHtml) {
        $titre = doublons_texte($titreHtml);
        if (mb_strlen($titre) >= 3) {
            $titres[$titre] = ($titres[$titre] ?? 0) + 1;
        }
    }
    foreach ($titres as $titre => $n) {
        if ($n > 1) {
            $problemes[] = "titre « {$titre} » répété {$n} fois";
        }
    }

    // Aucun lien (même libellé + même destination) plus de deux fois :
    // deux occurrences peuvent être un bouton haut/bas de longue liste,
    // trois n'ont pas de justification.
    preg_match_all('/<a\s[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', $html, $m, PREG_SET_ORDER);
    $liens = [];
    foreach ($m as $lien) {
        $libelle = doublons_texte($lien[2]);
        if (mb_strlen($libelle) >= 2) {
            $cle = $libelle . ' → ' . html_entity_decode($lien[1], ENT_QUOTES, 'UTF-8');
            $liens[$cle] = ($liens[$cle] ?? 0) + 1;
        }
    }
    foreach ($liens as $cle => $n) {
        if ($n > 2) {
            $problemes[] = "lien « {$cle} » présent {$n} fois";
        }
    }

    return $problemes;
}

function tests_doublons(): void
{
    echo "Doublons — pages clés sans bloc, titre ni bouton répétés\n";

    $pdo = db();
    $f = null;
    try {
        $pdo->beginTransaction();
        $f = matrice_fixtures();

        $pages = [
            'accueil visiteur' => ['GET', '/', null],
            'accueil connecté' => ['GET', '/', (int) $f['compte_branche_a']],
            'tableau de bord'  => ['GET', '/admin', (int) $f['account_admin']],
            'page article'     => ['GET', matrice_url('/articles/{slug}', $f), (int) $f['compte_branche_a']],
            'formulaire d’envoi' => ['GET', matrice_url('/propositions/nouvelle/{type}', $f), (int) $f['compte_personne_b']],
        ];

        $rendues = [];
        foreach ($pages as $nom => [$methode, $url, $compte]) {
            $_SESSION = $compte === null ? [] : ['account_id' => $compte, '_csrf' => 'jeton-essai-doublons'];
            $reponse = matrice_requete($methode, $url, $compte);
            verifier("doublons : la page « {$nom} » se rend (200)", $reponse['code'] === 200, (string) $reponse['code']);
            $rendues[$nom] = $reponse['corps'];

            $problemes = doublons_problemes($reponse['corps']);
            verifier(
                "doublons : aucun titre, id ni lien répété sur « {$nom} »",
                $problemes === [],
                implode(' ; ', $problemes)
            );
        }

        // Le cas signalé n° 1 : l'avertissement de sauvegarde ne doit
        // apparaître qu'une fois sur le tableau de bord (le bandeau de la
        // mise en page d'administration), jamais en plus dans la page.
        $tableau = $rendues['tableau de bord'] ?? '';
        preg_match_all('/<div class="message[^"]*"[^>]*>(.*?)<\/div>/is', $tableau, $m);
        $blocsSauvegarde = 0;
        foreach ($m[1] as $bloc) {
            if (str_contains(doublons_texte($bloc), 'sauvegarde')) {
                $blocsSauvegarde++;
            }
        }
        verifier(
            'doublons : un seul bloc « sauvegarde » sur le tableau de bord',
            $blocsSauvegarde <= 1 && !str_contains($tableau, 'Aucune sauvegarde enregistrée pour l’instant'),
            $blocsSauvegarde . ' bloc(s)'
        );

        // Le cas signalé n° 2 : sur l'accueil connecté, chaque section du
        // bloc « Dans la famille » n'est rendue qu'une fois (le même
        // <details> sert le téléphone et le grand écran).
        $accueil = $rendues['accueil connecté'] ?? '';
        foreach (['Anniversaires', 'Personnes ajoutées', 'Depuis votre dernière visite'] as $section) {
            verifier(
                "doublons : « {$section} » une seule fois sur l'accueil connecté",
                substr_count($accueil, '<h3>' . $section . '</h3>') <= 1,
                (string) substr_count($accueil, '<h3>' . $section . '</h3>')
            );
        }
        $debut = strpos($accueil, 'class="dans-la-famille');
        $blocFamille = $debut === false ? '' : substr($accueil, $debut, 20000);
        verifier(
            'doublons : un seul <details> dans le bloc « Dans la famille »',
            substr_count($blocFamille, '<details') <= 1 && substr_count($blocFamille, 'accueil-famille__bloc') <= 1,
            (string) substr_count($blocFamille, '<details')
        );

        // Formulaire d'envoi : un seul bouton d'envoi principal (le
        // brouillon, secondaire, est une autre action).
        $envoi = $rendues['formulaire d’envoi'] ?? '';
        preg_match_all('/<button[^>]*type="submit"[^>]*>(.*?)<\/button>/is', $envoi, $m);
        $envois = 0;
        foreach ($m[1] as $libelle) {
            $texteBouton = doublons_texte($libelle);
            if (str_contains($texteBouton, 'envoyer') || str_contains($texteBouton, 'proposer')) {
                $envois++;
            }
        }
        verifier('doublons : un seul bouton d’envoi principal dans le formulaire d’envoi', $envois === 1, (string) $envois);
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        matrice_supprimer_fichiers(is_array($f) ? $f : []);
    }
}
