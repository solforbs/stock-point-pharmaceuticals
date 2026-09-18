<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\database\seeders\ProductTaxDefaultSeeder.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Database\Seeders\ProductTaxDefaultSeeder
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-c13273fa2586a7fb83ad14277a13d96f7005db9fbc7597b0e26b582b0df910d1',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Database\\Seeders\\ProductTaxDefaultSeeder',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/database/seeders/ProductTaxDefaultSeeder.php',
      ),
    ),
    'namespace' => 'Database\\Seeders',
    'name' => 'Database\\Seeders\\ProductTaxDefaultSeeder',
    'shortName' => 'ProductTaxDefaultSeeder',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Go-live default chosen by the business on 2026-09-18: every product that
 * has no VAT treatment yet is standard-rated (16%) until it is reviewed
 * line by line. Standard-rating by default never under-declares output VAT;
 * exempt and zero-rated lines are corrected afterwards through the product
 * form. Products that already carry a tax code are never touched, so the
 * seeder is safe to re-run after that review has started.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 33,
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
      'DEFAULT_CODE' => 
      array (
        'declaringClassName' => 'Database\\Seeders\\ProductTaxDefaultSeeder',
        'implementingClassName' => 'Database\\Seeders\\ProductTaxDefaultSeeder',
        'name' => 'DEFAULT_CODE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'VAT_STD\'',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 47,
            'startFilePos' => 668,
            'endTokenPos' => 47,
            'endFilePos' => 676,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 42,
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
        'startLine' => 22,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Database\\Seeders',
        'declaringClassName' => 'Database\\Seeders\\ProductTaxDefaultSeeder',
        'implementingClassName' => 'Database\\Seeders\\ProductTaxDefaultSeeder',
        'currentClassName' => 'Database\\Seeders\\ProductTaxDefaultSeeder',
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