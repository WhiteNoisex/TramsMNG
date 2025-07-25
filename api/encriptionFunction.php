<?php
function generateStrongKey($length = 32) {
    // Define a string of characters that can be used in the key
    $keyChars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()-_=+{}[]|:;<>,.?';

    // Calculate the number of characters in the string
    $numChars = strlen($keyChars);

    // Initialize the key variable
    $key = '';

    // Generate random bytes using a secure source of randomness
    for ($i = 0; $i < $length; $i++) {
        // Use random_int for PHP 7 or above, or fallback to mt_rand for older versions
        if (function_exists('random_int')) {
            $randomIndex = random_int(0, $numChars - 1);
        } else {
            $randomIndex = mt_rand(0, $numChars - 1);
        }
        
        // Append a random character from the keyChars string to the key
        $key .= $keyChars[$randomIndex];
    }

    // Return the generated key
    return $key;
}


function decryptMessage($encr_Key, $encryptedData) {
    $cipher = "aes-256-gcm"; // Use AES-256-GCM for authenticated encryption
    if (in_array($cipher, openssl_get_cipher_methods())) {
        // Extract the IV, tag, and ciphertext from the base64-encoded encrypted data
        $decodedData = base64_decode($encryptedData);
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = substr($decodedData, 0, $ivlen);
        $tag = substr($decodedData, $ivlen, 16); // GCM tag length is always 16 bytes
        $ciphertext = substr($decodedData, $ivlen + 16);

        // Decrypt the ciphertext using the provided key, IV, and tag
        try{
            $plaintext = openssl_decrypt($ciphertext, $cipher, $encr_Key, OPENSSL_RAW_DATA, $iv, $tag);
        } catch (Exception $e) {
            return false;
        }
        if ($plaintext === false) {
            // Handle decryption failure
            error_log("Decryption failed: " . openssl_error_string());
            return false;
        }

        // Return the decrypted plaintext
        return $plaintext;
    } else {
        // Handle unsupported cipher method
        error_log("Cipher method $cipher is not supported.");
        return false;
    }
}


function encryptMessage($encr_Key, $plainText) {
    $cipher = "aes-256-gcm"; // Use AES-256-GCM for authenticated encryption
    if (in_array($cipher, openssl_get_cipher_methods())) {
        // Generate a secure random IV
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);

        // Encrypt the plaintext using authenticated encryption (GCM mode)
        try{
            $ciphertext = openssl_encrypt($plainText, $cipher, $encr_Key, OPENSSL_RAW_DATA, $iv, $tag);
        } catch (Exception $e) {
            return false;
        }
        

        if ($ciphertext === false) {
            // Handle encryption failure
            error_log("Encryption failed: " . openssl_error_string());
            return false;
        }

        // Concatenate IV and tag with the ciphertext for storage and later use in decryption
        $encryptedData = $iv . $tag . $ciphertext;

        // Return the encrypted data
        return base64_encode($encryptedData);
    } else {
        // Handle unsupported cipher method
        error_log("Cipher method $cipher is not supported.");
        return false;
    }
}






?>