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

    public static function encrypt($json)
    {
        self::initialize();
        $output = openssl_encrypt($json, self::CIPHER, base64_decode(self::$secretKey), OPENSSL_RAW_DATA, base64_decode(self::$secretIV));
        return base64_encode($output);
    }

    public static function decrypt($cypherJson)
    {
        self::initialize();
        $output = openssl_decrypt(base64_decode($cypherJson), self::CIPHER, base64_decode(self::$secretKey), OPENSSL_RAW_DATA, base64_decode(self::$secretIV));
        return $output;
    }

}
