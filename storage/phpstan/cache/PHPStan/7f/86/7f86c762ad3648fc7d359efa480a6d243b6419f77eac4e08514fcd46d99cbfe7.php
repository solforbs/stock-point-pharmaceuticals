<?php declare(strict_types = 1);

// osfsl-C:\xampp\htdocs\pharmacy_erp\database\seeders\LeaveTypesSeeder.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Database\Seeders\LeaveTypesSeeder
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-33c1d590313c4f56b2d13ead14bd88b2f00207b753c7c2305ae608dbd2425457-8.3.31-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Database\\Seeders\\LeaveTypesSeeder',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/database/seeders/LeaveTypesSeeder.php',
      ),
    ),
    'namespace' => 'Database\\Seeders',
    'name' => 'Database\\Seeders\\LeaveTypesSeeder',
    'shortName' => 'LeaveTypesSeeder',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Part 21.16 — the statutory leave types every organisation starts with
 * (Employment Act 2007, Cap. 226).
 *
 * [ASSUMPTION] Annual 21 working days (s.28), maternity 90 days (s.29),
 * paternity 14 days (s.29(8)), compassionate 5 days (common practice; not
 * statutory). Sick leave under s.30 is 7 days full pay then 7 days half pay
 * after two months\' service; it is modelled here as 14 paid days and the
 * half-pay split is left to the payroll officer — confirm with HR before
 * the first live leave year. Unpaid leave has no entitlement and never
 * blocks on balance.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 21,
    'endLine' => 53,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Database\\Seeder',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'TYPES' => 
      array (
        'declaringClassName' => 'Database\\Seeders\\LeaveTypesSeeder',
        'implementingClassName' => 'Database\\Seeders\\LeaveTypesSeeder',
        'name' => 'TYPES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[
    // code, name, days_per_year, is_paid, requires_document, carry_forward_max
    [\'ANNUAL\', \'Annual leave\', \'21\', true, false, \'10\'],
    [\'SICK\', \'Sick leave\', \'14\', true, true, \'0\'],
    [\'MATERNITY\', \'Maternity leave\', \'90\', true, true, \'0\'],
    [\'PATERNITY\', \'Paternity leave\', \'14\', true, false, \'0\'],
    [\'COMPASSIONATE\', \'Compassionate leave\', \'5\', true, false, \'0\'],
    [\'UNPAID\', \'Unpaid leave\', \'0\', false, false, \'0\'],
]',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 31,
            'startTokenPos' => 42,
            'startFilePos' => 780,
            'endTokenPos' => 166,
            'endFilePos' => 1250,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
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
      'run' => 
      array (
        'name' => 'run',
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
        'startLine' => 33,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Database\\Seeders',
        'declaringClassName' => 'Database\\Seeders\\LeaveTypesSeeder',
        'implementingClassName' => 'Database\\Seeders\\LeaveTypesSeeder',
        'currentClassName' => 'Database\\Seeders\\LeaveTypesSeeder',
        'aliasName' => NULL,
      ),
      'seedFor' => 
      array (
        'name' => 'seedFor',
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
            'startLine' => 44,
            'endLine' => 44,
            'startColumn' => 36,
            'endColumn' => 57,
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
        'docComment' => '/**
 * Ensures one organisation has the default leave types; existing rows
 * (and any edits made to them) are left alone.
 */',
        'startLine' => 44,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Database\\Seeders',
        'declaringClassName' => 'Database\\Seeders\\LeaveTypesSeeder',
        'implementingClassName' => 'Database\\Seeders\\LeaveTypesSeeder',
        'currentClassName' => 'Database\\Seeders\\LeaveTypesSeeder',
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