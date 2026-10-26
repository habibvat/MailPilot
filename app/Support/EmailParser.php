<?php

namespace App\Support;

final class EmailParser
{
    /** Fautes de frappe fréquentes dans le domaine, corrigées à l'import. */
    private const DOMAIN_TYPOS = [
        'gmail' => 'gmail.com', 'gmial.com' => 'gmail.com', 'gmal.com' => 'gmail.com', 'gmai.com' => 'gmail.com',
        'gamil.com' => 'gmail.com', 'gnail.com' => 'gmail.com', 'gmaill.com' => 'gmail.com', 'gmail.co' => 'gmail.com',
        'gmail.cm' => 'gmail.com', 'gmail.con' => 'gmail.com', 'gmail.om' => 'gmail.com', 'gmail.fr' => 'gmail.com',
        'hotmial.com' => 'hotmail.com', 'hotmal.com' => 'hotmail.com', 'hotmai.com' => 'hotmail.com',
        'hotmail.con' => 'hotmail.com', 'hotmail.co' => 'hotmail.com', 'hotmial.fr' => 'hotmail.fr',
        'yahoo.con' => 'yahoo.com', 'yahoo.co' => 'yahoo.com', 'yaho.fr' => 'yahoo.fr', 'yahooo.fr' => 'yahoo.fr',
        'yaho.com' => 'yahoo.com', 'yahooo.com' => 'yahoo.com',
        'outlok.com' => 'outlook.com', 'outlook.con' => 'outlook.com', 'outlok.fr' => 'outlook.fr',
    ];

    private const SYNTAX = '/^[a-z0-9.!#$%&\'*+\/=?^_`{|}~-]+@(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';

    /**
     * Découpe une cellule pouvant contenir plusieurs adresses (séparées par ; , / espace ou retour ligne)
     * et renvoie les adresses nettoyées (trim + minuscules), sans doublon.
     *
     * @return list<string>
     */
    public static function split(mixed $cell): array
    {
        if ($cell === null) {
            return [];
        }

        // « nom@domaine,com » : virgule tapée à la place du point avant l'extension.
        $cell = preg_replace('/(@[a-z0-9-]+(?:\.[a-z0-9-]+)*),(com|net|org|info|biz|io|co|uk|us|ca|au|nz|in|fr|de|it|es|pt|nl|be|ch|at|ie|pl|se|no|dk|fi|ru|tr|br|mx|ar|za|ng|ke|eg|ma|dz|tn|sn|ci|cm|sa|ae|qa|eu)(?![a-z0-9@._-])/iu', '$1.$2', (string) $cell);

        $tokens = preg_split('/[;,\/\s|]+/u', $cell, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $emails = [];
        foreach ($tokens as $token) {
            $email = self::clean($token);
            if ($email !== '') {
                $emails[$email] = true;
            }
        }

        return array_keys($emails);
    }

    public static function clean(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/^mailto:/', '', $value);

        $value = trim($value, " \t\n\r\0\x0B<>\"'()[].:");

        $at = strrpos($value, '@');
        if ($at !== false && isset(self::DOMAIN_TYPOS[$domain = substr($value, $at + 1)])) {
            $value = substr($value, 0, $at + 1).self::DOMAIN_TYPOS[$domain];
        }

        return $value;
    }

    public static function isValid(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && preg_match(self::SYNTAX, $email) === 1
            && ! str_contains($email, '..');
    }

    public static function domain(string $email): string
    {
        return substr(strrchr($email, '@') ?: '', 1);
    }

    public static function localPart(string $email): string
    {
        return strstr($email, '@', true) ?: $email;
    }
}
