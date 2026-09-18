<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\app\Services\Tax\Etims\LogDriver.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Tax\Etims\LogDriver
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-cd816b15ea71b5418565e21f19b686b7ca0cf24391570c4523ddcba878c00a6d',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Tax\\Etims\\LogDriver',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Services/Tax/Etims/LogDriver.php',
      ),
    ),
    'namespace' => 'App\\Services\\Tax\\Etims',
    'name' => 'App\\Services\\Tax\\Etims\\LogDriver',
    'shortName' => 'LogDriver',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Records every submission in the application log and issues a placeholder
 * control code, so the whole queue/retry/print path can be exercised before
 * KRA onboarding is complete. Control codes are prefixed LOG- so a printed
 * invoice can never be mistaken for a fiscalised one.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 14,
    'endLine' => 29,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'App\\Services\\Tax\\Etims\\EtimsDriver',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'submitInvoice' => 
      array (
        'name' => 'submitInvoice',
        'parameters' => 
        array (
          'payload' => 
          array (
            'name' => 'payload',
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
            'startLine' => 16,
            'endLine' => 16,
            'startColumn' => 35,
            'endColumn' => 48,
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
            'name' => 'App\\Services\\Tax\\Etims\\EtimsResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 16,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Tax\\Etims',
        'declaringClassName' => 'App\\Services\\Tax\\Etims\\LogDriver',
        'implementingClassName' => 'App\\Services\\Tax\\Etims\\LogDriver',
        'currentClassName' => 'App\\Services\\Tax\\Etims\\LogDriver',
        'aliasName' => NULL,
      ),
      'submitCreditNote' => 
      array (
        'name' => 'submitCreditNote',
        'parameters' => 
        array (
          'payload' => 
          array (
            'name' => 'payload',
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
            'startLine' => 23,
            'endLine' => 23,
            'startColumn' => 38,
            'endColumn' => 51,
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
            'name' => 'App\\Services\\Tax\\Etims\\EtimsResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 23,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Tax\\Etims',
        'declaringClassName' => 'App\\Services\\Tax\\Etims\\LogDriver',
        'implementingClassName' => 'App\\Services\\Tax\\Etims\\LogDriver',
        'currentClassName' => 'App\\Services\\Tax\\Etims\\LogDriver',
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