<?php
/**
 * Table des routes de l'application.
 *
 * Les contrôleurs sont de simples fichiers PHP : aucun framework,
 * aucune dépendance externe.
 */

declare(strict_types=1);

require_once __DIR__ . '/controllers/public.php';
require_once __DIR__ . '/controllers/auth.php';
require_once __DIR__ . '/controllers/files.php';
require_once __DIR__ . '/controllers/articles.php';
require_once __DIR__ . '/controllers/family.php';
require_once __DIR__ . '/controllers/tchat.php';
require_once __DIR__ . '/controllers/recherche.php';
require_once __DIR__ . '/controllers/admin_journal.php';
require_once __DIR__ . '/controllers/admin.php';
require_once __DIR__ . '/controllers/admin_apparence.php';
require_once __DIR__ . '/controllers/admin_composition.php';
require_once __DIR__ . '/controllers/admin_documents.php';
require_once __DIR__ . '/controllers/admin_branches.php';
require_once __DIR__ . '/controllers/admin_comptes.php';
require_once __DIR__ . '/controllers/admin_courriels.php';
require_once __DIR__ . '/controllers/admin_gazette.php';
require_once __DIR__ . '/controllers/admin_lieux.php';
require_once __DIR__ . '/controllers/admin_evenements.php';
require_once __DIR__ . '/controllers/admin_envoi.php';
require_once __DIR__ . '/controllers/admin_a_valider.php';
require_once __DIR__ . '/controllers/propositions.php';
require_once __DIR__ . '/controllers/admin_conversions.php';
require_once __DIR__ . '/controllers/compte.php';
require_once __DIR__ . '/controllers/desinscription.php';
require_once __DIR__ . '/controllers/gazette.php';
require_once __DIR__ . '/controllers/carte.php';
require_once __DIR__ . '/controllers/admin_pages.php';
require_once __DIR__ . '/controllers/admin_persons.php';
require_once __DIR__ . '/controllers/admin_import.php';
require_once __DIR__ . '/controllers/videos.php';
require_once __DIR__ . '/controllers/admin_videos.php';
require_once __DIR__ . '/controllers/albums.php';
require_once __DIR__ . '/controllers/admin_albums.php';
require_once __DIR__ . '/controllers/calendrier.php';
require_once __DIR__ . '/controllers/recettes.php';
require_once __DIR__ . '/controllers/admin_recettes.php';
require_once __DIR__ . '/controllers/admin_export.php';

// ---------------------------------------------------------------------------
// Partie publique
// ---------------------------------------------------------------------------
// Feuille de style des couleurs choisies (réglable dans l'administration).
route('GET', '/theme.css', 'theme_css');

route('GET', '/', 'public_accueil');
route('GET', '/articles', 'public_articles');
route('GET', '/articles/{slug}', 'public_article_detail');
route('GET', '/documents', 'public_documents');
route('GET', '/documents/{id}', 'public_document_detail');
route('GET', '/albums', 'public_albums');
route('GET', '/albums/{slug}', 'public_album');
route('GET', '/calendrier', 'public_calendrier');
route('GET', '/calendrier/ajouter', 'public_calendrier_ajouter');
route('POST', '/calendrier/ajouter', 'public_calendrier_ajouter_save');
route('GET', '/frise', 'public_frise');
route('GET', '/recettes', 'public_recettes');
route('GET', '/recettes/{slug}', 'public_recette');
route('GET', '/temoignages', 'public_temoignages');
route('GET', '/pages/{slug}', 'public_page');
route('GET', '/recherche', 'public_recherche');
route('GET', '/arbre', 'public_arbre');
route('GET', '/arbre/{id}', 'public_personne');
route('GET', '/api/arbre/{id}', 'api_arbre');

// Vidéos : onglet réservé aux comptes connectés.
route('GET', '/videos', 'public_videos');
route('GET', '/videos/{id}', 'public_video_detail');

// Fichiers : jamais servis directement, toujours après contrôle des droits.
route('GET', '/f/{id}', 'file_inline');
route('GET', '/f/{id}/telecharger', 'file_download');
route('GET', '/media/miniature/{id}', 'file_thumbnail');
route('GET', '/video/{id}', 'file_video');

// ---------------------------------------------------------------------------
// Connexion
// ---------------------------------------------------------------------------
route('GET', '/connexion', 'auth_login_form');
route('POST', '/connexion', 'auth_login_submit');
route('POST', '/deconnexion', 'auth_logout_action');

// ---------------------------------------------------------------------------
// Espace famille
// ---------------------------------------------------------------------------
route('GET', '/famille', 'family_home');

// Mes propositions : l'espace des journalistes (lecteurs et visiteurs refusés).
route('GET', '/propositions', 'propositions_index');
route('GET', '/propositions/nouvelle/{type}', 'proposition_form');
route('POST', '/propositions/nouvelle/{type}', 'proposition_save');
route('GET', '/propositions/{type}/{id}', 'proposition_form');
route('POST', '/propositions/{type}/{id}', 'proposition_save');
// Retirer une proposition en attente (l'auteur renonce).
route('POST', '/propositions/{type}/{id}/retirer', 'proposition_retrait');

// Envoi par morceaux réservé aux journalistes : le fichier reste « prêt » et
// ne devient un document que dans une proposition.
route('POST', '/propositions/fichier/init', 'propositions_fichier_init');
route('POST', '/propositions/fichier/morceau', 'propositions_fichier_morceau');
route('GET', '/propositions/fichier/etat', 'propositions_fichier_etat');
route('POST', '/propositions/fichier/terminer', 'propositions_fichier_terminer');
route('POST', '/propositions/fichier/annuler', 'propositions_fichier_annuler');

// Mon compte : changement de son propre mot de passe, pour tous les comptes.
route('GET', '/mon-compte', 'mon_compte');
// La gazette du mois : un numéro par groupe de droits, réservé aux ayants droit.
route('GET', '/gazette/{slug}', 'gazette_mois_page');

// Carte des lieux : dessinée par le serveur, aucune ressource externe.
route('GET', '/carte', 'public_carte');
route('GET', '/lieu/{id}', 'public_lieu');
route('POST', '/mon-compte/courriels', 'mon_compte_courriels');
route('GET', '/desinscription', 'desinscription');
route('POST', '/desinscription', 'desinscription_confirmer');
route('POST', '/mon-compte', 'mon_compte_save');

// Tchat privé : un seul salon, réservé aux personnes connectées.
route('GET', '/tchat', 'tchat_page');
route('GET', '/tchat/messages.json', 'tchat_messages_json');
route('POST', '/tchat/envoyer', 'tchat_envoyer');
route('POST', '/tchat/prenom', 'tchat_prenom_save');
route('POST', '/tchat/{id}/supprimer', 'tchat_supprimer');

// ---------------------------------------------------------------------------
// Administration
// ---------------------------------------------------------------------------
route('GET', '/admin', 'admin_dashboard');
route('GET', '/admin/courriels', 'admin_courriels');
route('GET', '/admin/gazette', 'admin_gazette');
route('GET', '/admin/gazette/{id}', 'admin_gazette_apercu');
route('POST', '/admin/gazette/auto', 'admin_gazette_auto');
route('POST', '/admin/gazette/{id}/retirer', 'admin_gazette_retirer');
route('POST', '/admin/gazette/{id}/mot', 'admin_gazette_mot');
route('POST', '/admin/gazette/{id}/envoyer', 'admin_gazette_envoyer');
route('POST', '/admin/gazette/{id}/passer', 'admin_gazette_passer');

// Envoi de gros fichiers par morceaux (administrateurs seulement).
// Le protocole répond en JSON ; les morceaux ne sont jamais servis.
route('GET', '/admin/a-valider', 'admin_a_valider');
route('GET', '/admin/a-valider/{type}/{id}', 'admin_a_valider_apercu');
route('POST', '/admin/a-valider/{type}/{id}/valider', 'admin_a_valider_valider');
route('POST', '/admin/a-valider/{type}/{id}/refuser', 'admin_a_valider_refuser');

route('GET', '/admin/envoyer', 'admin_envoi_page');
route('GET', '/admin/envoyer/lot', 'admin_envoi_lot');
route('POST', '/admin/envoyer/init', 'admin_envoi_init');
route('POST', '/admin/envoyer/morceau', 'admin_envoi_morceau');
route('GET', '/admin/envoyer/etat', 'admin_envoi_etat');
route('POST', '/admin/envoyer/terminer', 'admin_envoi_terminer');
route('POST', '/admin/envoyer/decision', 'admin_envoi_decision');
route('POST', '/admin/envoyer/annuler', 'admin_envoi_annuler');
route('POST', '/admin/envoyer/creer-document', 'admin_envoi_creer_document');
route('POST', '/admin/envoyer/supprimer', 'admin_envoi_supprimer');
route('POST', '/admin/envoyer/lot', 'admin_envoi_lot_save');
route('GET', '/admin/envois', 'admin_envois');

// Conversion des vidéos en MP4 : la file d'attente, surveillée par un
// minuteur systemd (app/cli/convertir.php). Rien n'est converti pendant une
// visite : ces pages remplissent et surveillent la file, c'est tout.
route('GET', '/admin/conversions', 'admin_conversions');
route('POST', '/admin/conversions/planifier', 'admin_conversion_planifier');
route('POST', '/admin/conversions/{id}/relancer', 'admin_conversion_relancer');
route('POST', '/admin/conversions/{id}/annuler', 'admin_conversion_annuler');

// Lieux de la carte : donner des coordonnées aux textes des fiches.
route('GET', '/admin/calendrier', 'admin_evenements');
route('GET', '/admin/calendrier/nouveau', 'admin_evenement_form');
route('POST', '/admin/calendrier/nouveau', 'admin_evenement_save');
route('GET', '/admin/calendrier/{id}/modifier', 'admin_evenement_form');
route('POST', '/admin/calendrier/{id}/modifier', 'admin_evenement_save');
route('POST', '/admin/calendrier/{id}/supprimer', 'admin_evenement_supprimer');
route('POST', '/admin/calendrier/{id}/valider', 'admin_evenement_valider');
route('GET', '/admin/lieux', 'admin_lieux');
route('POST', '/admin/lieux/placer', 'admin_lieux_placer');
route('POST', '/admin/lieux/supprimer', 'admin_lieux_supprimer');
route('POST', '/admin/courriels/reglages', 'admin_courriels_reglages');
route('POST', '/admin/courriels/essai', 'admin_courriels_essai');
route('GET', '/admin/etat', 'admin_status');
route('GET', '/admin/connexions', 'admin_connexions');
route('POST', '/admin/connexions/conservation', 'admin_connexions_reglage');
route('POST', '/admin/alertes/acquitter', 'admin_alertes_acquitter');
route('GET', '/admin/reglages', 'admin_settings');
route('POST', '/admin/reglages', 'admin_settings_save');
route('GET', '/admin/apparence', 'admin_apparence');
route('POST', '/admin/apparence', 'admin_apparence_save');
route('GET', '/admin/composition', 'admin_composition');
route('POST', '/admin/composition', 'admin_composition_action');
route('POST', '/admin/composition/reglages', 'admin_composition_reglages');
route('POST', '/admin/composition/retablir', 'admin_composition_retablir');
// Même page, présentée dans l'administration quand on vient de là.
route('GET', '/admin/mon-compte', 'mon_compte');
route('POST', '/admin/mon-compte', 'mon_compte_save');

// Documents
route('GET', '/admin/documents', 'admin_documents');
route('GET', '/admin/documents/nouveau', 'admin_document_form');
route('POST', '/admin/documents/nouveau', 'admin_document_save');
route('GET', '/admin/documents/{id}/modifier', 'admin_document_form');
route('POST', '/admin/documents/{id}/modifier', 'admin_document_save');
route('POST', '/admin/documents/{id}/supprimer', 'admin_document_delete');

// Personnes identifiées sur un document (photo, témoignage…)
route('GET', '/admin/documents/{id}/personnes', 'admin_document_persons');
route('POST', '/admin/documents/{id}/personnes/ajouter', 'admin_document_person_add');
route('POST', '/admin/documents/{id}/personnes/retirer', 'admin_document_person_remove');

// Albums photos
route('GET', '/admin/albums', 'admin_albums');
route('GET', '/admin/albums/nouveau', 'admin_album_form');
route('POST', '/admin/albums/nouveau', 'admin_album_save');
route('GET', '/admin/albums/{id}/modifier', 'admin_album_form');
route('POST', '/admin/albums/{id}/modifier', 'admin_album_save');
route('POST', '/admin/albums/{id}/supprimer', 'admin_album_delete');
route('GET', '/admin/albums/{id}/photos', 'admin_album_photos');
route('POST', '/admin/albums/{id}/photos/titre', 'admin_album_photo_save');
route('POST', '/admin/albums/{id}/photos/retirer', 'admin_album_photo_remove');
route('POST', '/admin/albums/{id}/photos/ajouter', 'admin_album_photos_ajouter');
route('POST', '/admin/albums/{id}/photos/visibilite', 'admin_album_photos_visibilite');

// Articles (fil d'actualité)
route('GET', '/admin/articles', 'admin_articles');
route('GET', '/admin/articles/nouveau', 'admin_article_form');
route('POST', '/admin/articles/nouveau', 'admin_article_save');
route('GET', '/admin/articles/{id}', 'admin_article_form');
route('POST', '/admin/articles/{id}', 'admin_article_save');
route('POST', '/admin/articles/{id}/supprimer', 'admin_article_delete');

// Vidéos (réglages propres : mise en avant, ordre, durée, téléchargement)
route('GET', '/admin/videos', 'admin_videos');
route('GET', '/admin/videos/nouvelle', 'admin_video_form');
route('POST', '/admin/videos/nouvelle', 'admin_video_save');
route('POST', '/admin/videos/importer', 'admin_video_import');
route('GET', '/admin/videos/{id}', 'admin_video_form');
route('POST', '/admin/videos/{id}', 'admin_video_save');
route('POST', '/admin/videos/{id}/mise-en-avant', 'admin_video_feature');
route('POST', '/admin/videos/{id}/supprimer', 'admin_video_delete');

// Recettes de famille
route('GET', '/admin/recettes', 'admin_recettes');
route('GET', '/admin/recettes/nouvelle', 'admin_recette_form');
route('POST', '/admin/recettes/nouvelle', 'admin_recette_save');
route('GET', '/admin/recettes/{id}', 'admin_recette_form');
route('POST', '/admin/recettes/{id}', 'admin_recette_save');
route('POST', '/admin/recettes/{id}/supprimer', 'admin_recette_delete');

// Export GEDCOM
route('GET', '/admin/export/gedcom', 'admin_export_gedcom');

// Catégories
route('GET', '/admin/categories', 'admin_categories');
route('POST', '/admin/categories/nouvelle', 'admin_category_create');
route('POST', '/admin/categories/{id}/modifier', 'admin_category_update');
route('POST', '/admin/categories/{id}/supprimer', 'admin_category_delete');

// Comptes de la famille : personnes, comptes de branche, administrateurs.
route('GET', '/admin/comptes', 'admin_comptes');
route('GET', '/admin/comptes/nouvelle', 'admin_comptes_form');
// Profil « Lecteur » : la branche et son compte partagé, créés ensemble.
route('POST', '/admin/comptes/lecteur', 'admin_compte_lecteur_save');
route('POST', '/admin/comptes/apercu', 'admin_comptes_apercu');
route('POST', '/admin/comptes/creer', 'admin_comptes_creer');
route('GET', '/admin/comptes/resultat/{slug}', 'admin_comptes_resultat');
route('GET', '/admin/comptes/{id}', 'admin_compte_form');
route('POST', '/admin/comptes/{id}', 'admin_compte_save');
route('POST', '/admin/comptes/{id}/mot-de-passe', 'admin_compte_password');
route('POST', '/admin/comptes/{id}/activer', 'admin_compte_toggle');
route('POST', '/admin/comptes/{id}/supprimer', 'admin_compte_delete');

// Branches et comptes de famille
route('GET', '/admin/branches', 'admin_branches');
route('GET', '/admin/branches/nouvelle', 'admin_branch_form');
route('POST', '/admin/branches/nouvelle', 'admin_branch_save');
route('GET', '/admin/branches/{id}', 'admin_branch_form');
route('POST', '/admin/branches/{id}', 'admin_branch_save');
route('POST', '/admin/branches/{id}/mot-de-passe', 'admin_branch_password');
route('POST', '/admin/branches/{id}/activer', 'admin_branch_toggle');
route('POST', '/admin/branches/{id}/supprimer', 'admin_branch_delete');

// Comptes administrateurs
route('GET', '/admin/admins', 'admin_admins');
route('POST', '/admin/admins/nouveau', 'admin_admin_create');
route('POST', '/admin/admins/{id}/mot-de-passe', 'admin_admin_password');
route('POST', '/admin/admins/{id}/activer', 'admin_admin_toggle');

// Pages d'informations
route('GET', '/admin/pages', 'admin_pages');
route('GET', '/admin/pages/nouvelle', 'admin_page_form');
route('POST', '/admin/pages/nouvelle', 'admin_page_save');
route('GET', '/admin/pages/{id}', 'admin_page_form');
route('POST', '/admin/pages/{id}', 'admin_page_save');
route('POST', '/admin/pages/{id}/supprimer', 'admin_page_delete');

// Arbre généalogique
route('GET', '/admin/arbre', 'admin_persons');
route('GET', '/admin/arbre/personne/nouvelle', 'admin_person_form');
route('POST', '/admin/arbre/personne/nouvelle', 'admin_person_save');
route('GET', '/admin/arbre/personne/{id}', 'admin_person_form');
route('POST', '/admin/arbre/personne/{id}', 'admin_person_save');
route('POST', '/admin/arbre/personne/{id}/supprimer', 'admin_person_delete');
route('GET', '/admin/arbre/couples', 'admin_couples');
route('GET', '/admin/arbre/couple/nouveau', 'admin_couple_form');
route('POST', '/admin/arbre/couple/nouveau', 'admin_couple_save');
route('GET', '/admin/arbre/couple/{id}', 'admin_couple_form');
route('POST', '/admin/arbre/couple/{id}', 'admin_couple_save');
route('POST', '/admin/arbre/couple/{id}/supprimer', 'admin_couple_delete');
route('GET', '/admin/arbre/import', 'admin_gedcom_form');
route('POST', '/admin/arbre/import', 'admin_gedcom_upload');
route('POST', '/admin/arbre/import/confirmer', 'admin_gedcom_confirm');

// Import depuis le dossier arrivée
route('GET', '/admin/importer', 'admin_import');
route('POST', '/admin/importer', 'admin_import_store');
route('POST', '/admin/importer/supprimer', 'admin_import_delete');
