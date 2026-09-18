<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\app\Services\Procurement\LandedCostAllocator.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Procurement\LandedCostAllocator
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-ae9e1f8bf7aecdc299237910f38d69a9d514e08ce6b511cb50998501773dfa23',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Procurement\\LandedCostAllocator',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Services/Procurement/LandedCostAllocator.php',
      ),
    ),
    'namespace' => 'App\\Services\\Procurement',
    'name' => 'App\\Services\\Procurement\\LandedCostAllocator',
    'shortName' => 'LandedCostAllocator',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Part 9.5 — allocates freight/clearing/insurance/duty (net of supplier
 * rebate) across a shipment\'s GRN lines, by value (default), weight, volume
 * or quantity, and folds the result into each line\'s landed_unit_cost.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 14,
    'endLine' => 56,
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
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'allocate' => 
      array (
        'name' => 'allocate',
        'parameters' => 
        array (
          'landedCost' => 
          array (
            'name' => 'landedCost',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\LandedCost',
                'isIdentifier' => false,
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
            'startColumn' => 30,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 16,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Procurement',
        'declaringClassName' => 'App\\Services\\Procurement\\LandedCostAllocator',
        'implementingClassName' => 'App\\Services\\Procurement\\LandedCostAllocator',
        'currentClassName' => 'App\\Services\\Procurement\\LandedCostAllocator',
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