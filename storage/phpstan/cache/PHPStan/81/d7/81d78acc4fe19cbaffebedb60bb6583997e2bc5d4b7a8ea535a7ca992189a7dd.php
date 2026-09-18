<?php declare(strict_types = 1);

// osfsl-C:\xampp\htdocs\pharmacy_erp\tests\Feature\Api\LeaveHttpTest.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Tests\Feature\Api\LeaveHttpTest
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-c8b9b80e7a5c088e8af1fec75ff340714bd5e96c9811039ad2172b94f08f1a46-8.3.31-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/tests/Feature/Api/LeaveHttpTest.php',
      ),
    ),
    'namespace' => 'Tests\\Feature\\Api',
    'name' => 'Tests\\Feature\\Api\\LeaveHttpTest',
    'shortName' => 'LeaveHttpTest',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Part 21.16 — leave: working-day counting, balances pro-rated from the
 * joining date, no overlaps, no overdrawn paid leave, and nobody approves
 * their own request.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 21,
    'endLine' => 172,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Tests\\TestCase',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Tests\\Support\\BuildsBlueprintWorld',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'employee' => 
      array (
        'declaringClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'name' => 'employee',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Models\\Employee',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 31,
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
      'setUp' => 
      array (
        'name' => 'setUp',
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
        'startLine' => 27,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Tests\\Feature\\Api',
        'declaringClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'currentClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'aliasName' => NULL,
      ),
      'test_a_request_counts_working_days_and_only_approval_consumes_the_balance' => 
      array (
        'name' => 'test_a_request_counts_working_days_and_only_approval_consumes_the_balance',
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
        'startLine' => 40,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Tests\\Feature\\Api',
        'declaringClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'currentClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'aliasName' => NULL,
      ),
      'test_overlapping_and_overdrawn_requests_are_refused_but_unpaid_leave_is_not_capped' => 
      array (
        'name' => 'test_overlapping_and_overdrawn_requests_are_refused_but_unpaid_leave_is_not_capped',
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
        'startLine' => 71,
        'endLine' => 95,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Tests\\Feature\\Api',
        'declaringClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'currentClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'aliasName' => NULL,
      ),
      'test_nobody_approves_their_own_leave_without_payroll_approve_own' => 
      array (
        'name' => 'test_nobody_approves_their_own_leave_without_payroll_approve_own',
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
        'startLine' => 97,
        'endLine' => 106,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Tests\\Feature\\Api',
        'declaringClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'currentClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'aliasName' => NULL,
      ),
      'test_rejection_needs_a_reason_and_approval_needs_the_permission' => 
      array (
        'name' => 'test_rejection_needs_a_reason_and_approval_needs_the_permission',
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
        'startLine' => 108,
        'endLine' => 123,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Tests\\Feature\\Api',
        'declaringClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'currentClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'aliasName' => NULL,
      ),
      'test_a_requester_without_approval_rights_sees_and_requests_only_their_own_leave' => 
      array (
        'name' => 'test_a_requester_without_approval_rights_sees_and_requests_only_their_own_leave',
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
        'startLine' => 125,
        'endLine' => 144,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Tests\\Feature\\Api',
        'declaringClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'currentClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'aliasName' => NULL,
      ),
      'type' => 
      array (
        'name' => 'type',
        'parameters' => 
        array (
          'code' => 
          array (
            'name' => 'code',
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
            'startLine' => 146,
            'endLine' => 146,
            'startColumn' => 27,
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
            'name' => 'App\\Models\\LeaveType',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 146,
        'endLine' => 151,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Tests\\Feature\\Api',
        'declaringClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'currentClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'aliasName' => NULL,
      ),
      'actingAsNewUser' => 
      array (
        'name' => 'actingAsNewUser',
        'parameters' => 
        array (
          'username' => 
          array (
            'name' => 'username',
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
            'startLine' => 156,
            'endLine' => 156,
            'startColumn' => 38,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'permissions' => 
          array (
            'name' => 'permissions',
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
            'startLine' => 156,
            'endLine' => 156,
            'startColumn' => 56,
            'endColumn' => 73,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'roleName' => 
          array (
            'name' => 'roleName',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 156,
                'endLine' => 156,
                'startTokenPos' => 2175,
                'startFilePos' => 9898,
                'endTokenPos' => 2175,
                'endFilePos' => 9901,
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
                      'name' => 'string',
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
            'startLine' => 156,
            'endLine' => 156,
            'startColumn' => 76,
            'endColumn' => 99,
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
            'name' => 'App\\Models\\User',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  list<string>  $permissions
 */',
        'startLine' => 156,
        'endLine' => 171,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Tests\\Feature\\Api',
        'declaringClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'implementingClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
        'currentClassName' => 'Tests\\Feature\\Api\\LeaveHttpTest',
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