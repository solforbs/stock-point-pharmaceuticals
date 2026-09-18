<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\app\Services\Pricing\PricingSimulator.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Pricing\PricingSimulator
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-81afc7c37ce54fbaf4ffc496c6678124bfbbe25aebc15b88ba49e951638b4dca',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Pricing\\PricingSimulator',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Services/Pricing/PricingSimulator.php',
      ),
    ),
    'namespace' => 'App\\Services\\Pricing',
    'name' => 'App\\Services\\Pricing\\PricingSimulator',
    'shortName' => 'PricingSimulator',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Part 4.10 — the price modelling screen\'s arithmetic, in one pure
 * function so the UI, the tests and the blueprint\'s worked examples all
 * run the same numbers. No database access; nothing is posted.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 10,
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
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'simulate' => 
      array (
        'name' => 'simulate',
        'parameters' => 
        array (
          'in' => 
          array (
            'name' => 'in',
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
            'startLine' => 20,
            'endLine' => 20,
            'startColumn' => 30,
            'endColumn' => 38,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array{
 *     cost: string, list_price: string, quantity?: string, discount_pct?: string,
 *     min_margin_pct?: string, bonus_buy_qty?: string, bonus_free_qty?: string,
 *     funded_by?: string, monthly_volume?: string, round_to?: string,
 * }  $in
 * @return array<string, mixed>
 */',
        'startLine' => 20,
        'endLine' => 100,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Pricing',
        'declaringClassName' => 'App\\Services\\Pricing\\PricingSimulator',
        'implementingClassName' => 'App\\Services\\Pricing\\PricingSimulator',
        'currentClassName' => 'App\\Services\\Pricing\\PricingSimulator',
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