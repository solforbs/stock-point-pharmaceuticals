<?php declare(strict_types = 1);

// osfsl-C:/xampp/htdocs/pharmacy_erp/vendor/composer/../pragmarx/google2fa/src/Support/Base32.php-PHPStan\BetterReflection\Reflection\ReflectionClass-PragmaRX\Google2FA\Support\Base32
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-56c194566ccf8a39301b31cc14f614e1fd9579911dfca386c569fdbdfe2285b4-8.3.31-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/vendor/composer/../pragmarx/google2fa/src/Support/Base32.php',
      ),
    ),
    'namespace' => 'PragmaRX\\Google2FA\\Support',
    'name' => 'PragmaRX\\Google2FA\\Support\\Base32',
    'shortName' => 'Base32',
    'isInterface' => false,
    'isTrait' => true,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 10,
    'endLine' => 230,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'enforceGoogleAuthenticatorCompatibility' => 
      array (
        'declaringClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'implementingClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'name' => 'enforceGoogleAuthenticatorCompatibility',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 45,
            'startFilePos' => 444,
            'endTokenPos' => 45,
            'endFilePos' => 447,
          ),
        ),
        'docComment' => '/**
 * Enforce Google Authenticator compatibility.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 62,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      'charCountBits' => 
      array (
        'name' => 'charCountBits',
        'parameters' => 
        array (
          'b32' => 
          array (
            'name' => 'b32',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 25,
            'endLine' => 26,
            'startColumn' => 9,
            'endColumn' => 12,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Calculate char count bits.
 *
 * @param string $b32
 *
 * @return int
 */',
        'startLine' => 24,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'PragmaRX\\Google2FA\\Support',
        'declaringClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'implementingClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'currentClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'aliasName' => NULL,
      ),
      'generateBase32RandomKey' => 
      array (
        'name' => 'generateBase32RandomKey',
        'parameters' => 
        array (
          'length' => 
          array (
            'name' => 'length',
            'default' => 
            array (
              'code' => '16',
              'attributes' => 
              array (
                'startLine' => 45,
                'endLine' => 45,
                'startTokenPos' => 94,
                'startFilePos' => 1186,
                'endTokenPos' => 94,
                'endFilePos' => 1187,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 45,
            'endLine' => 45,
            'startColumn' => 9,
            'endColumn' => 20,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'prefix' => 
          array (
            'name' => 'prefix',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 47,
                'endLine' => 47,
                'startTokenPos' => 105,
                'startFilePos' => 1239,
                'endTokenPos' => 105,
                'endFilePos' => 1240,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 46,
            'endLine' => 47,
            'startColumn' => 9,
            'endColumn' => 20,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Generate a digit secret key in base32 format.
 *
 * @param int    $length
 * @param string $prefix
 *
 * @throws \\Exception
 * @throws \\PragmaRX\\Google2FA\\Exceptions\\InvalidCharactersException
 * @throws \\PragmaRX\\Google2FA\\Exceptions\\SecretKeyTooShortException
 * @throws \\PragmaRX\\Google2FA\\Exceptions\\IncompatibleWithGoogleAuthenticatorException
 *
 * @return string
 */',
        'startLine' => 44,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PragmaRX\\Google2FA\\Support',
        'declaringClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'implementingClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'currentClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'aliasName' => NULL,
      ),
      'base32Decode' => 
      array (
        'name' => 'base32Decode',
        'parameters' => 
        array (
          'b32' => 
          array (
            'name' => 'b32',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 70,
            'endLine' => 71,
            'startColumn' => 9,
            'endColumn' => 12,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Decodes a base32 string into a binary string.
 *
 * @param string $b32
 *
 * @throws \\PragmaRX\\Google2FA\\Exceptions\\InvalidCharactersException
 * @throws \\PragmaRX\\Google2FA\\Exceptions\\SecretKeyTooShortException
 * @throws \\PragmaRX\\Google2FA\\Exceptions\\IncompatibleWithGoogleAuthenticatorException
 *
 * @return string
 */',
        'startLine' => 69,
        'endLine' => 78,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PragmaRX\\Google2FA\\Support',
        'declaringClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'implementingClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'currentClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'aliasName' => NULL,
      ),
      'isCharCountNotAPowerOfTwo' => 
      array (
        'name' => 'isCharCountNotAPowerOfTwo',
        'parameters' => 
        array (
          'b32' => 
          array (
            'name' => 'b32',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 88,
            'endLine' => 89,
            'startColumn' => 9,
            'endColumn' => 12,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Check if the string length is power of two.
 *
 * @param string $b32
 *
 * @return bool
 */',
        'startLine' => 87,
        'endLine' => 92,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'PragmaRX\\Google2FA\\Support',
        'declaringClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'implementingClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'currentClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'aliasName' => NULL,
      ),
      'strPadBase32' => 
      array (
        'name' => 'strPadBase32',
        'parameters' => 
        array (
          'string' => 
          array (
            'name' => 'string',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 105,
            'endLine' => 106,
            'startColumn' => 9,
            'endColumn' => 15,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'length' => 
          array (
            'name' => 'length',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 107,
            'endLine' => 107,
            'startColumn' => 9,
            'endColumn' => 15,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Pad string with random base 32 chars.
 *
 * @param string $string
 * @param int    $length
 *
 * @throws \\Exception
 *
 * @return string
 */',
        'startLine' => 104,
        'endLine' => 118,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'PragmaRX\\Google2FA\\Support',
        'declaringClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'implementingClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'currentClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'aliasName' => NULL,
      ),
      'toBase32' => 
      array (
        'name' => 'toBase32',
        'parameters' => 
        array (
          'string' => 
          array (
            'name' => 'string',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 128,
            'endLine' => 129,
            'startColumn' => 9,
            'endColumn' => 15,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Encode a string to Base32.
 *
 * @param string $string
 *
 * @return string
 */',
        'startLine' => 127,
        'endLine' => 134,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PragmaRX\\Google2FA\\Support',
        'declaringClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'implementingClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'currentClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'aliasName' => NULL,
      ),
      'getRandomNumber' => 
      array (
        'name' => 'getRandomNumber',
        'parameters' => 
        array (
          'from' => 
          array (
            'name' => 'from',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 146,
                'endLine' => 146,
                'startTokenPos' => 395,
                'startFilePos' => 3379,
                'endTokenPos' => 395,
                'endFilePos' => 3379,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 146,
            'endLine' => 146,
            'startColumn' => 40,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'to' => 
          array (
            'name' => 'to',
            'default' => 
            array (
              'code' => '31',
              'attributes' => 
              array (
                'startLine' => 146,
                'endLine' => 146,
                'startTokenPos' => 402,
                'startFilePos' => 3388,
                'endTokenPos' => 402,
                'endFilePos' => 3389,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 146,
            'endLine' => 146,
            'startColumn' => 51,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a random number.
 *
 * @param int $from
 * @param int $to
 *
 * @throws \\Exception
 *
 * @return int
 */',
        'startLine' => 146,
        'endLine' => 149,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'PragmaRX\\Google2FA\\Support',
        'declaringClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'implementingClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'currentClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'aliasName' => NULL,
      ),
      'validateSecret' => 
      array (
        'name' => 'validateSecret',
        'parameters' => 
        array (
          'b32' => 
          array (
            'name' => 'b32',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 161,
            'endLine' => 162,
            'startColumn' => 9,
            'endColumn' => 12,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Validate the secret.
 *
 * @param string $b32
 *
 * @throws \\PragmaRX\\Google2FA\\Exceptions\\InvalidCharactersException
 * @throws \\PragmaRX\\Google2FA\\Exceptions\\SecretKeyTooShortException
 * @throws \\PragmaRX\\Google2FA\\Exceptions\\IncompatibleWithGoogleAuthenticatorException
 */',
        'startLine' => 160,
        'endLine' => 169,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'PragmaRX\\Google2FA\\Support',
        'declaringClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'implementingClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'currentClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'aliasName' => NULL,
      ),
      'checkGoogleAuthenticatorCompatibility' => 
      array (
        'name' => 'checkGoogleAuthenticatorCompatibility',
        'parameters' => 
        array (
          'b32' => 
          array (
            'name' => 'b32',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 179,
            'endLine' => 180,
            'startColumn' => 9,
            'endColumn' => 12,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Check if the secret key is compatible with Google Authenticator.
 *
 * @param string $b32
 *
 * @throws IncompatibleWithGoogleAuthenticatorException
 */',
        'startLine' => 178,
        'endLine' => 188,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'PragmaRX\\Google2FA\\Support',
        'declaringClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'implementingClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'currentClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'aliasName' => NULL,
      ),
      'checkForValidCharacters' => 
      array (
        'name' => 'checkForValidCharacters',
        'parameters' => 
        array (
          'b32' => 
          array (
            'name' => 'b32',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 198,
            'endLine' => 199,
            'startColumn' => 9,
            'endColumn' => 12,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Check if all secret key characters are valid.
 *
 * @param string $b32
 *
 * @throws \\PragmaRX\\Google2FA\\Exceptions\\InvalidCharactersException
 */',
        'startLine' => 197,
        'endLine' => 207,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'PragmaRX\\Google2FA\\Support',
        'declaringClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'implementingClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'currentClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'aliasName' => NULL,
      ),
      'checkIsBigEnough' => 
      array (
        'name' => 'checkIsBigEnough',
        'parameters' => 
        array (
          'b32' => 
          array (
            'name' => 'b32',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 217,
            'endLine' => 218,
            'startColumn' => 9,
            'endColumn' => 12,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Check if secret key length is big enough.
 *
 * @param string $b32
 *
 * @throws \\PragmaRX\\Google2FA\\Exceptions\\SecretKeyTooShortException
 */',
        'startLine' => 216,
        'endLine' => 229,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'PragmaRX\\Google2FA\\Support',
        'declaringClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'implementingClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'currentClassName' => 'PragmaRX\\Google2FA\\Support\\Base32',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
      ),
    ),
  ),
));