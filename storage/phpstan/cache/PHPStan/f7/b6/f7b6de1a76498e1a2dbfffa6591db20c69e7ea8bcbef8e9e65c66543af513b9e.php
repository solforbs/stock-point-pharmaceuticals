<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\app\Services\Sales\SaleModes.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Sales\SaleModes
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-98e69f6275150ec6c1912660c7d3af1f1c847b0cb65d1bd173f6fcfd233652b9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Sales\\SaleModes',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Services/Sales/SaleModes.php',
      ),
    ),
    'namespace' => 'App\\Services\\Sales',
    'name' => 'App\\Services\\Sales\\SaleModes',
    'shortName' => 'SaleModes',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * V6 Part 10.1 — retail, wholesale and dispensing are configuration, not
 * separate systems. A branch enables the modes it trades in; one of them is
 * the mode a terminal opens in, and moving off it is a permission-gated,
 * audited switch (Part 24.2).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 14,
    'endLine' => 59,
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
      'RETAIL' => 
      array (
        'declaringClassName' => 'App\\Services\\Sales\\SaleModes',
        'implementingClassName' => 'App\\Services\\Sales\\SaleModes',
        'name' => 'RETAIL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'RETAIL\'',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 33,
            'startFilePos' => 396,
            'endTokenPos' => 33,
            'endFilePos' => 403,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'WHOLESALE' => 
      array (
        'declaringClassName' => 'App\\Services\\Sales\\SaleModes',
        'implementingClassName' => 'App\\Services\\Sales\\SaleModes',
        'name' => 'WHOLESALE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'WHOLESALE\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 44,
            'startFilePos' => 436,
            'endTokenPos' => 44,
            'endFilePos' => 446,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 41,
      ),
      'DISPENSING' => 
      array (
        'declaringClassName' => 'App\\Services\\Sales\\SaleModes',
        'implementingClassName' => 'App\\Services\\Sales\\SaleModes',
        'name' => 'DISPENSING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'DISPENSING\'',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 55,
            'startFilePos' => 480,
            'endTokenPos' => 55,
            'endFilePos' => 491,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 43,
      ),
      'ALL' => 
      array (
        'declaringClassName' => 'App\\Services\\Sales\\SaleModes',
        'implementingClassName' => 'App\\Services\\Sales\\SaleModes',
        'name' => 'ALL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[self::RETAIL, self::WHOLESALE, self::DISPENSING]',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 66,
            'startFilePos' => 518,
            'endTokenPos' => 80,
            'endFilePos' => 566,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 73,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'enabledFor' => 
      array (
        'name' => 'enabledFor',
        'parameters' => 
        array (
          'branch' => 
          array (
            'name' => 'branch',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\Branch',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 27,
            'endLine' => 27,
            'startColumn' => 39,
            'endColumn' => 52,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return list<string>
 */',
        'startLine' => 27,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Services\\Sales',
        'declaringClassName' => 'App\\Services\\Sales\\SaleModes',
        'implementingClassName' => 'App\\Services\\Sales\\SaleModes',
        'currentClassName' => 'App\\Services\\Sales\\SaleModes',
        'aliasName' => NULL,
      ),
      'defaultFor' => 
      array (
        'name' => 'defaultFor',
        'parameters' => 
        array (
          'branch' => 
          array (
            'name' => 'branch',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\Branch',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 48,
            'endLine' => 48,
            'startColumn' => 39,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
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
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * The mode the POS opens in: a per-branch setting when the owner has
 * chosen one, otherwise the first enabled mode in retail → wholesale →
 * dispensing order. Null when the branch trades in nothing.
 */',
        'startLine' => 48,
        'endLine' => 58,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Services\\Sales',
        'declaringClassName' => 'App\\Services\\Sales\\SaleModes',
        'implementingClassName' => 'App\\Services\\Sales\\SaleModes',
        'currentClassName' => 'App\\Services\\Sales\\SaleModes',
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