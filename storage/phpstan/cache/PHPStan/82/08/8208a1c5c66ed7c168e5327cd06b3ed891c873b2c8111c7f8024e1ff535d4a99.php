<?php declare(strict_types = 1);

// osfsl-C:\xampp\htdocs\pharmacy_erp\app\Services\Inventory\ProductImportValidationException.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Inventory\ProductImportValidationException
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-771bf7c2b9919d317998110e2f350d34b43344a38085aadcc3bbafdc343d32a6-8.3.31-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Inventory\\ProductImportValidationException',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Services/Inventory/ProductImportValidationException.php',
      ),
    ),
    'namespace' => 'App\\Services\\Inventory',
    'name' => 'App\\Services\\Inventory\\ProductImportValidationException',
    'shortName' => 'ProductImportValidationException',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * One or more rows of a product attribute import failed validation, so
 * nothing was written (the import is all-or-nothing).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 9,
    'endLine' => 18,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'RuntimeException',
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
      'rowErrors' => 
      array (
        'declaringClassName' => 'App\\Services\\Inventory\\ProductImportValidationException',
        'implementingClassName' => 'App\\Services\\Inventory\\ProductImportValidationException',
        'name' => 'rowErrors',
        'modifiers' => 2177,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 14,
        'endLine' => 14,
        'startColumn' => 33,
        'endColumn' => 64,
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
          'rowErrors' => 
          array (
            'name' => 'rowErrors',
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
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 14,
            'endLine' => 14,
            'startColumn' => 33,
            'endColumn' => 64,
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
 * @param  array<int, list<string>>  $rowErrors  keyed by 1-based row number; 0 is the file as a whole
 */',
        'startLine' => 14,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Inventory',
        'declaringClassName' => 'App\\Services\\Inventory\\ProductImportValidationException',
        'implementingClassName' => 'App\\Services\\Inventory\\ProductImportValidationException',
        'currentClassName' => 'App\\Services\\Inventory\\ProductImportValidationException',
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