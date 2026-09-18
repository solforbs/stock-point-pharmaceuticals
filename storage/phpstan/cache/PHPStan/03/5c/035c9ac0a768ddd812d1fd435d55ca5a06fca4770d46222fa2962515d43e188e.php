<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\app\Models\PayrollRun.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\PayrollRun
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-1d5db18d6e2bce2f1ddcbd0321db401a109f01fff80d24c655223a209af35aeb',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\PayrollRun',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Models/PayrollRun.php',
      ),
    ),
    'namespace' => 'App\\Models',
    'name' => 'App\\Models\\PayrollRun',
    'shortName' => 'PayrollRun',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * @property-read array<int, array<string, mixed>>|null $bands_snapshot_json
 * @property-read Carbon|null $bands_as_of
 * @property-read Carbon|null $posted_at
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 15,
    'endLine' => 50,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'fillable' => 
      array (
        'declaringClassName' => 'App\\Models\\PayrollRun',
        'implementingClassName' => 'App\\Models\\PayrollRun',
        'name' => 'fillable',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'organisation_id\', \'branch_id\', \'doc_number\', \'period_year\', \'period_month\', \'status\', \'bands_snapshot_json\', \'bands_as_of\', \'total_gross\', \'total_paye\', \'total_nssf_employee\', \'total_nssf_employer\', \'total_shif\', \'total_housing_levy_employee\', \'total_housing_levy_employer\', \'total_other_deductions\', \'total_net\', \'journal_id\', \'payment_journal_id\', \'created_by\', \'approved_by\', \'posted_by\', \'computed_at\', \'approved_at\', \'posted_at\', \'paid_at\']',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 24,
            'startTokenPos' => 50,
            'startFilePos' => 453,
            'endTokenPos' => 130,
            'endFilePos' => 938,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 6,
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
      'casts' => 
      array (
        'name' => 'casts',
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
        'docComment' => NULL,
        'startLine' => 26,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\PayrollRun',
        'implementingClassName' => 'App\\Models\\PayrollRun',
        'currentClassName' => 'App\\Models\\PayrollRun',
        'aliasName' => NULL,
      ),
      'lines' => 
      array (
        'name' => 'lines',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return HasMany<PayrollRunLine, $this>
 */',
        'startLine' => 41,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\PayrollRun',
        'implementingClassName' => 'App\\Models\\PayrollRun',
        'currentClassName' => 'App\\Models\\PayrollRun',
        'aliasName' => NULL,
      ),
      'periodLabel' => 
      array (
        'name' => 'periodLabel',
        'parameters' => 
        array (
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
        'startLine' => 46,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\PayrollRun',
        'implementingClassName' => 'App\\Models\\PayrollRun',
        'currentClassName' => 'App\\Models\\PayrollRun',
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