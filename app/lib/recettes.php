<?php
/**
 * Recettes de famille.
 *
 * Petits textes transmis de génération en génération : ingrédients,
 * préparation, astuce, et si possible une photo du plat ou du carnet.
 * Même visibilité que les autres contenus du site.
 */

declare(strict_types=1);

/** Adresse publique d'une recette. */
function recipe_url(array $recipe): string
{
    return '/recettes/' . rawurlencode((string) $recipe['slug']);
}

/** Condition SQL des recettes visibles. */
function recipe_visible_clause(string $alias, array &$params): string
{
    return visibility_clause('recipe', $alias, $params);
}

/** Une recette par son identifiant (sans contrôle de droits). */
function find_recipe(int $id): ?array
{
    return db_one('SELECT * FROM recipes WHERE id = ?', [$id]);
}

/** Une recette visible par la personne connectée, par son adresse. */
function find_viewable_recipe(string $slug): ?array
{
    $params = [];
    $clause = recipe_visible_clause('r', $params);
    $recette = db_one(
        "SELECT * FROM recipes r WHERE r.slug = :slug AND {$clause}",
        $params + [':slug' => $slug]
    );
    if ($recette !== null) {
        return $recette;
    }

    // Une recette proposée et pas encore validée n'est pas dans les listes :
    // son auteur seul la relit, par son adresse.
    $compte = current_account();
    if ($compte === null) {
        return null;
    }
    $propre = db_one('SELECT * FROM recipes WHERE slug = ?', [$slug]);

    return $propre !== null && proposition_mienne($compte, 'recipe', $propre) ? $propre : null;
}

/** Recettes visibles, de la plus récente à la plus ancienne. */
function recipes_visible(int $limit = 0, int $offset = 0, ?int &$total = null): array
{
    $params = [];
    $clause = recipe_visible_clause('r', $params);
    $total  = (int) db_value("SELECT COUNT(*) FROM recipes r WHERE {$clause}", $params, 0);

    $sql = "SELECT r.* FROM recipes r WHERE {$clause}
            ORDER BY r.title COLLATE NOCASE";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit . ' OFFSET ' . max(0, $offset);
    }

    return db_all($sql, $params);
}

/** Photo d'une recette, si elle est visible. */
function recipe_photo(array $recipe): ?array
{
    if (empty($recipe['photo_document_id'])) {
        return null;
    }

    $params = [':id' => (int) $recipe['photo_document_id']];
    $clause = visibility_clause('document', 'd', $params);

    return db_one("SELECT d.* FROM documents d WHERE d.id = :id AND {$clause}", $params);
}

/**
 * Découpe les ingrédients : une ligne = un ingrédient.
 * Fonction pure, testable sans base.
 *
 * @return string[]
 */
function recipe_ingredient_lines(string $texte): array
{
    $lignes = [];
    foreach (preg_split('/\R/u', $texte) ?: [] as $ligne) {
        $ligne = trim(preg_replace('/^\s*[-*•]\s*/u', '', $ligne) ?? $ligne);
        if ($ligne !== '') {
            $lignes[] = $ligne;
        }
    }

    return $lignes;
}

/** Y a-t-il au moins une recette visible ? (pour le menu) */
function recipes_exist(): bool
{
    return count_visible('recipes', 'recipe') > 0;
}
