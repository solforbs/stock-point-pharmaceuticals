<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\app\Services\Inventory\StockLedgerService.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Inventory\StockLedgerService
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-845d68e6aeed2b6ef2897dbfbeee924545179dd3c4695fd741359076345fcb30',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Inventory\\StockLedgerService',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Services/Inventory/StockLedgerService.php',
      ),
    ),
    'namespace' => 'App\\Services\\Inventory',
    'name' => 'App\\Services\\Inventory\\StockLedgerService',
    'shortName' => 'StockLedgerService',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Part 7.1 — ledger-first inventory. stock_ledgers is the single source of
 * truth; stock_balances is a derived, reconcilable cache. Direct mutation of
 * stock_balances outside this service is architecturally prohibited — every
 * write goes through post(), which writes the ledger row first and then
 * updates the cache from it, inside one transaction.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 107,
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
      'post' => 
      array (
        'name' => 'post',
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
            'startLine' => 27,
            'endLine' => 27,
            'startColumn' => 26,
            'endColumn' => 36,
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
            'name' => 'App\\Models\\StockLedger',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array{
 *     organisation_id?: ?string, txn_type: string, product_id: string, batch_id: string, store_id: string,
 *     location_id?: ?string, qty_base: string, unit_cost?: string,
 *     source_doc_type: string, source_doc_id: string, source_doc_line_id?: ?string,
 *     reverses_ledger_id?: ?string, user_id: int, branch_id: string, txn_datetime?: \\DateTimeInterface,
 * }  $data
 */',
        'startLine' => 27,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Inventory',
        'declaringClassName' => 'App\\Services\\Inventory\\StockLedgerService',
        'implementingClassName' => 'App\\Services\\Inventory\\StockLedgerService',
        'currentClassName' => 'App\\Services\\Inventory\\StockLedgerService',
        'aliasName' => NULL,
      ),
      'reverse' => 
      array (
        'name' => 'reverse',
        'parameters' => 
        array (
          'original' => 
          array (
            'name' => 'original',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\StockLedger',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 52,
            'endLine' => 52,
            'startColumn' => 29,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'txnType' => 
          array (
            'name' => 'txnType',
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
            'startLine' => 52,
            'endLine' => 52,
            'startColumn' => 52,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'overrides' => 
          array (
            'name' => 'overrides',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 52,
                'endLine' => 52,
                'startTokenPos' => 234,
                'startFilePos' => 1987,
                'endTokenPos' => 235,
                'endFilePos' => 1988,
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
            'startLine' => 52,
            'endLine' => 52,
            'startColumn' => 69,
            'endColumn' => 89,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Models\\StockLedger',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Posts a compensating row that reverses an existing ledger entry exactly
 * (Part 7.2\'s SALE_VOID / correction rule) — the original row is never
 * touched.
 */',
        'startLine' => 52,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Inventory',
        'declaringClassName' => 'App\\Services\\Inventory\\StockLedgerService',
        'implementingClassName' => 'App\\Services\\Inventory\\StockLedgerService',
        'currentClassName' => 'App\\Services\\Inventory\\StockLedgerService',
        'aliasName' => NULL,
      ),
      'applyToBalance' => 
      array (
        'name' => 'applyToBalance',
        'parameters' => 
        array (
          'ledger' => 
          array (
            'name' => 'ledger',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\StockLedger',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 69,
            'endLine' => 69,
            'startColumn' => 37,
            'endColumn' => 55,
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
        'startLine' => 69,
        'endLine' => 106,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Inventory',
        'declaringClassName' => 'App\\Services\\Inventory\\StockLedgerService',
        'implementingClassName' => 'App\\Services\\Inventory\\StockLedgerService',
        'currentClassName' => 'App\\Services\\Inventory\\StockLedgerService',
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