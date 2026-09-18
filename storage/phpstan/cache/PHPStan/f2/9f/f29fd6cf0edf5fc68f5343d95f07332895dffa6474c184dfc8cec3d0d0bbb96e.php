<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\database\seeders\TaxCodeSeeder.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Database\Seeders\TaxCodeSeeder
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-3507959fb4fbc9e3410a49b97f507161ade44ad46fe77b07d53e7df533910af2',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Database\\Seeders\\TaxCodeSeeder',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/database/seeders/TaxCodeSeeder.php',
      ),
    ),
    'namespace' => 'Database\\Seeders',
    'name' => 'Database\\Seeders\\TaxCodeSeeder',
    'shortName' => 'TaxCodeSeeder',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Part 13 — the three Kenyan VAT treatments every product must eventually
 * carry: standard-rated, zero-rated and exempt. Products are seeded without
 * a tax code (see MrlPricelistSeeder) because which treatment applies is a
 * compliance decision per product; this seeder only makes the codes exist so
 * that decision can be recorded through the product form.
 *
 * [ASSUMPTION] 16% is the standard rate under the VAT Act 2013, in force
 * from its commencement on 2 September 2013 apart from the temporary 14%
 * relief of April–December 2020, which is not modelled here.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 21,
    'endLine' => 50,
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
      'CODES' => 
      array (
        'declaringClassName' => 'Database\\Seeders\\TaxCodeSeeder',
        'implementingClassName' => 'Database\\Seeders\\TaxCodeSeeder',
        'name' => 'CODES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[[\'VAT_STD\', \'VAT standard rate (16%)\', \'16.000\', true], [\'VAT_ZERO\', \'VAT zero-rated\', \'0.000\', true], [\'VAT_EXEMPT\', \'VAT exempt\', \'0.000\', false]]',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 30,
            'startTokenPos' => 49,
            'startFilePos' => 919,
            'endTokenPos' => 93,
            'endFilePos' => 1098,
          ),
        ),
        'docComment' => '/**
 * @var list<array{0: string, 1: string, 2: string, 3: bool}> [code, name, rate_pct, is_recoverable]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'RATES_EFFECTIVE_FROM' => 
      array (
        'declaringClassName' => 'Database\\Seeders\\TaxCodeSeeder',
        'implementingClassName' => 'Database\\Seeders\\TaxCodeSeeder',
        'name' => 'RATES_EFFECTIVE_FROM',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'2013-09-02\'',
          'attributes' => 
          array (
            'startLine' => 32,
            'endLine' => 32,
            'startTokenPos' => 104,
            'startFilePos' => 1142,
            'endTokenPos' => 104,
            'endFilePos' => 1153,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 53,
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
        'startLine' => 34,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Database\\Seeders',
        'declaringClassName' => 'Database\\Seeders\\TaxCodeSeeder',
        'implementingClassName' => 'Database\\Seeders\\TaxCodeSeeder',
        'currentClassName' => 'Database\\Seeders\\TaxCodeSeeder',
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