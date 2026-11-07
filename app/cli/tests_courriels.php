<?php
/**
 * Tests du socle « courriels » (lot 1) : adresses, accords, jetons, file
 * d'envoi, client SMTP et notifications d'articles.
 *
 * Rien n'est envoyé pour de vrai : le « serveur SMTP » est une conversation
 * scriptée (CourrielSmtpScript). Les données d'essai vivent dans une
 * transaction annulée : la base du site est rendue intacte.
 */

declare(strict_types=1);

/** Petit compte de test (transaction annulée à la fin). */
function tests_courriel_compte(array $champs): int
{
    $maintenant = time();

    // Un compte inscrit aux nouveaux articles l'est « depuis une heure », sauf
    // mention contraire : les articles publiés maintenant arrivent donc après
    // son accord, comme dans la vraie vie.
    if ((int) ($champs['notify_articles'] ?? 0) === 1
        && !array_key_exists('notify_articles_depuis', $champs)) {
        $champs['notify_articles_depuis'] = $maintenant - 3600;
    }

    return db_insert('accounts', array_merge([
        'username'      => 'essai.' . bin2hex(random_bytes(4)),
        'display_name'  => 'Essai',
        'password_hash' => password_hash('mot-de-passe-de-test', PASSWORD_DEFAULT),
        'role'          => 'famille',
        'branch_id'     => null,
        'is_active'     => 1,
        'account_type'  => 'personne',
        'first_name'    => 'Essai',
        'last_name'     => 'Famille',
        'see_all'       => 0,
        'email'         => '',
        'notify_articles' => 0,
        // Par défaut, un compte inscrit l'est depuis une heure : les articles
        // publiés « maintenant » sont donc bien après son accord.
        'notify_articles_depuis' => 0,
        'notify_gazette'  => 0,
        'created_at'    => $maintenant,
        'updated_at'    => $maintenant,
    ], $champs));
}

/** Article de test. */
function tests_courriel_article(string $titre, string $visibilite = 'public', int $datePublication = 0, int $notify = 1): int
{
    $maintenant = time();

    return db_insert('articles', [
        'slug'         => 'essai-' . bin2hex(random_bytes(5)),
        'title'        => $titre,
        'summary'      => 'Résumé d’essai pour le courriel.',
        'body'         => 'Texte complet (il ne doit jamais partir par courriel).',
        'category'     => 'Souvenirs',
        'visibility'   => $visibilite,
        'status'       => 'publie',
        'published_at' => $datePublication > 0 ? $datePublication : $maintenant,
        'notify'       => $notify,
        'created_at'   => $maintenant,
        'updated_at'   => $maintenant,
    ]);
}

function tests_courriels(): void
{
    echo "Courriels — adresses, accords, jetons, file, SMTP, notifications\n";

    // 1.28.0 : l'envoi est coupé par défaut. Cette famille éprouve le code
    // d'envoi lui-même : elle l'allume, et la suite rétablit le réglage.
    tests_courriels_activer();

    // -----------------------------------------------------------------------
    // 1. Adresses
    // -----------------------------------------------------------------------
    $valides = ['marie.dupont@exemple.fr', 'a@b.fr', 'jean-paul.le.gall@exemple.fr'];
    $invalides = ['', 'sans-arobase', 'deux@@exemple.fr', 'a b@exemple.fr', "ligne\ncassée@exemple.fr",
        'virgule,cote@exemple.fr', '<script>@exemple.fr', str_repeat('a', 190) . '@exemple.fr'];

    $okValides = array_filter($valides, 'courriel_adresse_valide');
    verifier(
        'courriel : les adresses correctes sont acceptées',
        count($okValides) === count($valides),
        implode(', ', array_diff($valides, $okValides))
    );
    $faux = array_filter($invalides, 'courriel_adresse_valide');
    verifier('courriel : les adresses douteuses sont refusées', $faux === [], implode(', ', $faux));
    verifier(
        'courriel : une adresse est rangée en minuscules',
        courriel_adresse_normaliser('Marie.Dupont@EXEMPLE.fr') === 'marie.dupont@exemple.fr'
    );
    verifier(
        'courriel : l’adresse est masquée dans les journaux',
        courriel_masquer('marie.dupont@exemple.fr') === 'm***@exemple.fr',
        courriel_masquer('marie.dupont@exemple.fr')
    );

    // -----------------------------------------------------------------------
    // 2. Configuration absente : rien ne part, mais la file est conservée
    // -----------------------------------------------------------------------
    verifier('courriel : sans configuration, l’envoi est inactif', !courriel_configure());
    $essaiVide = courriel_envoyer(['recipient' => 'marie.dupont@exemple.fr', 'subject' => 'x', 'text' => 'y']);
    verifier(
        'courriel : sans configuration, l’envoi explique pourquoi il n’a rien fait',
        !$essaiVide['ok'] && str_contains($essaiVide['message'], 'config.local.php'),
        $essaiVide['message']
    );

    $pdo = db();
    $transaction = false;

    try {
        $pdo->beginTransaction();
        $transaction = true;

        // -------------------------------------------------------------------
        // 3. Comptes, accords et unicité de l'adresse
        // -------------------------------------------------------------------
        $brancheA = db_insert('branches', [
            'name' => 'Essai A', 'code' => 'ESSAIA', 'description' => '',
            'is_active' => 1, 'see_all' => 0, 'created_at' => time(), 'updated_at' => time(),
        ]);

        $inscrit   = tests_courriel_compte(['email' => 'inscrit@exemple.fr', 'notify_articles' => 1,
            'notify_gazette' => 1, 'branch_id' => $brancheA]);
        $sansAccord = tests_courriel_compte(['email' => 'sans.accord@exemple.fr', 'branch_id' => $brancheA]);
        $sansAdresse = tests_courriel_compte(['notify_articles' => 1, 'branch_id' => $brancheA]);

        verifier(
            'courriel : une adresse ne peut pas servir à deux comptes actifs',
            !courriel_adresse_libre('inscrit@exemple.fr', 0)
            && courriel_adresse_libre('INSCRIT@exemple.fr', $inscrit)
        );

        // -------------------------------------------------------------------
        // 4. Jetons de désinscription
        // -------------------------------------------------------------------
        $jeton = courriel_jeton($inscrit, 'articles');
        verifier(
            'jeton : long, hexadécimal, et toujours le même pour la même personne',
            preg_match('/^[0-9a-f]{64}$/', $jeton) === 1 && $jeton === courriel_jeton($inscrit, 'articles')
        );
        verifier(
            'jeton : il retrouve bien la personne et le type d’envoi',
            courriel_jeton_compte($jeton, 'articles') === $inscrit
            && courriel_jeton_compte($jeton, 'gazette') === null
        );
        verifier(
            'jeton : un jeton modifié ou inventé ne donne accès à rien',
            courriel_jeton_compte(substr($jeton, 0, -1) . ($jeton[63] === 'a' ? 'b' : 'a'), 'articles') === null
            && courriel_jeton_compte(str_repeat('a', 64), 'articles') === null
            && courriel_jeton_compte('court', 'articles') === null
        );
        $hashStocke = (string) db_value('SELECT token_hash FROM email_tokens WHERE account_id = ?', [$inscrit], '');
        verifier(
            'jeton : seule l’empreinte du jeton est enregistrée en base',
            $hashStocke !== '' && $hashStocke !== $jeton && $hashStocke === hash('sha256', $jeton)
        );

        // -------------------------------------------------------------------
        // 5. File d'envoi
        // -------------------------------------------------------------------
        $id1 = courriel_enfiler([
            'account_id' => $inscrit, 'kind' => 'article',
            'recipient' => 'Inscrit@Exemple.fr', 'subject' => 'Sujet',
            'text' => 'Texte', 'html' => '<p>Texte</p>',
            'dedupe' => 'essai:1:' . $inscrit,
        ]);
        $id2 = courriel_enfiler([
            'account_id' => $inscrit, 'kind' => 'article',
            'recipient' => 'inscrit@exemple.fr', 'subject' => 'Doublon',
            'text' => 'Texte', 'html' => '', 'dedupe' => 'essai:1:' . $inscrit,
        ]);
        verifier('file : un message est mis en attente', $id1 > 0);
        verifier('file : le même message n’est pas mis deux fois', $id2 === 0);
        verifier(
            'file : le destinataire est rangé en minuscules',
            (string) db_value('SELECT recipient FROM email_queue WHERE id = ?', [$id1], '') === 'inscrit@exemple.fr'
        );
        $id3 = courriel_enfiler(['recipient' => 'adresse-invalide', 'subject' => 'x', 'text' => 'y']);
        verifier('file : une adresse invalide n’entre pas dans la file', $id3 === 0);

        $avantFile = courriel_compteurs();
        $id4 = courriel_enfiler([
            'account_id' => $inscrit, 'kind' => 'article', 'recipient' => 'inscrit@exemple.fr',
            'subject' => 'Deuxième sujet', 'text' => 'Texte', 'html' => '', 'dedupe' => 'essai:2',
        ]);
        $compteurs = courriel_compteurs();
        verifier(
            'file : les compteurs de l’administration sont justes',
            $id4 > 0
            && $compteurs['attente'] === $avantFile['attente'] + 1
            && $compteurs['abonnes'] >= 1,
            'attente=' . $compteurs['attente'] . ' abonnés=' . $compteurs['abonnes']
        );

        // -------------------------------------------------------------------
        // 6. Conversation SMTP (faux serveur scripté)
        // -------------------------------------------------------------------
        $config = [
            'host' => 'smtp.exemple.fr', 'port' => 587, 'encryption' => 'tls',
            'username' => 'compte', 'password' => 'secret-de-test',
            'from' => 'site@exemple.fr', 'from_name' => 'Histoire de famille', 'timeout' => 5,
        ];
        $brut = courriel_message_brut($config, 'marie.dupont@exemple.fr', 'Nouvel article', 'Texte', '<p>Html</p>', [
            'desinscription' => 'https://exemple.fr/desinscription?jeton=abc',
        ]);
        verifier(
            'message : un seul destinataire, jamais de copie visible',
            substr_count(strtolower($brut), 'to: <marie.dupont@exemple.fr>') === 1
            && !str_contains(strtolower($brut), 'cc:') && !str_contains(strtolower($brut), 'bcc:')
        );
        verifier(
            'message : les en-têtes de désinscription sont présents',
            str_contains($brut, 'List-Unsubscribe: <https://exemple.fr/desinscription?jeton=abc>')
            && str_contains($brut, 'List-Unsubscribe-Post: List-Unsubscribe=One-Click')
        );
        verifier(
            'message : le sujet accentué est encodé (pas de casse dans les logiciels de messagerie)',
            str_contains($brut, 'Subject: =?UTF-8?B?')
        );

        $reponsesOk = ["220 smtp.exemple.fr ESMTP", "250-smtp.exemple.fr\n250-STARTTLS\n250 AUTH LOGIN PLAIN",
            '220 2.0.0 Ready', "250-smtp.exemple.fr\n250 AUTH LOGIN PLAIN", '334 VXNlcm5hbWU6',
            '334 UGFzc3dvcmQ6', '235 2.7.0 Authentication successful', '250 2.1.0 Ok',
            '250 2.1.5 Ok', '354 End data with <CR><LF>.<CR><LF>', '250 2.0.0 Ok: queued', '221 Bye'];
        $faux = new CourrielSmtpScript($reponsesOk);
        $envoi = courriel_smtp_conversation($faux, $config, 'site@exemple.fr', 'marie.dupont@exemple.fr',
            "Subject: essai\r\n\r\n.une ligne qui commence par un point");
        verifier('smtp : une conversation complète réussit', $envoi['ok'], $envoi['message']);
        verifier('smtp : STARTTLS est demandé avant tout mot de passe', $faux->tls === true);
        verifier(
            'smtp : l’authentification LOGIN envoie identifiant puis mot de passe en base64',
            in_array(base64_encode('compte'), $faux->ecrit, true)
            && in_array(base64_encode('secret-de-test'), $faux->ecrit, true)
        );
        verifier(
            'smtp : une ligne commençant par un point est doublée (le message n’est pas coupé)',
            in_array('..une ligne qui commence par un point', $faux->ecrit, true)
        );
        verifier('smtp : la conversation se termine par QUIT', $faux->ferme && end($faux->ecrit) === 'QUIT');

        $mauvaisMotDePasse = new CourrielSmtpScript([
            '220 pret', "250-smtp\n250-STARTTLS\n250 AUTH LOGIN", '220 go', '250 ok',
            '334 user', '334 pass', '535 5.7.8 Authentication credentials invalid',
        ]);
        $refus = courriel_smtp_conversation($mauvaisMotDePasse, $config, 'site@exemple.fr', 'a@exemple.fr', 'x');
        verifier(
            'smtp : un mot de passe refusé est une erreur définitive (pas de nouvel essai)',
            !$refus['ok'] && $refus['definitif'] === true
        );

        $adresseInconnue = new CourrielSmtpScript([
            '220 pret', "250-smtp\n250-STARTTLS\n250 AUTH LOGIN", '220 go', '250 ok',
            '334 user', '334 pass', '235 ok', '250 ok', '550 5.1.1 No such user',
        ]);
        $inconnue = courriel_smtp_conversation($adresseInconnue, $config, 'site@exemple.fr', 'a@exemple.fr', 'x');
        verifier(
            'smtp : une adresse inconnue est signalée clairement',
            !$inconnue['ok'] && str_contains($inconnue['message'], 'n’existe pas')
        );

        $sansTls = new CourrielSmtpScript(['220 pret', '250 smtp.exemple.fr']);
        $refusTls = courriel_smtp_conversation($sansTls, $config, 'site@exemple.fr', 'a@exemple.fr', 'x');
        verifier(
            'smtp : sans STARTTLS annoncé, rien n’est envoyé (le mot de passe ne passe pas en clair)',
            !$refusTls['ok'] && $refusTls['definitif'] === true
        );

        // -------------------------------------------------------------------
        // 7. Mise en page du courriel
        // -------------------------------------------------------------------
        $rendu = courriel_habiller('Un titre', ['Premier paragraphe', 'Deuxième paragraphe'], [
            'lien' => 'https://exemple.fr/articles/essai',
            'libelle_lien' => 'Lire l’article',
            'desinscription' => 'https://exemple.fr/desinscription?jeton=abc',
        ]);
        $palette = apparence_variables();
        verifier(
            'courriel : le texte lisible reprend le titre, les paragraphes et les liens',
            str_contains($rendu['text'], 'Un titre')
            && str_contains($rendu['text'], 'Premier paragraphe')
            && str_contains($rendu['text'], 'Se désinscrire')
            && str_contains($rendu['text'], 'Gérer mes préférences')
        );
        verifier(
            'courriel : le HTML utilise les couleurs de la palette, en ligne',
            str_contains($rendu['html'], $palette['accent'])
            && str_contains($rendu['html'], 'style="')
            && !str_contains($rendu['html'], '<link')
        );
        verifier(
            'courriel : aucun script ni ressource externe dans le corps du message',
            !str_contains($rendu['html'], '<script')
            && !preg_match('/src="https?:/', $rendu['html'])
            && !preg_match('/url\(https?:/', $rendu['html'])
        );
        verifier(
            'courriel : le nom du site et l’adresse du site figurent en pied',
            str_contains($rendu['html'], e(site_setting('name')))
            && str_contains($rendu['html'], '/mon-compte')
        );

        // -------------------------------------------------------------------
        // 8. Destinataires selon la visibilité de l'article
        // -------------------------------------------------------------------
        $brancheB = db_insert('branches', [
            'name' => 'Essai B', 'code' => 'ESSAIB', 'description' => '',
            'is_active' => 1, 'see_all' => 0, 'created_at' => time(), 'updated_at' => time(),
        ]);
        $compteB = tests_courriel_compte(['email' => 'branche.b@exemple.fr', 'notify_articles' => 1,
            'branch_id' => $brancheB]);
        $voitTout = tests_courriel_compte(['email' => 'tout@exemple.fr', 'notify_articles' => 1,
            'branch_id' => $brancheB, 'see_all' => 1]);
        $admin = tests_courriel_compte(['email' => 'admin@exemple.fr', 'notify_articles' => 1,
            'role' => 'admin', 'account_type' => 'admin']);

        $destinataires = static function (int $articleId): array {
            $lignes = db_all(
                "SELECT account_id FROM email_queue WHERE kind = 'article' AND dedupe_key LIKE ? ORDER BY account_id",
                ['article:' . $articleId . ':%']
            );
            return array_map(static fn (array $l): int => (int) $l['account_id'], $lignes);
        };

        $articlePublic = tests_courriel_article('Article public', 'public');
        courriel_notifier_article(db_one('SELECT * FROM articles WHERE id = ?', [$articlePublic]));
        $recus = $destinataires($articlePublic);
        $attendus = [$inscrit, $compteB, $voitTout, $admin];
        verifier(
            'article public : toutes les personnes inscrites sont prévenues',
            array_diff($attendus, $recus) === [],
            implode(', ', $recus)
        );
        verifier(
            'article public : la personne sans accord et celle sans adresse ne reçoivent rien',
            !in_array($sansAccord, $recus, true) && !in_array($sansAdresse, $recus, true)
        );

        // Une deuxième notification ne crée aucun doublon.
        $avant = (int) db_value("SELECT COUNT(*) FROM email_queue WHERE kind = 'article'", [], 0);
        courriel_notifier_article(db_one('SELECT * FROM articles WHERE id = ?', [$articlePublic]));
        $apres = (int) db_value("SELECT COUNT(*) FROM email_queue WHERE kind = 'article'", [], 0);
        verifier('article : pas de doublon si l’article est modifié ou republié', $avant === $apres);

        $articleFamille = tests_courriel_article('Article famille', 'famille');
        courriel_notifier_article(db_one('SELECT * FROM articles WHERE id = ?', [$articleFamille]));
        verifier(
            'article famille : tout compte connecté inscrit le reçoit',
            array_diff($attendus, $destinataires($articleFamille)) === [],
            implode(', ', $destinataires($articleFamille))
        );

        $articleBranche = tests_courriel_article('Article branche A', 'branches');
        set_content_branches('article', $articleBranche, [$brancheA]);
        courriel_notifier_article(db_one('SELECT * FROM articles WHERE id = ?', [$articleBranche]));
        $recusBranche = $destinataires($articleBranche);
        verifier(
            'article réservé à la branche A : seuls les comptes de cette branche (et ceux qui voient tout) le reçoivent',
            in_array($inscrit, $recusBranche, true)
            && in_array($voitTout, $recusBranche, true)
            && in_array($admin, $recusBranche, true)
            && !in_array($compteB, $recusBranche, true),
            implode(', ', $recusBranche)
        );

        $articlePrive = tests_courriel_article('Article privé', 'prive');
        courriel_notifier_article(db_one('SELECT * FROM articles WHERE id = ?', [$articlePrive]));
        $recusPrive = $destinataires($articlePrive);
        verifier(
            'article privé : seul l’administrateur inscrit le reçoit',
            in_array($admin, $recusPrive, true)
            && !in_array($inscrit, $recusPrive, true)
            && !in_array($compteB, $recusPrive, true),
            implode(', ', $recusPrive)
        );

        // La règle de visibilité des courriels est celle du site.
        $reglesIdentiques = true;
        $articleBrancheComplet = db_one('SELECT * FROM articles WHERE id = ?', [$articleBranche]);
        $branchesArticle = content_branch_ids('article', $articleBranche);
        foreach ([$inscrit, $compteB, $voitTout, $admin, $sansAccord] as $idCompte) {
            $compte = db_one('SELECT * FROM accounts WHERE id = ?', [$idCompte]);
            // can_view() lit le compte courant : on se met tour à tour dans sa
            // peau, comme le ferait une visite du site.
            $_SESSION['account_id'] = $idCompte;
            $parLeSite   = can_view('branches', $branchesArticle);
            $parCourriel = compte_voit_article($compte, $articleBrancheComplet);
            if ($parLeSite !== $parCourriel) {
                $reglesIdentiques = false;
            }
        }
        $_SESSION = [];
        verifier('courriel : la règle de visibilité est exactement celle du site', $reglesIdentiques);

        // -------------------------------------------------------------------
        // 8 bis. La date d'accord : jamais les nouvelles déjà anciennes
        // -------------------------------------------------------------------
        // Un compte tout juste inscrit et un compte inscrit depuis longtemps.
        $nouveau = tests_courriel_compte([
            'email' => 'nouveau@exemple.fr', 'notify_articles' => 1,
            'notify_articles_depuis' => time() - 60,
        ]);
        $ancien = tests_courriel_compte([
            'email' => 'ancien@exemple.fr', 'notify_articles' => 1,
            'notify_articles_depuis' => time() - 40 * 86400,
        ]);

        // Un article publié il y a dix jours : il est encore dans la fenêtre
        // de 30 jours de la commande, mais il n'est neuf pour personne qui
        // vient de s'inscrire.
        $vieux = tests_courriel_article('Article d’il y a dix jours', 'public', time() - 10 * 86400);
        courriel_notifier_article(db_one('SELECT * FROM articles WHERE id = ?', [$vieux]));
        $recusVieux = $destinataires($vieux);
        verifier(
            'date d’accord : un nouvel inscrit ne reçoit pas les articles déjà publiés',
            !in_array($nouveau, $recusVieux, true),
            implode(', ', $recusVieux)
        );
        verifier(
            'date d’accord : une personne inscrite depuis plus longtemps les reçoit',
            in_array($ancien, $recusVieux, true)
        );

        $neuf = tests_courriel_article('Article publié maintenant', 'public', time() - 30);
        courriel_notifier_article(db_one('SELECT * FROM articles WHERE id = ?', [$neuf]));
        verifier(
            'date d’accord : le nouvel inscrit reçoit bien les articles publiés après son accord',
            in_array($nouveau, $destinataires($neuf), true),
            implode(', ', $destinataires($neuf))
        );

        // La commande d'envoi applique la même règle : c'est là que le retard
        // des 30 derniers jours arrivait autrefois.
        $ancienSansEnvoi = tests_courriel_article('Article jamais notifié', 'public', time() - 5 * 86400);
        $avantEchus = (int) db_value(
            "SELECT COUNT(*) FROM email_queue WHERE dedupe_key LIKE ?",
            ['article:' . $ancienSansEnvoi . ':%'],
            0
        );
        courriel_articles_echus();
        $lignesVieux = db_all(
            "SELECT account_id FROM email_queue WHERE dedupe_key LIKE ?",
            ['article:' . $ancienSansEnvoi . ':%']
        );
        $idsVieux = array_map(static fn (array $l): int => (int) $l['account_id'], $lignesVieux);
        verifier(
            'date d’accord : la commande ne renvoie pas non plus les 30 derniers jours au nouvel inscrit',
            $avantEchus === 0
            && !in_array($nouveau, $idsVieux, true)
            && in_array($ancien, $idsVieux, true),
            implode(', ', $idsVieux)
        );

        // Décocher puis recocher repose la date : les articles d'entre-temps
        // ne repartent pas.
        $avant = db_one('SELECT * FROM accounts WHERE id = ?', [$nouveau]);
        $coche = courriel_accord_articles($avant, true);
        verifier(
            'date d’accord : la date ne bouge pas tant que la case reste cochée',
            $coche['notify_articles'] === 1
            && $coche['notify_articles_depuis'] === (int) $avant['notify_articles_depuis'],
            (string) $coche['notify_articles_depuis']
        );

        $decoche = courriel_accord_articles($avant, false);
        db_update('accounts', $nouveau, [
            'notify_articles' => $decoche['notify_articles'],
            'notify_articles_depuis' => $decoche['notify_articles_depuis'],
        ]);
        verifier(
            'date d’accord : décocher efface la date',
            $decoche === ['notify_articles' => 0, 'notify_articles_depuis' => 0]
            && (int) db_value('SELECT notify_articles_depuis FROM accounts WHERE id = ?', [$nouveau], 1) === 0
        );

        // Pendant la désinscription, un article paraît.
        $pendant = tests_courriel_article('Article paru pendant la désinscription', 'public', time() - 10);
        courriel_notifier_article(db_one('SELECT * FROM articles WHERE id = ?', [$pendant]));
        verifier(
            'date d’accord : un compte désinscrit ne reçoit rien',
            !in_array($nouveau, $destinataires($pendant), true)
        );

        // La personne recoche plus tard : la date repart de maintenant.
        $apres = db_one('SELECT * FROM accounts WHERE id = ?', [$nouveau]);
        $recoche = courriel_accord_articles($apres, true);
        db_update('accounts', $nouveau, [
            'notify_articles' => $recoche['notify_articles'],
            'notify_articles_depuis' => $recoche['notify_articles_depuis'],
        ]);
        verifier(
            'date d’accord : recocher pose une date toute neuve',
            $recoche['notify_articles'] === 1
            && $recoche['notify_articles_depuis'] >= $avant['notify_articles_depuis'],
            (string) $recoche['notify_articles_depuis']
        );

        $retard = tests_courriel_article('Article de la veille', 'public', time() - 86400);
        courriel_notifier_article(db_one('SELECT * FROM articles WHERE id = ?', [$retard]));
        verifier(
            'date d’accord : après un ré-abonnement, la veille ne repart pas',
            !in_array($nouveau, $destinataires($retard), true)
        );

        // La page « Mon compte » enregistre bien la date, et pas seulement la
        // case. (Appel direct : matrice_requete() remet la session à zéro, ce
        // qui effacerait le jeton anti-CSRF au passage. Le contrôleur est donc
        // chargé ici : sans lui, l'appel lèverait une erreur avalée par le
        // catch ci-dessous, et le test passerait à côté du vrai problème.)
        require_once dirname(__DIR__) . '/controllers/compte.php';
        db_update('accounts', $nouveau, ['notify_articles' => 0, 'notify_articles_depuis' => 0]);
        $_SESSION = ['account_id' => $nouveau];
        $_POST    = [
            '_csrf'           => csrf_token(),
            'email'           => 'nouveau@exemple.fr',
            'notify_articles' => '1',
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/mon-compte/courriels';
        $niveau = ob_get_level();
        ob_start();
        try {
            mon_compte_courriels();
        } catch (Throwable) {
            // Fin normale : la page renvoie vers « Mon compte ».
        }
        while (ob_get_level() > $niveau) {
            ob_end_clean();
        }
        $_POST = [];
        $enregistre = db_one('SELECT notify_articles, notify_articles_depuis FROM accounts WHERE id = ?', [$nouveau]);
        verifier(
            'date d’accord : cocher la case dans « Mon compte » pose la date',
            (int) $enregistre['notify_articles'] === 1
            && (int) $enregistre['notify_articles_depuis'] > 0,
            json_encode($enregistre)
        );
        $_SESSION = [];

        // -------------------------------------------------------------------
        // 9. Article programmé, contenu, annulation
        // -------------------------------------------------------------------
        $programme = tests_courriel_article('Article programmé', 'public', time() + 3600);
        courriel_notifier_article(db_one('SELECT * FROM articles WHERE id = ?', [$programme]));
        verifier('article programmé : rien ne part avant l’heure', $destinataires($programme) === []);

        // À l'échéance, c'est la commande qui prend le relais.
        db_update('articles', $programme, ['published_at' => time() - 60]);
        courriel_articles_echus();
        verifier(
            'article programmé : la commande prévient la famille à l’heure dite',
            array_diff($attendus, $destinataires($programme)) === [],
            implode(', ', $destinataires($programme))
        );

        $sansCase = tests_courriel_article('Article sans prévenance', 'public', 0, 0);
        courriel_notifier_article(db_one('SELECT * FROM articles WHERE id = ?', [$sansCase]));
        verifier('article : la case décochée empêche tout envoi', $destinataires($sansCase) === []);

        $brouillon = tests_courriel_article('Article à annuler', 'public');
        courriel_notifier_article(db_one('SELECT * FROM articles WHERE id = ?', [$brouillon]));
        $avantAnnulation = count($destinataires($brouillon));
        $annules = courriel_annuler_article($brouillon);
        verifier(
            'article repassé en brouillon : les envois en attente sont annulés',
            $avantAnnulation >= 4 && $annules === $avantAnnulation,
            $avantAnnulation . ' / ' . $annules
        );
        $enAttente = (int) db_value(
            "SELECT COUNT(*) FROM email_queue WHERE status = 'attente' AND dedupe_key LIKE ?",
            ['article:' . $brouillon . ':%'],
            0
        );
        verifier('article annulé : plus rien n’attend dans la file', $enAttente === 0);

        // Contenu : ni texte intégral, ni photo, et un lien vers la connexion
        // quand l'article n'est pas public.
        $messagePrive = null;
        foreach (courriel_derniers(200) as $ligne) {
            if (str_contains((string) $ligne['subject'], 'Article privé')) {
                $messagePrive = $ligne;
                break;
            }
        }
        verifier('article privé : un message a bien été préparé', $messagePrive !== null);
        if ($messagePrive !== null) {
            $complet = db_one('SELECT body_text FROM email_queue WHERE id = ?', [(int) $messagePrive['id']]);
            $texte = (string) ($complet['body_text'] ?? '');
            verifier(
                'article privé : le texte intégral n’est jamais envoyé',
                !str_contains($texte, 'Texte complet')
            );
            verifier(
                'article privé : le lien passe par la connexion',
                str_contains($texte, '/connexion?next=')
            );
        }

        // -------------------------------------------------------------------
        // 10. Plafond quotidien et essais
        // -------------------------------------------------------------------
        db_update('email_queue', (int) $id1, [
            'status' => 'envoye', 'sent_at' => time(), 'attempts' => 1,
        ]);
        save_site_setting('courriel_plafond', '1');
        $selection = courriel_a_envoyer(10);
        verifier(
            'plafond : une fois le plafond atteint, plus rien n’est envoyé',
            $selection['messages'] === [] && $selection['restant'] === 0,
            'restant=' . $selection['restant']
        );

        save_site_setting('courriel_plafond', '50');
        $selection = courriel_a_envoyer(2);
        verifier(
            'plafond : la commande n’envoie que par petits lots de 10 au plus',
            count($selection['messages']) === 2 && courriel_plafond_jour() === 50,
            count($selection['messages']) . ' message(s)'
        );

        // Un message déjà essayé trois fois sort de la file d'attente.
        db_update('email_queue', (int) $selection['messages'][0]['id'], ['attempts' => 3]);
        $apresTrois = courriel_a_envoyer(10);
        verifier(
            'échecs : après trois essais, un message n’est plus représenté',
            !in_array((int) $selection['messages'][0]['id'],
                array_map(static fn (array $m): int => (int) $m['id'], $apresTrois['messages']), true)
        );

        // -------------------------------------------------------------------
        // 11. La commande d'envoi de bout en bout (faux serveur scripté)
        // -------------------------------------------------------------------
        // On repart d'une file propre pour ce passage. Aucune connexion
        // réseau : la « conversation » est une liste de réponses.
        db_exec('DELETE FROM email_queue');
        db_update('accounts', $inscrit, ['notify_articles' => 1, 'notify_gazette' => 1]);
        db_update('accounts', $sansAccord, ['email' => 'sans.accord@exemple.fr', 'notify_articles' => 0]);

        $articleEnvoi = tests_courriel_article('Article à envoyer', 'public');
        courriel_notifier_article(db_one('SELECT * FROM articles WHERE id = ?', [$articleEnvoi]));
        $enFile = (int) db_value("SELECT COUNT(*) FROM email_queue WHERE status = 'attente'", [], 0);
        verifier('commande : les messages en attente sont prêts', $enFile >= 4, (string) $enFile);

        $reglages = [
            'host' => 'smtp.exemple.fr', 'port' => 587, 'encryption' => 'tls',
            'username' => 'compte', 'password' => 'secret-de-test',
            'from_address' => 'site@exemple.fr', 'from_name' => 'Histoire de famille', 'timeout' => 5,
        ];
        $reussite = ['220 pret', "250-smtp\n250-STARTTLS\n250 AUTH LOGIN", '220 go', '250 ok',
            '334 user', '334 pass', '235 ok', '250 ok', '250 ok', '354 go', '250 queued', '221 bye'];

        // Une conversation neuve par message, comme une vraie connexion.
        $conversation = static function (array $lignes): Closure {
            return static fn (): CourrielSmtpScript => new CourrielSmtpScript($lignes);
        };

        $envoi1 = courriel_traiter_file(
            ['lot' => 2, 'pause' => 0, 'essais' => 3, 'purger' => 90, 'echus' => false],
            null,
            $conversation($reussite),
            $reglages
        );
        verifier(
            'commande : elle n’envoie que par petits lots (2 ici)',
            $envoi1['envoyes'] === 2 && $envoi1['echecs'] === 0,
            $envoi1['envoyes'] . ' envoyé(s)'
        );
        verifier(
            'commande : un message envoyé passe à « envoyé » avec sa date',
            (int) db_value("SELECT COUNT(*) FROM email_queue WHERE status = 'envoye' AND sent_at > 0", [], 0) === 2
        );

        // Échec passager : le message reste en attente pour un nouvel essai.
        $echec = $conversation(['220 pret', '421 4.7.0 Trop de choses, revenez plus tard']);
        $envoi2 = courriel_traiter_file(
            ['lot' => 1, 'pause' => 0, 'essais' => 3, 'purger' => 90, 'echus' => false],
            null,
            $echec,
            $reglages
        );
        verifier(
            'commande : un échec passager laisse le message en attente',
            $envoi2['echecs'] === 1
            && (int) db_value("SELECT COUNT(*) FROM email_queue WHERE status = 'attente' AND attempts = 1", [], 0) === 1
        );
        $erreurLisible = (string) db_value("SELECT last_error FROM email_queue WHERE status = 'attente' AND attempts = 1", [], '');
        verifier(
            'commande : l’échec est expliqué en français, sans détail technique',
            $erreurLisible !== '' && !str_contains($erreurLisible, 'secret-de-test'),
            $erreurLisible
        );

        $envoi3 = courriel_traiter_file(
            ['lot' => 1, 'pause' => 0, 'essais' => 3, 'purger' => 90, 'echus' => false],
            null,
            $conversation($reussite),
            $reglages
        );
        verifier(
            'commande : le nouvel essai réussit et le message part',
            $envoi3['envoyes'] === 1 && $envoi3['echecs'] === 0,
            $envoi3['envoyes'] . ' / ' . $envoi3['echecs']
        );

        // Plafond : le reste de la file attend demain.
        save_site_setting('courriel_plafond', '1');
        $envoi4 = courriel_traiter_file(
            ['lot' => 10, 'pause' => 0, 'essais' => 3, 'purger' => 90, 'echus' => false],
            null,
            $conversation($reussite),
            $reglages
        );
        verifier(
            'commande : le plafond quotidien arrête l’envoi, sans rien perdre',
            $envoi4['envoyes'] === 0 && $envoi4['arret'] === 'plafond'
            && (int) db_value("SELECT COUNT(*) FROM email_queue WHERE status = 'attente'", [], 0) >= 1,
            $envoi4['envoyes'] . ' envoyé(s), arrêt « ' . $envoi4['arret'] . ' »'
        );
        save_site_setting('courriel_plafond', '50');

        // Sans configuration, la file est conservée et rien n'est perdu.
        db_exec('DELETE FROM email_queue');
        $articleSansConfig = tests_courriel_article('Article sans serveur', 'public');
        courriel_notifier_article(db_one('SELECT * FROM articles WHERE id = ?', [$articleSansConfig]));
        $sansConfig = courriel_traiter_file(['pause' => 0, 'echus' => false]);
        verifier(
            'commande : sans serveur d’envoi, elle explique et garde la file',
            $sansConfig['configure'] === false && $sansConfig['arret'] === 'non_configure'
            && (int) db_value("SELECT COUNT(*) FROM email_queue WHERE status = 'attente'", [], 0) >= 4,
            $sansConfig['raison']
        );

        // -------------------------------------------------------------------
        // 12. Désinscription (page et « One-Click »)
        // -------------------------------------------------------------------
        $jetonInscrit = courriel_jeton($inscrit, 'articles');

        $page = matrice_requete('GET', '/desinscription?type=articles&jeton=' . $jetonInscrit, null);
        verifier(
            'désinscription : la page demande une confirmation, sans rien changer',
            $page['code'] === 200
            && str_contains($page['corps'], 'Confirmer la désinscription')
            && (int) db_value('SELECT notify_articles FROM accounts WHERE id = ?', [$inscrit], 0) === 1,
            'code ' . $page['code']
        );

        $invalide = matrice_requete('GET', '/desinscription?type=articles&jeton=' . str_repeat('f', 64), null);
        verifier('désinscription : un jeton inconnu affiche « lien invalide »', $invalide['code'] === 404);

        $oneClick = matrice_requete('POST', '/desinscription', null, [], [
            'type' => 'articles', 'jeton' => $jetonInscrit, 'List-Unsubscribe' => 'One-Click',
        ]);
        verifier(
            'désinscription en un clic : le compte est désinscrit',
            $oneClick['code'] === 200
            && (int) db_value('SELECT notify_articles FROM accounts WHERE id = ?', [$inscrit], 0) === 0,
            'code ' . $oneClick['code']
        );
        verifier(
            'désinscription : la gazette reste cochée (un type d’envoi à la fois)',
            (int) db_value('SELECT notify_gazette FROM accounts WHERE id = ?', [$inscrit], 0) === 1
        );

        // -------------------------------------------------------------------
        // 13. Purge
        // -------------------------------------------------------------------
        $vieux = courriel_enfiler([
            'account_id' => $inscrit, 'kind' => 'article', 'recipient' => 'inscrit@exemple.fr',
            'subject' => 'Vieux message', 'text' => 'Texte', 'html' => '',
        ]);
        db_update('email_queue', $vieux, [
            'status' => 'envoye', 'sent_at' => time() - 200 * 86400, 'attempts' => 1,
        ]);
        $purges = courriel_purger(90);
        verifier('purge : les messages terminés de plus de 90 jours sont retirés', $purges >= 1, (string) $purges);

        // -------------------------------------------------------------------
        // 13 bis. Le tableau des derniers envois se replie (plus de colonne
        //        coupée à droite sur un grand écran).
        // -------------------------------------------------------------------
        $vueCourriels = (string) @file_get_contents(dirname(__DIR__) . '/views/admin/courriels.php');
        $feuille     = (string) @file_get_contents(dirname(__DIR__, 2) . '/public/assets/style.css');
        verifier(
            'page Courriels : le tableau des envois porte sa propre classe',
            str_contains($vueCourriels, 'class="tableau tableau--courriels"')
            && str_contains($vueCourriels, 'class="colonne-erreur"')
        );
        verifier(
            'page Courriels : l’erreur et l’objet peuvent passer à la ligne',
            str_contains($feuille, '.tableau--courriels th,')
            && str_contains($feuille, 'overflow-wrap: anywhere;')
        );
        verifier(
            'page Courriels : plus de défilement horizontal sur un grand écran',
            (bool) preg_match(
                '/@media \(min-width: 62rem\) \{[^}]*\.tableau-defilant \.tableau--courriels \{ min-width: 0;/s',
                $feuille
            )
        );

        // -------------------------------------------------------------------
        // 14. Journaux : jamais d'adresse complète
        // -------------------------------------------------------------------
        $journaux = db_all("SELECT details FROM activity_log WHERE action LIKE 'courriel%'");
        $fuite = '';
        foreach ($journaux as $journal) {
            if (preg_match('/[a-z0-9._-]+@[a-z0-9.-]+/i', (string) $journal['details'], $m) === 1) {
                $fuite = $m[0];
            }
        }
        verifier('journal : aucune adresse complète dans le journal d’activité', $fuite === '', $fuite);

        $tousJournaux = db_all('SELECT details FROM activity_log');
        $secret = '';
        foreach ($tousJournaux as $journal) {
            if (str_contains((string) $journal['details'], 'secret-de-test')) {
                $secret = 'mot de passe SMTP recopié';
            }
        }
        verifier('journal : aucun mot de passe d’envoi dans le journal', $secret === '', $secret);
    } finally {
        if ($transaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        save_site_setting('courriel_plafond', '');
    }
}
