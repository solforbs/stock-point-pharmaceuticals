<?php declare(strict_types = 1);

// osfsl-C:/xampp/htdocs/pharmacy_erp/vendor/composer/../laravel/framework/src/Illuminate/Auth/SessionGuard.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Auth\SessionGuard
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-e14846c4d824ee4f412270c10a9f93a5d1d9d62b924d6ce01a5d8aa73bbc3264-8.3.31-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Auth\\SessionGuard',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/vendor/composer/../laravel/framework/src/Illuminate/Auth/SessionGuard.php',
      ),
    ),
    'namespace' => 'Illuminate\\Auth',
    'name' => 'Illuminate\\Auth\\SessionGuard',
    'shortName' => 'SessionGuard',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 30,
    'endLine' => 1053,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Illuminate\\Contracts\\Auth\\StatefulGuard',
      1 => 'Illuminate\\Contracts\\Auth\\SupportsBasicAuth',
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Auth\\GuardHelpers',
      1 => 'Illuminate\\Support\\Traits\\Macroable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'name' => 
      array (
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'name' => 'name',
        'modifiers' => 2177,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * The name of the guard. Typically "web".
 *
 * Corresponds to guard name in authentication configuration.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 41,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'lastAttempted' => 
      array (
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'name' => 'lastAttempted',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The user we last attempted to retrieve.
 *
 * @var \\Illuminate\\Contracts\\Auth\\Authenticatable
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 48,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'viaRemember' => 
      array (
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'name' => 'viaRemember',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 55,
            'endLine' => 55,
            'startTokenPos' => 182,
            'startFilePos' => 1638,
            'endTokenPos' => 182,
            'endFilePos' => 1642,
          ),
        ),
        'docComment' => '/**
 * Indicates if the user was authenticated via a recaller cookie.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 55,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'rememberDuration' => 
      array (
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'name' => 'rememberDuration',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '576000',
          'attributes' => 
          array (
            'startLine' => 62,
            'endLine' => 62,
            'startTokenPos' => 193,
            'startFilePos' => 1799,
            'endTokenPos' => 193,
            'endFilePos' => 1804,
          ),
        ),
        'docComment' => '/**
 * The number of minutes that the "remember me" cookie should be valid for.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 62,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'session' => 
      array (
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'name' => 'session',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The session used by the guard.
 *
 * @var \\Illuminate\\Contracts\\Session\\Session
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 69,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'cookie' => 
      array (
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'name' => 'cookie',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The Illuminate cookie creator service.
 *
 * @var \\Illuminate\\Contracts\\Cookie\\QueueingFactory
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 76,
        'endLine' => 76,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'request' => 
      array (
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'name' => 'request',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The request instance.
 *
 * @var \\Symfony\\Component\\HttpFoundation\\Request
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 83,
        'endLine' => 83,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'events' => 
      array (
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'name' => 'events',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The event dispatcher instance.
 *
 * @var \\Illuminate\\Contracts\\Events\\Dispatcher
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 90,
        'endLine' => 90,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'timebox' => 
      array (
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'name' => 'timebox',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The timebox instance.
 *
 * @var \\Illuminate\\Support\\Timebox
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 97,
        'endLine' => 97,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'timeboxDuration' => 
      array (
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'name' => 'timeboxDuration',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The number of microseconds that the timebox should wait for.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 104,
        'endLine' => 104,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'rehashOnLogin' => 
      array (
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'name' => 'rehashOnLogin',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Indicates if passwords should be rehashed on login if needed.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 111,
        'endLine' => 111,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'hashKey' => 
      array (
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'name' => 'hashKey',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The key used to hash recaller cookie values.
 *
 * @var string|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 118,
        'endLine' => 118,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'loggedOut' => 
      array (
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'name' => 'loggedOut',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 125,
            'endLine' => 125,
            'startTokenPos' => 260,
            'startFilePos' => 3005,
            'endTokenPos' => 260,
            'endFilePos' => 3009,
          ),
        ),
        'docComment' => '/**
 * Indicates if the logout method has been called.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 125,
        'endLine' => 125,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'recallAttempted' => 
      array (
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'name' => 'recallAttempted',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 132,
            'endLine' => 132,
            'startTokenPos' => 271,
            'startFilePos' => 3149,
            'endTokenPos' => 271,
            'endFilePos' => 3153,
          ),
        ),
        'docComment' => '/**
 * Indicates if a token user retrieval has been attempted.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 132,
        'endLine' => 132,
        'startColumn' => 5,
        'endColumn' => 39,
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
            'startLine' => 147,
            'endLine' => 147,
            'startColumn' => 9,
            'endColumn' => 13,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'provider' => 
          array (
            'name' => 'provider',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Auth\\UserProvider',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 148,
            'endLine' => 148,
            'startColumn' => 9,
            'endColumn' => 30,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'session' => 
          array (
            'name' => 'session',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Session\\Session',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 149,
            'endLine' => 149,
            'startColumn' => 9,
            'endColumn' => 24,
            'parameterIndex' => 2,
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
                'startLine' => 150,
                'endLine' => 150,
                'startTokenPos' => 303,
                'startFilePos' => 3754,
                'endTokenPos' => 303,
                'endFilePos' => 3757,
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
                      'name' => 'Symfony\\Component\\HttpFoundation\\Request',
                      'isIdentifier' => false,
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
            'startLine' => 150,
            'endLine' => 150,
            'startColumn' => 9,
            'endColumn' => 32,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'timebox' => 
          array (
            'name' => 'timebox',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 151,
                'endLine' => 151,
                'startTokenPos' => 313,
                'startFilePos' => 3788,
                'endTokenPos' => 313,
                'endFilePos' => 3791,
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
                      'name' => 'Illuminate\\Support\\Timebox',
                      'isIdentifier' => false,
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
            'startLine' => 151,
            'endLine' => 151,
            'startColumn' => 9,
            'endColumn' => 32,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'rehashOnLogin' => 
          array (
            'name' => 'rehashOnLogin',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 152,
                'endLine' => 152,
                'startTokenPos' => 322,
                'startFilePos' => 3824,
                'endTokenPos' => 322,
                'endFilePos' => 3827,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 152,
            'endLine' => 152,
            'startColumn' => 9,
            'endColumn' => 34,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
          'timeboxDuration' => 
          array (
            'name' => 'timeboxDuration',
            'default' => 
            array (
              'code' => '200000',
              'attributes' => 
              array (
                'startLine' => 153,
                'endLine' => 153,
                'startTokenPos' => 331,
                'startFilePos' => 3861,
                'endTokenPos' => 331,
                'endFilePos' => 3866,
              ),
            ),
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
            'startLine' => 153,
            'endLine' => 153,
            'startColumn' => 9,
            'endColumn' => 37,
            'parameterIndex' => 6,
            'isOptional' => true,
          ),
          'hashKey' => 
          array (
            'name' => 'hashKey',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 154,
                'endLine' => 154,
                'startTokenPos' => 341,
                'startFilePos' => 3896,
                'endTokenPos' => 341,
                'endFilePos' => 3899,
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
                      'name' => 'string',
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
            'startLine' => 154,
            'endLine' => 154,
            'startColumn' => 9,
            'endColumn' => 31,
            'parameterIndex' => 7,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new authentication guard.
 *
 * @param  string  $name
 * @param  \\Illuminate\\Contracts\\Auth\\UserProvider  $provider
 * @param  \\Illuminate\\Contracts\\Session\\Session  $session
 * @param  \\Symfony\\Component\\HttpFoundation\\Request|null  $request
 * @param  \\Illuminate\\Support\\Timebox|null  $timebox
 * @param  bool  $rehashOnLogin
 * @param  int  $timeboxDuration
 * @param  string|null  $hashKey
 */',
        'startLine' => 146,
        'endLine' => 164,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'user' => 
      array (
        'name' => 'user',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the currently authenticated user.
 *
 * @return \\Illuminate\\Contracts\\Auth\\Authenticatable|null
 */',
        'startLine' => 171,
        'endLine' => 207,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'userFromRecaller' => 
      array (
        'name' => 'userFromRecaller',
        'parameters' => 
        array (
          'recaller' => 
          array (
            'name' => 'recaller',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 215,
            'endLine' => 215,
            'startColumn' => 41,
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
 * Pull a user from the repository by its "remember me" cookie token.
 *
 * @param  \\Illuminate\\Auth\\Recaller  $recaller
 * @return mixed
 */',
        'startLine' => 215,
        'endLine' => 245,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'recaller' => 
      array (
        'name' => 'recaller',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the decrypted recaller cookie for the request.
 *
 * @return \\Illuminate\\Auth\\Recaller|null
 */',
        'startLine' => 252,
        'endLine' => 261,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'id' => 
      array (
        'name' => 'id',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the ID for the currently authenticated user.
 *
 * @return int|string|null
 */',
        'startLine' => 268,
        'endLine' => 277,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'once' => 
      array (
        'name' => 'once',
        'parameters' => 
        array (
          'credentials' => 
          array (
            'name' => 'credentials',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 285,
                'endLine' => 285,
                'startTokenPos' => 990,
                'startFilePos' => 7953,
                'endTokenPos' => 991,
                'endFilePos' => 7954,
              ),
            ),
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
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 285,
            'endLine' => 285,
            'startColumn' => 26,
            'endColumn' => 71,
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
 * Log a user into the application without sessions or cookies.
 *
 * @param  array  $credentials
 * @return bool
 */',
        'startLine' => 285,
        'endLine' => 300,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'onceUsingId' => 
      array (
        'name' => 'onceUsingId',
        'parameters' => 
        array (
          'id' => 
          array (
            'name' => 'id',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 308,
            'endLine' => 308,
            'startColumn' => 33,
            'endColumn' => 35,
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
 * Log the given user ID into the application without sessions or cookies.
 *
 * @param  mixed  $id
 * @return \\Illuminate\\Contracts\\Auth\\Authenticatable|false
 */',
        'startLine' => 308,
        'endLine' => 317,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'validate' => 
      array (
        'name' => 'validate',
        'parameters' => 
        array (
          'credentials' => 
          array (
            'name' => 'credentials',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 325,
                'endLine' => 325,
                'startTokenPos' => 1144,
                'startFilePos' => 8918,
                'endTokenPos' => 1145,
                'endFilePos' => 8919,
              ),
            ),
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
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 325,
            'endLine' => 325,
            'startColumn' => 30,
            'endColumn' => 75,
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
 * Validate a user\'s credentials.
 *
 * @param  array  $credentials
 * @return bool
 */',
        'startLine' => 325,
        'endLine' => 338,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'basic' => 
      array (
        'name' => 'basic',
        'parameters' => 
        array (
          'field' => 
          array (
            'name' => 'field',
            'default' => 
            array (
              'code' => '\'email\'',
              'attributes' => 
              array (
                'startLine' => 349,
                'endLine' => 349,
                'startTokenPos' => 1252,
                'startFilePos' => 9670,
                'endTokenPos' => 1252,
                'endFilePos' => 9676,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 349,
            'endLine' => 349,
            'startColumn' => 27,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'extraConditions' => 
          array (
            'name' => 'extraConditions',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 349,
                'endLine' => 349,
                'startTokenPos' => 1259,
                'startFilePos' => 9698,
                'endTokenPos' => 1260,
                'endFilePos' => 9699,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 349,
            'endLine' => 349,
            'startColumn' => 45,
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
 * Attempt to authenticate using HTTP Basic Auth.
 *
 * @param  string  $field
 * @param  array  $extraConditions
 * @return \\Symfony\\Component\\HttpFoundation\\Response|null
 *
 * @throws \\Symfony\\Component\\HttpKernel\\Exception\\UnauthorizedHttpException
 */',
        'startLine' => 349,
        'endLine' => 363,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'onceBasic' => 
      array (
        'name' => 'onceBasic',
        'parameters' => 
        array (
          'field' => 
          array (
            'name' => 'field',
            'default' => 
            array (
              'code' => '\'email\'',
              'attributes' => 
              array (
                'startLine' => 374,
                'endLine' => 374,
                'startTokenPos' => 1339,
                'startFilePos' => 10519,
                'endTokenPos' => 1339,
                'endFilePos' => 10525,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 374,
            'endLine' => 374,
            'startColumn' => 31,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'extraConditions' => 
          array (
            'name' => 'extraConditions',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 374,
                'endLine' => 374,
                'startTokenPos' => 1346,
                'startFilePos' => 10547,
                'endTokenPos' => 1347,
                'endFilePos' => 10548,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 374,
            'endLine' => 374,
            'startColumn' => 49,
            'endColumn' => 69,
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
 * Perform a stateless HTTP Basic login attempt.
 *
 * @param  string  $field
 * @param  array  $extraConditions
 * @return \\Symfony\\Component\\HttpFoundation\\Response|null
 *
 * @throws \\Symfony\\Component\\HttpKernel\\Exception\\UnauthorizedHttpException
 */',
        'startLine' => 374,
        'endLine' => 381,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'attemptBasic' => 
      array (
        'name' => 'attemptBasic',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Symfony\\Component\\HttpFoundation\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 391,
            'endLine' => 391,
            'startColumn' => 37,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'field' => 
          array (
            'name' => 'field',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 391,
            'endLine' => 391,
            'startColumn' => 55,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'extraConditions' => 
          array (
            'name' => 'extraConditions',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 391,
                'endLine' => 391,
                'startTokenPos' => 1425,
                'startFilePos' => 11094,
                'endTokenPos' => 1426,
                'endFilePos' => 11095,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 391,
            'endLine' => 391,
            'startColumn' => 63,
            'endColumn' => 83,
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
 * Attempt to authenticate using basic authentication.
 *
 * @param  \\Symfony\\Component\\HttpFoundation\\Request  $request
 * @param  string  $field
 * @param  array  $extraConditions
 * @return bool
 */',
        'startLine' => 391,
        'endLine' => 400,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'basicCredentials' => 
      array (
        'name' => 'basicCredentials',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Symfony\\Component\\HttpFoundation\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 409,
            'endLine' => 409,
            'startColumn' => 41,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'field' => 
          array (
            'name' => 'field',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 409,
            'endLine' => 409,
            'startColumn' => 59,
            'endColumn' => 64,
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
 * Get the credential array for an HTTP Basic request.
 *
 * @param  \\Symfony\\Component\\HttpFoundation\\Request  $request
 * @param  string  $field
 * @return array
 */',
        'startLine' => 409,
        'endLine' => 412,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'failedBasicResponse' => 
      array (
        'name' => 'failedBasicResponse',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the response for basic authentication.
 *
 * @return void
 *
 * @throws \\Symfony\\Component\\HttpKernel\\Exception\\UnauthorizedHttpException
 */',
        'startLine' => 421,
        'endLine' => 424,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'attempt' => 
      array (
        'name' => 'attempt',
        'parameters' => 
        array (
          'credentials' => 
          array (
            'name' => 'credentials',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 433,
                'endLine' => 433,
                'startTokenPos' => 1571,
                'startFilePos' => 12243,
                'endTokenPos' => 1572,
                'endFilePos' => 12244,
              ),
            ),
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
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 433,
            'endLine' => 433,
            'startColumn' => 29,
            'endColumn' => 74,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'remember' => 
          array (
            'name' => 'remember',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 433,
                'endLine' => 433,
                'startTokenPos' => 1579,
                'startFilePos' => 12259,
                'endTokenPos' => 1579,
                'endFilePos' => 12263,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 433,
            'endLine' => 433,
            'startColumn' => 77,
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
 * Attempt to authenticate a user using the given credentials.
 *
 * @param  array  $credentials
 * @param  bool  $remember
 * @return bool
 */',
        'startLine' => 433,
        'endLine' => 460,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'attemptWhen' => 
      array (
        'name' => 'attemptWhen',
        'parameters' => 
        array (
          'credentials' => 
          array (
            'name' => 'credentials',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 470,
                'endLine' => 470,
                'startTokenPos' => 1749,
                'startFilePos' => 13756,
                'endTokenPos' => 1750,
                'endFilePos' => 13757,
              ),
            ),
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
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 470,
            'endLine' => 470,
            'startColumn' => 33,
            'endColumn' => 78,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'callbacks' => 
          array (
            'name' => 'callbacks',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 470,
                'endLine' => 470,
                'startTokenPos' => 1757,
                'startFilePos' => 13773,
                'endTokenPos' => 1757,
                'endFilePos' => 13776,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 470,
            'endLine' => 470,
            'startColumn' => 81,
            'endColumn' => 97,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'remember' => 
          array (
            'name' => 'remember',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 470,
                'endLine' => 470,
                'startTokenPos' => 1764,
                'startFilePos' => 13791,
                'endTokenPos' => 1764,
                'endFilePos' => 13795,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 470,
            'endLine' => 470,
            'startColumn' => 100,
            'endColumn' => 116,
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
 * Attempt to authenticate a user with credentials and additional callbacks.
 *
 * @param  array  $credentials
 * @param  array|callable|null  $callbacks
 * @param  bool  $remember
 * @return bool
 */',
        'startLine' => 470,
        'endLine' => 494,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'hasValidCredentials' => 
      array (
        'name' => 'hasValidCredentials',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 503,
            'endLine' => 503,
            'startColumn' => 44,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'credentials' => 
          array (
            'name' => 'credentials',
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
            'startLine' => 503,
            'endLine' => 503,
            'startColumn' => 51,
            'endColumn' => 85,
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
 * Determine if the user matches the credentials.
 *
 * @param  mixed  $user
 * @param  array  $credentials
 * @return bool
 */',
        'startLine' => 503,
        'endLine' => 512,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'shouldLogin' => 
      array (
        'name' => 'shouldLogin',
        'parameters' => 
        array (
          'callbacks' => 
          array (
            'name' => 'callbacks',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 521,
            'endLine' => 521,
            'startColumn' => 36,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Auth\\Authenticatable',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 521,
            'endLine' => 521,
            'startColumn' => 48,
            'endColumn' => 76,
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
 * Determine if the user should login by executing the given callbacks.
 *
 * @param  array|callable|null  $callbacks
 * @param  \\Illuminate\\Contracts\\Auth\\Authenticatable  $user
 * @return bool
 */',
        'startLine' => 521,
        'endLine' => 530,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'rehashPasswordIfRequired' => 
      array (
        'name' => 'rehashPasswordIfRequired',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Auth\\Authenticatable',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 539,
            'endLine' => 539,
            'startColumn' => 49,
            'endColumn' => 77,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'credentials' => 
          array (
            'name' => 'credentials',
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
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 539,
            'endLine' => 539,
            'startColumn' => 80,
            'endColumn' => 120,
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
 * Rehash the user\'s password if enabled and required.
 *
 * @param  \\Illuminate\\Contracts\\Auth\\Authenticatable  $user
 * @param  array  $credentials
 * @return void
 */',
        'startLine' => 539,
        'endLine' => 544,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'loginUsingId' => 
      array (
        'name' => 'loginUsingId',
        'parameters' => 
        array (
          'id' => 
          array (
            'name' => 'id',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 553,
            'endLine' => 553,
            'startColumn' => 34,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'remember' => 
          array (
            'name' => 'remember',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 553,
                'endLine' => 553,
                'startTokenPos' => 2129,
                'startFilePos' => 16468,
                'endTokenPos' => 2129,
                'endFilePos' => 16472,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 553,
            'endLine' => 553,
            'startColumn' => 39,
            'endColumn' => 55,
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
 * Log the given user ID into the application.
 *
 * @param  mixed  $id
 * @param  bool  $remember
 * @return \\Illuminate\\Contracts\\Auth\\Authenticatable|false
 */',
        'startLine' => 553,
        'endLine' => 562,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'login' => 
      array (
        'name' => 'login',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Auth\\Authenticatable',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 571,
            'endLine' => 571,
            'startColumn' => 27,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'remember' => 
          array (
            'name' => 'remember',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 571,
                'endLine' => 571,
                'startTokenPos' => 2200,
                'startFilePos' => 16909,
                'endTokenPos' => 2200,
                'endFilePos' => 16913,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 571,
            'endLine' => 571,
            'startColumn' => 58,
            'endColumn' => 74,
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
 * Log a user into the application.
 *
 * @param  \\Illuminate\\Contracts\\Auth\\Authenticatable  $user
 * @param  bool  $remember
 * @return void
 */',
        'startLine' => 571,
        'endLine' => 590,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'updateSession' => 
      array (
        'name' => 'updateSession',
        'parameters' => 
        array (
          'id' => 
          array (
            'name' => 'id',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 598,
            'endLine' => 598,
            'startColumn' => 38,
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
 * Update the session with the given ID and regenerate the session\'s token.
 *
 * @param  string  $id
 * @return void
 */',
        'startLine' => 598,
        'endLine' => 603,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'ensureRememberTokenIsSet' => 
      array (
        'name' => 'ensureRememberTokenIsSet',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Auth\\Authenticatable',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 611,
            'endLine' => 611,
            'startColumn' => 49,
            'endColumn' => 77,
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
 * Create a new "remember me" token for the user if one doesn\'t already exist.
 *
 * @param  \\Illuminate\\Contracts\\Auth\\Authenticatable  $user
 * @return void
 */',
        'startLine' => 611,
        'endLine' => 616,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'queueRecallerCookie' => 
      array (
        'name' => 'queueRecallerCookie',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Auth\\Authenticatable',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 624,
            'endLine' => 624,
            'startColumn' => 44,
            'endColumn' => 72,
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
 * Queue the recaller cookie into the cookie jar.
 *
 * @param  \\Illuminate\\Contracts\\Auth\\Authenticatable  $user
 * @return void
 */',
        'startLine' => 624,
        'endLine' => 631,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'createRecaller' => 
      array (
        'name' => 'createRecaller',
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
            'startLine' => 639,
            'endLine' => 639,
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
 * Create a "remember me" cookie for a given ID.
 *
 * @param  string  $value
 * @return \\Symfony\\Component\\HttpFoundation\\Cookie
 */',
        'startLine' => 639,
        'endLine' => 642,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'hashPasswordForCookie' => 
      array (
        'name' => 'hashPasswordForCookie',
        'parameters' => 
        array (
          'passwordHash' => 
          array (
            'name' => 'passwordHash',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 650,
            'endLine' => 650,
            'startColumn' => 43,
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
 * Create a HMAC of the password hash for storage in cookies.
 *
 * @param  string  $passwordHash
 * @return string
 */',
        'startLine' => 650,
        'endLine' => 657,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'logout' => 
      array (
        'name' => 'logout',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Log the user out of the application.
 *
 * @return void
 */',
        'startLine' => 664,
        'endLine' => 685,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'logoutCurrentDevice' => 
      array (
        'name' => 'logoutCurrentDevice',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Log the user out of the application on their current device only.
 *
 * This method does not cycle the "remember" token.
 *
 * @return void
 */',
        'startLine' => 694,
        'endLine' => 711,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'clearUserDataFromStorage' => 
      array (
        'name' => 'clearUserDataFromStorage',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Remove the user data from the session and cookies.
 *
 * @return void
 */',
        'startLine' => 718,
        'endLine' => 729,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'cycleRememberToken' => 
      array (
        'name' => 'cycleRememberToken',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Auth\\Authenticatable',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 737,
            'endLine' => 737,
            'startColumn' => 43,
            'endColumn' => 71,
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
 * Refresh the "remember me" token for the user.
 *
 * @param  \\Illuminate\\Contracts\\Auth\\Authenticatable  $user
 * @return void
 */',
        'startLine' => 737,
        'endLine' => 742,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'logoutOtherDevices' => 
      array (
        'name' => 'logoutOtherDevices',
        'parameters' => 
        array (
          'password' => 
          array (
            'name' => 'password',
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
            'startLine' => 754,
            'endLine' => 754,
            'startColumn' => 40,
            'endColumn' => 71,
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
 * Invalidate other sessions for the current user.
 *
 * The application must be using the AuthenticateSession middleware.
 *
 * @param  string  $password
 * @return \\Illuminate\\Contracts\\Auth\\Authenticatable|null
 *
 * @throws \\Illuminate\\Auth\\AuthenticationException
 */',
        'startLine' => 754,
        'endLine' => 770,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'rehashUserPasswordForDeviceLogout' => 
      array (
        'name' => 'rehashUserPasswordForDeviceLogout',
        'parameters' => 
        array (
          'password' => 
          array (
            'name' => 'password',
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
            'startLine' => 780,
            'endLine' => 780,
            'startColumn' => 58,
            'endColumn' => 89,
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
 * Rehash the current user\'s password for logging out other devices via AuthenticateSession.
 *
 * @param  string  $password
 * @return \\Illuminate\\Contracts\\Auth\\Authenticatable|null
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 780,
        'endLine' => 791,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'attempting' => 
      array (
        'name' => 'attempting',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 799,
            'endLine' => 799,
            'startColumn' => 32,
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
 * Register an authentication attempt event listener.
 *
 * @param  mixed  $callback
 * @return void
 */',
        'startLine' => 799,
        'endLine' => 802,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'fireAttemptEvent' => 
      array (
        'name' => 'fireAttemptEvent',
        'parameters' => 
        array (
          'credentials' => 
          array (
            'name' => 'credentials',
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
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 811,
            'endLine' => 811,
            'startColumn' => 41,
            'endColumn' => 81,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'remember' => 
          array (
            'name' => 'remember',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 811,
                'endLine' => 811,
                'startTokenPos' => 3094,
                'startFilePos' => 24302,
                'endTokenPos' => 3094,
                'endFilePos' => 24306,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 811,
            'endLine' => 811,
            'startColumn' => 84,
            'endColumn' => 100,
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
 * Fire the attempt event with the arguments.
 *
 * @param  array  $credentials
 * @param  bool  $remember
 * @return void
 */',
        'startLine' => 811,
        'endLine' => 814,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'fireValidatedEvent' => 
      array (
        'name' => 'fireValidatedEvent',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 822,
            'endLine' => 822,
            'startColumn' => 43,
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
 * Fires the validated event if the dispatcher is set.
 *
 * @param  \\Illuminate\\Contracts\\Auth\\Authenticatable  $user
 * @return void
 */',
        'startLine' => 822,
        'endLine' => 825,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'fireLoginEvent' => 
      array (
        'name' => 'fireLoginEvent',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 834,
            'endLine' => 834,
            'startColumn' => 39,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'remember' => 
          array (
            'name' => 'remember',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 834,
                'endLine' => 834,
                'startTokenPos' => 3174,
                'startFilePos' => 24958,
                'endTokenPos' => 3174,
                'endFilePos' => 24962,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 834,
            'endLine' => 834,
            'startColumn' => 46,
            'endColumn' => 62,
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
 * Fire the login event if the dispatcher is set.
 *
 * @param  \\Illuminate\\Contracts\\Auth\\Authenticatable  $user
 * @param  bool  $remember
 * @return void
 */',
        'startLine' => 834,
        'endLine' => 837,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'fireAuthenticatedEvent' => 
      array (
        'name' => 'fireAuthenticatedEvent',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 845,
            'endLine' => 845,
            'startColumn' => 47,
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
 * Fire the authenticated event if the dispatcher is set.
 *
 * @param  \\Illuminate\\Contracts\\Auth\\Authenticatable  $user
 * @return void
 */',
        'startLine' => 845,
        'endLine' => 848,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'fireOtherDeviceLogoutEvent' => 
      array (
        'name' => 'fireOtherDeviceLogoutEvent',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 856,
            'endLine' => 856,
            'startColumn' => 51,
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
 * Fire the other device logout event if the dispatcher is set.
 *
 * @param  \\Illuminate\\Contracts\\Auth\\Authenticatable  $user
 * @return void
 */',
        'startLine' => 856,
        'endLine' => 859,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'fireFailedEvent' => 
      array (
        'name' => 'fireFailedEvent',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 868,
            'endLine' => 868,
            'startColumn' => 40,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'credentials' => 
          array (
            'name' => 'credentials',
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
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 868,
            'endLine' => 868,
            'startColumn' => 47,
            'endColumn' => 87,
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
 * Fire the failed authentication attempt event with the given arguments.
 *
 * @param  \\Illuminate\\Contracts\\Auth\\Authenticatable|null  $user
 * @param  array  $credentials
 * @return void
 */',
        'startLine' => 868,
        'endLine' => 871,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'getLastAttempted' => 
      array (
        'name' => 'getLastAttempted',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the last user we attempted to authenticate.
 *
 * @return \\Illuminate\\Contracts\\Auth\\Authenticatable
 */',
        'startLine' => 878,
        'endLine' => 881,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'getName' => 
      array (
        'name' => 'getName',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a unique identifier for the auth session value.
 *
 * @return string
 */',
        'startLine' => 888,
        'endLine' => 891,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'getRecallerName' => 
      array (
        'name' => 'getRecallerName',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the name of the cookie used to store the "recaller".
 *
 * @return string
 */',
        'startLine' => 898,
        'endLine' => 901,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'viaRemember' => 
      array (
        'name' => 'viaRemember',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the user was authenticated via "remember me" cookie.
 *
 * @return bool
 */',
        'startLine' => 908,
        'endLine' => 911,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'getRememberDuration' => 
      array (
        'name' => 'getRememberDuration',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the number of minutes the remember me cookie should be valid for.
 *
 * @return int
 */',
        'startLine' => 918,
        'endLine' => 921,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'setRememberDuration' => 
      array (
        'name' => 'setRememberDuration',
        'parameters' => 
        array (
          'minutes' => 
          array (
            'name' => 'minutes',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 929,
            'endLine' => 929,
            'startColumn' => 41,
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
 * Set the number of minutes the remember me cookie should be valid for.
 *
 * @param  int  $minutes
 * @return $this
 */',
        'startLine' => 929,
        'endLine' => 934,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'getCookieJar' => 
      array (
        'name' => 'getCookieJar',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the cookie creator instance used by the guard.
 *
 * @return \\Illuminate\\Contracts\\Cookie\\QueueingFactory
 *
 * @throws \\RuntimeException
 */',
        'startLine' => 943,
        'endLine' => 950,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'setCookieJar' => 
      array (
        'name' => 'setCookieJar',
        'parameters' => 
        array (
          'cookie' => 
          array (
            'name' => 'cookie',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Cookie\\QueueingFactory',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 958,
            'endLine' => 958,
            'startColumn' => 34,
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
 * Set the cookie creator instance used by the guard.
 *
 * @param  \\Illuminate\\Contracts\\Cookie\\QueueingFactory  $cookie
 * @return void
 */',
        'startLine' => 958,
        'endLine' => 961,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'getDispatcher' => 
      array (
        'name' => 'getDispatcher',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the event dispatcher instance.
 *
 * @return \\Illuminate\\Contracts\\Events\\Dispatcher
 */',
        'startLine' => 968,
        'endLine' => 971,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'setDispatcher' => 
      array (
        'name' => 'setDispatcher',
        'parameters' => 
        array (
          'events' => 
          array (
            'name' => 'events',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Events\\Dispatcher',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 979,
            'endLine' => 979,
            'startColumn' => 35,
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
 * Set the event dispatcher instance.
 *
 * @param  \\Illuminate\\Contracts\\Events\\Dispatcher  $events
 * @return void
 */',
        'startLine' => 979,
        'endLine' => 982,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'getSession' => 
      array (
        'name' => 'getSession',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the session store used by the guard.
 *
 * @return \\Illuminate\\Contracts\\Session\\Session
 */',
        'startLine' => 989,
        'endLine' => 992,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'getUser' => 
      array (
        'name' => 'getUser',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the currently cached user.
 *
 * @return \\Illuminate\\Contracts\\Auth\\Authenticatable|null
 */',
        'startLine' => 999,
        'endLine' => 1002,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'setUser' => 
      array (
        'name' => 'setUser',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Auth\\Authenticatable',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1010,
            'endLine' => 1010,
            'startColumn' => 29,
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
 * Set the current user.
 *
 * @param  \\Illuminate\\Contracts\\Auth\\Authenticatable  $user
 * @return $this
 */',
        'startLine' => 1010,
        'endLine' => 1019,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'getRequest' => 
      array (
        'name' => 'getRequest',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the current request instance.
 *
 * @return \\Symfony\\Component\\HttpFoundation\\Request
 */',
        'startLine' => 1026,
        'endLine' => 1029,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'setRequest' => 
      array (
        'name' => 'setRequest',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Symfony\\Component\\HttpFoundation\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1037,
            'endLine' => 1037,
            'startColumn' => 32,
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
 * Set the current request instance.
 *
 * @param  \\Symfony\\Component\\HttpFoundation\\Request  $request
 * @return $this
 */',
        'startLine' => 1037,
        'endLine' => 1042,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
        'aliasName' => NULL,
      ),
      'getTimebox' => 
      array (
        'name' => 'getTimebox',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the timebox instance used by the guard.
 *
 * @return \\Illuminate\\Support\\Timebox
 */',
        'startLine' => 1049,
        'endLine' => 1052,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Auth',
        'declaringClassName' => 'Illuminate\\Auth\\SessionGuard',
        'implementingClassName' => 'Illuminate\\Auth\\SessionGuard',
        'currentClassName' => 'Illuminate\\Auth\\SessionGuard',
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