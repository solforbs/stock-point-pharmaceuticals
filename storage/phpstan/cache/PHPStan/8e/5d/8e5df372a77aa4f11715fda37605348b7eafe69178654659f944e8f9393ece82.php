<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\app\Services\Finance\SalesJournalMapper.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Finance\SalesJournalMapper
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-8fe608cd82211c790d83b455a5a3f00f4cdd8be65737b0b82fa75bb7493fdf8a',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Finance\\SalesJournalMapper',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Services/Finance/SalesJournalMapper.php',
      ),
    ),
    'namespace' => 'App\\Services\\Finance',
    'name' => 'App\\Services\\Finance\\SalesJournalMapper',
    'shortName' => 'SalesJournalMapper',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Part 12.3 — the posting map, for a single posted sale. One journal entry
 * covers both the revenue side and the cost-of-goods side, since a journal
 * only needs to balance overall, not line-pair by line-pair.
 *
 * Revenue side:
 *   Dr  Cash/Bank/M-PESA clearing / Accounts Receivable   (grand_total, split across
 *       however the sale was paid — see $paidByMethod / $creditAmount)
 *   Dr  Sales discounts (contra-revenue)                  (discount_total)
 *   Cr  Sales - {mode}                                     (subtotal, i.e. gross of
 *       discount, net of tax)
 *   Cr  VAT payable                                        (tax_total)
 *
 * Cost side:
 *   Dr  Cost of goods sold                                 (cost_total, less bonus cost)
 *   Dr  Bonus goods cost                                   (bonus line cost only)
 *   Cr  Inventory                                           (cost_total)
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 25,
    'endLine' => 101,
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
      'MODE_REVENUE_ROLE' => 
      array (
        'declaringClassName' => 'App\\Services\\Finance\\SalesJournalMapper',
        'implementingClassName' => 'App\\Services\\Finance\\SalesJournalMapper',
        'name' => 'MODE_REVENUE_ROLE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'RETAIL\' => \'SALES_RETAIL\', \'WHOLESALE\' => \'SALES_WHOLESALE\', \'DISPENSING\' => \'SALES_DISPENSING\']',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 31,
            'startTokenPos' => 28,
            'startFilePos' => 1059,
            'endTokenPos' => 51,
            'endFilePos' => 1187,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'buildLines' => 
      array (
        'name' => 'buildLines',
        'parameters' => 
        array (
          'sale' => 
          array (
            'name' => 'sale',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\Sale',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 32,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'paidByMethod' => 
          array (
            'name' => 'paidByMethod',
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
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 44,
            'endColumn' => 62,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'creditAmount' => 
          array (
            'name' => 'creditAmount',
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
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 65,
            'endColumn' => 84,
            'parameterIndex' => 2,
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
 * @param  array<string, string>  $paidByMethod  method => amount paid at checkout
 * @return list<array{account_role: string, debit?: string, credit?: string, partner_type?: ?string, partner_id?: ?string, narration?: string}>
 */',
        'startLine' => 37,
        'endLine' => 100,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Finance',
        'declaringClassName' => 'App\\Services\\Finance\\SalesJournalMapper',
        'implementingClassName' => 'App\\Services\\Finance\\SalesJournalMapper',
        'currentClassName' => 'App\\Services\\Finance\\SalesJournalMapper',
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