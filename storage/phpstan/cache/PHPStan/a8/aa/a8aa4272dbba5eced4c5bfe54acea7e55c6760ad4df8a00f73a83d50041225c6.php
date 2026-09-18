<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-bcmod
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-dev-master@709e512-8.3.31',
   'data' => 
  array (
    'name' => 'bcmod',
    'parameters' => 
    array (
      'num1' => 
      array (
        'name' => 'num1',
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
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 20,
        'endColumn' => 31,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'num2' => 
      array (
        'name' => 'num2',
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
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 34,
        'endColumn' => 45,
        'parameterIndex' => 1,
        'isOptional' => false,
      ),
      'scale' => 
      array (
        'name' => 'scale',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 55,
            'startFilePos' => 1229,
            'endTokenPos' => 55,
            'endFilePos' => 1232,
          ),
        ),
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
          'data' => 
          array (
            'types' => 
            array (
              0 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'int',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'null',
                  'isIdentifier' => true,
                ),
              ),
            ),
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
        'startColumn' => 48,
        'endColumn' => 65,
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
        'name' => 'string',
        'isIdentifier' => true,
      ),
    ),
    'attributes' => 
    array (
      0 => 
      array (
        'name' => 'JetBrains\\PhpStorm\\Pure',
        'isRepeated' => false,
        'arguments' => 
        array (
        ),
      ),
      1 => 
      array (
        'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
        'isRepeated' => false,
        'arguments' => 
        array (
          0 => 
          array (
            'code' => '\'8.0\'',
            'attributes' => 
            array (
              'startLine' => 26,
              'endLine' => 26,
              'startTokenPos' => 30,
              'startFilePos' => 1160,
              'endTokenPos' => 30,
              'endFilePos' => 1164,
            ),
          ),
        ),
      ),
    ),
    'docComment' => '/**
 * Get modulus of an arbitrary precision number
 * @link https://php.net/manual/en/function.bcmod.php
 * @param string $num1 <p>
 * The dividend, as a string. Since PHP 7.2, the divided is no longer truncated to an integer.
 * </p>
 * @param string $num2 <p>
 * The divisor, as a string. Since PHP 7.2, the divisor is no longer truncated to an integer.
 * </p>
 * @param int|null $scale [optional] <p>
 * This optional parameter is used to set the number of digits after the
 * decimal place in the result. If omitted, it will default to the scale
 * set globally with the {@link bcscale()} function, or fallback to 0 if
 * this has not been set. Available since PHP 7.2.
 * </p>
 * @return string the modulus as a string.
 * @throws \\DivisionByZeroError if <i>divisor</i> is 0. Available since PHP 8.0.
 */',
    'startLine' => 25,
    'endLine' => 29,
    'startColumn' => 5,
    'endColumn' => 5,
    'couldThrow' => false,
    'isClosure' => false,
    'isGenerator' => false,
    'isVariadic' => false,
    'isStatic' => false,
    'namespace' => NULL,
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\InternalLocatedSource',
      'data' => 
      array (
        'name' => 'bcmod',
        'filename' => 'phpstorm-stubs:bcmath/bcmath.stub',
        'extensionName' => 'bcmath',
        'aliasName' => NULL,
      ),
    ),
  ),
));