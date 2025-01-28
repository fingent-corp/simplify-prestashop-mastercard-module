<?php
/**
 * Copyright (c) 2023-2026 Mastercard
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Main class of the Mastercard Simplify Api Logger
 *
 * This class handles logging for the Mastercard Simplify API.
 * It is responsible for recording API request and response data,
 * as well as any errors or relevant information that may be useful for debugging
 * or monitoring API interactions.
 */
class MastercardSimplifyApiLogger
{
    protected $cipher;
    protected $cipherAlgo;
    protected $filename;

    public function __construct($hash)
    {
        $this->cipherAlgo  = "AES-256-CBC";
        $this->cipher       = $hash;
        $this->filename     = _PS_ROOT_DIR_ . '/var/logs/mastercard_simplify.log';
    }

    /**
     * Encrypt the log message.
     */
    public function encryptLog($message)
    {
        $ivLen          = openssl_cipher_iv_length($this->cipherAlgo);
        $iv             = openssl_random_pseudo_bytes($ivLen);
        $cipherText     = openssl_encrypt($message, $this->cipherAlgo, $this->cipher, 0, $iv);

        return  base64_encode($iv . $cipherText);
    }

    /**
     * Encrypts and writes a log message to a secure log file.
     *
     * @param string $message The log message to be encrypted and stored.
     */
    public function writeEncryptedLog($message)
    {
        $encryptedMessage = $this->encryptLog($message, $this->cipher);
        file_put_contents($this->filename, $encryptedMessage . PHP_EOL, FILE_APPEND);
    }


    /**
     * Decrypt the log message.
     */
    public function decryptLog($cipherTextIv)
    {
        $ivLen              = openssl_cipher_iv_length($this->cipherAlgo);
        $cipherTextIv       = base64_decode($cipherTextIv);
        $iv                 = substr($cipherTextIv, 0, $ivLen);
        $cipherText         = substr($cipherTextIv, $ivLen);

        return openssl_decrypt($cipherText, $this->cipherAlgo, $this->cipher, 0, $iv);
    }

    /**
     * Read and decrypt log entries.
    */
    public function readDecryptedLog()
    {
        $decryptedLogData = [];
        $logfilepath      = _PS_ROOT_DIR_ . '/var/logs/mastercard_simplify.log';

        // Check if the log file exists
        if (file_exists($logfilepath)) {
            // Read log file into an array where each element is a line in the file
            $logEntries = file($logfilepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            // Iterate through log entries
            foreach ($logEntries as $cipherText) {
                // Decrypt each log entry
                $decryptedMessage    = $this->decryptLog($cipherText);
                $decryptedLogData[]   = $decryptedMessage . PHP_EOL; // Add newline after each decrypted message
            }

            // If there are decrypted messages, convert array to string
            if (!empty($decryptedLogData)) {
                $decryptedLogData = implode('', $decryptedLogData); // Convert array to string
            } else {
                $decryptedLogData = ''; // If no data, make it an empty string
            }
        } else {
            $decryptedLogData = 'Log file not found.';
        }

        // Output the decrypted log data
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="mastercard_simplify.log"');
        header('Content-Length: ' . strlen($decryptedLogData)); // Calculate length of the string
        echo $decryptedLogData;
    }
}
