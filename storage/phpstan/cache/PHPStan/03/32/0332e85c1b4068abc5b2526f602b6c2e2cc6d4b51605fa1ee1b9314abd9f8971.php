<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\app\Services\Procurement\ThreeWayMatchService.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Procurement\ThreeWayMatchService
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-da773fa81defc973615018c662e901a206c0b188e23d49966a40f7b1fb8c0752',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Services/Procurement/ThreeWayMatchService.php',
      ),
    ),
    'namespace' => 'App\\Services\\Procurement',
    'name' => 'App\\Services\\Procurement\\ThreeWayMatchService',
    'shortName' => 'ThreeWayMatchService',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Part 9.4 — compares PO <-> GRN <-> Supplier Invoice on quantity and price,
 * per line. Only a matched invoice creates an accounts_payable entry and
 * posts the Part 12.3 "Supplier invoice matched" journal (Dr GRN accrual /
 * Dr VAT input / Cr Accounts payable); everything else sits in an
 * exception queue. Tolerances follow the Part 9.3 defaults.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 30,
    'endLine' => 150,
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
      'DEFAULT_PRICE_VARIANCE_PCT' => 
      array (
        'declaringClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'implementingClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'name' => 'DEFAULT_PRICE_VARIANCE_PCT',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'2.0\'',
          'attributes' => 
          array (
            'startLine' => 32,
            'endLine' => 32,
            'startTokenPos' => 95,
            'startFilePos' => 880,
            'endTokenPos' => 95,
            'endFilePos' => 884,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 53,
      ),
      'DEFAULT_PRICE_VARIANCE_ABS' => 
      array (
        'declaringClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'implementingClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'name' => 'DEFAULT_PRICE_VARIANCE_ABS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'500.0000\'',
          'attributes' => 
          array (
            'startLine' => 34,
            'endLine' => 34,
            'startTokenPos' => 106,
            'startFilePos' => 935,
            'endTokenPos' => 106,
            'endFilePos' => 944,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 58,
      ),
    ),
    'immediateProperties' => 
    array (
      'journalPoster' => 
      array (
        'declaringClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'implementingClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'name' => 'journalPoster',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Finance\\JournalPoster',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 33,
        'endColumn' => 77,
        'isPromoted' => true,
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
          'journalPoster' => 
          array (
            'name' => 'journalPoster',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Finance\\JournalPoster',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 36,
            'endLine' => 36,
            'startColumn' => 33,
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
        'docComment' => NULL,
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 81,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Procurement',
        'declaringClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'implementingClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'currentClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'aliasName' => NULL,
      ),
      'match' => 
      array (
        'name' => 'match',
        'parameters' => 
        array (
          'invoice' => 
          array (
            'name' => 'invoice',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\SupplierInvoice',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 38,
            'endLine' => 38,
            'startColumn' => 27,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'matchedBy' => 
          array (
            'name' => 'matchedBy',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 38,
                'endLine' => 38,
                'startTokenPos' => 145,
                'startFilePos' => 1101,
                'endTokenPos' => 145,
                'endFilePos' => 1104,
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
                      'name' => 'int',
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
            'startLine' => 38,
            'endLine' => 38,
            'startColumn' => 53,
            'endColumn' => 74,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Procurement\\MatchResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 38,
        'endLine' => 99,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Procurement',
        'declaringClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'implementingClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'currentClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'aliasName' => NULL,
      ),
      'postPayable' => 
      array (
        'name' => 'postPayable',
        'parameters' => 
        array (
          'invoice' => 
          array (
            'name' => 'invoice',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\SupplierInvoice',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 101,
            'endLine' => 101,
            'startColumn' => 34,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'matchedBy' => 
          array (
            'name' => 'matchedBy',
            'default' => NULL,
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
                      'name' => 'int',
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
            'startLine' => 101,
            'endLine' => 101,
            'startColumn' => 60,
            'endColumn' => 74,
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
        'docComment' => NULL,
        'startLine' => 101,
        'endLine' => 144,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Procurement',
        'declaringClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'implementingClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'currentClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'aliasName' => NULL,
      ),
      'fallbackUserId' => 
      array (
        'name' => 'fallbackUserId',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 146,
        'endLine' => 149,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Procurement',
        'declaringClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'implementingClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
        'currentClassName' => 'App\\Services\\Procurement\\ThreeWayMatchService',
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