<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\app\Console\Commands\OpenFinancialPeriods.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Console\Commands\OpenFinancialPeriods
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-ac5f84f8e0f248e0216c16a1cdef4562cc39e2a24efb62d4714bd7e881409f04',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Console\\Commands\\OpenFinancialPeriods',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Console/Commands/OpenFinancialPeriods.php',
      ),
    ),
    'namespace' => 'App\\Console\\Commands',
    'name' => 'App\\Console\\Commands\\OpenFinancialPeriods',
    'shortName' => 'OpenFinancialPeriods',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Part 12.4 — nothing posts outside an open period, so the current month and
 * the ones ahead of it must always exist. Runs daily; only creates periods
 * that are missing and never reopens a closed one.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 16,
    'endLine' => 43,
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
        'declaringClassName' => 'App\\Console\\Commands\\OpenFinancialPeriods',
        'implementingClassName' => 'App\\Console\\Commands\\OpenFinancialPeriods',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'finance:open-periods {--ahead=1 : How many months after the current one to open}\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 50,
            'startFilePos' => 478,
            'endTokenPos' => 50,
            'endFilePos' => 559,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 110,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\OpenFinancialPeriods',
        'implementingClassName' => 'App\\Console\\Commands\\OpenFinancialPeriods',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Make sure the current and upcoming monthly financial periods exist (Part 12.4)\'',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 59,
            'startFilePos' => 592,
            'endTokenPos' => 59,
            'endFilePos' => 671,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
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
        'startLine' => 22,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\OpenFinancialPeriods',
        'implementingClassName' => 'App\\Console\\Commands\\OpenFinancialPeriods',
        'currentClassName' => 'App\\Console\\Commands\\OpenFinancialPeriods',
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