<?php

namespace Witty\LaravelDbBackup\Commands\Helpers;

use Illuminate\Support\Facades\Config;

/**
 * Class Encrypt
 *
 * @package Witty\LaravelDbBackup\Commands\Helpers
 */
class Encrypt
{
    /**
     * Encrypt File
     *
     * @param string $file
     *
     * @return bool
     */
    public static function encryptFile($file)
    {
        if (!is_file($file)) {
            return false;
        }

        $content = file_get_contents($file);

        $encrypted = self::encrypt($content);

        file_put_contents($file, $encrypted);

        return true;
    }

    /**
     * Decrypt File
     *
     * @param string $file
     *
     * @return bool
     */
    public static function decryptFile($file)
    {
        if (!is_file($file)) {
            return false;
        }

        $content = file_get_contents($file);

        $decrypted = self::decrypt($content);

        file_put_contents($file, $decrypted);

        return true;
    }

    /**
     * Encrypt
     *
     * @param mixed $decryptedContent
     *
     * @return bool|string
     */
    public static function encrypt($decryptedContent)
    {
        $key = Config::get('db-backup.encrypt.key');
        $decrypted = $decryptedContent;

        $ekey = hash('SHA256', $key, true);

        srand();

        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC), MCRYPT_RAND);

        if ((strlen($iv_base64 = rtrim(base64_encode($iv), '=')) != 22)) {
            return false;
        }

        $encrypted = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $ekey, $decrypted . md5($decrypted), MCRYPT_MODE_CBC, $iv));

        return $iv_base64 . $encrypted;
    }

    /**
     * Decrypt
     *
     * @param mixed $encryptedContent
     *
     * @return bool|string
     */
    public static function decrypt($encryptedContent)
    {
        $key = Config::get('db-backup.encrypt.key');
        $encrypted = $encryptedContent;

        $ekey = hash('SHA256', $key, true);
        $iv = base64_decode(substr($encrypted, 0, 22) . '==');

        $encrypted = substr($encrypted, 22);
        $decrypted = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $ekey, base64_decode($encrypted), MCRYPT_MODE_CBC, $iv), "\0\4");

        $hash = substr($decrypted, -32);
        $decrypted = substr($decrypted, 0, -32);

        if ((md5($decrypted) != $hash)) {
            return false;
        }

        return $decrypted;
    }
}