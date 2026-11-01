<?php
/**
 * Mise en forme d'un texte saisi par un administrateur.
 *
 * On n'accepte JAMAIS de HTML brut : le texte est d'abord échappé, puis
 * un petit sous-ensemble de Markdown est converti (titres, gras, italique,
 * listes, liens http/https).
 */

declare(strict_types=1);

/** Convertit un texte en HTML sûr. */
function render_text(?string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    $lines  = explode("\n", $text);
    $html   = '';
    $para   = [];
    $list   = null; // 'ul' ou 'ol'

    $flushPara = static function () use (&$para, &$html): void {
        if ($para !== []) {
            $html .= '<p>' . implode("<br>\n", array_map('text_inline', $para)) . "</p>\n";
            $para = [];
        }
    };

    $closeList = static function () use (&$list, &$html): void {
        if ($list !== null) {
            $html .= '</' . $list . ">\n";
            $list = null;
        }
    };

    foreach ($lines as $line) {
        $trimmed = rtrim($line);

        if (trim($trimmed) === '') {
            $flushPara();
            $closeList();
            continue;
        }

        if (preg_match('/^(#{2,4})\s+(.*)$/', $trimmed, $m)) {
            $flushPara();
            $closeList();
            $level = strlen($m[1]);
            $html .= '<h' . $level . '>' . text_inline(trim($m[2])) . '</h' . $level . ">\n";
            continue;
        }

        if (preg_match('/^\s*[-*]\s+(.*)$/', $trimmed, $m)) {
            $flushPara();
            if ($list !== 'ul') {
                $closeList();
                $html .= "<ul>\n";
                $list = 'ul';
            }
            $html .= '<li>' . text_inline(trim($m[1])) . "</li>\n";
            continue;
        }

        if (preg_match('/^\s*\d+[.)]\s+(.*)$/', $trimmed, $m)) {
            $flushPara();
            if ($list !== 'ol') {
                $closeList();
                $html .= "<ol>\n";
                $list = 'ol';
            }
            $html .= '<li>' . text_inline(trim($m[1])) . "</li>\n";
            continue;
        }

        $closeList();
        $para[] = $trimmed;
    }

    $flushPara();
    $closeList();

    return $html;
}

/** Échappe puis applique les marques en ligne. */
function text_inline(string $line): string
{
    $safe = e($line);

    // Liens [texte](https://exemple.fr) : uniquement http, https ou chemin interne.
    $safe = preg_replace(
        '/\[([^\]]+)\]\(((?:https?:\/\/|\/)[^\s()]+)\)/',
        '<a href="$2" rel="noopener">$1</a>',
        $safe
    ) ?? $safe;

    // Gras puis italique.
    $safe = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $safe) ?? $safe;
    $safe = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $safe) ?? $safe;

    return $safe;
}

/**
 * Résumé d'un texte brut (sans mise en forme) pour les listes.
 *
 * La mise en forme légère est retirée au passage : un aperçu ne doit pas
 * afficher « **gras** », « ## Titre » ni « - puce ».
 */
function text_summary(?string $text, int $length = 160): string
{
    $plain = text_to_plain($text);
    return excerpt($plain, $length);
}

/** Texte débarrassé de sa mise en forme légère (titres, gras, liens, puces). */
function text_to_plain(?string $text): string
{
    $plain = str_replace(["\r\n", "\r"], "\n", (string) $text);

    // Liens [texte](adresse) → texte.
    $plain = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/u', '$1', $plain) ?? $plain;
    // Titres Markdown : retirés entièrement (un titre n'est pas du texte
    // suivi, il ne doit pas se retrouver collé au paragraphe dans un aperçu).
    $plain = preg_replace('/^[ \t]*#{1,6}[ \t].*$/mu', '', $plain) ?? $plain;
    // Puces et numéros de liste.
    $plain = preg_replace('/^[ \t]*(?:[-*+]|\d+[.)])[ \t]+/mu', '', $plain) ?? $plain;
    // Gras, italique, code en ligne.
    $plain = str_replace(['**', '`'], '', $plain);
    $plain = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/u', '$1', $plain) ?? $plain;

    return preg_replace('/\s+/u', ' ', $plain) ?? $plain;
}
