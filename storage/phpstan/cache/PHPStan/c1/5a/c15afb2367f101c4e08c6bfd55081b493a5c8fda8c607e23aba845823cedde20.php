<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\app\Services\Procurement\ReorderAdvisor.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Procurement\ReorderAdvisor
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-5186abd8aed7712ad24b607a9d7dec6c5fbf1c4e83960034a8b7924559c1ba40',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Procurement\\ReorderAdvisor',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Services/Procurement/ReorderAdvisor.php',
      ),
    ),
    'namespace' => 'App\\Services\\Procurement',
    'name' => 'App\\Services\\Procurement\\ReorderAdvisor',
    'shortName' => 'ReorderAdvisor',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Part 22.1 / 0.1 — the preserved v5 reorder arithmetic, honestly labelled:
 * deterministic and auditable, not "AI".
 *
 *   order_qty   = max(round(reorder_point × coverage_factor − free_to_sell − on_order), min_order_qty)
 *   required_by = today + lead_time_days + 2
 *
 * grouped by the supplier who last delivered the product. Only products
 * whose free-to-sell plus on-order has fallen to the reorder point appear.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 19,
    'endLine' => 94,
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
      'COVERAGE_FACTOR' => 
      array (
        'declaringClassName' => 'App\\Services\\Procurement\\ReorderAdvisor',
        'implementingClassName' => 'App\\Services\\Procurement\\ReorderAdvisor',
        'name' => 'COVERAGE_FACTOR',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'3\'',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 38,
            'startFilePos' => 645,
            'endTokenPos' => 38,
            'endFilePos' => 647,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 39,
      ),
      'MIN_ORDER_QTY' => 
      array (
        'declaringClassName' => 'App\\Services\\Procurement\\ReorderAdvisor',
        'implementingClassName' => 'App\\Services\\Procurement\\ReorderAdvisor',
        'name' => 'MIN_ORDER_QTY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'10\'',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 49,
            'startFilePos' => 684,
            'endTokenPos' => 49,
            'endFilePos' => 687,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 38,
      ),
    ),
    'immediateProperties' => 
    array (
      'report' => 
      array (
        'declaringClassName' => 'App\\Services\\Procurement\\ReorderAdvisor',
        'implementingClassName' => 'App\\Services\\Procurement\\ReorderAdvisor',
        'name' => 'report',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Inventory\\InventoryReport',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 33,
        'endColumn' => 72,
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
          'report' => 
          array (
            'name' => 'report',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Inventory\\InventoryReport',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 25,
            'endLine' => 25,
            'startColumn' => 33,
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
        'docComment' => NULL,
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 76,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Procurement',
        'declaringClassName' => 'App\\Services\\Procurement\\ReorderAdvisor',
        'implementingClassName' => 'App\\Services\\Procurement\\ReorderAdvisor',
        'currentClassName' => 'App\\Services\\Procurement\\ReorderAdvisor',
        'aliasName' => NULL,
      ),
      'suggestions' => 
      array (
        'name' => 'suggestions',
        'parameters' => 
        array (
          'organisationId' => 
          array (
            'name' => 'organisationId',
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
            'startLine' => 30,
            'endLine' => 30,
            'startColumn' => 33,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'branchId' => 
          array (
            'name' => 'branchId',
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
            'startLine' => 30,
            'endLine' => 30,
            'startColumn' => 57,
            'endColumn' => 72,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return list<array<string, mixed>>
 */',
        'startLine' => 30,
        'endLine' => 93,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Procurement',
        'declaringClassName' => 'App\\Services\\Procurement\\ReorderAdvisor',
        'implementingClassName' => 'App\\Services\\Procurement\\ReorderAdvisor',
        'currentClassName' => 'App\\Services\\Procurement\\ReorderAdvisor',
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