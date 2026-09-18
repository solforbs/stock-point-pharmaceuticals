<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\database\seeders\DiscountAuthoritySeeder.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Database\Seeders\DiscountAuthoritySeeder
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-6c4eea98c56ee725764a91d8b877943082f11eb743cab7fafd943bda1c706133',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Database\\Seeders\\DiscountAuthoritySeeder',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/database/seeders/DiscountAuthoritySeeder.php',
      ),
    ),
    'namespace' => 'Database\\Seeders',
    'name' => 'Database\\Seeders\\DiscountAuthoritySeeder',
    'shortName' => 'DiscountAuthoritySeeder',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 9,
    'endLine' => 37,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Database\\Seeder',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'AUTHORITY' => 
      array (
        'declaringClassName' => 'Database\\Seeders\\DiscountAuthoritySeeder',
        'implementingClassName' => 'Database\\Seeders\\DiscountAuthoritySeeder',
        'name' => 'AUTHORITY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[
    //  role                   line %   header %  may override floor
    \'Cashier\' => [\'2.000\', \'0.000\', false],
    \'Senior Cashier\' => [\'5.000\', \'2.000\', false],
    \'Wholesale Rep\' => [\'8.000\', \'5.000\', false],
    \'Operations Manager\' => [\'15.000\', \'10.000\', true],
    \'Director\' => [\'100.000\', \'100.000\', true],
]',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 23,
            'startTokenPos' => 42,
            'startFilePos' => 443,
            'endTokenPos' => 121,
            'endFilePos' => 791,
          ),
        ),
        'docComment' => '/**
 * Part 4.5 — the discount authority matrix. A business policy decision
 * for the owner (Part 32 step 3); these are the blueprint\'s starting
 * values. Roles not listed may not discount at all.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'run' => 
      array (
        'name' => 'run',
        'parameters' => 
        array (
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
        'startLine' => 25,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Database\\Seeders',
        'declaringClassName' => 'Database\\Seeders\\DiscountAuthoritySeeder',
        'implementingClassName' => 'Database\\Seeders\\DiscountAuthoritySeeder',
        'currentClassName' => 'Database\\Seeders\\DiscountAuthoritySeeder',
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