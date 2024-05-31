<?php

namespace App\Helpers\GestoPago;

class AesCipher
{
    const CIPHER = 'AES-256-CBC';
    private static $secretKey;
    private static $secretIV;

    public static function initialize()
    {
        self::$secretKey = ENV('AES_SECRET_KEY');
        self::$secretIV = ENV('AES_SECRET_IV');
    }

    public static function encrypt($text)
    {
        self::initialize();
        $output = openssl_encrypt($text, self::CIPHER, base64_decode(self::$secretKey), OPENSSL_RAW_DATA, base64_decode(self::$secretIV));
        return base64_encode($output);
    }

    public static function decrypt($cypherText)
    {
        self::initialize();
        $output = openssl_decrypt(base64_decode($cypherText), self::CIPHER, base64_decode(self::$secretKey), OPENSSL_RAW_DATA, base64_decode(self::$secretIV));
        return $output;
    }

}
