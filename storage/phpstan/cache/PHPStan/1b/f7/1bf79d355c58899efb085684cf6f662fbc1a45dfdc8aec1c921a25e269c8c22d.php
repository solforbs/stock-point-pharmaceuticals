<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\app\Services\Finance\JournalPoster.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Finance\JournalPoster
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-c1d63529c8fdc03bc36058dec810f79b7a6a94826e6a16a49ae1c0dd08db5923',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Finance\\JournalPoster',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Services/Finance/JournalPoster.php',
      ),
    ),
    'namespace' => 'App\\Services\\Finance',
    'name' => 'App\\Services\\Finance\\JournalPoster',
    'shortName' => 'JournalPoster',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Part 12.1 — "For every journal_entry: Sigma debit_amount = Sigma credit_amount,
 * exactly, to 4dp." Enforced here in the service layer (the DB-level CHECK on
 * journal_entry_lines only stops a single line being both a debit and a
 * credit; the entry-wide balance is this class\'s job) before a single row
 * is written.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 29,
    'endLine' => 95,
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
          'header' => 
          array (
            'name' => 'header',
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
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 26,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'lines' => 
          array (
            'name' => 'lines',
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
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 41,
            'endColumn' => 52,
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
            'name' => 'App\\Models\\JournalEntry',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array{organisation_id: string, branch_id: ?string, entry_date: \\DateTimeInterface, source_doc_type: string, source_doc_id: string, narration: string, posted_by: int, reverses_journal_id?: ?string}  $header
 * @param  list<array{account_role: string, debit?: string, credit?: string, branch_id?: ?string, partner_type?: ?string, partner_id?: ?string, tax_code_id?: ?string, narration?: ?string}>  $lines
 */',
        'startLine' => 35,
        'endLine' => 94,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Finance',
        'declaringClassName' => 'App\\Services\\Finance\\JournalPoster',
        'implementingClassName' => 'App\\Services\\Finance\\JournalPoster',
        'currentClassName' => 'App\\Services\\Finance\\JournalPoster',
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