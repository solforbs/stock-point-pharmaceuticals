<?php declare(strict_types = 1);

// osfsl-C:\xampp\htdocs\pharmacy_erp\tests\Feature\Api\ProductCatalogueHttpTest.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Tests\Feature\Api\ProductCatalogueHttpTest
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-c9a513f8cd9d8f96cc07f4252047ada12df6045a6be4bb78c590dac7e1ee68f1-8.3.31-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/tests/Feature/Api/ProductCatalogueHttpTest.php',
      ),
    ),
    'namespace' => 'Tests\\Feature\\Api',
    'name' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
    'shortName' => 'ProductCatalogueHttpTest',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Part 5 — catalogue maintenance: the category tree and the bulk product
 * attribute round trip (CSV export, edit, all-or-nothing import).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 178,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Tests\\TestCase',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Tests\\Support\\BuildsBlueprintWorld',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'para' => 
      array (
        'declaringClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'name' => 'para',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Models\\Product',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 26,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'vat' => 
      array (
        'declaringClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'name' => 'vat',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Models\\TaxCode',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 25,
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
      'setUp' => 
      array (
        'name' => 'setUp',
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
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Tests\\Feature\\Api',
        'declaringClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'currentClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'aliasName' => NULL,
      ),
      'test_categories_are_created_updated_and_listed_with_inactive_ones' => 
      array (
        'name' => 'test_categories_are_created_updated_and_listed_with_inactive_ones',
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
        'startLine' => 40,
        'endLine' => 65,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Tests\\Feature\\Api',
        'declaringClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'currentClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'aliasName' => NULL,
      ),
      'test_import_updates_existing_products_and_blank_cells_leave_values_unchanged' => 
      array (
        'name' => 'test_import_updates_existing_products_and_blank_cells_leave_values_unchanged',
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
        'startLine' => 67,
        'endLine' => 108,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Tests\\Feature\\Api',
        'declaringClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'currentClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'aliasName' => NULL,
      ),
      'test_one_bad_row_rejects_the_whole_file_with_per_row_errors' => 
      array (
        'name' => 'test_one_bad_row_rejects_the_whole_file_with_per_row_errors',
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
        'startLine' => 110,
        'endLine' => 136,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Tests\\Feature\\Api',
        'declaringClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'currentClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'aliasName' => NULL,
      ),
      'test_missing_categories_are_created_only_when_asked' => 
      array (
        'name' => 'test_missing_categories_are_created_only_when_asked',
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
        'startLine' => 138,
        'endLine' => 151,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Tests\\Feature\\Api',
        'declaringClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'currentClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'aliasName' => NULL,
      ),
      'test_export_is_a_csv_that_re_imports_unchanged' => 
      array (
        'name' => 'test_export_is_a_csv_that_re_imports_unchanged',
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
        'startLine' => 153,
        'endLine' => 166,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Tests\\Feature\\Api',
        'declaringClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'currentClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'aliasName' => NULL,
      ),
      'test_catalogue_maintenance_requires_product_edit' => 
      array (
        'name' => 'test_catalogue_maintenance_requires_product_edit',
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
        'startLine' => 168,
        'endLine' => 177,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Tests\\Feature\\Api',
        'declaringClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
        'currentClassName' => 'Tests\\Feature\\Api\\ProductCatalogueHttpTest',
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