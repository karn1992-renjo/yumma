<?php

namespace App\Services\WhatsApp;

/**
 * Checks template text against the rules WhatsApp applies when approving it, so mistakes are caught
 * before you submit (a rejected template costs a day). The same rules run live in the admin form.
 */
class WhatsAppTemplateRules
{
    public const MIN_WORDS_PER_VARIABLE = 8;     // Meta rejected a 2-words-per-variable text; ~10+ is safe
    public const MAX_LENGTH = 1024;

    /** @return int[] the variable numbers found, in order of appearance */
    public static function variables(string $body): array
    {
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $m);

        return array_map('intval', $m[1]);
    }

    /**
     * @return array{errors: string[], warnings: string[], variables: int, words_per_variable: ?float}
     */
    public static function check(string $body): array
    {
        $errors = [];
        $warnings = [];
        $body = trim($body);
        $vars = self::variables($body);
        $count = count($vars);

        if ($body === '') {
            $errors[] = 'Write the message text.';
        }
        if (mb_strlen($body) > self::MAX_LENGTH) {
            $errors[] = 'The text is longer than ' . self::MAX_LENGTH . ' characters.';
        }
        if ($vars !== range(1, $count) && $count > 0) {
            $errors[] = 'Variables must be numbered in order: {{1}}, {{2}}, {{3}}… each used once, in the order they appear.';
        }
        if (preg_match('/^\{\{\s*\d+\s*\}\}/', $body)) {
            $errors[] = 'The text cannot start with a variable. Begin with a few words, for example "Hi {{1}}".';
        }
        if (preg_match('/\{\{\s*\d+\s*\}\}[\s.!?,:;…)\'"]*$/u', $body)) {
            $errors[] = 'The text cannot end with a variable. Finish with a sentence, for example "…. Thank you!".';
        }
        if (preg_match('/\{\{\s*\d+\s*\}\}\s*\{\{\s*\d+\s*\}\}/', $body)) {
            $errors[] = 'Two variables cannot sit next to each other. Put words between them.';
        }
        if (preg_match('/\n{3,}| {4,}|\t/', $body)) {
            $errors[] = 'Remove tabs, more than two line breaks in a row and long runs of spaces.';
        }

        $ratio = null;
        if ($count > 0) {
            $words = str_word_count(preg_replace('/\{\{\s*\d+\s*\}\}/', ' ', $body) ?? '', 0, 'ÀÁÂÃÄÅàáâãäåÇçÈÉÊËèéêëÌÍÎÏìíîïÑñÒÓÔÕÖòóôõöÙÚÛÜùúûü0123456789');
            $ratio = round($words / $count, 1);
            if ($ratio < self::MIN_WORDS_PER_VARIABLE) {
                $errors[] = "Too many variables for the length: {$count} variable(s) but only {$words} other words. Add more fixed text or remove variables (aim for at least 10 words for each variable).";
            } elseif ($ratio < 10) {
                $warnings[] = 'Close to the limit. Adding a sentence or two makes approval more likely.';
            }
            if ($count > 5) {
                $warnings[] = 'Many variables. Three or fewer is the safest.';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings, 'variables' => $count, 'words_per_variable' => $ratio];
    }
}
