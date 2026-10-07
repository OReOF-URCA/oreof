<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Utils/Tools.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 22/02/2023 09:06
 */

namespace App\Utils;

use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\String\Slugger\AsciiSlugger;
use function Symfony\Component\String\u;
use Transliterator;


abstract class Tools
{
    public static function telFormat(?string $number): string
    {
        if (null === $number) {
            return '';
        }

        $number = str_replace(['.', '-', ' '], '', $number);

        if (str_starts_with($number, '33')) {
            $number = '0'.mb_substr($number, 2, mb_strlen($number));
        }

        if (str_starts_with($number, '+33')) {
            $number = '0'.mb_substr($number, 3, mb_strlen($number));
        }

        if (9 === mb_strlen($number)) {
            $number = '0'.$number;
        }

        if (10 === mb_strlen($number)) {
            $str = chunk_split($number, 2, ' ');
        } else {
            $str = str_replace('.', ' ', $number);
        }

        return $str;
    }

    public static function slug(string $texte): string
    {
        return (new AsciiSlugger())->slug($texte);
    }

    public static function convertToFloat(mixed $value): ?float
    {
        $value = trim($value);
        $value = str_replace([',', '.'], '.', $value);

        return (float) $value;
    }

    public static function convertDate(string|null $date): DateTimeInterface
    {
        if (null === $date) {
            return new DateTime();
        }

        return new DateTime($date);
    }

    public static function convertToTime(?string $time): ?DateTimeInterface
    {
        if (null === $time) {
            return null;
        }
        return DateTime::createFromFormat('H:i', $time) ?? null;
    }

    public static function FileName(string $string, int $size = 50): \Symfony\Component\String\AbstractString|\Symfony\Component\String\UnicodeString
    {
        $slugger = new AsciiSlugger();
        $slug = $slugger->slug($string);

        return u($slug)->truncate($size);
    }

    public static function formatDir(string $dir): string
    {
        if (!str_ends_with($dir, '/')) {
            $dir = $dir.'/';
        }

        return $dir;
    }

    public static function filtreHeures(?float $heures): float|string
    {
        if ($heures === null) {
            return '-';
        }

        return $heures > 0.0 ? $heures : '-';
    }

    public static function adjNumeral(int $number): string
    {
        //si 1 => 1ére
        //si 2 ou plus => 2éme

        if ($number === 1) {
            return $number.'ère';
        }

        return $number.'ème';
    }


    public static function removeAccent(?string $inputString): ?string
    {
        if ($inputString !== null) {
            $rules = "À > A; Ç > C; É > E; È > E; é > e; è > e; à > a; â > a; ô > o; ù > u; î > i; :: NFC;";
            $transliterator = Transliterator::createFromRules($rules);

            return $transliterator?->transliterate($inputString) ?? $inputString;
        }

        return null;
    }

    public static function isEmptyArrayOrCollection(array|Collection|null $array): bool
    {
        if ($array === null) {
            return true;
        }

        if (is_array($array)) {
            return $array === [];
        }

        return $array->count() === 0;
    }
}
