<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Encryption;

class SaltGenerator
{
    private const CHARSET = 'AbcDE123IJKLMN67QRSTUVWXYZaBCdefghijklmn123opq45rs67tuv89wxyz0FGH45OP89';

    public static function generate(int $length = 4): string
    {
        $charset       = self::CHARSET;
        $charsetLength = strlen($charset);
        $salt          = '';

        for ($i = 0; $i < $length; $i++) {
            $salt .= $charset[random_int(0, $charsetLength - 1)];
        }

        return $salt;
    }
}
