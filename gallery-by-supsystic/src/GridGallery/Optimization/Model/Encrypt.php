<?php

class GridGallery_Optimization_Model_Encrypt {

	private
	$_CIPHER = null; // OPENSSL_CIPHER_NAME; // 'aes-128-cbc' is AES-128
	private
	$_IV_SIZE = null;
	public
	function encrypt( $pureString, $encryptionKey = '' ) {
		if ( empty( $encryptionKey ) ) {
			$encryptionKey = $this->getEncryptKey();
		}

		if ( function_exists( 'openssl_encrypt' ) ) {
			$this->initCrypt();
			$encryptionKey = substr( $encryptionKey, 0, 16 );
			$iv            = openssl_random_pseudo_bytes( $this->_IV_SIZE );
			$ciphertext    = openssl_encrypt( $pureString, $this->_CIPHER, $encryptionKey, OPENSSL_RAW_DATA, $iv );

			return base64_encode( $iv . $ciphertext );
		} else {
			return base64_encode( $pureString );
		}
	}

	public
	function decrypt( $encryptedString, $encryptionKey = '' ) {
		if ( empty( $encryptionKey ) ) {
			$encryptionKey = $this->getEncryptKey();
		}

		if ( function_exists( 'openssl_decrypt' ) ) {
			$this->initCrypt();
			$encryptionKey = substr( $encryptionKey, 0, 16 );
			$ciphertext    = base64_decode( $encryptedString );
			$iv            = substr( $ciphertext, 0, $this->_IV_SIZE );
			$ciphertext    = substr( $ciphertext, $this->_IV_SIZE );
			$plaintext     = openssl_decrypt( $ciphertext, $this->_CIPHER, $encryptionKey, OPENSSL_RAW_DATA, $iv );

			return rtrim( $plaintext, "\0" );
		} else {
			$decryptedString = base64_decode( $encryptedString );

			return $decryptedString;
		}
	}

	private
	function initCrypt() {
		$this->_CIPHER  = 'aes-128-cbc';
		$this->_IV_SIZE = openssl_cipher_iv_length( $this->_CIPHER );
	}

	private
	function getEncryptKey() {
		$authKey = AUTH_KEY;
		if ( strlen( $authKey ) < 16 ) {
			for ( $i = strlen( $authKey ); $i < 16; $i ++ ) {
				$authKey .= '1';
			}
		}

		return $authKey;
	}
}


