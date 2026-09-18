<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\app\Services\Procurement\SupplierPaymentService.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Procurement\SupplierPaymentService
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-5ca3b52c9c912c48a983c308f596dd5e56c723c54bdbf476cfdf38b6161bfe76',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Procurement\\SupplierPaymentService',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Services/Procurement/SupplierPaymentService.php',
      ),
    ),
    'namespace' => 'App\\Services\\Procurement',
    'name' => 'App\\Services\\Procurement\\SupplierPaymentService',
    'shortName' => 'SupplierPaymentService',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Part 12.3 "Supplier payment": Dr Accounts payable / Cr Bank (or cash /
 * M-PESA clearing), with a signed AP sub-ledger row. A payment may never
 * exceed the supplier\'s outstanding balance — that is how a business pays
 * twice for the same delivery.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 21,
    'endLine' => 81,
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
      'journalPoster' => 
      array (
        'declaringClassName' => 'App\\Services\\Procurement\\SupplierPaymentService',
        'implementingClassName' => 'App\\Services\\Procurement\\SupplierPaymentService',
        'name' => 'journalPoster',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Finance\\JournalPoster',
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
        'startColumn' => 33,
        'endColumn' => 77,
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
          'journalPoster' => 
          array (
            'name' => 'journalPoster',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Finance\\JournalPoster',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 23,
            'endLine' => 23,
            'startColumn' => 33,
            'endColumn' => 77,
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
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 81,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Procurement',
        'declaringClassName' => 'App\\Services\\Procurement\\SupplierPaymentService',
        'implementingClassName' => 'App\\Services\\Procurement\\SupplierPaymentService',
        'currentClassName' => 'App\\Services\\Procurement\\SupplierPaymentService',
        'aliasName' => NULL,
      ),
      'pay' => 
      array (
        'name' => 'pay',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
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
            'startLine' => 28,
            'endLine' => 28,
            'startColumn' => 25,
            'endColumn' => 35,
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
            'name' => 'App\\Models\\SupplierPayment',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array{organisation_id: string, branch_id: string, supplier_id: string, method: string, reference?: ?string, amount: string, paid_by: int}  $data
 */',
        'startLine' => 28,
        'endLine' => 80,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Procurement',
        'declaringClassName' => 'App\\Services\\Procurement\\SupplierPaymentService',
        'implementingClassName' => 'App\\Services\\Procurement\\SupplierPaymentService',
        'currentClassName' => 'App\\Services\\Procurement\\SupplierPaymentService',
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