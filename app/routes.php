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
require_once __DIR__ . '/controllers/family.php';
require_once __DIR__ . '/controllers/admin.php';
require_once __DIR__ . '/controllers/admin_documents.php';
require_once __DIR__ . '/controllers/admin_branches.php';
require_once __DIR__ . '/controllers/admin_pages.php';
require_once __DIR__ . '/controllers/admin_persons.php';
require_once __DIR__ . '/controllers/admin_import.php';

// ---------------------------------------------------------------------------
// Partie publique
// ---------------------------------------------------------------------------
route('GET', '/', 'public_accueil');
route('GET', '/documents', 'public_documents');
route('GET', '/documents/{id}', 'public_document_detail');
route('GET', '/pages/{slug}', 'public_page');
route('GET', '/arbre', 'public_arbre');
route('GET', '/arbre/{id}', 'public_personne');
route('GET', '/api/arbre/{id}', 'api_arbre');

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

// ---------------------------------------------------------------------------
// Administration
// ---------------------------------------------------------------------------
route('GET', '/admin', 'admin_dashboard');
route('GET', '/admin/etat', 'admin_status');
route('GET', '/admin/reglages', 'admin_settings');
route('POST', '/admin/reglages', 'admin_settings_save');
route('GET', '/admin/mon-compte', 'admin_account');
route('POST', '/admin/mon-compte', 'admin_account_save');

// Documents
route('GET', '/admin/documents', 'admin_documents');
route('GET', '/admin/documents/nouveau', 'admin_document_form');
route('POST', '/admin/documents/nouveau', 'admin_document_save');
route('GET', '/admin/documents/{id}/modifier', 'admin_document_form');
route('POST', '/admin/documents/{id}/modifier', 'admin_document_save');
route('POST', '/admin/documents/{id}/supprimer', 'admin_document_delete');

// Catégories
route('GET', '/admin/categories', 'admin_categories');
route('POST', '/admin/categories/nouvelle', 'admin_category_create');
route('POST', '/admin/categories/{id}/modifier', 'admin_category_update');
route('POST', '/admin/categories/{id}/supprimer', 'admin_category_delete');

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
