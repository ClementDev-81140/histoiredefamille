<?php
/**
 * Petite mémoire de formulaire : en cas d'erreur de validation, les valeurs
 * saisies et les messages sont conservés en session le temps d'un aller-retour
 * (pattern POST → redirect → GET).
 */

declare(strict_types=1);

/** Enregistre les erreurs et les valeurs saisies, puis redirige. */
function form_fail(array $errors, array $old, string $back): never
{
    $_SESSION['_form_errors'] = array_values($errors);
    $_SESSION['_form_old']    = $old;
    redirect($back);
}

/** Récupère (et vide) les erreurs de formulaire. */
function form_errors(): array
{
    $errors = $_SESSION['_form_errors'] ?? [];
    unset($_SESSION['_form_errors']);
    return is_array($errors) ? $errors : [];
}

/** Récupère (et vide) les valeurs saisies précédemment. */
function form_old(): array
{
    $old = $_SESSION['_form_old'] ?? [];
    unset($_SESSION['_form_old']);
    return is_array($old) ? $old : [];
}

/**
 * Valeur d'un champ : valeur précédente si présente, sinon celle de la base,
 * sinon la valeur par défaut.
 */
function old_value(array $old, string $key, mixed $default = ''): mixed
{
    return array_key_exists($key, $old) ? $old[$key] : $default;
}
