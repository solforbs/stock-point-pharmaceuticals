<?php declare(strict_types = 1);

// odsl-C:/xampp/htdocs/pharmacy_erp/vendor/composer/../laravel/framework/src/Illuminate/Foundation/helpers.php-PHPStan\BetterReflection\Reflection\ReflectionFunction-validator
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-84a94be4f68fd0f749a87f05a7cb1b78ddc07c7d2121b56c6f05ff6ef80d34c6',
   'data' => 
  array (
    'name' => 'validator',
    'parameters' => 
    array (
      'data' => 
      array (
        'name' => 'data',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 1072,
            'endLine' => 1072,
            'startTokenPos' => 4847,
            'startFilePos' => 28862,
            'endTokenPos' => 4847,
            'endFilePos' => 28865,
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
        'startLine' => 1072,
        'endLine' => 1072,
        'startColumn' => 24,
        'endColumn' => 42,
        'parameterIndex' => 0,
        'isOptional' => true,
      ),
      'rules' => 
      array (
        'name' => 'rules',
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 1072,
            'endLine' => 1072,
            'startTokenPos' => 4856,
            'startFilePos' => 28883,
            'endTokenPos' => 4857,
            'endFilePos' => 28884,
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
        ),
        'startLine' => 1072,
        'endLine' => 1072,
        'startColumn' => 45,
        'endColumn' => 61,
        'parameterIndex' => 1,
        'isOptional' => true,
      ),
      'messages' => 
      array (
        'name' => 'messages',
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 1072,
            'endLine' => 1072,
            'startTokenPos' => 4866,
            'startFilePos' => 28905,
            'endTokenPos' => 4867,
            'endFilePos' => 28906,
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
        ),
        'startLine' => 1072,
        'endLine' => 1072,
        'startColumn' => 64,
        'endColumn' => 83,
        'parameterIndex' => 2,
        'isOptional' => true,
      ),
      'attributes' => 
      array (
        'name' => 'attributes',
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 1072,
            'endLine' => 1072,
            'startTokenPos' => 4876,
            'startFilePos' => 28929,
            'endTokenPos' => 4877,
            'endFilePos' => 28930,
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
        ),
        'startLine' => 1072,
        'endLine' => 1072,
        'startColumn' => 86,
        'endColumn' => 107,
        'parameterIndex' => 3,
        'isOptional' => true,
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
              'name' => 'Illuminate\\Contracts\\Validation\\Validator',
              'isIdentifier' => false,
            ),
          ),
          1 => 
          array (
            'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
            'data' => 
            array (
              'name' => 'Illuminate\\Contracts\\Validation\\Factory',
              'isIdentifier' => false,
            ),
          ),
        ),
      ),
    ),
    'attributes' => 
    array (
    ),
    'docComment' => '/**
 * Create a new Validator instance.
 *
 * @return ($data is null ? \\Illuminate\\Contracts\\Validation\\Factory : \\Illuminate\\Contracts\\Validation\\Validator)
 */',
    'startLine' => 1072,
    'endLine' => 1081,
    'startColumn' => 5,
    'endColumn' => 5,
    'couldThrow' => false,
    'isClosure' => false,
    'isGenerator' => false,
    'isVariadic' => true,
    'isStatic' => false,
    'namespace' => NULL,
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'validator',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/vendor/composer/../laravel/framework/src/Illuminate/Foundation/helpers.php',
      ),
    ),
  ),
));