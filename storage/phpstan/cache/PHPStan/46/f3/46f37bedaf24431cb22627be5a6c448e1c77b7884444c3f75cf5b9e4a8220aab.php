<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\app\Services\Finance\PaymentMethodAccounts.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Finance\PaymentMethodAccounts
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-1454907caa150d33ef489cda74c2cdcd17fc68ef572cc1a296f573104d0ff6b6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Finance\\PaymentMethodAccounts',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Services/Finance/PaymentMethodAccounts.php',
      ),
    ),
    'namespace' => 'App\\Services\\Finance',
    'name' => 'App\\Services\\Finance\\PaymentMethodAccounts',
    'shortName' => 'PaymentMethodAccounts',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * The payment-method -> GL account role mapping, shared by SalesJournalMapper
 * (money in at checkout time) and ReceiptService (money in against an
 * existing AR balance) — one place, so the two never drift apart.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 10,
    'endLine' => 24,
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
      'ROLE' => 
      array (
        'declaringClassName' => 'App\\Services\\Finance\\PaymentMethodAccounts',
        'implementingClassName' => 'App\\Services\\Finance\\PaymentMethodAccounts',
        'name' => 'ROLE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'CASH\' => \'CASH\', \'BANK\' => \'BANK\', \'MPESA\' => \'MPESA_CLEARING\', \'CARD\' => \'BANK\', \'CHEQUE\' => \'BANK\']',
          'attributes' => 
          array (
            'startLine' => 12,
            'endLine' => 18,
            'startTokenPos' => 23,
            'startFilePos' => 322,
            'endTokenPos' => 60,
            'endFilePos' => 471,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 12,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'role' => 
      array (
        'name' => 'role',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
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
            'startLine' => 20,
            'endLine' => 20,
            'startColumn' => 33,
            'endColumn' => 46,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 20,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Services\\Finance',
        'declaringClassName' => 'App\\Services\\Finance\\PaymentMethodAccounts',
        'implementingClassName' => 'App\\Services\\Finance\\PaymentMethodAccounts',
        'currentClassName' => 'App\\Services\\Finance\\PaymentMethodAccounts',
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