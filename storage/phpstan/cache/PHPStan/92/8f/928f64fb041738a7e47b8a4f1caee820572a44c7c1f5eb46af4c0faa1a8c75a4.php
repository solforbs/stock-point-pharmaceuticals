<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\app\Console\Commands\ImportOpeningStock.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Console\Commands\ImportOpeningStock
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-41d7bcdf33050215041dda2f7521d634910e9b3e2488b8dfb771856ca45c9471',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Console\\Commands\\ImportOpeningStock',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Console/Commands/ImportOpeningStock.php',
      ),
    ),
    'namespace' => 'App\\Console\\Commands',
    'name' => 'App\\Console\\Commands\\ImportOpeningStock',
    'shortName' => 'ImportOpeningStock',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Loads the go-live stock take from a CSV with the header
 * product_code,batch_number,expiry_date,qty,unit_cost[,manufacture_date]
 * (qty and unit_cost per product base unit). All-or-nothing: any bad row
 * is reported and nothing is posted.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 104,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Console\\Command',
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
      'signature' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\ImportOpeningStock',
        'implementingClassName' => 'App\\Console\\Commands\\ImportOpeningStock',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'inventory:import-opening-stock
        {file : Path to the CSV file}
        {--branch=LDW : Branch code}
        {--store=MAIN : Store code within the branch}
        {--user= : Email of the user recorded as having posted it}
        {--dry-run : Validate only; post nothing}\'',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 25,
            'startTokenPos' => 55,
            'startFilePos' => 570,
            'endTokenPos' => 55,
            'endFilePos' => 847,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 51,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\ImportOpeningStock',
        'implementingClassName' => 'App\\Console\\Commands\\ImportOpeningStock',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Import opening stock (batches, expiry, quantity, cost) from a CSV into a store\'',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 64,
            'startFilePos' => 880,
            'endTokenPos' => 64,
            'endFilePos' => 959,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 110,
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
      'handle' => 
      array (
        'name' => 'handle',
        'parameters' => 
        array (
          'openingStock' => 
          array (
            'name' => 'openingStock',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Inventory\\OpeningStockService',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 28,
            'endColumn' => 60,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 29,
        'endLine' => 76,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\ImportOpeningStock',
        'implementingClassName' => 'App\\Console\\Commands\\ImportOpeningStock',
        'currentClassName' => 'App\\Console\\Commands\\ImportOpeningStock',
        'aliasName' => NULL,
      ),
      'readCsv' => 
      array (
        'name' => 'readCsv',
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
            'startLine' => 81,
            'endLine' => 81,
            'startColumn' => 36,
            'endColumn' => 47,
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
 * @return list<array<string, string>>
 */',
        'startLine' => 81,
        'endLine' => 103,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\ImportOpeningStock',
        'implementingClassName' => 'App\\Console\\Commands\\ImportOpeningStock',
        'currentClassName' => 'App\\Console\\Commands\\ImportOpeningStock',
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