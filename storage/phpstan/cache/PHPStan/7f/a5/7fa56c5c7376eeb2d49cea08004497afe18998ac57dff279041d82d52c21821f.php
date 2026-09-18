<?php declare(strict_types = 1);

// osfsl-C:\xampp\htdocs\pharmacy_erp\app\Services\Inventory\LedgerReconciliation.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Inventory\LedgerReconciliation
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-454f73881f7d5d0885586eed622f1d84138501ee843c5ac735d4d0c5545ef4e9-8.3.31-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Inventory\\LedgerReconciliation',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Services/Inventory/LedgerReconciliation.php',
      ),
    ),
    'namespace' => 'App\\Services\\Inventory',
    'name' => 'App\\Services\\Inventory\\LedgerReconciliation',
    'shortName' => 'LedgerReconciliation',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Part 7.1 / 12.4 — the reconciliation checks that must return zero rows:
 * every stock_balance equals SUM(stock_ledger) for its triple, every ledger
 * row has a balance row, and the ledger inventory value equals the GL
 * inventory account. Shared by the nightly `inventory:reconcile-ledger`
 * command and the System Health screen so both see the same truth.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 84,
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
      'balanceDrift' => 
      array (
        'name' => 'balanceDrift',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Support\\Collection',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Balance rows whose cached quantity differs from the ledger sum.
 *
 * @return Collection<int, \\stdClass> rows of product_id, batch_id, store_id, cached, ledger
 */',
        'startLine' => 25,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Inventory',
        'declaringClassName' => 'App\\Services\\Inventory\\LedgerReconciliation',
        'implementingClassName' => 'App\\Services\\Inventory\\LedgerReconciliation',
        'currentClassName' => 'App\\Services\\Inventory\\LedgerReconciliation',
        'aliasName' => NULL,
      ),
      'orphanLedgerRows' => 
      array (
        'name' => 'orphanLedgerRows',
        'parameters' => 
        array (
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
        'docComment' => '/** Ledger rows with no matching balance row. */',
        'startLine' => 40,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Inventory',
        'declaringClassName' => 'App\\Services\\Inventory\\LedgerReconciliation',
        'implementingClassName' => 'App\\Services\\Inventory\\LedgerReconciliation',
        'currentClassName' => 'App\\Services\\Inventory\\LedgerReconciliation',
        'aliasName' => NULL,
      ),
      'negativeBalances' => 
      array (
        'name' => 'negativeBalances',
        'parameters' => 
        array (
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
        'docComment' => '/** Negative balances — legal only from offline sync (Part 17.4). */',
        'startLine' => 53,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Inventory',
        'declaringClassName' => 'App\\Services\\Inventory\\LedgerReconciliation',
        'implementingClassName' => 'App\\Services\\Inventory\\LedgerReconciliation',
        'currentClassName' => 'App\\Services\\Inventory\\LedgerReconciliation',
        'aliasName' => NULL,
      ),
      'glComparison' => 
      array (
        'name' => 'glComparison',
        'parameters' => 
        array (
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
 * Ledger inventory value against the GL inventory account, per organisation.
 *
 * @return list<array{organisation_id: string, organisation: string, ledger_value: string, gl_value: ?string, matches: bool}>
 */',
        'startLine' => 63,
        'endLine' => 83,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Inventory',
        'declaringClassName' => 'App\\Services\\Inventory\\LedgerReconciliation',
        'implementingClassName' => 'App\\Services\\Inventory\\LedgerReconciliation',
        'currentClassName' => 'App\\Services\\Inventory\\LedgerReconciliation',
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