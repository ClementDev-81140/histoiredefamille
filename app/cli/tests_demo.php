<?php
/**
 * Tests de la purge des données de démonstration (1.26.0).
 *
 * Trois familles :
 *   1. le registre écrit par demo.php (lecture, écriture, plages d'identifiants) ;
 *   2. l'inventaire et la purge : on ne touche QUE ce qui vient de la
 *      démonstration, les fichiers suivent, les comptes ne bougent jamais ;
 *   3. la commande elle-même : elle montre, demande OUI, sauvegarde avant.
 *
 * Tout se passe dans une transaction annulée : la base du site est rendue
 * intacte, et les fichiers d'essai sont effacés à la fin.
 */

declare(strict_types=1);

/** Toute la famille « demo ». */
function tests_demo(): void
{
    echo "Données de démonstration — inventaire, purge, commande\n";

    // 1. Le registre, sur un fichier temporaire.
    $registre = storage_path('tmp') . '/essai-registre-' . bin2hex(random_bytes(4)) . '.json';
    $ecrit = demo_registry_write(['documents' => ['min' => 12, 'max' => 14]], $registre);
    $lu    = demo_registry_read($registre);
    verifier(
        'démo : le registre note les plages d’identifiants créés par demo.php',
        $ecrit && ($lu['tables']['documents']['min'] ?? 0) === 12
            && ($lu['tables']['documents']['max'] ?? 0) === 14
            && (int) $lu['date'] > 0,
        json_encode($lu)
    );
    verifier(
        'démo : un registre absent ou illisible ne fait pas d’erreur',
        demo_registry_read($registre . '.absent')['tables'] === []
    );
    @unlink($registre);

    // Le registre est aussi recopié en base : si le dossier `storage` est
    // reconstruit (seule la base restaurée), la purge reste capable de trouver
    // les contenus de démonstration.
    $vraiChemin = demo_registry_path();
    $avantFichier = is_file($vraiChemin) ? (string) file_get_contents($vraiChemin) : null;
    try {
        db_transaction(static function () use ($vraiChemin): void {
            demo_registry_write(['articles' => ['min' => 400, 'max' => 402]]);
            verifier(
                'démo : le registre est recopié dans les réglages du site',
                str_contains(site_setting('demo_registry'), '"articles"'),
                site_setting('demo_registry')
            );

            @unlink($vraiChemin);
            $relu = demo_registry_read();
            verifier(
                'démo : registre effacé du disque, la purge le retrouve en base',
                ($relu['tables']['articles']['min'] ?? 0) === 400
                    && ($relu['tables']['articles']['max'] ?? 0) === 402,
                json_encode($relu)
            );

            demo_registry_oublier();
            verifier(
                'démo : « oublier le registre » efface aussi la copie en base',
                site_setting('demo_registry') === '' && demo_registry_read()['tables'] === []
            );

            throw new RuntimeException('fin du test, annulation');
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'fin du test, annulation') {
            throw $e;
        }
    } finally {
        if ($avantFichier !== null) {
            @file_put_contents($vraiChemin, $avantFichier);
        } else {
            @unlink($vraiChemin);
        }
    }

    // 2. Inventaire et purge, dans une transaction annulée.
    $fichiers = [];
    try {
        db_transaction(static function () use (&$fichiers): void {
            $compte = db_insert('accounts', [
                'username'      => 'demo-purge-' . bin2hex(random_bytes(4)),
                'display_name'  => 'Essai purge (démo)',
                'password_hash' => password_make('MotDePasseEssai123!'),
                'role'          => 'famille',
                'account_type'  => 'personne',
                'is_active'     => 1,
                'created_at'    => time(),
                'updated_at'    => time(),
            ]);

            // Un vrai document de démonstration, avec son fichier et sa miniature.
            $rel = storage_target_path('txt');
            file_put_contents(storage_absolute($rel), "Document de démonstration.\n");
            $thumb = storage_target_path('jpg');
            file_put_contents(storage_absolute($thumb), 'miniature');
            $fichiers[] = $rel;
            $fichiers[] = $thumb;

            $docDemo = db_insert('documents', [
                'title' => 'Lettre fictive (démo)', 'description' => null, 'doc_date' => null,
                'doc_date_sort' => null, 'category_id' => null, 'kind' => 'document',
                'visibility' => 'famille', 'rel_path' => $rel, 'thumb_path' => $thumb,
                'original_name' => 'lettre.txt', 'mime' => 'text/plain', 'ext' => 'txt',
                'size' => (int) filesize(storage_absolute($rel)), 'width' => null, 'height' => null,
                'is_attachment' => 0, 'created_by' => $compte,
                'created_at' => time(), 'updated_at' => time(),
            ]);

            // Un vrai contenu, qui ne doit JAMAIS être touché.
            $relVrai = storage_target_path('txt');
            file_put_contents(storage_absolute($relVrai), "Vrai souvenir de famille.\n");
            $fichiers[] = $relVrai;
            $docVrai = db_insert('documents', [
                'title' => 'Photo du vrai mariage', 'description' => null, 'doc_date' => null,
                'doc_date_sort' => null, 'category_id' => null, 'kind' => 'document',
                'visibility' => 'famille', 'rel_path' => $relVrai, 'thumb_path' => null,
                'original_name' => 'mariage.txt', 'mime' => 'text/plain', 'ext' => 'txt',
                'size' => 10, 'width' => null, 'height' => null,
                'is_attachment' => 0, 'created_by' => $compte,
                'created_at' => time(), 'updated_at' => time(),
            ]);

            $articleDemo = db_insert('articles', [
                'title' => 'Article de démonstration (démo)', 'slug' => 'demo-' . bin2hex(random_bytes(3)),
                'summary' => '', 'body' => 'Texte.', 'category' => '', 'visibility' => 'famille',
                'status' => 'publie', 'published_at' => time(), 'author_id' => $compte,
                'created_at' => time(), 'updated_at' => time(),
            ]);
            $articleVrai = db_insert('articles', [
                'title' => 'Le vrai baptême de 1952', 'slug' => 'vrai-' . bin2hex(random_bytes(3)),
                'summary' => '', 'body' => 'Texte.', 'category' => '', 'visibility' => 'famille',
                'status' => 'publie', 'published_at' => time(), 'author_id' => $compte,
                'created_at' => time(), 'updated_at' => time(),
            ]);

            $personneDemo = db_insert('persons', [
                'first_name' => 'Jean', 'last_name' => 'Martin', 'notes' => 'Personnage fictif créé pour la démonstration.',
                'visibility' => 'famille', 'created_at' => time(), 'updated_at' => time(),
            ]);
            $personneVraie = db_insert('persons', [
                'first_name' => 'Louise', 'last_name' => 'Dupont', 'notes' => 'Notre grand-mère.',
                'visibility' => 'famille', 'created_at' => time(), 'updated_at' => time(),
            ]);

            $inventaire = demo_inventaire();
            $ids = [];
            foreach ($inventaire as $element) {
                $ids[$element['table']] = $element['ids'];
            }

            verifier(
                'démo : l’inventaire reconnaît les contenus marqués « (démo) »',
                in_array($docDemo, $ids['documents'] ?? [], true)
                    && in_array($articleDemo, $ids['articles'] ?? [], true)
                    && in_array($personneDemo, $ids['persons'] ?? [], true),
                json_encode(array_map('count', $ids))
            );
            verifier(
                'démo : l’inventaire laisse tranquilles les vrais souvenirs',
                !in_array($docVrai, $ids['documents'] ?? [], true)
                    && !in_array($articleVrai, $ids['articles'] ?? [], true)
                    && !in_array($personneVraie, $ids['persons'] ?? [], true)
            );
            verifier(
                'démo : l’inventaire montre des exemples lisibles, pas seulement des numéros',
                ($inventaire[0]['exemples'] ?? []) !== []
                    && str_contains(implode(' ', array_merge(...array_column($inventaire, 'exemples'))), 'démo'),
                json_encode(array_column($inventaire, 'exemples'))
            );
            verifier(
                'démo : aucun compte n’est jamais listé pour suppression',
                !in_array('accounts', array_column($inventaire, 'table'), true),
                json_encode(array_column($inventaire, 'table'))
            );

            verifier(
                'démo : sans registre, le marqueur suffit à reconnaître une base ancienne',
                demo_inventaire(['tables' => [], 'date' => 0, 'version' => '']) !== []
            );

            // Liens de branches : `content_branches` n'a pas de clé étrangère
            // sur le contenu, il doit donc être nettoyé à la main.
            $brancheEssai = db_insert('branches', [
                'code' => 'DEMO' . strtoupper(bin2hex(random_bytes(2))), 'name' => 'Branche d’essai (démo)',
                'description' => null, 'see_all' => 0, 'is_active' => 1,
                'created_at' => time(), 'updated_at' => time(),
            ]);
            db_insert('content_branches', ['kind' => 'document', 'content_id' => $docDemo, 'branch_id' => $brancheEssai]);
            db_insert('content_branches', ['kind' => 'person', 'content_id' => $personneDemo, 'branch_id' => $brancheEssai]);
            verifier(
                'démo : avant la purge, les liens de branche existent bien',
                (int) db_value('SELECT COUNT(*) FROM content_branches WHERE branch_id = ?', [$brancheEssai], 0) === 2
            );

            $resultat = demo_purger($inventaire);
            verifier(
                'démo : la purge supprime les contenus de démonstration listés',
                db_one('SELECT * FROM documents WHERE id = ?', [$docDemo]) === null
                    && db_one('SELECT * FROM articles WHERE id = ?', [$articleDemo]) === null
                    && db_one('SELECT * FROM persons WHERE id = ?', [$personneDemo]) === null,
                json_encode($resultat)
            );
            verifier(
                'démo : la purge ne touche NI aux vrais contenus NI aux comptes',
                db_one('SELECT * FROM documents WHERE id = ?', [$docVrai]) !== null
                    && db_one('SELECT * FROM articles WHERE id = ?', [$articleVrai]) !== null
                    && db_one('SELECT * FROM persons WHERE id = ?', [$personneVraie]) !== null
                    && db_one('SELECT * FROM accounts WHERE id = ?', [$compte]) !== null
            );
            verifier(
                'démo : les fichiers du document de démonstration sont effacés, ceux des vrais restent',
                !is_file(storage_absolute($rel)) && !is_file(storage_absolute($thumb))
                    && is_file(storage_absolute($relVrai)),
                json_encode([is_file(storage_absolute($rel)), is_file(storage_absolute($relVrai))])
            );
            verifier(
                'démo : la purge n’oublie aucun lien de branche derrière elle',
                (int) db_value('SELECT COUNT(*) FROM content_branches WHERE content_id IN (?, ?)',
                    [$docDemo, $personneDemo], 0) === 0,
                (string) db_value('SELECT COUNT(*) FROM content_branches WHERE branch_id = ?', [$brancheEssai], 0)
            );
            verifier(
                'démo : après la purge, la vérification ne voit plus de contenu de démonstration',
                demo_present()['contenus'] === 0,
                json_encode(demo_present())
            );

            throw new RuntimeException('fin du test, annulation');
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'fin du test, annulation') {
            throw $e;
        }
    }

    foreach ($fichiers as $rel) {
        @unlink(storage_absolute($rel));
    }

    // 3. La commande elle-même.
    $code = (string) @file_get_contents(HDF_APP . '/cli/purger-demo.php');
    verifier(
        'démo : la commande existe et refuse d’être appelée par le web',
        $code !== '' && str_contains($code, 'is_cli()')
    );
    verifier(
        'démo : elle montre la liste AVANT de demander confirmation, et exige OUI',
        str_contains($code, 'demo_inventaire()')
            && str_contains($code, '--liste')
            && str_contains($code, "'OUI'")
            && strpos($code, 'demo_inventaire()') < strpos($code, "'OUI'")
    );
    verifier(
        'démo : elle fait une sauvegarde AVANT de supprimer, et s’arrête si elle échoue',
        str_contains($code, 'sauvegarde_executer(')
            && strpos($code, 'sauvegarde_executer(') < strpos($code, 'demo_purger(')
            && str_contains($code, "Rien n’a été supprimé")
    );
    verifier(
        'démo : elle rappelle que les comptes ne sont jamais supprimés',
        str_contains($code, 'demo_comptes_restants()')
            && str_contains($code, 'NON supprimés')
    );
    $demoCode = (string) @file_get_contents(HDF_APP . '/cli/demo.php');
    verifier(
        'démo : demo.php note ce qu’il crée (registre), sans quoi la purge serait aveugle',
        str_contains($demoCode, 'demo_marques(') && str_contains($demoCode, 'demo_registry_write(')
    );
    verifier(
        'démo : la vérification avant ouverture s’appuie sur le même inventaire',
        str_contains((string) @file_get_contents(HDF_APP . '/lib/mise_en_ligne.php'), 'demo_present()')
    );
}
