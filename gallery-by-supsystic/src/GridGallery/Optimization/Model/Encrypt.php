<?php

class GridGallery_Optimization_Model_Encrypt
{
  private $_CIPHER = null; // OPENSSL_CIPHER_NAME; // 'aes-128-cbc' is AES-128
  private $_IV_SIZE = null;
  private $_PAYLOAD_PREFIX = 'v2:';
  private $_MAC_SIZE = 32;

  public function encrypt($pureString, $encryptionKey = '')
  {
    if (!$this->canUseOpenSsl()) {
      return false;
    }

    $keys = $this->getEncryptKeys($encryptionKey);
    if (!$keys || !$this->initCrypt()) {
      return false;
    }

    $iv = $this->getRandomBytes($this->_IV_SIZE);
    if (false === $iv) {
      return false;
    }

    $ciphertext = openssl_encrypt($pureString, $this->_CIPHER, $keys['encrypt'], OPENSSL_RAW_DATA, $iv);
    if (false === $ciphertext) {
      return false;
    }

    $mac = hash_hmac('sha256', $iv . $ciphertext, $keys['auth'], true);

    return $this->_PAYLOAD_PREFIX . base64_encode($iv . $mac . $ciphertext);
  }

  public function decrypt($encryptedString, $encryptionKey = '')
  {
    if (!$this->canUseOpenSsl()) {
      return false;
    }

    if (0 !== strpos($encryptedString, $this->_PAYLOAD_PREFIX)) {
      return $this->decryptLegacy($encryptedString, $encryptionKey);
    }

    $keys = $this->getEncryptKeys($encryptionKey);
    if (!$keys || !$this->initCrypt()) {
      return false;
    }

    $payload = base64_decode(substr($encryptedString, strlen($this->_PAYLOAD_PREFIX)), true);
    if (!is_string($payload) || strlen($payload) <= ($this->_IV_SIZE + $this->_MAC_SIZE)) {
      return false;
    }

    $iv = substr($payload, 0, $this->_IV_SIZE);
    $mac = substr($payload, $this->_IV_SIZE, $this->_MAC_SIZE);
    $ciphertext = substr($payload, $this->_IV_SIZE + $this->_MAC_SIZE);
    $expectedMac = hash_hmac('sha256', $iv . $ciphertext, $keys['auth'], true);

    if (!$this->hashEquals($expectedMac, $mac)) {
      return false;
    }

    $plaintext = openssl_decrypt($ciphertext, $this->_CIPHER, $keys['encrypt'], OPENSSL_RAW_DATA, $iv);

    return false === $plaintext ? false : $plaintext;
  }

  private function initCrypt()
  {
    $this->_CIPHER = 'aes-128-cbc';
    $this->_IV_SIZE = openssl_cipher_iv_length($this->_CIPHER);

    return false !== $this->_IV_SIZE;
  }

  private function decryptLegacy($encryptedString, $encryptionKey = '')
  {
    $legacyKey = $this->getLegacyEncryptKey($encryptionKey);
    if (false === $legacyKey || !$this->initCrypt()) {
      return false;
    }

    $ciphertext = base64_decode($encryptedString, true);
    if (!is_string($ciphertext) || strlen($ciphertext) <= $this->_IV_SIZE) {
      return false;
    }

    $iv = substr($ciphertext, 0, $this->_IV_SIZE);
    $ciphertext = substr($ciphertext, $this->_IV_SIZE);
    $plaintext = openssl_decrypt($ciphertext, $this->_CIPHER, $legacyKey, OPENSSL_RAW_DATA, $iv);

    return false === $plaintext ? false : rtrim($plaintext, "\0");
  }

  private function canUseOpenSsl()
  {
    return function_exists('openssl_encrypt') && function_exists('openssl_decrypt');
  }

  private function getEncryptKeys($encryptionKey = '')
  {
    $keyMaterial = $this->getKeyMaterial($encryptionKey);
    if (false === $keyMaterial) {
      return false;
    }

    return [
      'encrypt' => substr(hash_hmac('sha256', 'grid-gallery-encryption', $keyMaterial, true), 0, 16),
      'auth' => hash_hmac('sha256', 'grid-gallery-authentication', $keyMaterial, true),
    ];
  }

  private function getLegacyEncryptKey($encryptionKey = '')
  {
    $keyMaterial = $this->getKeyMaterial($encryptionKey);
    if (false === $keyMaterial) {
      return false;
    }

    return substr($keyMaterial, 0, 16);
  }

  private function getKeyMaterial($encryptionKey = '')
  {
    $authKey = empty($encryptionKey) && defined('AUTH_KEY') ? AUTH_KEY : $encryptionKey;
    if (!is_string($authKey) || strlen($authKey) < 16) {
      return false;
    }

    return $authKey;
  }

  private function getRandomBytes($length)
  {
    if (function_exists('random_bytes')) {
      try {
        return random_bytes($length);
      } catch (Exception $e) {
        return false;
      }
    }

    $strong = false;
    $bytes = openssl_random_pseudo_bytes($length, $strong);

    return $strong ? $bytes : false;
  }

  private function hashEquals($expected, $actual)
  {
    if (function_exists('hash_equals')) {
      return hash_equals($expected, $actual);
    }

    if (!is_string($expected) || !is_string($actual) || strlen($expected) !== strlen($actual)) {
      return false;
    }

    $result = 0;
    for ($i = 0; $i < strlen($expected); $i++) {
      $result |= ord($expected[$i]) ^ ord($actual[$i]);
    }

    return 0 === $result;
  }
}
