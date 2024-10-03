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
 * This class handles logging for the Mastercard Simplify API. It is responsible for recording API request and response data, 
 * as well as any errors or relevant information that may be useful for debugging or monitoring API interactions.
 */
class Mastercard_Simplify_Api_Logger {
    protected $cipher;
    protected $cipher_algo;
    protected $filename;

    public function __construct( $hash ) {
        $this->cipher_algo = "AES-256-CBC";
        $this->cipher      = $hash;
        $this->filename = _PS_ROOT_DIR_ . '/var/logs/mastercard_simplify.log';
    }

    /**
     * Encrypt the log message.
     */
    public function encrypt_log( $message ) {
        $iv_len = openssl_cipher_iv_length( $this->cipher_algo );
        $iv = openssl_random_pseudo_bytes( $iv_len );
        $cipher_text = openssl_encrypt( $message, $this->cipher_algo, $this->cipher, 0, $iv );
        $cipher_text_iv = base64_encode( $iv . $cipher_text );
        return $cipher_text_iv;
    }

    /**
     * Encrypts and writes a log message to a secure log file.
     *
     * @param string $message The log message to be encrypted and stored.
     */
    public function write_encrypted_log( $message ) {
        $encrypted_message = $this->encrypt_log( $message, $this->cipher );
        file_put_contents( $this->filename, $encrypted_message . PHP_EOL, FILE_APPEND );
    }


    /**
     * Decrypt the log message.
     */
    public function decrypt_log( $cipher_text_iv ) {
        $iv_len             = openssl_cipher_iv_length( $this->cipher_algo ); 
        $cipher_text_iv     = base64_decode( $cipher_text_iv );
        $iv                 = substr( $cipher_text_iv, 0, $iv_len );
        $cipher_text        = substr( $cipher_text_iv, $iv_len );
        $original_plaintext = openssl_decrypt( $cipher_text, $this->cipher_algo, $this->cipher, $options = 0, $iv );

        return $original_plaintext;
    }

    /**
     * Read and decrypt log entries.
    */
    public function read_decrypted_log() {
        $decrypted_log_data = [];
        $log_file_path = _PS_ROOT_DIR_ . '/var/logs/mastercard_simplify.log';

        // Check if the log file exists
        if (file_exists($log_file_path)) {
            // Read log file into an array where each element is a line in the file
            $log_entries = file($log_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            // Iterate through log entries
            foreach ($log_entries as $cipher_text) {
                // Decrypt each log entry
                $decrypted_message = $this->decrypt_log($cipher_text);
                $decrypted_log_data[] = $decrypted_message . PHP_EOL; // Add newline after each decrypted message
            }

            // If there are decrypted messages, convert array to string
            if (!empty($decrypted_log_data)) {
                $decrypted_log_data = implode('', $decrypted_log_data); // Convert array to string
            } else {
                $decrypted_log_data = ''; // If no data, make it an empty string
            }
        } else {
            $decrypted_log_data = 'Log file not found.';
        }

        // Output the decrypted log data
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="mastercard_simplify.log"');
        header('Content-Length: ' . strlen($decrypted_log_data)); // Calculate length of the string
        echo $decrypted_log_data;
    }

}
