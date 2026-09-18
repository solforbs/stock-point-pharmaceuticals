<?php declare(strict_types = 1);

// osfsl-C:/xampp/htdocs/pharmacy_erp/vendor/composer/../laravel/framework/src/Illuminate/Testing/TestResponse.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Testing\TestResponse
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-8852d02ccbe7d607ad1d51f1fece412090592770d8b51f89eb62a06f2483f0bb-8.3.31-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Testing\\TestResponse',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/vendor/composer/../laravel/framework/src/Illuminate/Testing/TestResponse.php',
      ),
    ),
    'namespace' => 'Illuminate\\Testing',
    'name' => 'Illuminate\\Testing\\TestResponse',
    'shortName' => 'TestResponse',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * @template TResponse of \\Symfony\\Component\\HttpFoundation\\Response
 *
 * @mixin \\Illuminate\\Http\\Response
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 37,
    'endLine' => 2093,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'ArrayAccess',
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Testing\\Concerns\\AssertsStatusCodes',
      1 => 'Illuminate\\Support\\Traits\\Conditionable',
      2 => 'Illuminate\\Support\\Traits\\Dumpable',
      3 => 'Illuminate\\Support\\Traits\\Tappable',
      4 => 'Illuminate\\Support\\Traits\\Macroable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'baseRequest' => 
      array (
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'name' => 'baseRequest',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The original request.
 *
 * @var \\Illuminate\\Http\\Request|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 48,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 24,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'baseResponse' => 
      array (
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'name' => 'baseResponse',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The response to delegate to.
 *
 * @var TResponse
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 55,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'exceptions' => 
      array (
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'name' => 'exceptions',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The collection of logged exceptions for the request.
 *
 * @var \\Illuminate\\Support\\Collection
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 62,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'streamedContent' => 
      array (
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'name' => 'streamedContent',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The streamed content of the response.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 69,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'decodedResponseJson' => 
      array (
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'name' => 'decodedResponseJson',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The decoded response JSON.
 *
 * @var \\Illuminate\\Testing\\AssertableJsonString|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 76,
        'endLine' => 76,
        'startColumn' => 5,
        'endColumn' => 35,
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
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'response' => 
          array (
            'name' => 'response',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 84,
            'endLine' => 84,
            'startColumn' => 33,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'request' => 
          array (
            'name' => 'request',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 84,
                'endLine' => 84,
                'startTokenPos' => 234,
                'startFilePos' => 2224,
                'endTokenPos' => 234,
                'endFilePos' => 2227,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 84,
            'endLine' => 84,
            'startColumn' => 44,
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
 * Create a new test response instance.
 *
 * @param  TResponse  $response
 * @param  \\Illuminate\\Http\\Request|null  $request
 */',
        'startLine' => 84,
        'endLine' => 89,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'fromBaseResponse' => 
      array (
        'name' => 'fromBaseResponse',
        'parameters' => 
        array (
          'response' => 
          array (
            'name' => 'response',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 100,
            'endLine' => 100,
            'startColumn' => 45,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'request' => 
          array (
            'name' => 'request',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 100,
                'endLine' => 100,
                'startTokenPos' => 287,
                'startFilePos' => 2659,
                'endTokenPos' => 287,
                'endFilePos' => 2662,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 100,
            'endLine' => 100,
            'startColumn' => 56,
            'endColumn' => 70,
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
 * Create a new TestResponse from another response.
 *
 * @template R of TResponse
 *
 * @param  R  $response
 * @param  \\Illuminate\\Http\\Request|null  $request
 * @return static<R>
 */',
        'startLine' => 100,
        'endLine' => 103,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSuccessful' => 
      array (
        'name' => 'assertSuccessful',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the response has a successful status code.
 *
 * @return $this
 */',
        'startLine' => 110,
        'endLine' => 118,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSuccessfulPrecognition' => 
      array (
        'name' => 'assertSuccessfulPrecognition',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the Precognition request was successful.
 *
 * @return $this
 */',
        'startLine' => 125,
        'endLine' => 141,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertClientError' => 
      array (
        'name' => 'assertClientError',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the response is a client error.
 *
 * @return $this
 */',
        'startLine' => 148,
        'endLine' => 156,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertServerError' => 
      array (
        'name' => 'assertServerError',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the response is a server error.
 *
 * @return $this
 */',
        'startLine' => 163,
        'endLine' => 171,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertStatus' => 
      array (
        'name' => 'assertStatus',
        'parameters' => 
        array (
          'status' => 
          array (
            'name' => 'status',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 179,
            'endLine' => 179,
            'startColumn' => 34,
            'endColumn' => 40,
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
 * Assert that the response has the given status code.
 *
 * @param  int  $status
 * @return $this
 */',
        'startLine' => 179,
        'endLine' => 186,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'statusMessageWithDetails' => 
      array (
        'name' => 'statusMessageWithDetails',
        'parameters' => 
        array (
          'expected' => 
          array (
            'name' => 'expected',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 195,
            'endLine' => 195,
            'startColumn' => 49,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'actual' => 
          array (
            'name' => 'actual',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 195,
            'endLine' => 195,
            'startColumn' => 60,
            'endColumn' => 66,
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
 * Get an assertion message for a status assertion containing extra details when available.
 *
 * @param  string|int  $expected
 * @param  string|int  $actual
 * @return string
 */',
        'startLine' => 195,
        'endLine' => 198,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertRedirect' => 
      array (
        'name' => 'assertRedirect',
        'parameters' => 
        array (
          'uri' => 
          array (
            'name' => 'uri',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 206,
                'endLine' => 206,
                'startTokenPos' => 652,
                'startFilePos' => 5376,
                'endTokenPos' => 652,
                'endFilePos' => 5379,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 206,
            'endLine' => 206,
            'startColumn' => 36,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert whether the response is redirecting to a given URI.
 *
 * @param  string|null  $uri
 * @return $this
 */',
        'startLine' => 206,
        'endLine' => 218,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertRedirectContains' => 
      array (
        'name' => 'assertRedirectContains',
        'parameters' => 
        array (
          'uri' => 
          array (
            'name' => 'uri',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 226,
            'endLine' => 226,
            'startColumn' => 44,
            'endColumn' => 47,
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
 * Assert whether the response is redirecting to a URI that contains the given URI.
 *
 * @param  string  $uri
 * @return $this
 */',
        'startLine' => 226,
        'endLine' => 238,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertRedirectBack' => 
      array (
        'name' => 'assertRedirectBack',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert whether the response is redirecting back to the previous location.
 *
 * @return $this
 */',
        'startLine' => 245,
        'endLine' => 255,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertRedirectBackWithErrors' => 
      array (
        'name' => 'assertRedirectBackWithErrors',
        'parameters' => 
        array (
          'keys' => 
          array (
            'name' => 'keys',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 265,
                'endLine' => 265,
                'startTokenPos' => 906,
                'startFilePos' => 7121,
                'endTokenPos' => 907,
                'endFilePos' => 7122,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 265,
            'endLine' => 265,
            'startColumn' => 50,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'format' => 
          array (
            'name' => 'format',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 265,
                'endLine' => 265,
                'startTokenPos' => 914,
                'startFilePos' => 7135,
                'endTokenPos' => 914,
                'endFilePos' => 7138,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 265,
            'endLine' => 265,
            'startColumn' => 62,
            'endColumn' => 75,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'errorBag' => 
          array (
            'name' => 'errorBag',
            'default' => 
            array (
              'code' => '\'default\'',
              'attributes' => 
              array (
                'startLine' => 265,
                'endLine' => 265,
                'startTokenPos' => 921,
                'startFilePos' => 7153,
                'endTokenPos' => 921,
                'endFilePos' => 7161,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 265,
            'endLine' => 265,
            'startColumn' => 78,
            'endColumn' => 98,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert whether the response is redirecting back to the previous location with the given errors in the session.
 *
 * @param  string|array  $keys
 * @param  mixed  $format
 * @param  string  $errorBag
 * @return $this
 */',
        'startLine' => 265,
        'endLine' => 272,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertRedirectBackWithoutErrors' => 
      array (
        'name' => 'assertRedirectBackWithoutErrors',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert whether the response is redirecting back to the previous location with no errors in the session.
 *
 * @return $this
 */',
        'startLine' => 279,
        'endLine' => 286,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertRedirectToRoute' => 
      array (
        'name' => 'assertRedirectToRoute',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 295,
            'endLine' => 295,
            'startColumn' => 43,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 295,
                'endLine' => 295,
                'startTokenPos' => 1002,
                'startFilePos' => 7880,
                'endTokenPos' => 1003,
                'endFilePos' => 7881,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 295,
            'endLine' => 295,
            'startColumn' => 50,
            'endColumn' => 65,
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
 * Assert whether the response is redirecting to a given route.
 *
 * @param  \\BackedEnum|string  $name
 * @param  mixed  $parameters
 * @return $this
 */',
        'startLine' => 295,
        'endLine' => 307,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertRedirectToSignedRoute' => 
      array (
        'name' => 'assertRedirectToSignedRoute',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 317,
                'endLine' => 317,
                'startTokenPos' => 1083,
                'startFilePos' => 8482,
                'endTokenPos' => 1083,
                'endFilePos' => 8485,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 317,
            'endLine' => 317,
            'startColumn' => 49,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 317,
                'endLine' => 317,
                'startTokenPos' => 1090,
                'startFilePos' => 8502,
                'endTokenPos' => 1091,
                'endFilePos' => 8503,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 317,
            'endLine' => 317,
            'startColumn' => 63,
            'endColumn' => 78,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'absolute' => 
          array (
            'name' => 'absolute',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 317,
                'endLine' => 317,
                'startTokenPos' => 1098,
                'startFilePos' => 8518,
                'endTokenPos' => 1098,
                'endFilePos' => 8521,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 317,
            'endLine' => 317,
            'startColumn' => 81,
            'endColumn' => 96,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert whether the response is redirecting to a given signed route.
 *
 * @param  \\BackedEnum|string|null  $name
 * @param  mixed  $parameters
 * @param  bool  $absolute
 * @return $this
 */',
        'startLine' => 317,
        'endLine' => 346,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertRedirectToAction' => 
      array (
        'name' => 'assertRedirectToAction',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 355,
            'endLine' => 355,
            'startColumn' => 44,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 355,
                'endLine' => 355,
                'startTokenPos' => 1305,
                'startFilePos' => 9659,
                'endTokenPos' => 1306,
                'endFilePos' => 9660,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 355,
            'endLine' => 355,
            'startColumn' => 51,
            'endColumn' => 66,
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
 * Assert whether the response is redirecting to a given controller action.
 *
 * @param  string|array  $name
 * @param  array  $parameters
 * @return $this
 */',
        'startLine' => 355,
        'endLine' => 367,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertHeader' => 
      array (
        'name' => 'assertHeader',
        'parameters' => 
        array (
          'headerName' => 
          array (
            'name' => 'headerName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 376,
            'endLine' => 376,
            'startColumn' => 34,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 376,
                'endLine' => 376,
                'startTokenPos' => 1389,
                'startFilePos' => 10229,
                'endTokenPos' => 1389,
                'endFilePos' => 10232,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 376,
            'endLine' => 376,
            'startColumn' => 47,
            'endColumn' => 59,
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
 * Asserts that the response contains the given header and equals the optional value.
 *
 * @param  string  $headerName
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 376,
        'endLine' => 392,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertHeaderContains' => 
      array (
        'name' => 'assertHeaderContains',
        'parameters' => 
        array (
          'headerName' => 
          array (
            'name' => 'headerName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 401,
            'endLine' => 401,
            'startColumn' => 42,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 401,
            'endLine' => 401,
            'startColumn' => 55,
            'endColumn' => 60,
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
 * Asserts that the response contains the given header and that its value contains the given string.
 *
 * @param  string  $headerName
 * @param  string  $value
 * @return $this
 */',
        'startLine' => 401,
        'endLine' => 415,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertHeaderMissing' => 
      array (
        'name' => 'assertHeaderMissing',
        'parameters' => 
        array (
          'headerName' => 
          array (
            'name' => 'headerName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 423,
            'endLine' => 423,
            'startColumn' => 41,
            'endColumn' => 51,
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
 * Asserts that the response does not contain the given header.
 *
 * @param  string  $headerName
 * @return $this
 */',
        'startLine' => 423,
        'endLine' => 430,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertLocation' => 
      array (
        'name' => 'assertLocation',
        'parameters' => 
        array (
          'uri' => 
          array (
            'name' => 'uri',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 438,
            'endLine' => 438,
            'startColumn' => 36,
            'endColumn' => 39,
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
 * Assert that the current location header matches the given URI.
 *
 * @param  string  $uri
 * @return $this
 */',
        'startLine' => 438,
        'endLine' => 445,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertDownload' => 
      array (
        'name' => 'assertDownload',
        'parameters' => 
        array (
          'filename' => 
          array (
            'name' => 'filename',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 453,
                'endLine' => 453,
                'startTokenPos' => 1741,
                'startFilePos' => 12449,
                'endTokenPos' => 1741,
                'endFilePos' => 12452,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 453,
            'endLine' => 453,
            'startColumn' => 36,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the response offers a file download.
 *
 * @param  string|null  $filename
 * @return $this
 */',
        'startLine' => 453,
        'endLine' => 493,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertPlainCookie' => 
      array (
        'name' => 'assertPlainCookie',
        'parameters' => 
        array (
          'cookieName' => 
          array (
            'name' => 'cookieName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 502,
            'endLine' => 502,
            'startColumn' => 39,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 502,
                'endLine' => 502,
                'startTokenPos' => 2070,
                'startFilePos' => 14290,
                'endTokenPos' => 2070,
                'endFilePos' => 14293,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 502,
            'endLine' => 502,
            'startColumn' => 52,
            'endColumn' => 64,
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
 * Asserts that the response contains the given cookie and equals the optional value.
 *
 * @param  string  $cookieName
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 502,
        'endLine' => 507,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertCookie' => 
      array (
        'name' => 'assertCookie',
        'parameters' => 
        array (
          'cookieName' => 
          array (
            'name' => 'cookieName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 518,
            'endLine' => 518,
            'startColumn' => 34,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 518,
                'endLine' => 518,
                'startTokenPos' => 2111,
                'startFilePos' => 14708,
                'endTokenPos' => 2111,
                'endFilePos' => 14711,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 518,
            'endLine' => 518,
            'startColumn' => 47,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'encrypted' => 
          array (
            'name' => 'encrypted',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 518,
                'endLine' => 518,
                'startTokenPos' => 2118,
                'startFilePos' => 14727,
                'endTokenPos' => 2118,
                'endFilePos' => 14730,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 518,
            'endLine' => 518,
            'startColumn' => 62,
            'endColumn' => 78,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'unserialize' => 
          array (
            'name' => 'unserialize',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 518,
                'endLine' => 518,
                'startTokenPos' => 2125,
                'startFilePos' => 14748,
                'endTokenPos' => 2125,
                'endFilePos' => 14752,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 518,
            'endLine' => 518,
            'startColumn' => 81,
            'endColumn' => 100,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Asserts that the response contains the given cookie and equals the optional value.
 *
 * @param  string  $cookieName
 * @param  mixed  $value
 * @param  bool  $encrypted
 * @param  bool  $unserialize
 * @return $this
 */',
        'startLine' => 518,
        'endLine' => 537,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertCookieExpired' => 
      array (
        'name' => 'assertCookieExpired',
        'parameters' => 
        array (
          'cookieName' => 
          array (
            'name' => 'cookieName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 545,
            'endLine' => 545,
            'startColumn' => 41,
            'endColumn' => 51,
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
 * Asserts that the response contains the given cookie and is expired.
 *
 * @param  string  $cookieName
 * @return $this
 */',
        'startLine' => 545,
        'endLine' => 560,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertCookieNotExpired' => 
      array (
        'name' => 'assertCookieNotExpired',
        'parameters' => 
        array (
          'cookieName' => 
          array (
            'name' => 'cookieName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 568,
            'endLine' => 568,
            'startColumn' => 44,
            'endColumn' => 54,
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
 * Asserts that the response contains the given cookie and is not expired.
 *
 * @param  string  $cookieName
 * @return $this
 */',
        'startLine' => 568,
        'endLine' => 583,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertCookieMissing' => 
      array (
        'name' => 'assertCookieMissing',
        'parameters' => 
        array (
          'cookieName' => 
          array (
            'name' => 'cookieName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 591,
            'endLine' => 591,
            'startColumn' => 41,
            'endColumn' => 51,
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
 * Asserts that the response does not contain the given cookie.
 *
 * @param  string  $cookieName
 * @return $this
 */',
        'startLine' => 591,
        'endLine' => 599,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'getCookie' => 
      array (
        'name' => 'getCookie',
        'parameters' => 
        array (
          'cookieName' => 
          array (
            'name' => 'cookieName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 609,
            'endLine' => 609,
            'startColumn' => 31,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'decrypt' => 
          array (
            'name' => 'decrypt',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 609,
                'endLine' => 609,
                'startTokenPos' => 2564,
                'startFilePos' => 17540,
                'endTokenPos' => 2564,
                'endFilePos' => 17543,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 609,
            'endLine' => 609,
            'startColumn' => 44,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'unserialize' => 
          array (
            'name' => 'unserialize',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 609,
                'endLine' => 609,
                'startTokenPos' => 2571,
                'startFilePos' => 17561,
                'endTokenPos' => 2571,
                'endFilePos' => 17565,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 609,
            'endLine' => 609,
            'startColumn' => 61,
            'endColumn' => 80,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the given cookie from the response.
 *
 * @param  string  $cookieName
 * @param  bool  $decrypt
 * @param  bool  $unserialize
 * @return \\Symfony\\Component\\HttpFoundation\\Cookie|null
 */',
        'startLine' => 609,
        'endLine' => 635,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertContent' => 
      array (
        'name' => 'assertContent',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 643,
            'endLine' => 643,
            'startColumn' => 35,
            'endColumn' => 40,
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
 * Assert that the given string matches the response content.
 *
 * @param  string  $value
 * @return $this
 */',
        'startLine' => 643,
        'endLine' => 648,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertStreamed' => 
      array (
        'name' => 'assertStreamed',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the response was streamed.
 *
 * @return $this
 */',
        'startLine' => 655,
        'endLine' => 663,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertNotStreamed' => 
      array (
        'name' => 'assertNotStreamed',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the response was not streamed.
 *
 * @return $this
 */',
        'startLine' => 670,
        'endLine' => 678,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertStreamedContent' => 
      array (
        'name' => 'assertStreamedContent',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 686,
            'endLine' => 686,
            'startColumn' => 43,
            'endColumn' => 48,
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
 * Assert that the given string matches the streamed response content.
 *
 * @param  string  $value
 * @return $this
 */',
        'startLine' => 686,
        'endLine' => 691,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertStreamedJsonContent' => 
      array (
        'name' => 'assertStreamedJsonContent',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 701,
            'endLine' => 701,
            'startColumn' => 47,
            'endColumn' => 52,
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
 * Assert that the given array matches the streamed JSON response content.
 *
 * @param  array  $value
 * @return $this
 *
 * @throws \\JsonException
 */',
        'startLine' => 701,
        'endLine' => 704,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSee' => 
      array (
        'name' => 'assertSee',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 713,
            'endLine' => 713,
            'startColumn' => 31,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'escape' => 
          array (
            'name' => 'escape',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 713,
                'endLine' => 713,
                'startTokenPos' => 2973,
                'startFilePos' => 20464,
                'endTokenPos' => 2973,
                'endFilePos' => 20467,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 713,
            'endLine' => 713,
            'startColumn' => 39,
            'endColumn' => 52,
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
 * Assert that the given string or array of strings are contained within the response.
 *
 * @param  string|list<string>  $value
 * @param  bool  $escape
 * @return $this
 */',
        'startLine' => 713,
        'endLine' => 724,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSeeHtml' => 
      array (
        'name' => 'assertSeeHtml',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 732,
            'endLine' => 732,
            'startColumn' => 35,
            'endColumn' => 40,
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
 * Assert that the given HTML string or array of HTML strings are contained within the response.
 *
 * @param  string|list<string>  $value
 * @return $this
 */',
        'startLine' => 732,
        'endLine' => 735,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSeeInOrder' => 
      array (
        'name' => 'assertSeeInOrder',
        'parameters' => 
        array (
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 744,
            'endLine' => 744,
            'startColumn' => 38,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'escape' => 
          array (
            'name' => 'escape',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 744,
                'endLine' => 744,
                'startTokenPos' => 3102,
                'startFilePos' => 21308,
                'endTokenPos' => 3102,
                'endFilePos' => 21311,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 744,
            'endLine' => 744,
            'startColumn' => 53,
            'endColumn' => 66,
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
 * Assert that the given strings are contained in order within the response.
 *
 * @param  list<string>  $values
 * @param  bool  $escape
 * @return $this
 */',
        'startLine' => 744,
        'endLine' => 751,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSeeHtmlInOrder' => 
      array (
        'name' => 'assertSeeHtmlInOrder',
        'parameters' => 
        array (
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 759,
            'endLine' => 759,
            'startColumn' => 42,
            'endColumn' => 54,
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
 * Assert that the given HTML strings are contained in order within the response.
 *
 * @param  list<string>  $values
 * @return $this
 */',
        'startLine' => 759,
        'endLine' => 762,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSeeText' => 
      array (
        'name' => 'assertSeeText',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 771,
            'endLine' => 771,
            'startColumn' => 35,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'escape' => 
          array (
            'name' => 'escape',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 771,
                'endLine' => 771,
                'startTokenPos' => 3208,
                'startFilePos' => 22069,
                'endTokenPos' => 3208,
                'endFilePos' => 22072,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 771,
            'endLine' => 771,
            'startColumn' => 43,
            'endColumn' => 56,
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
 * Assert that the given string or array of strings are contained within the response text.
 *
 * @param  string|list<string>  $value
 * @param  bool  $escape
 * @return $this
 */',
        'startLine' => 771,
        'endLine' => 780,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSeeTextInOrder' => 
      array (
        'name' => 'assertSeeTextInOrder',
        'parameters' => 
        array (
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 789,
            'endLine' => 789,
            'startColumn' => 42,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'escape' => 
          array (
            'name' => 'escape',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 789,
                'endLine' => 789,
                'startTokenPos' => 3298,
                'startFilePos' => 22570,
                'endTokenPos' => 3298,
                'endFilePos' => 22573,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 789,
            'endLine' => 789,
            'startColumn' => 57,
            'endColumn' => 70,
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
 * Assert that the given strings are contained in order within the response text.
 *
 * @param  list<string>  $values
 * @param  bool  $escape
 * @return $this
 */',
        'startLine' => 789,
        'endLine' => 796,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertDontSee' => 
      array (
        'name' => 'assertDontSee',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 805,
            'endLine' => 805,
            'startColumn' => 35,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'escape' => 
          array (
            'name' => 'escape',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 805,
                'endLine' => 805,
                'startTokenPos' => 3377,
                'startFilePos' => 23043,
                'endTokenPos' => 3377,
                'endFilePos' => 23046,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 805,
            'endLine' => 805,
            'startColumn' => 43,
            'endColumn' => 56,
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
 * Assert that the given string or array of strings are not contained within the response.
 *
 * @param  string|list<string>  $value
 * @param  bool  $escape
 * @return $this
 */',
        'startLine' => 805,
        'endLine' => 816,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertDontSeeHtml' => 
      array (
        'name' => 'assertDontSeeHtml',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 824,
            'endLine' => 824,
            'startColumn' => 39,
            'endColumn' => 44,
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
 * Assert that the given HTML string or array of HTML strings are not contained within the response.
 *
 * @param  string|list<string>  $value
 * @return $this
 */',
        'startLine' => 824,
        'endLine' => 827,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertDontSeeText' => 
      array (
        'name' => 'assertDontSeeText',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 836,
            'endLine' => 836,
            'startColumn' => 39,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'escape' => 
          array (
            'name' => 'escape',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 836,
                'endLine' => 836,
                'startTokenPos' => 3504,
                'startFilePos' => 23921,
                'endTokenPos' => 3504,
                'endFilePos' => 23924,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 836,
            'endLine' => 836,
            'startColumn' => 47,
            'endColumn' => 60,
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
 * Assert that the given string or array of strings are not contained within the response text.
 *
 * @param  string|list<string>  $value
 * @param  bool  $escape
 * @return $this
 */',
        'startLine' => 836,
        'endLine' => 845,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJson' => 
      array (
        'name' => 'assertJson',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 854,
            'endLine' => 854,
            'startColumn' => 32,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'strict' => 
          array (
            'name' => 'strict',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 854,
                'endLine' => 854,
                'startTokenPos' => 3598,
                'startFilePos' => 24399,
                'endTokenPos' => 3598,
                'endFilePos' => 24403,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 854,
            'endLine' => 854,
            'startColumn' => 40,
            'endColumn' => 54,
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
 * Assert that the response is a superset of the given JSON.
 *
 * @param  array|callable  $value
 * @param  bool  $strict
 * @return $this
 */',
        'startLine' => 854,
        'endLine' => 871,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJsonPath' => 
      array (
        'name' => 'assertJsonPath',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 880,
            'endLine' => 880,
            'startColumn' => 36,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'expect' => 
          array (
            'name' => 'expect',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 880,
            'endLine' => 880,
            'startColumn' => 43,
            'endColumn' => 49,
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
 * Assert that the expected value and type exists at the given path in the response.
 *
 * @param  string  $path
 * @param  mixed  $expect
 * @return $this
 */',
        'startLine' => 880,
        'endLine' => 885,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJsonPaths' => 
      array (
        'name' => 'assertJsonPaths',
        'parameters' => 
        array (
          'paths' => 
          array (
            'name' => 'paths',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 892,
            'endLine' => 892,
            'startColumn' => 37,
            'endColumn' => 48,
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
 * Assert that the expected values and types exist at the given paths in the response.
 *
 * @return $this
 */',
        'startLine' => 892,
        'endLine' => 899,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJsonPathsCanonicalizing' => 
      array (
        'name' => 'assertJsonPathsCanonicalizing',
        'parameters' => 
        array (
          'paths' => 
          array (
            'name' => 'paths',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 906,
            'endLine' => 906,
            'startColumn' => 51,
            'endColumn' => 62,
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
 * Assert that the given paths in the response contain all of the expected values without looking at the order.
 *
 * @return $this
 */',
        'startLine' => 906,
        'endLine' => 913,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJsonPathCanonicalizing' => 
      array (
        'name' => 'assertJsonPathCanonicalizing',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 922,
            'endLine' => 922,
            'startColumn' => 50,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'expect' => 
          array (
            'name' => 'expect',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 922,
            'endLine' => 922,
            'startColumn' => 57,
            'endColumn' => 69,
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
 * Assert that the given path in the response contains all of the expected values without looking at the order.
 *
 * @param  string  $path
 * @param  array  $expect
 * @return $this
 */',
        'startLine' => 922,
        'endLine' => 927,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertExactJson' => 
      array (
        'name' => 'assertExactJson',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 935,
            'endLine' => 935,
            'startColumn' => 37,
            'endColumn' => 47,
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
 * Assert that the response has the exact given JSON.
 *
 * @param  array  $data
 * @return $this
 */',
        'startLine' => 935,
        'endLine' => 940,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSimilarJson' => 
      array (
        'name' => 'assertSimilarJson',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 948,
            'endLine' => 948,
            'startColumn' => 39,
            'endColumn' => 49,
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
 * Assert that the response has the similar JSON as given.
 *
 * @param  array  $data
 * @return $this
 */',
        'startLine' => 948,
        'endLine' => 953,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJsonFragments' => 
      array (
        'name' => 'assertJsonFragments',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 961,
            'endLine' => 961,
            'startColumn' => 41,
            'endColumn' => 51,
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
 * Assert that the response contains the given JSON fragments.
 *
 * @param  array  $data
 * @return $this
 */',
        'startLine' => 961,
        'endLine' => 968,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJsonFragment' => 
      array (
        'name' => 'assertJsonFragment',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 976,
            'endLine' => 976,
            'startColumn' => 40,
            'endColumn' => 50,
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
 * Assert that the response contains the given JSON fragment.
 *
 * @param  array  $data
 * @return $this
 */',
        'startLine' => 976,
        'endLine' => 981,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJsonMissing' => 
      array (
        'name' => 'assertJsonMissing',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 990,
            'endLine' => 990,
            'startColumn' => 39,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'exact' => 
          array (
            'name' => 'exact',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 990,
                'endLine' => 990,
                'startTokenPos' => 4038,
                'startFilePos' => 27672,
                'endTokenPos' => 4038,
                'endFilePos' => 27676,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 990,
            'endLine' => 990,
            'startColumn' => 52,
            'endColumn' => 65,
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
 * Assert that the response does not contain the given JSON fragment.
 *
 * @param  array  $data
 * @param  bool  $exact
 * @return $this
 */',
        'startLine' => 990,
        'endLine' => 995,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJsonMissingExact' => 
      array (
        'name' => 'assertJsonMissingExact',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1003,
            'endLine' => 1003,
            'startColumn' => 44,
            'endColumn' => 54,
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
 * Assert that the response does not contain the exact JSON fragment.
 *
 * @param  array  $data
 * @return $this
 */',
        'startLine' => 1003,
        'endLine' => 1008,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJsonMissingPath' => 
      array (
        'name' => 'assertJsonMissingPath',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1016,
            'endLine' => 1016,
            'startColumn' => 43,
            'endColumn' => 54,
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
 * Assert that the response does not contain the given path.
 *
 * @param  string  $path
 * @return $this
 */',
        'startLine' => 1016,
        'endLine' => 1021,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJsonMissingPaths' => 
      array (
        'name' => 'assertJsonMissingPaths',
        'parameters' => 
        array (
          'paths' => 
          array (
            'name' => 'paths',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1028,
            'endLine' => 1028,
            'startColumn' => 44,
            'endColumn' => 55,
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
 * Assert that the response does not contain the given paths.
 *
 * @return $this
 */',
        'startLine' => 1028,
        'endLine' => 1035,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJsonStructure' => 
      array (
        'name' => 'assertJsonStructure',
        'parameters' => 
        array (
          'structure' => 
          array (
            'name' => 'structure',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1044,
                'endLine' => 1044,
                'startTokenPos' => 4192,
                'startFilePos' => 28918,
                'endTokenPos' => 4192,
                'endFilePos' => 28921,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'array',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1044,
            'endLine' => 1044,
            'startColumn' => 41,
            'endColumn' => 64,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'responseData' => 
          array (
            'name' => 'responseData',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1044,
                'endLine' => 1044,
                'startTokenPos' => 4202,
                'startFilePos' => 28947,
                'endTokenPos' => 4202,
                'endFilePos' => 28950,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'array',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1044,
            'endLine' => 1044,
            'startColumn' => 67,
            'endColumn' => 93,
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
 * Assert that the response has a given JSON structure.
 *
 * @param  array|null  $structure
 * @param  array|null  $responseData
 * @return $this
 */',
        'startLine' => 1044,
        'endLine' => 1049,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertExactJsonStructure' => 
      array (
        'name' => 'assertExactJsonStructure',
        'parameters' => 
        array (
          'structure' => 
          array (
            'name' => 'structure',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1058,
                'endLine' => 1058,
                'startTokenPos' => 4244,
                'startFilePos' => 29320,
                'endTokenPos' => 4244,
                'endFilePos' => 29323,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'array',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1058,
            'endLine' => 1058,
            'startColumn' => 46,
            'endColumn' => 69,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'responseData' => 
          array (
            'name' => 'responseData',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1058,
                'endLine' => 1058,
                'startTokenPos' => 4254,
                'startFilePos' => 29349,
                'endTokenPos' => 4254,
                'endFilePos' => 29352,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'array',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1058,
            'endLine' => 1058,
            'startColumn' => 72,
            'endColumn' => 98,
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
 * Assert that the response has the exact JSON structure.
 *
 * @param  array|null  $structure
 * @param  array|null  $responseData
 * @return $this
 */',
        'startLine' => 1058,
        'endLine' => 1063,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJsonCount' => 
      array (
        'name' => 'assertJsonCount',
        'parameters' => 
        array (
          'count' => 
          array (
            'name' => 'count',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1072,
            'endLine' => 1072,
            'startColumn' => 37,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'key' => 
          array (
            'name' => 'key',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1072,
                'endLine' => 1072,
                'startTokenPos' => 4301,
                'startFilePos' => 29724,
                'endTokenPos' => 4301,
                'endFilePos' => 29727,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1072,
            'endLine' => 1072,
            'startColumn' => 49,
            'endColumn' => 59,
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
 * Assert that the response JSON has the expected count of items at the given key.
 *
 * @param  int  $count
 * @param  string|null  $key
 * @return $this
 */',
        'startLine' => 1072,
        'endLine' => 1077,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJsonValidationErrors' => 
      array (
        'name' => 'assertJsonValidationErrors',
        'parameters' => 
        array (
          'errors' => 
          array (
            'name' => 'errors',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1086,
            'endLine' => 1086,
            'startColumn' => 48,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'responseKey' => 
          array (
            'name' => 'responseKey',
            'default' => 
            array (
              'code' => '\'errors\'',
              'attributes' => 
              array (
                'startLine' => 1086,
                'endLine' => 1086,
                'startTokenPos' => 4343,
                'startFilePos' => 30088,
                'endTokenPos' => 4343,
                'endFilePos' => 30095,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1086,
            'endLine' => 1086,
            'startColumn' => 57,
            'endColumn' => 79,
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
 * Assert that the response has the given JSON validation errors.
 *
 * @param  string|array  $errors
 * @param  string  $responseKey
 * @return $this
 */',
        'startLine' => 1086,
        'endLine' => 1128,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertOnlyJsonValidationErrors' => 
      array (
        'name' => 'assertOnlyJsonValidationErrors',
        'parameters' => 
        array (
          'errors' => 
          array (
            'name' => 'errors',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1137,
            'endLine' => 1137,
            'startColumn' => 52,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'responseKey' => 
          array (
            'name' => 'responseKey',
            'default' => 
            array (
              'code' => '\'errors\'',
              'attributes' => 
              array (
                'startLine' => 1137,
                'endLine' => 1137,
                'startTokenPos' => 4629,
                'startFilePos' => 31920,
                'endTokenPos' => 4629,
                'endFilePos' => 31927,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1137,
            'endLine' => 1137,
            'startColumn' => 61,
            'endColumn' => 83,
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
 * Assert that the response has the given JSON validation errors but does not have any other JSON validation errors.
 *
 * @param  string|array  $errors
 * @param  string  $responseKey
 * @return $this
 */',
        'startLine' => 1137,
        'endLine' => 1155,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJsonValidationErrorFor' => 
      array (
        'name' => 'assertJsonValidationErrorFor',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1164,
            'endLine' => 1164,
            'startColumn' => 50,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'responseKey' => 
          array (
            'name' => 'responseKey',
            'default' => 
            array (
              'code' => '\'errors\'',
              'attributes' => 
              array (
                'startLine' => 1164,
                'endLine' => 1164,
                'startTokenPos' => 4815,
                'startFilePos' => 32835,
                'endTokenPos' => 4815,
                'endFilePos' => 32842,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1164,
            'endLine' => 1164,
            'startColumn' => 56,
            'endColumn' => 78,
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
 * Assert the response has any JSON validation errors for the given key.
 *
 * @param  string  $key
 * @param  string  $responseKey
 * @return $this
 */',
        'startLine' => 1164,
        'endLine' => 1180,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJsonMissingValidationErrors' => 
      array (
        'name' => 'assertJsonMissingValidationErrors',
        'parameters' => 
        array (
          'keys' => 
          array (
            'name' => 'keys',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1189,
                'endLine' => 1189,
                'startTokenPos' => 4934,
                'startFilePos' => 33735,
                'endTokenPos' => 4934,
                'endFilePos' => 33738,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1189,
            'endLine' => 1189,
            'startColumn' => 55,
            'endColumn' => 66,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'responseKey' => 
          array (
            'name' => 'responseKey',
            'default' => 
            array (
              'code' => '\'errors\'',
              'attributes' => 
              array (
                'startLine' => 1189,
                'endLine' => 1189,
                'startTokenPos' => 4941,
                'startFilePos' => 33756,
                'endTokenPos' => 4941,
                'endFilePos' => 33763,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1189,
            'endLine' => 1189,
            'startColumn' => 69,
            'endColumn' => 91,
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
 * Assert that the response has no JSON validation errors for the given keys.
 *
 * @param  string|array|null  $keys
 * @param  string  $responseKey
 * @return $this
 */',
        'startLine' => 1189,
        'endLine' => 1222,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJsonIsArray' => 
      array (
        'name' => 'assertJsonIsArray',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1230,
                'endLine' => 1230,
                'startTokenPos' => 5178,
                'startFilePos' => 34889,
                'endTokenPos' => 5178,
                'endFilePos' => 34892,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1230,
            'endLine' => 1230,
            'startColumn' => 39,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the given key is a JSON array.
 *
 * @param  string|null  $key
 * @return $this
 */',
        'startLine' => 1230,
        'endLine' => 1243,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertJsonIsObject' => 
      array (
        'name' => 'assertJsonIsObject',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1251,
                'endLine' => 1251,
                'startTokenPos' => 5262,
                'startFilePos' => 35372,
                'endTokenPos' => 5262,
                'endFilePos' => 35375,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1251,
            'endLine' => 1251,
            'startColumn' => 40,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the given key is a JSON object.
 *
 * @param  string|null  $key
 * @return $this
 */',
        'startLine' => 1251,
        'endLine' => 1264,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'decodeResponseJson' => 
      array (
        'name' => 'decodeResponseJson',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Validate the decoded response JSON.
 *
 * @return \\Illuminate\\Testing\\AssertableJsonString
 *
 * @throws \\Throwable
 */',
        'startLine' => 1273,
        'endLine' => 1297,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'json' => 
      array (
        'name' => 'json',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1305,
                'endLine' => 1305,
                'startTokenPos' => 5529,
                'startFilePos' => 36848,
                'endTokenPos' => 5529,
                'endFilePos' => 36851,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1305,
            'endLine' => 1305,
            'startColumn' => 26,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the decoded response JSON.
 *
 * @param  string|null  $key
 * @return mixed
 */',
        'startLine' => 1305,
        'endLine' => 1308,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'collect' => 
      array (
        'name' => 'collect',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1316,
                'endLine' => 1316,
                'startTokenPos' => 5562,
                'startFilePos' => 37126,
                'endTokenPos' => 5562,
                'endFilePos' => 37129,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1316,
            'endLine' => 1316,
            'startColumn' => 29,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the decoded JSON body of the response as a collection.
 *
 * @param  string|null  $key
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 1316,
        'endLine' => 1319,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertViewIs' => 
      array (
        'name' => 'assertViewIs',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1327,
            'endLine' => 1327,
            'startColumn' => 34,
            'endColumn' => 39,
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
 * Assert that the response view equals the given value.
 *
 * @param  string  $value
 * @return $this
 */',
        'startLine' => 1327,
        'endLine' => 1334,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertViewHas' => 
      array (
        'name' => 'assertViewHas',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1343,
            'endLine' => 1343,
            'startColumn' => 35,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1343,
                'endLine' => 1343,
                'startTokenPos' => 5648,
                'startFilePos' => 37759,
                'endTokenPos' => 5648,
                'endFilePos' => 37762,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1343,
            'endLine' => 1343,
            'startColumn' => 41,
            'endColumn' => 53,
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
 * Assert that the response view has a given piece of bound data.
 *
 * @param  string|array  $key
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 1343,
        'endLine' => 1369,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertViewHasAll' => 
      array (
        'name' => 'assertViewHasAll',
        'parameters' => 
        array (
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1377,
            'endLine' => 1377,
            'startColumn' => 38,
            'endColumn' => 52,
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
 * Assert that the response view has a given list of bound data.
 *
 * @param  array  $bindings
 * @return $this
 */',
        'startLine' => 1377,
        'endLine' => 1388,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'viewData' => 
      array (
        'name' => 'viewData',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1396,
                'endLine' => 1396,
                'startTokenPos' => 6061,
                'startFilePos' => 39830,
                'endTokenPos' => 6061,
                'endFilePos' => 39833,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1396,
            'endLine' => 1396,
            'startColumn' => 30,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a piece of data from the original view.
 *
 * @param  string|null  $key
 * @return mixed
 */',
        'startLine' => 1396,
        'endLine' => 1407,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertViewMissing' => 
      array (
        'name' => 'assertViewMissing',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1415,
            'endLine' => 1415,
            'startColumn' => 39,
            'endColumn' => 42,
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
 * Assert that the response view is missing a piece of bound data.
 *
 * @param  string  $key
 * @return $this
 */',
        'startLine' => 1415,
        'endLine' => 1422,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'ensureResponseHasView' => 
      array (
        'name' => 'ensureResponseHasView',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Ensure that the response has a view as its original content.
 *
 * @return $this
 */',
        'startLine' => 1429,
        'endLine' => 1436,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'responseHasView' => 
      array (
        'name' => 'responseHasView',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the original response is a view.
 *
 * @return bool
 */',
        'startLine' => 1443,
        'endLine' => 1446,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertValid' => 
      array (
        'name' => 'assertValid',
        'parameters' => 
        array (
          'keys' => 
          array (
            'name' => 'keys',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1456,
                'endLine' => 1456,
                'startTokenPos' => 6264,
                'startFilePos' => 41209,
                'endTokenPos' => 6264,
                'endFilePos' => 41212,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1456,
            'endLine' => 1456,
            'startColumn' => 33,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'errorBag' => 
          array (
            'name' => 'errorBag',
            'default' => 
            array (
              'code' => '\'default\'',
              'attributes' => 
              array (
                'startLine' => 1456,
                'endLine' => 1456,
                'startTokenPos' => 6271,
                'startFilePos' => 41227,
                'endTokenPos' => 6271,
                'endFilePos' => 41235,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1456,
            'endLine' => 1456,
            'startColumn' => 47,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'responseKey' => 
          array (
            'name' => 'responseKey',
            'default' => 
            array (
              'code' => '\'errors\'',
              'attributes' => 
              array (
                'startLine' => 1456,
                'endLine' => 1456,
                'startTokenPos' => 6278,
                'startFilePos' => 41253,
                'endTokenPos' => 6278,
                'endFilePos' => 41260,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1456,
            'endLine' => 1456,
            'startColumn' => 70,
            'endColumn' => 92,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the given keys do not have validation errors.
 *
 * @param  string|array|null  $keys
 * @param  string  $errorBag
 * @param  string  $responseKey
 * @return $this
 */',
        'startLine' => 1456,
        'endLine' => 1489,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertInvalid' => 
      array (
        'name' => 'assertInvalid',
        'parameters' => 
        array (
          'errors' => 
          array (
            'name' => 'errors',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1499,
                'endLine' => 1499,
                'startTokenPos' => 6536,
                'startFilePos' => 42606,
                'endTokenPos' => 6536,
                'endFilePos' => 42609,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1499,
            'endLine' => 1499,
            'startColumn' => 35,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'errorBag' => 
          array (
            'name' => 'errorBag',
            'default' => 
            array (
              'code' => '\'default\'',
              'attributes' => 
              array (
                'startLine' => 1500,
                'endLine' => 1500,
                'startTokenPos' => 6543,
                'startFilePos' => 42658,
                'endTokenPos' => 6543,
                'endFilePos' => 42666,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1500,
            'endLine' => 1500,
            'startColumn' => 35,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'responseKey' => 
          array (
            'name' => 'responseKey',
            'default' => 
            array (
              'code' => '\'errors\'',
              'attributes' => 
              array (
                'startLine' => 1501,
                'endLine' => 1501,
                'startTokenPos' => 6550,
                'startFilePos' => 42718,
                'endTokenPos' => 6550,
                'endFilePos' => 42725,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1501,
            'endLine' => 1501,
            'startColumn' => 35,
            'endColumn' => 57,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the response has the given validation errors.
 *
 * @param  string|array|null  $errors
 * @param  string  $errorBag
 * @param  string  $responseKey
 * @return $this
 */',
        'startLine' => 1499,
        'endLine' => 1545,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertOnlyInvalid' => 
      array (
        'name' => 'assertOnlyInvalid',
        'parameters' => 
        array (
          'errors' => 
          array (
            'name' => 'errors',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1555,
                'endLine' => 1555,
                'startTokenPos' => 6884,
                'startFilePos' => 44826,
                'endTokenPos' => 6884,
                'endFilePos' => 44829,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1555,
            'endLine' => 1555,
            'startColumn' => 39,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'errorBag' => 
          array (
            'name' => 'errorBag',
            'default' => 
            array (
              'code' => '\'default\'',
              'attributes' => 
              array (
                'startLine' => 1555,
                'endLine' => 1555,
                'startTokenPos' => 6891,
                'startFilePos' => 44844,
                'endTokenPos' => 6891,
                'endFilePos' => 44852,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1555,
            'endLine' => 1555,
            'startColumn' => 55,
            'endColumn' => 75,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'responseKey' => 
          array (
            'name' => 'responseKey',
            'default' => 
            array (
              'code' => '\'errors\'',
              'attributes' => 
              array (
                'startLine' => 1555,
                'endLine' => 1555,
                'startTokenPos' => 6898,
                'startFilePos' => 44870,
                'endTokenPos' => 6898,
                'endFilePos' => 44877,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1555,
            'endLine' => 1555,
            'startColumn' => 78,
            'endColumn' => 100,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the response has the given validation errors but does not have any other validation errors.
 *
 * @param  string|array|null  $errors
 * @param  string  $errorBag
 * @param  string  $responseKey
 * @return $this
 */',
        'startLine' => 1555,
        'endLine' => 1579,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSessionHas' => 
      array (
        'name' => 'assertSessionHas',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1588,
            'endLine' => 1588,
            'startColumn' => 38,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1588,
                'endLine' => 1588,
                'startTokenPos' => 7120,
                'startFilePos' => 45950,
                'endTokenPos' => 7120,
                'endFilePos' => 45953,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1588,
            'endLine' => 1588,
            'startColumn' => 44,
            'endColumn' => 56,
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
 * Assert that the session has a given value.
 *
 * @param  string|array  $key
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 1588,
        'endLine' => 1606,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSessionHasAll' => 
      array (
        'name' => 'assertSessionHasAll',
        'parameters' => 
        array (
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1614,
            'endLine' => 1614,
            'startColumn' => 41,
            'endColumn' => 55,
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
 * Assert that the session has a given list of values.
 *
 * @param  array  $bindings
 * @return $this
 */',
        'startLine' => 1614,
        'endLine' => 1635,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSessionHasInput' => 
      array (
        'name' => 'assertSessionHasInput',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1644,
            'endLine' => 1644,
            'startColumn' => 43,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1644,
                'endLine' => 1644,
                'startTokenPos' => 7454,
                'startFilePos' => 47569,
                'endTokenPos' => 7454,
                'endFilePos' => 47572,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1644,
            'endLine' => 1644,
            'startColumn' => 49,
            'endColumn' => 61,
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
 * Assert that the session has a given value in the flashed input array.
 *
 * @param  string|array  $key
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 1644,
        'endLine' => 1670,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSessionMissingInput' => 
      array (
        'name' => 'assertSessionMissingInput',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1678,
            'endLine' => 1678,
            'startColumn' => 47,
            'endColumn' => 50,
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
 * Assert that the session is missing a given key in the flashed input array.
 *
 * @param  string|array  $key
 * @return $this
 */',
        'startLine' => 1678,
        'endLine' => 1694,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSessionHasErrors' => 
      array (
        'name' => 'assertSessionHasErrors',
        'parameters' => 
        array (
          'keys' => 
          array (
            'name' => 'keys',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1704,
                'endLine' => 1704,
                'startTokenPos' => 7761,
                'startFilePos' => 49237,
                'endTokenPos' => 7762,
                'endFilePos' => 49238,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1704,
            'endLine' => 1704,
            'startColumn' => 44,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'format' => 
          array (
            'name' => 'format',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1704,
                'endLine' => 1704,
                'startTokenPos' => 7769,
                'startFilePos' => 49251,
                'endTokenPos' => 7769,
                'endFilePos' => 49254,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1704,
            'endLine' => 1704,
            'startColumn' => 56,
            'endColumn' => 69,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'errorBag' => 
          array (
            'name' => 'errorBag',
            'default' => 
            array (
              'code' => '\'default\'',
              'attributes' => 
              array (
                'startLine' => 1704,
                'endLine' => 1704,
                'startTokenPos' => 7776,
                'startFilePos' => 49269,
                'endTokenPos' => 7776,
                'endFilePos' => 49277,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1704,
            'endLine' => 1704,
            'startColumn' => 72,
            'endColumn' => 92,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the session has the given errors.
 *
 * @param  string|array  $keys
 * @param  mixed  $format
 * @param  string  $errorBag
 * @return $this
 */',
        'startLine' => 1704,
        'endLine' => 1721,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSessionDoesntHaveErrors' => 
      array (
        'name' => 'assertSessionDoesntHaveErrors',
        'parameters' => 
        array (
          'keys' => 
          array (
            'name' => 'keys',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1731,
                'endLine' => 1731,
                'startTokenPos' => 7936,
                'startFilePos' => 50098,
                'endTokenPos' => 7937,
                'endFilePos' => 50099,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1731,
            'endLine' => 1731,
            'startColumn' => 51,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'format' => 
          array (
            'name' => 'format',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1731,
                'endLine' => 1731,
                'startTokenPos' => 7944,
                'startFilePos' => 50112,
                'endTokenPos' => 7944,
                'endFilePos' => 50115,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1731,
            'endLine' => 1731,
            'startColumn' => 63,
            'endColumn' => 76,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'errorBag' => 
          array (
            'name' => 'errorBag',
            'default' => 
            array (
              'code' => '\'default\'',
              'attributes' => 
              array (
                'startLine' => 1731,
                'endLine' => 1731,
                'startTokenPos' => 7951,
                'startFilePos' => 50130,
                'endTokenPos' => 7951,
                'endFilePos' => 50138,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1731,
            'endLine' => 1731,
            'startColumn' => 79,
            'endColumn' => 99,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the session is missing the given errors.
 *
 * @param  string|array  $keys
 * @param  string|null  $format
 * @param  string  $errorBag
 * @return $this
 */',
        'startLine' => 1731,
        'endLine' => 1756,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSessionHasNoErrors' => 
      array (
        'name' => 'assertSessionHasNoErrors',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the session has no errors.
 *
 * @return $this
 *
 * @throws \\JsonException
 */',
        'startLine' => 1765,
        'endLine' => 1790,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSessionHasErrorsIn' => 
      array (
        'name' => 'assertSessionHasErrorsIn',
        'parameters' => 
        array (
          'errorBag' => 
          array (
            'name' => 'errorBag',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1800,
            'endLine' => 1800,
            'startColumn' => 46,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'keys' => 
          array (
            'name' => 'keys',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1800,
                'endLine' => 1800,
                'startTokenPos' => 8356,
                'startFilePos' => 52193,
                'endTokenPos' => 8357,
                'endFilePos' => 52194,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1800,
            'endLine' => 1800,
            'startColumn' => 57,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'format' => 
          array (
            'name' => 'format',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1800,
                'endLine' => 1800,
                'startTokenPos' => 8364,
                'startFilePos' => 52207,
                'endTokenPos' => 8364,
                'endFilePos' => 52210,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1800,
            'endLine' => 1800,
            'startColumn' => 69,
            'endColumn' => 82,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the session has the given errors.
 *
 * @param  string  $errorBag
 * @param  string|array  $keys
 * @param  mixed  $format
 * @return $this
 */',
        'startLine' => 1800,
        'endLine' => 1803,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'assertSessionMissing' => 
      array (
        'name' => 'assertSessionMissing',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1812,
            'endLine' => 1812,
            'startColumn' => 42,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1812,
                'endLine' => 1812,
                'startTokenPos' => 8402,
                'startFilePos' => 52520,
                'endTokenPos' => 8402,
                'endFilePos' => 52523,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1812,
            'endLine' => 1812,
            'startColumn' => 48,
            'endColumn' => 60,
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
 * Assert that the session does not have a given key.
 *
 * @param  string|array  $key
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 1812,
        'endLine' => 1834,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'session' => 
      array (
        'name' => 'session',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the current session store.
 *
 * @return \\Illuminate\\Session\\Store
 */',
        'startLine' => 1841,
        'endLine' => 1850,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'ddHeaders' => 
      array (
        'name' => 'ddHeaders',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dump the headers from the response and end the script.
 *
 * @return never
 */',
        'startLine' => 1857,
        'endLine' => 1862,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'ddBody' => 
      array (
        'name' => 'ddBody',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1870,
                'endLine' => 1870,
                'startTokenPos' => 8661,
                'startFilePos' => 53862,
                'endTokenPos' => 8661,
                'endFilePos' => 53865,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1870,
            'endLine' => 1870,
            'startColumn' => 28,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dump the body of the response and end the script.
 *
 * @param  string|null  $key
 * @return never
 */',
        'startLine' => 1870,
        'endLine' => 1879,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'ddJson' => 
      array (
        'name' => 'ddJson',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1887,
                'endLine' => 1887,
                'startTokenPos' => 8718,
                'startFilePos' => 54202,
                'endTokenPos' => 8718,
                'endFilePos' => 54205,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1887,
            'endLine' => 1887,
            'startColumn' => 28,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dump the JSON payload from the response and end the script.
 *
 * @param  string|null  $key
 * @return never
 */',
        'startLine' => 1887,
        'endLine' => 1890,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'ddSession' => 
      array (
        'name' => 'ddSession',
        'parameters' => 
        array (
          'keys' => 
          array (
            'name' => 'keys',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1898,
                'endLine' => 1898,
                'startTokenPos' => 8748,
                'startFilePos' => 54431,
                'endTokenPos' => 8749,
                'endFilePos' => 54432,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1898,
            'endLine' => 1898,
            'startColumn' => 31,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dump the session from the response and end the script.
 *
 * @param  string|array  $keys
 * @return never
 */',
        'startLine' => 1898,
        'endLine' => 1903,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'dump' => 
      array (
        'name' => 'dump',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1911,
                'endLine' => 1911,
                'startTokenPos' => 8782,
                'startFilePos' => 54653,
                'endTokenPos' => 8782,
                'endFilePos' => 54656,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1911,
            'endLine' => 1911,
            'startColumn' => 26,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dump the content from the response.
 *
 * @param  string|null  $key
 * @return $this
 */',
        'startLine' => 1911,
        'endLine' => 1928,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'dumpHeaders' => 
      array (
        'name' => 'dumpHeaders',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dump the headers from the response.
 *
 * @return $this
 */',
        'startLine' => 1935,
        'endLine' => 1940,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'dumpSession' => 
      array (
        'name' => 'dumpSession',
        'parameters' => 
        array (
          'keys' => 
          array (
            'name' => 'keys',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1948,
                'endLine' => 1948,
                'startTokenPos' => 8920,
                'startFilePos' => 55356,
                'endTokenPos' => 8921,
                'endFilePos' => 55357,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1948,
            'endLine' => 1948,
            'startColumn' => 33,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dump the session from the response.
 *
 * @param  string|array  $keys
 * @return $this
 */',
        'startLine' => 1948,
        'endLine' => 1959,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'streamedContent' => 
      array (
        'name' => 'streamedContent',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the streamed content from the response.
 *
 * @return string
 */',
        'startLine' => 1966,
        'endLine' => 1988,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'withExceptions' => 
      array (
        'name' => 'withExceptions',
        'parameters' => 
        array (
          'exceptions' => 
          array (
            'name' => 'exceptions',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Collection',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1996,
            'endLine' => 1996,
            'startColumn' => 36,
            'endColumn' => 57,
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
 * Set the previous exceptions on the response.
 *
 * @param  \\Illuminate\\Support\\Collection  $exceptions
 * @return $this
 */',
        'startLine' => 1996,
        'endLine' => 2001,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      '__get' => 
      array (
        'name' => '__get',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2009,
            'endLine' => 2009,
            'startColumn' => 27,
            'endColumn' => 30,
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
 * Dynamically access base response parameters.
 *
 * @param  string  $key
 * @return mixed
 */',
        'startLine' => 2009,
        'endLine' => 2012,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      '__isset' => 
      array (
        'name' => '__isset',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2020,
            'endLine' => 2020,
            'startColumn' => 29,
            'endColumn' => 32,
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
 * Proxy isset() checks to the underlying base response.
 *
 * @param  string  $key
 * @return bool
 */',
        'startLine' => 2020,
        'endLine' => 2023,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'offsetExists' => 
      array (
        'name' => 'offsetExists',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2031,
            'endLine' => 2031,
            'startColumn' => 34,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given offset exists.
 *
 * @param  string  $offset
 * @return bool
 */',
        'startLine' => 2031,
        'endLine' => 2036,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'offsetGet' => 
      array (
        'name' => 'offsetGet',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2044,
            'endLine' => 2044,
            'startColumn' => 31,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the value for a given offset.
 *
 * @param  string  $offset
 * @return mixed
 */',
        'startLine' => 2044,
        'endLine' => 2049,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'offsetSet' => 
      array (
        'name' => 'offsetSet',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2060,
            'endLine' => 2060,
            'startColumn' => 31,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2060,
            'endLine' => 2060,
            'startColumn' => 40,
            'endColumn' => 45,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the value at the given offset.
 *
 * @param  string  $offset
 * @param  mixed  $value
 * @return void
 *
 * @throws \\LogicException
 */',
        'startLine' => 2060,
        'endLine' => 2063,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      'offsetUnset' => 
      array (
        'name' => 'offsetUnset',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2073,
            'endLine' => 2073,
            'startColumn' => 33,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Unset the value at the given offset.
 *
 * @param  string  $offset
 * @return void
 *
 * @throws \\LogicException
 */',
        'startLine' => 2073,
        'endLine' => 2076,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
      '__call' => 
      array (
        'name' => '__call',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2085,
            'endLine' => 2085,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2085,
            'endLine' => 2085,
            'startColumn' => 37,
            'endColumn' => 41,
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
 * Handle dynamic calls into macros or pass missing methods to the base response.
 *
 * @param  string  $method
 * @param  array  $args
 * @return mixed
 */',
        'startLine' => 2085,
        'endLine' => 2092,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Testing',
        'declaringClassName' => 'Illuminate\\Testing\\TestResponse',
        'implementingClassName' => 'Illuminate\\Testing\\TestResponse',
        'currentClassName' => 'Illuminate\\Testing\\TestResponse',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
        'Illuminate\\Testing\\Concerns\\AssertsStatusCodes' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\testing\\concerns\\assertsstatuscodes::__call',
          ),
        ),
        'Illuminate\\Support\\Traits\\Conditionable' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\support\\traits\\conditionable::__call',
          ),
        ),
        'Illuminate\\Support\\Traits\\Dumpable' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\support\\traits\\dumpable::__call',
          ),
        ),
        'Illuminate\\Support\\Traits\\Tappable' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\support\\traits\\tappable::__call',
          ),
        ),
        'Illuminate\\Support\\Traits\\Macroable' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\support\\traits\\macroable::__call',
          ),
        ),
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
        'illuminate\\testing\\concerns\\assertsstatuscodes::__call' => 'Illuminate\\Testing\\Concerns\\AssertsStatusCodes::__call',
        'illuminate\\support\\traits\\conditionable::__call' => 'Illuminate\\Support\\Traits\\Conditionable::__call',
        'illuminate\\support\\traits\\dumpable::__call' => 'Illuminate\\Support\\Traits\\Dumpable::__call',
        'illuminate\\support\\traits\\tappable::__call' => 'Illuminate\\Support\\Traits\\Tappable::__call',
        'illuminate\\support\\traits\\macroable::__call' => 'Illuminate\\Support\\Traits\\Macroable::__call',
      ),
    ),
  ),
));