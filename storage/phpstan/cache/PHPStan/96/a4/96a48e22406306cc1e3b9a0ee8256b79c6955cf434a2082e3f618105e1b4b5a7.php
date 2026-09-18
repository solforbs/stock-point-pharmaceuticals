<?php declare(strict_types = 1);

// osfsl-C:\xampp\htdocs\pharmacy_erp\app\Services\Admin\BackupService.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Admin\BackupService
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-3153d9f3d9ffc0d820647b5365a5a6c0a89cf962a0ac9f5b47e0f45fd35a6406-8.3.31-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Admin\\BackupService',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/app/Services/Admin/BackupService.php',
      ),
    ),
    'namespace' => 'App\\Services\\Admin',
    'name' => 'App\\Services\\Admin\\BackupService',
    'shortName' => 'BackupService',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Part 17 — operations: consistent gzip-compressed MySQL dumps written to
 * config(\'backup.path\'), the same directory deploy/backup.sh uses on the
 * server, so the Backup screen lists both. The password reaches mysqldump
 * only through the MYSQL_PWD environment variable, never the command line
 * (where any local user could read it from the process list).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 204,
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
      'NAME_PATTERN' => 
      array (
        'declaringClassName' => 'App\\Services\\Admin\\BackupService',
        'implementingClassName' => 'App\\Services\\Admin\\BackupService',
        'name' => 'NAME_PATTERN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'/^(db|files)-\\d{8}-\\d{6}\\.(sql\\.gz|tar\\.gz)$/\'',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 45,
            'startFilePos' => 760,
            'endTokenPos' => 45,
            'endFilePos' => 806,
          ),
        ),
        'docComment' => '/** Files this service will list, download or prune: db dumps and deploy/backup.sh\'s files archives. */',
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 80,
      ),
      'XAMPP_MYSQLDUMP' => 
      array (
        'declaringClassName' => 'App\\Services\\Admin\\BackupService',
        'implementingClassName' => 'App\\Services\\Admin\\BackupService',
        'name' => 'XAMPP_MYSQLDUMP',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'C:\\xampp\\mysql\\bin\\mysqldump.exe\'',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 56,
            'startFilePos' => 846,
            'endTokenPos' => 56,
            'endFilePos' => 883,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 75,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'directory' => 
      array (
        'name' => 'directory',
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
        'startLine' => 24,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Admin',
        'declaringClassName' => 'App\\Services\\Admin\\BackupService',
        'implementingClassName' => 'App\\Services\\Admin\\BackupService',
        'currentClassName' => 'App\\Services\\Admin\\BackupService',
        'aliasName' => NULL,
      ),
      'binary' => 
      array (
        'name' => 'binary',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
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
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * The mysqldump binary that will be run, or null when none can be found.
 */',
        'startLine' => 32,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Admin',
        'declaringClassName' => 'App\\Services\\Admin\\BackupService',
        'implementingClassName' => 'App\\Services\\Admin\\BackupService',
        'currentClassName' => 'App\\Services\\Admin\\BackupService',
        'aliasName' => NULL,
      ),
      'create' => 
      array (
        'name' => 'create',
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
        'docComment' => '/**
 * Takes a dump now and returns its listing entry.
 *
 * @return array{name: string, size: int, created_at: string, kind: string}
 *
 * @throws BackupFailedException
 */',
        'startLine' => 53,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Admin',
        'declaringClassName' => 'App\\Services\\Admin\\BackupService',
        'implementingClassName' => 'App\\Services\\Admin\\BackupService',
        'currentClassName' => 'App\\Services\\Admin\\BackupService',
        'aliasName' => NULL,
      ),
      'list' => 
      array (
        'name' => 'list',
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
        'docComment' => '/**
 * Every backup file in the directory, newest first.
 *
 * @return list<array{name: string, size: int, created_at: string, kind: string}>
 */',
        'startLine' => 88,
        'endLine' => 105,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Admin',
        'declaringClassName' => 'App\\Services\\Admin\\BackupService',
        'implementingClassName' => 'App\\Services\\Admin\\BackupService',
        'currentClassName' => 'App\\Services\\Admin\\BackupService',
        'aliasName' => NULL,
      ),
      'pathFor' => 
      array (
        'name' => 'pathFor',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
            'startLine' => 111,
            'endLine' => 111,
            'startColumn' => 29,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
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
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * The absolute path of a listed backup, or null. The name must equal a
 * file in the listing exactly, so no path can ever escape the directory.
 */',
        'startLine' => 111,
        'endLine' => 120,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Admin',
        'declaringClassName' => 'App\\Services\\Admin\\BackupService',
        'implementingClassName' => 'App\\Services\\Admin\\BackupService',
        'currentClassName' => 'App\\Services\\Admin\\BackupService',
        'aliasName' => NULL,
      ),
      'prune' => 
      array (
        'name' => 'prune',
        'parameters' => 
        array (
          'retentionDays' => 
          array (
            'name' => 'retentionDays',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 127,
            'endLine' => 127,
            'startColumn' => 27,
            'endColumn' => 44,
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
 * Deletes backups older than the retention window; returns the names removed.
 *
 * @return list<string>
 */',
        'startLine' => 127,
        'endLine' => 138,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Admin',
        'declaringClassName' => 'App\\Services\\Admin\\BackupService',
        'implementingClassName' => 'App\\Services\\Admin\\BackupService',
        'currentClassName' => 'App\\Services\\Admin\\BackupService',
        'aliasName' => NULL,
      ),
      'runDump' => 
      array (
        'name' => 'runDump',
        'parameters' => 
        array (
          'write' => 
          array (
            'name' => 'write',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 148,
            'endLine' => 148,
            'startColumn' => 32,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Runs mysqldump and hands its output to $write in chunks. Tests replace
 * this method so no real dump is taken.
 *
 * @param  \\Closure(string): void  $write
 *
 * @throws BackupFailedException
 */',
        'startLine' => 148,
        'endLine' => 188,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Services\\Admin',
        'declaringClassName' => 'App\\Services\\Admin\\BackupService',
        'implementingClassName' => 'App\\Services\\Admin\\BackupService',
        'currentClassName' => 'App\\Services\\Admin\\BackupService',
        'aliasName' => NULL,
      ),
      'entry' => 
      array (
        'name' => 'entry',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
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
            'startLine' => 193,
            'endLine' => 193,
            'startColumn' => 28,
            'endColumn' => 39,
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
 * @return array{name: string, size: int, created_at: string, kind: string}
 */',
        'startLine' => 193,
        'endLine' => 203,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Admin',
        'declaringClassName' => 'App\\Services\\Admin\\BackupService',
        'implementingClassName' => 'App\\Services\\Admin\\BackupService',
        'currentClassName' => 'App\\Services\\Admin\\BackupService',
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