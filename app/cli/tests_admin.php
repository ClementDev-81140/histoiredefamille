<?php
/**
 * Tests de l'administration (correctif 1.25.2).
 *
 * Trois sujets, une même idée : **l'administration ne parle plus de « partie
 * publique » ni de « partie famille »**, parce qu'une page sert souvent les
 * deux. Le menu range le travail par nature, le tableau de bord tient en une
 * carte « Contenus », et chaque liste porte un filtre « Visibilité ».
 *
 * Aucun droit n'est modifié : ces tests vérifient l'affichage et les filtres,
 * pas les règles d'accès (la matrice d'accès s'en charge).
 */

declare(strict_types=1);

/** Toute la famille « admin ». */
function tests_admin(): void
{
    echo "Administration — menu, tableau de bord et filtres de visibilité\n";

    // Le lien « Courriels » du menu n'existe que si l'envoi est réactivé
    // (1.28.0). Cette famille vérifie qu'aucun écran n'a été perdu : elle a
    // donc besoin des courriels allumés, et le réglage est rendu à la fin.
    tests_courriels_activer();

    try {
        db_transaction(static function (): void {
            tests_admin_menu();
            tests_admin_tableau();
            tests_admin_filtres();
            tests_admin_videos();

            throw new RuntimeException('fin du test, annulation');
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'fin du test, annulation') {
            throw $e;
        }
    }

    verifier('admin : les contenus d’essai sont annulés', true);
}

/** 1. Le menu latéral : des groupes de travail, plus de « parties ». */
function tests_admin_menu(): void
{
    $admin = tests_profils_compte('admin');
    $page  = tests_envoi_requete('GET', '/admin', $admin);
    $corps = (string) $page['corps'];

    verifier(
        'menu : les deux titres « Partie publique » et « Partie famille » ont disparu',
        (int) $page['code'] === 200
            && !str_contains($corps, 'Partie publique')
            && !str_contains($corps, 'Partie famille'),
        'code ' . $page['code']
    );

    // Les cinq groupes, dans l'ordre demandé, suivis de « Système ».
    $groupes = ['Contenus', 'Envois', 'Personnes', 'Journal', 'Système'];
    $positions = [];
    foreach ($groupes as $groupe) {
        $positions[$groupe] = strpos($corps, '<p class="admin-menu__titre">' . $groupe . '</p>');
    }
    verifier(
        'menu : cinq groupes de travail, dans l’ordre Contenus → Système',
        !in_array(false, $positions, true)
            && $positions['Contenus'] < $positions['Envois']
            && $positions['Envois'] < $positions['Personnes']
            && $positions['Personnes'] < $positions['Journal']
            && $positions['Journal'] < $positions['Système'],
        json_encode($positions, JSON_UNESCAPED_UNICODE)
    );

    // Aucun intitulé de groupe ne parle de « public » ni de « famille ».
    preg_match_all('#<p class="admin-menu__titre">([^<]+)</p>#', $corps, $trouves);
    $titres = array_map('trim', $trouves[1] ?? []);
    $fautifs = array_values(array_filter(
        $titres,
        static fn (string $t): bool => stripos($t, 'public') !== false || stripos($t, 'famille') !== false
    ));
    verifier(
        'menu : aucun intitulé de groupe ne dit « public » ou « famille »',
        $fautifs === [] && $titres === $groupes,
        implode(', ', $titres)
    );

    // « À valider » reste en tête, avec son compteur quand il y en a.
    $positionValider = strpos($corps, 'href="/admin/a-valider"');
    verifier(
        'menu : « À valider » est en tête, avant les groupes',
        $positionValider !== false && $positionValider < $positions['Contenus'],
        'position ' . var_export($positionValider, true)
    );

    // Tous les liens d'avant sont là : aucun écran n'a été perdu.
    $attendus = [
        '/admin/articles', '/admin/pages', '/admin/documents', '/admin/albums',
        '/admin/videos', '/admin/recettes', '/admin/categories',
        '/admin/envoyer', '/admin/importer', '/admin/conversions', '/admin/envois',
        '/admin/arbre', '/admin/comptes', '/admin/branches', '/admin/admins', '/admin/lieux',
        '/admin/courriels', '/admin/gazette', '/admin/apparence', '/admin/composition',
        '/admin/reglages', '/admin/connexions', '/admin/etat',
    ];
    $manquants = [];
    foreach ($attendus as $lien) {
        if (!str_contains($corps, 'href="' . $lien . '"')) {
            $manquants[] = $lien;
        }
    }
    verifier(
        'menu : les vingt-trois pages de l’administration sont toujours là',
        $manquants === [],
        implode(', ', $manquants)
    );
}

/** 2. Le tableau de bord : une carte « Contenus ». */
function tests_admin_tableau(): void
{
    $admin = tests_profils_compte('admin');

    // Un document public et un document privé, pour vérifier « dont publics ».
    $maintenant = time();
    $suffixe    = bin2hex(random_bytes(3));
    foreach (['public', 'prive'] as $visibilite) {
        db_insert('documents', [
            'title' => 'Document ' . $visibilite . ' ' . $suffixe,
            'kind' => 'document', 'visibility' => $visibilite,
            'rel_path' => 'essai/' . $suffixe . '-' . $visibilite . '.txt',
            'original_name' => $suffixe . '-' . $visibilite . '.txt',
            'mime' => 'text/plain', 'ext' => 'txt', 'size' => 1,
            'created_at' => $maintenant, 'updated_at' => $maintenant,
        ]);
    }

    $documentsPublics = (int) db_value(
        "SELECT COUNT(*) FROM documents WHERE visibility = 'public' AND is_attachment = 0",
        [],
        0
    );
    $articlesPublics = (int) db_value(
        "SELECT COUNT(*) FROM articles WHERE visibility = 'public' AND status = 'publie' AND published_at <= ?",
        [$maintenant],
        0
    );
    $pagesPubliques = (int) db_value(
        "SELECT COUNT(*) FROM pages WHERE visibility = 'public' AND is_published = 1",
        [],
        0
    );
    $attendu = $documentsPublics + $articlesPublics + $pagesPubliques;

    $page  = tests_envoi_requete('GET', '/admin', $admin);
    $corps = (string) $page['corps'];

    verifier(
        'tableau de bord : une seule carte « Contenus », plus de deux colonnes',
        (int) $page['code'] === 200
            && str_contains($corps, '<h2>Contenus</h2>')
            && !str_contains($corps, 'deux-colonnes')
            && !str_contains($corps, 'panneau--public')
            && !str_contains($corps, 'panneau--famille'),
        'code ' . $page['code']
    );

    verifier(
        'tableau de bord : « dont publics » compte documents, articles et pages publiés',
        str_contains($corps, 'dont publics : <strong>' . $attendu . '</strong>'),
        'attendu ' . $attendu
    );

    // Les sept compteurs demandés, chacun avec un nombre.
    $intitules = ['documents', 'articles', 'page', 'vidéo', 'personne', 'compte', 'proposition'];
    $absents   = [];
    foreach ($intitules as $motif) {
        if (preg_match('#<strong>\d+</strong> ' . preg_quote($motif, '#') . '#u', $corps) !== 1) {
            $absents[] = $motif;
        }
    }
    verifier(
        'tableau de bord : documents, articles, pages, vidéos, personnes, comptes, propositions',
        $absents === [],
        implode(', ', $absents)
    );

    // Les raccourcis mènent aux listes, filtre déjà posé.
    $raccourcis = [
        '/admin/documents?visibilite=public',
        '/admin/articles?visibilite=public',
        '/admin/pages?visibilite=public',
    ];
    $manquants = array_values(array_filter(
        $raccourcis,
        static fn (string $lien): bool => !str_contains($corps, 'href="' . $lien . '"')
    ));
    verifier(
        'tableau de bord : les raccourcis ouvrent les listes filtrées sur « Public »',
        $manquants === [],
        implode(', ', $manquants)
    );
}

/** 3. Le filtre « Visibilité » de chaque liste. */
function tests_admin_filtres(): void
{
    $admin      = tests_profils_compte('admin');
    $maintenant = time();
    $suffixe    = bin2hex(random_bytes(3));

    // Un contenu public et un contenu privé par liste, aux noms improbables.
    $titres = [
        'articles' => [
            'table'  => 'articles',
            'champs' => static fn (string $nom, string $visibilite): array => [
                'title' => $nom, 'slug' => 'essai-' . bin2hex(random_bytes(4)),
                'summary' => '', 'body' => 'Texte d’essai.', 'category' => '',
                'visibility' => $visibilite, 'status' => 'publie', 'published_at' => time(),
                'created_at' => $maintenant, 'updated_at' => $maintenant,
            ],
        ],
        'pages' => [
            'table'  => 'pages',
            'champs' => static fn (string $nom, string $visibilite): array => [
                'title' => $nom, 'slug' => 'essai-' . bin2hex(random_bytes(4)),
                'body' => 'Texte d’essai.', 'visibility' => $visibilite,
                'is_published' => 1, 'created_at' => $maintenant, 'updated_at' => $maintenant,
            ],
        ],
        'albums' => [
            'table'  => 'albums',
            'champs' => static fn (string $nom, string $visibilite): array => [
                'title' => $nom, 'slug' => 'essai-' . bin2hex(random_bytes(4)),
                'description' => '', 'visibility' => $visibilite,
                'created_at' => $maintenant, 'updated_at' => $maintenant,
            ],
        ],
        'recettes' => [
            'table'  => 'recipes',
            'champs' => static fn (string $nom, string $visibilite): array => [
                'title' => $nom, 'slug' => 'essai-' . bin2hex(random_bytes(4)),
                'ingredients' => 'Un peu de tout.', 'preparation' => 'Mélanger.',
                'visibility' => $visibilite,
                'created_at' => $maintenant, 'updated_at' => $maintenant,
            ],
        ],
    ];

    foreach ($titres as $liste => $spec) {
        $nomPublic = 'Public ' . $liste . ' ' . $suffixe;
        $nomPrive  = 'Prive ' . $liste . ' ' . $suffixe;
        db_insert($spec['table'], ($spec['champs'])($nomPublic, 'public'));
        db_insert($spec['table'], ($spec['champs'])($nomPrive, 'prive'));

        // Sans filtre : les deux sont là.
        $sansFiltre = (string) tests_envoi_requete('GET', '/admin/' . $liste, $admin)['corps'];
        verifier(
            'filtre ' . $liste . ' : sans filtre, tous les contenus sont listés',
            str_contains($sansFiltre, $nomPublic) && str_contains($sansFiltre, $nomPrive)
        );

        // Avec ?visibilite=public : seul le public reste.
        $filtre = (string) tests_envoi_requete('GET', '/admin/' . $liste . '?visibilite=public', $admin)['corps'];
        verifier(
            'filtre ' . $liste . ' : « Public » ne montre que les contenus publics',
            str_contains($filtre, $nomPublic) && !str_contains($filtre, $nomPrive),
            'le privé apparaît encore'
        );

        // Avec ?visibilite=prive : l'inverse.
        $prive = (string) tests_envoi_requete('GET', '/admin/' . $liste . '?visibilite=prive', $admin)['corps'];
        verifier(
            'filtre ' . $liste . ' : « Privé » ne montre que les contenus privés',
            str_contains($prive, $nomPrive) && !str_contains($prive, $nomPublic),
            'le public apparaît encore'
        );

        // Le formulaire est un GET, et sa valeur est conservée.
        verifier(
            'filtre ' . $liste . ' : formulaire GET, choix conservé',
            str_contains($filtre, 'method="get" action="/admin/' . $liste . '"')
                && str_contains($filtre, 'name="visibilite"')
                && preg_match('#<option value="public" selected>#', $filtre) === 1,
            'formulaire absent ou non conservé'
        );
    }

    // Valeur inventée : aucun filtre, aucune erreur (liste complète).
    $inventee = (string) tests_envoi_requete('GET', '/admin/articles?visibilite=nimportequoi', $admin)['corps'];
    verifier(
        'filtre : une visibilité inventée est ignorée (liste complète, pas d’erreur)',
        str_contains($inventee, 'Public articles ' . $suffixe)
            && str_contains($inventee, 'Prive articles ' . $suffixe)
    );

    // Les deux listes qui avaient déjà le filtre le gardent.
    foreach (['documents', 'videos'] as $liste) {
        $corps = (string) tests_envoi_requete('GET', '/admin/' . $liste, $admin)['corps'];
        verifier(
            'filtre ' . $liste . ' : le filtre de visibilité est toujours là',
            str_contains($corps, 'name="visibilite"')
        );
    }
}

/** 4. Vidéos : aucun ancien texte ne subsiste. */
function tests_admin_videos(): void
{
    // Phrases écrites avant la 1.21.0 (le site convertit désormais tout seul),
    // corrigées en 1.21.1 : elles ne doivent jamais revenir.
    $anciennes = [
        'ne la reconvertit pas',
        'ne convertit rien',
        'ne convertit aucun',
        'ne reconvertit pas',
        'sans conversion',
        'aucune conversion',
        'reste telle quelle : le site',
        'le Raspberry Pi ne convertit',
    ];

    $fichiers = array_merge(
        glob(HDF_APP . '/views/admin/video*.php') ?: [],
        glob(HDF_APP . '/views/public/video*.php') ?: [],
        glob(HDF_APP . '/views/public/videos.php') ?: [],
        [HDF_APP . '/controllers/admin_videos.php', HDF_APP . '/controllers/public.php']
    );

    $trouvees = [];
    foreach ($fichiers as $fichier) {
        $texte = (string) @file_get_contents($fichier);
        foreach ($anciennes as $phrase) {
            if (str_contains($texte, $phrase)) {
                $trouvees[] = basename($fichier) . ' : « ' . $phrase . ' »';
            }
        }
    }
    verifier(
        'vidéos : plus aucune phrase d’avant la conversion automatique',
        $trouvees === [],
        implode(' ; ', $trouvees)
    );

    // Le formulaire dit la vérité : MP4 publié tel quel, le reste converti.
    $formulaire = (string) @file_get_contents(HDF_APP . '/views/admin/video_form.php');
    verifier(
        'vidéos : le formulaire explique la conversion en arrière-plan',
        str_contains($formulaire, 'converti automatiquement en arrière-plan')
            && str_contains($formulaire, 'publié tel quel')
    );
}
