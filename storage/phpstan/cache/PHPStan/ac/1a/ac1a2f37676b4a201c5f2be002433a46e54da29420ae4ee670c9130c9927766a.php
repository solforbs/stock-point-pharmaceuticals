<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\app\Console\Commands\CreateAdminUser.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Console\Commands\CreateAdminUser
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-6b6958cb99e1a9fadd6148abf4f3228e1ecefa84f0e22d127631e4bfdadc12f4',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Console\\Commands\\CreateAdminUser',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Console/Commands/CreateAdminUser.php',
      ),
    ),
    'namespace' => 'App\\Console\\Commands',
    'name' => 'App\\Console\\Commands\\CreateAdminUser',
    'shortName' => 'CreateAdminUser',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Part 18.3 — the first account has to come from somewhere other than the
 * application it administers. This creates a user holding the blueprint\'s
 * "System Administrator" role (users, settings, audit log — no clinical or
 * financial posting rights) in the chosen branches.
 *
 * --full-access additionally grants a "Super Administrator" role that
 * carries every permission. That deliberately bypasses separation of
 * duties: it exists for initial setup and development, and should be
 * removed from real people before go-live.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 27,
    'endLine' => 135,
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
      'SYSTEM_ADMINISTRATOR' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\CreateAdminUser',
        'implementingClassName' => 'App\\Console\\Commands\\CreateAdminUser',
        'name' => 'SYSTEM_ADMINISTRATOR',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'System Administrator\'',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 77,
            'startFilePos' => 995,
            'endTokenPos' => 77,
            'endFilePos' => 1016,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 63,
      ),
      'SUPER_ADMINISTRATOR' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\CreateAdminUser',
        'implementingClassName' => 'App\\Console\\Commands\\CreateAdminUser',
        'name' => 'SUPER_ADMINISTRATOR',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'Super Administrator\'',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 88,
            'startFilePos' => 1059,
            'endTokenPos' => 88,
            'endFilePos' => 1079,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 61,
      ),
    ),
    'immediateProperties' => 
    array (
      'signature' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\CreateAdminUser',
        'implementingClassName' => 'App\\Console\\Commands\\CreateAdminUser',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'user:create-admin
        {email : The email address the administrator signs in with}
        {--name= : Display name (defaults to "System Administrator")}
        {--username= : Username (defaults to the part of the email before @)}
        {--password= : Password, at least 12 characters (a strong one is generated and shown once when omitted)}
        {--branch=* : Branch code(s) to grant access to (defaults to every active branch)}
        {--full-access : Also grant the Super Administrator role carrying every permission (setup and development only)}\'',
          'attributes' => 
          array (
            'startLine' => 33,
            'endLine' => 39,
            'startTokenPos' => 97,
            'startFilePos' => 1110,
            'endTokenPos' => 97,
            'endFilePos' => 1669,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 33,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 122,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\CreateAdminUser',
        'implementingClassName' => 'App\\Console\\Commands\\CreateAdminUser',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Create an administrator account and assign its roles per branch (Part 18.3)\'',
          'attributes' => 
          array (
            'startLine' => 41,
            'endLine' => 41,
            'startTokenPos' => 106,
            'startFilePos' => 1702,
            'endTokenPos' => 106,
            'endFilePos' => 1778,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 41,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 107,
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
        'startLine' => 43,
        'endLine' => 134,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\CreateAdminUser',
        'implementingClassName' => 'App\\Console\\Commands\\CreateAdminUser',
        'currentClassName' => 'App\\Console\\Commands\\CreateAdminUser',
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