<?php
/**
 * Famille « contacts » (1.28.0) : adresses affichées et courriels coupés.
 *
 * Ce que l'on vérifie, dans l'ordre :
 *   1. les deux adresses affichées (contact, assistance) viennent des réglages,
 *      avec les valeurs livrées en repli, et passent par une seule fonction ;
 *   2. elles sont écrites aux endroits demandés, en lien mailto ordinaire
 *      (pied de page, connexion, « Mon compte », accueil, pages d'erreur) ;
 *   3. avec le réglage « courriels_actifs » faux (défaut), rien n'entre dans la
 *      file et rien ne part — mais tout le code d'envoi est encore là ;
 *   4. les écrans de courriel sont masqués (menu, champs d'adresse, cases de
 *      consentement, envoi de la gazette, case « Prévenir les inscrits ») ;
 *   5. les adresses personnelles des comptes ne sont jamais affichées, et
 *      enregistrer un compte ne perd pas une adresse déjà saisie ;
 *   6. réactiver le réglage fait revenir les écrans (et seuls ceux-là).
 *
 * Tout se passe dans une transaction annulée : la base du site est rendue
 * intacte, y compris les réglages touchés ici.
 */

declare(strict_types=1);

/** Compte de test avec une adresse, pour vérifier qu'elle n'est jamais montrée. */
function tests_contacts_compte(?int $branche, string $email, bool $admin = false): int
{
    return db_insert('accounts', [
        'username'        => 'contacts.' . bin2hex(random_bytes(4)),
        'display_name'    => 'Contacts Essai',
        'password_hash'   => password_make('MotDePasseEssai123!'),
        'role'            => $admin ? 'admin' : 'famille',
        'account_type'    => $admin ? 'admin' : 'personne',
        'first_name'      => 'Contacts',
        'last_name'       => 'Essai',
        'branch_id'       => $branche,
        'see_all'         => 0,
        'is_active'       => 1,
        'email'           => $email,
        'notify_articles' => 1,
        'notify_gazette'  => 1,
        'created_at'      => time(),
        'updated_at'      => time(),
    ]);
}

/** Petit branche de test. */
function tests_contacts_branche(): int
{
    return db_insert('branches', [
        'code'        => 'CTC' . strtoupper(bin2hex(random_bytes(2))),
        'name'        => 'Contacts ' . bin2hex(random_bytes(2)),
        'description' => null,
        'see_all'     => 0,
        'is_active'   => 1,
        'created_at'  => time(),
        'updated_at'  => time(),
    ]);
}

function tests_contacts(): void
{
    echo "Contacts — adresses affichées, courriels coupés, écrans masqués\n";

    $pdo = db();
    $transaction = false;

    try {
        $pdo->beginTransaction();
        $transaction = true;

        // ===================================================================
        // 1. Les adresses : une seule fonction de lecture, des valeurs de repli
        // ===================================================================
        // Sans réglage en base, l'adresse vient de la configuration livrée avec
        // le site : c'est elle qui donne les valeurs initiales.
        save_site_setting('contact', '');
        save_site_setting('support', '');
        verifier(
            'adresses : sans réglage, l’adresse de contact vient de la configuration du site',
            site_adresse('contact') === courriel_adresse_normaliser((string) config('site.contact')),
            site_adresse('contact') . ' ≠ ' . (string) config('site.contact')
        );
        verifier(
            'adresses : sans réglage, l’adresse d’assistance vient de la configuration du site',
            site_adresse('support') === courriel_adresse_normaliser((string) config('site.support')),
            site_adresse('support') . ' ≠ ' . (string) config('site.support')
        );
        $deux = site_adresses();
        verifier(
            'adresses : site_adresses() donne les deux, dans l’ordre attendu',
            array_keys($deux) === ['contact', 'support']
                && $deux['contact'] === site_adresse('contact')
                && $deux['support'] === site_adresse('support'),
            json_encode($deux)
        );
        verifier(
            'adresses : les deux valeurs livrées avec le site sont bien celles annoncées',
            SITE_ADRESSES_DEFAUT === [
                'contact' => 'contact@histoiredefamille.eu',
                'support' => 'support@histoiredefamille.eu',
            ],
            json_encode(SITE_ADRESSES_DEFAUT)
        );

        save_site_setting('contact', 'Autre.Adresse@Exemple.FR');
        verifier(
            'adresses : le réglage l’emporte, et l’adresse est rangée en minuscules',
            site_adresse('contact') === 'autre.adresse@exemple.fr',
            site_adresse('contact')
        );

        save_site_setting('contact', 'pas-une-adresse');
        verifier(
            'adresses : une adresse invalide en base n’est jamais affichée (repli sur le défaut)',
            site_adresse('contact') === 'contact@histoiredefamille.eu',
            site_adresse('contact')
        );
        save_site_setting('contact', '');

        verifier('adresses : une clé inconnue ne rend rien', site_adresse('autre') === '');

        // ===================================================================
        // 2. L'affichage : les bons endroits, en lien mailto ordinaire
        // ===================================================================
        $branche = tests_contacts_branche();
        $compte  = tests_contacts_compte($branche, 'personne.privee@exemple.fr');
        $admin   = tests_contacts_compte(null, 'admin.prive@exemple.fr', true);

        // Le réglage livré, faux, avant de lire la moindre page : c'est l'état
        // normal du site depuis la 1.28.0.
        save_site_setting('courriels_actifs', '0');

        $mailtoContact = 'mailto:' . site_adresse('contact');
        $mailtoSupport = 'mailto:' . site_adresse('support');

        $accueil = tests_envoi_requete('GET', '/', null);
        verifier(
            'accueil : « Nous écrire » avec l’adresse de contact, en lien mailto',
            (int) $accueil['code'] === 200
                && str_contains((string) $accueil['corps'], 'Nous écrire')
                && str_contains((string) $accueil['corps'], $mailtoContact),
            'code ' . $accueil['code']
        );

        $connexion = tests_envoi_requete('GET', '/connexion', null);
        verifier(
            'connexion : « Mot de passe oublié ou besoin d’un compte ? » vers l’assistance',
            (int) $connexion['code'] === 200
                && str_contains((string) $connexion['corps'], 'Mot de passe oublié ou besoin d’un compte ?')
                && str_contains((string) $connexion['corps'], $mailtoSupport),
            'code ' . $connexion['code']
        );

        $page404 = tests_envoi_requete('GET', '/cette-page-n-existe-vraiment-pas', null);
        verifier(
            'page 404 : une ligne vers l’assistance',
            (int) $page404['code'] === 404
                && str_contains((string) $page404['corps'], $mailtoSupport),
            'code ' . $page404['code']
        );

        $refus = tests_envoi_requete('GET', '/admin/reglages', $compte);
        verifier(
            'accès refusé : la page dit aussi à qui écrire',
            (int) $refus['code'] === 403
                && str_contains((string) $refus['corps'], 'Accès refusé')
                && str_contains((string) $refus['corps'], $mailtoSupport),
            'code ' . $refus['code']
        );

        $monCompte = tests_envoi_requete('GET', '/mon-compte', $compte);
        verifier(
            'mon compte : « Besoin d’aide ? » vers l’assistance',
            (int) $monCompte['code'] === 200
                && str_contains((string) $monCompte['corps'], 'Besoin d’aide ?')
                && str_contains((string) $monCompte['corps'], $mailtoSupport),
            'code ' . $monCompte['code']
        );

        $documents = tests_envoi_requete('GET', '/documents', null);
        verifier(
            'pied de page : « Une question ? » et l’adresse de contact sur une page publique',
            (int) $documents['code'] === 200
                && str_contains((string) $documents['corps'], 'Une question ?')
                && str_contains((string) $documents['corps'], $mailtoContact),
            'code ' . $documents['code']
        );

        $admin_page = tests_envoi_requete('GET', '/admin', $admin);
        verifier(
            'pied de page : la même ligne dans l’administration',
            (int) $admin_page['code'] === 200
                && str_contains((string) $admin_page['corps'], 'Une question ?')
                && str_contains((string) $admin_page['corps'], $mailtoContact),
            'code ' . $admin_page['code']
        );

        // Aucune adresse personnelle ne doit apparaître nulle part.
        $fuites = [];
        foreach ([$accueil, $connexion, $monCompte, $documents, $admin_page, $page404] as $page) {
            foreach (['personne.privee@exemple.fr', 'admin.prive@exemple.fr'] as $privee) {
                if (str_contains((string) $page['corps'], $privee)) {
                    $fuites[] = $privee;
                }
            }
        }
        verifier(
            'données : aucune adresse de compte n’est affichée sur ces pages',
            $fuites === [],
            implode(', ', array_unique($fuites))
        );

        // ===================================================================
        // 3. Courriels coupés : rien en file, rien qui part
        // ===================================================================
        save_site_setting('courriels_actifs', '');
        verifier(
            'réglage : les courriels sont coupés quand le réglage est absent (défaut)',
            site_courriels_actifs() === false
        );
        save_site_setting('courriels_actifs', '0');
        verifier('réglage : « 0 » vaut coupé', site_courriels_actifs() === false);

        $fileAvant = (int) db_value('SELECT COUNT(*) FROM email_queue', [], 0);
        $idFile = courriel_enfiler([
            'recipient'   => 'marie.dupont@exemple.fr',
            'subject'     => 'Essai',
            'text'        => 'Ce message ne doit jamais entrer dans la file.',
            'kind'        => 'article',
        ]);
        $fileApres = (int) db_value('SELECT COUNT(*) FROM email_queue', [], 0);
        verifier(
            'file : aucun message n’est mis en attente quand les courriels sont coupés',
            $idFile === 0 && $fileApres === $fileAvant,
            'id ' . $idFile . ', file ' . $fileAvant . ' → ' . $fileApres
        );

        $envoi = courriel_envoyer(['recipient' => 'marie.dupont@exemple.fr', 'subject' => 'x', 'text' => 'y']);
        verifier(
            'envoi : refusé, et le message dit que c’est volontaire (pas une panne)',
            $envoi['ok'] === false
                && str_contains($envoi['message'], 'désactivés volontairement')
                && !str_contains($envoi['message'], 'config.local.php'),
            $envoi['message']
        );

        $traitement = courriel_traiter_file(['pause' => 0, 'gazette' => false]);
        verifier(
            'commande : la file n’est ni préparée ni envoyée (arrêt « desactive »)',
            ($traitement['arret'] ?? '') === 'desactive'
                && (int) $traitement['envoyes'] === 0
                && (int) $traitement['enfiles'] === 0,
            (string) ($traitement['arret'] ?? '')
        );

        // Un article publié « maintenant » ne doit rien préparer du tout.
        db_insert('articles', [
            'slug'         => 'essai-contacts-' . bin2hex(random_bytes(4)),
            'title'        => 'Article sans courriel',
            'summary'      => 'Résumé.',
            'body'         => 'Texte.',
            'category'     => 'Souvenirs',
            'visibility'   => 'public',
            'status'       => 'publie',
            'published_at' => time() - 60,
            'notify'       => 1,
            'created_at'   => time(),
            'updated_at'   => time(),
        ]);
        $echus = courriel_articles_echus();
        $fileEncore = (int) db_value('SELECT COUNT(*) FROM email_queue', [], 0);
        verifier(
            'article publié : aucune notification n’est préparée',
            $echus === 0 && $fileEncore === $fileAvant,
            'préparés ' . $echus . ', file ' . $fileEncore
        );

        // La commande et la vérification avant ouverture disent la même chose.
        $cli = (string) file_get_contents(dirname(__DIR__) . '/cli/envoyer-courriels.php');
        verifier(
            'commande app/cli/envoyer-courriels.php : elle s’arrête et l’écrit',
            str_contains($cli, 'site_courriels_actifs()')
                && str_contains($cli, 'désactivés volontairement')
                && str_contains($cli, 'exit(0)')
        );

        $controles = mise_en_ligne_controles(['systeme' => false]);
        $ligneCourriel = null;
        foreach ($controles as $controle) {
            if (($controle['cle'] ?? '') === 'courriel') {
                $ligneCourriel = $controle;
            }
        }
        verifier(
            'vérification avant ouverture : « Envoi de courriels » est OK, désactivé volontairement',
            $ligneCourriel !== null
                && ($ligneCourriel['statut'] ?? '') === 'ok'
                && str_contains((string) ($ligneCourriel['detail'] ?? ''), 'désactivé volontairement'),
            $ligneCourriel === null ? 'ligne absente' : json_encode($ligneCourriel, JSON_UNESCAPED_UNICODE)
        );

        // ===================================================================
        // 4. Les écrans de courriel sont masqués
        // ===================================================================
        $adminCoupe = tests_envoi_requete('GET', '/admin', $admin);
        verifier(
            'menu : le lien « Courriels » disparaît de l’administration',
            (int) $adminCoupe['code'] === 200
                && !str_contains((string) $adminCoupe['corps'], 'href="/admin/courriels"'),
            'code ' . $adminCoupe['code'] . ', lien encore présent'
        );

        $compteCoupe  = tests_envoi_requete('GET', '/mon-compte', $compte);
        $compteHtml   = (string) $compteCoupe['corps'];
        verifier(
            'mon compte : ni section « Mes courriels », ni champ d’adresse, ni case de consentement',
            !str_contains($compteHtml, 'Mes courriels')
                && !str_contains($compteHtml, 'name="email"')
                && !str_contains($compteHtml, 'name="notify_articles"')
                && !str_contains($compteHtml, 'name="notify_gazette"')
                && !str_contains($compteHtml, 'désinscrire'),
            'un élément de courriel est encore là'
        );

        verifier(
            'mon compte : la page reste lisible et garde l’aide par courriel',
            (int) $compteCoupe['code'] === 200
                && str_contains($compteHtml, 'Besoin d’aide ?')
                && str_contains($compteHtml, $mailtoSupport),
            'code ' . $compteCoupe['code']
        );

        $formulaireCompte = tests_envoi_requete('GET', '/admin/comptes/' . $compte, $admin);
        verifier(
            'fiche d’un compte : le bloc « Courriels » n’est plus proposé',
            (int) $formulaireCompte['code'] === 200
                && !str_contains((string) $formulaireCompte['corps'], 'name="email"')
                && !str_contains((string) $formulaireCompte['corps'], 'name="notify_articles"')
                && !str_contains((string) $formulaireCompte['corps'], 'name="notify_gazette"'),
            'code ' . $formulaireCompte['code']
        );

        $creation = tests_envoi_requete('GET', '/admin/comptes/nouvelle', $admin);
        verifier(
            'création d’un compte : pas de champ d’adresse électronique',
            (int) $creation['code'] === 200
                && !str_contains((string) $creation['corps'], 'name="email"'),
            'code ' . $creation['code']
        );

        $gazette = tests_envoi_requete('GET', '/admin/gazette', $admin);
        $gazetteHtml = (string) $gazette['corps'];
        verifier(
            'gazette : plus d’envoi par courriel (ni bouton, ni envoi automatique, ni « Envoyé le »)',
            (int) $gazette['code'] === 200
                && !str_contains($gazetteHtml, 'Envoi automatique')
                && !str_contains($gazetteHtml, 'Envoyé le')
                && str_contains($gazetteHtml, 'Les courriels sont désactivés'),
            'code ' . $gazette['code']
        );

        $articleId = (int) db_value('SELECT id FROM articles ORDER BY id DESC LIMIT 1', [], 0);
        $article = tests_envoi_requete('GET', '/admin/articles/' . $articleId, $admin);
        verifier(
            'article publié : la case « Prévenir les inscrits par courriel » n’est plus là',
            (int) $article['code'] === 200
                && !str_contains((string) $article['corps'], 'Prévenir les inscrits par courriel')
                && !str_contains((string) $article['corps'], 'name="notify"'),
            'code ' . $article['code']
        );

        $gazettePublique = tests_envoi_requete('GET', '/gazette', null);
        verifier(
            'gazette publique : plus de lien « Gérer mes courriels » pour un visiteur',
            !str_contains((string) $gazettePublique['corps'], 'Gérer mes courriels'),
            'lien encore présent'
        );

        $pageCourriels = tests_envoi_requete('GET', '/admin/courriels', $admin);
        verifier(
            'page Courriels (adresse directe) : elle dit que tout est coupé, sans formulaire d’envoi',
            (int) $pageCourriels['code'] === 200
                && str_contains((string) $pageCourriels['corps'], 'Les courriels sont désactivés sur ce site')
                && !str_contains((string) $pageCourriels['corps'], '/admin/courriels/essai'),
            'code ' . $pageCourriels['code']
        );

        // La création de compte garde « Copier le message ».
        $resultatVue = (string) file_get_contents(dirname(__DIR__) . '/views/admin/comptes_resultat.php');
        verifier(
            'création de compte : « Copier le message » reste là (transmission à la main)',
            str_contains($resultatVue, 'Copier le message')
        );

        // ===================================================================
        // 5. Enregistrer un compte ne perd pas l'adresse déjà saisie
        // ===================================================================
        $nouveauJeton = static function () use ($admin): string {
            $_SESSION = ['account_id' => $admin];
            return csrf_token();
        };
        $modifier = tests_envoi_requete('POST', '/admin/comptes/' . $compte, $admin, [
            '_csrf'      => $nouveauJeton(),
            'profil'     => 'journaliste',
            'nom'        => 'Essai',
            'prenom'     => 'Contacts',
            'identifiant' => 'contacts' . bin2hex(random_bytes(3)),
            'branche'    => (string) $branche,
            'see_all'    => '0',
        ]);
        $apres = db_one('SELECT email, notify_articles, notify_gazette FROM accounts WHERE id = ?', [$compte]);
        verifier(
            'enregistrement d’un compte sans champ d’adresse : l’adresse et les accords sont conservés',
            $modifier['code'] === 303
                && (string) ($apres['email'] ?? '') === 'personne.privee@exemple.fr'
                && (int) ($apres['notify_articles'] ?? 0) === 1
                && (int) ($apres['notify_gazette'] ?? 0) === 1,
            $modifier['code'] . ' ' . json_encode($apres)
        );

        // ===================================================================
        // 6. Réactiver : les écrans reviennent, et rien d'autre ne change
        // ===================================================================
        save_site_setting('courriels_actifs', '1');
        verifier('réglage : « 1 » rallume l’envoi', site_courriels_actifs() === true);

        $monCompteActif = tests_envoi_requete('GET', '/mon-compte', $compte);
        verifier(
            'réactivé : « Mes courriels » revient dans mon compte',
            str_contains((string) $monCompteActif['corps'], 'Mes courriels')
                && str_contains((string) $monCompteActif['corps'], 'name="email"'),
            'section absente'
        );

        $menuActif = tests_envoi_requete('GET', '/admin', $admin);
        verifier(
            'réactivé : le lien « Courriels » revient dans le menu',
            str_contains((string) $menuActif['corps'], 'href="/admin/courriels"')
        );

        $articleActif = tests_envoi_requete('GET', '/admin/articles/' . $articleId, $admin);
        verifier(
            'réactivé : la case « Prévenir les inscrits par courriel » revient',
            str_contains((string) $articleActif['corps'], 'Prévenir les inscrits par courriel')
        );

        // Les adresses affichées, elles, ne changent pas d'un iota.
        $accueilActif = tests_envoi_requete('GET', '/', null);
        verifier(
            'réactivé : les adresses affichées restent celles des réglages',
            str_contains((string) $accueilActif['corps'], $mailtoContact)
                && str_contains((string) $accueilActif['corps'], 'Une question ?'),
            'adresse disparue'
        );

        // ===================================================================
        // 7. Le réglage se règle par le formulaire, et se vérifie
        // ===================================================================
        $reglages = tests_envoi_requete('GET', '/admin/reglages', $admin);
        verifier(
            'réglages : les deux champs d’adresse et la case « courriels » sont dans le formulaire',
            (int) $reglages['code'] === 200
                && str_contains((string) $reglages['corps'], 'name="contact"')
                && str_contains((string) $reglages['corps'], 'name="support"')
                && str_contains((string) $reglages['corps'], 'name="courriels_actifs"'),
            'code ' . $reglages['code']
        );

        $enregistrer = static function (array $champs) use ($admin, $nouveauJeton): array {
            return tests_envoi_requete('POST', '/admin/reglages', $admin, array_merge([
                '_csrf'      => $nouveauJeton(),
                'name'       => 'Histoire de famille',
                'subtitle'   => 'Essai',
                'contact'    => 'contact@histoiredefamille.eu',
                'support'    => 'support@histoiredefamille.eu',
                'bureau'     => '',
                'devise'     => '',
                'fondee'     => '',
                'description' => '',
                'adresse'    => '',
                'tchat_actif' => '1',
                'tchat_conservation_mois' => '12',
                'envoi_morceau_mo' => '5',
                'envoi_max_mo' => '4096',
                'conversion_active' => '1',
                'conversion_qualite' => 'equilibre',
            ], $champs));
        };

        save_site_setting('contact', 'contact@histoiredefamille.eu');
        $mauvais = $enregistrer(['contact' => 'pas-une-adresse']);
        verifier(
            'réglages : une adresse de contact invalide est refusée, l’adresse en place reste',
            $mauvais['code'] === 303
                && $mauvais['location'] === '/admin/reglages'
                && site_adresse('contact') === 'contact@histoiredefamille.eu'
                && ($_SESSION['_form_errors'] ?? []) !== [],
            $mauvais['code'] . ' ' . $mauvais['location'] . ' ' . site_adresse('contact')
        );

        // Une adresse valide, elle, est enregistrée telle quelle (en minuscules).
        $bon = $enregistrer(['contact' => 'Secretariat@Exemple.FR', 'support' => 'Aide@Exemple.FR']);
        verifier(
            'réglages : les deux adresses valides sont enregistrées et normalisées',
            $bon['code'] === 303
                && site_adresse('contact') === 'secretariat@exemple.fr'
                && site_adresse('support') === 'aide@exemple.fr',
            site_adresse('contact') . ' / ' . site_adresse('support')
        );

        // Vides, elles reprennent celles livrées avec le site (jamais de page
        // sans adresse à écrire).
        $vides = $enregistrer(['contact' => '', 'support' => '']);
        verifier(
            'réglages : un champ vide remet l’adresse livrée avec le site',
            $vides['code'] === 303
                && site_adresse('contact') === 'contact@histoiredefamille.eu'
                && site_adresse('support') === 'support@histoiredefamille.eu',
            site_adresse('contact') . ' / ' . site_adresse('support')
        );

        save_site_setting('courriels_actifs', '0');
        save_site_setting('contact', '');
    } finally {
        if ($transaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}
