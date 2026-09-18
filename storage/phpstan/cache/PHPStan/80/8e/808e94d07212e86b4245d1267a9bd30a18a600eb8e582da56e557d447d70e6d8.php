<?php declare(strict_types = 1);

// osfsl-C:/xampp/htdocs/pharmacy_erp/vendor/composer/../laravel/framework/src/Illuminate/Support/Testing/Fakes/MailFake.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Support\Testing\Fakes\MailFake
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-007401493baa0a5743fc784e19c8bcfb6c5e70fcc3e6cb136d1bad735e121941-8.3.31-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/vendor/composer/../laravel/framework/src/Illuminate/Support/Testing/Fakes/MailFake.php',
      ),
    ),
    'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
    'name' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
    'shortName' => 'MailFake',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 20,
    'endLine' => 679,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Illuminate\\Contracts\\Mail\\Factory',
      1 => 'Illuminate\\Support\\Testing\\Fakes\\Fake',
      2 => 'Illuminate\\Contracts\\Mail\\Mailer',
      3 => 'Illuminate\\Contracts\\Mail\\MailQueue',
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Support\\Traits\\ForwardsCalls',
      1 => 'Illuminate\\Support\\Traits\\ReflectsClosures',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'manager' => 
      array (
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'name' => 'manager',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The mailer instance.
 *
 * @var MailManager
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'currentMailer' => 
      array (
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'name' => 'currentMailer',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The mailer currently being used to send a message.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'mailables' => 
      array (
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'name' => 'mailables',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 43,
            'endLine' => 43,
            'startTokenPos' => 130,
            'startFilePos' => 990,
            'endTokenPos' => 131,
            'endFilePos' => 991,
          ),
        ),
        'docComment' => '/**
 * All of the mailables that have been sent.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 43,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'queuedMailables' => 
      array (
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'name' => 'queuedMailables',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 50,
            'endLine' => 50,
            'startTokenPos' => 142,
            'startFilePos' => 1120,
            'endTokenPos' => 143,
            'endFilePos' => 1121,
          ),
        ),
        'docComment' => '/**
 * All of the mailables that have been queued.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 50,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 36,
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
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'manager' => 
          array (
            'name' => 'manager',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Mail\\MailManager',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 57,
            'endLine' => 57,
            'startColumn' => 33,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new mail fake.
 *
 * @param  MailManager  $manager
 */',
        'startLine' => 57,
        'endLine' => 61,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'assertSent' => 
      array (
        'name' => 'assertSent',
        'parameters' => 
        array (
          'mailable' => 
          array (
            'name' => 'mailable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 70,
            'endLine' => 70,
            'startColumn' => 32,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 70,
                'endLine' => 70,
                'startTokenPos' => 200,
                'startFilePos' => 1644,
                'endTokenPos' => 200,
                'endFilePos' => 1647,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 70,
            'endLine' => 70,
            'startColumn' => 43,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert if a mailable was sent based on a truth-test callback.
 *
 * @param  string|\\Closure  $mailable
 * @param  callable|array|string|int|null  $callback
 * @return void
 */',
        'startLine' => 70,
        'endLine' => 97,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'assertSentTimes' => 
      array (
        'name' => 'assertSentTimes',
        'parameters' => 
        array (
          'mailable' => 
          array (
            'name' => 'mailable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 106,
            'endLine' => 106,
            'startColumn' => 37,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'times' => 
          array (
            'name' => 'times',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 106,
                'endLine' => 106,
                'startTokenPos' => 420,
                'startFilePos' => 2829,
                'endTokenPos' => 420,
                'endFilePos' => 2829,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 106,
            'endLine' => 106,
            'startColumn' => 48,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert if a mailable was sent a number of times.
 *
 * @param  string  $mailable
 * @param  int  $times
 * @return void
 */',
        'startLine' => 106,
        'endLine' => 118,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'assertSentOnce' => 
      array (
        'name' => 'assertSentOnce',
        'parameters' => 
        array (
          'mailable' => 
          array (
            'name' => 'mailable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 126,
            'endLine' => 126,
            'startColumn' => 36,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert if a mailable was sent exactly once.
 *
 * @param  string  $mailable
 * @return void
 */',
        'startLine' => 126,
        'endLine' => 129,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'assertNotOutgoing' => 
      array (
        'name' => 'assertNotOutgoing',
        'parameters' => 
        array (
          'mailable' => 
          array (
            'name' => 'mailable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 138,
            'endLine' => 138,
            'startColumn' => 39,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 138,
                'endLine' => 138,
                'startTokenPos' => 541,
                'startFilePos' => 3703,
                'endTokenPos' => 541,
                'endFilePos' => 3706,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 138,
            'endLine' => 138,
            'startColumn' => 50,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if a mailable was not sent or queued to be sent based on a truth-test callback.
 *
 * @param  string|\\Closure  $mailable
 * @param  callable|null  $callback
 * @return void
 */',
        'startLine' => 138,
        'endLine' => 142,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'assertNotSent' => 
      array (
        'name' => 'assertNotSent',
        'parameters' => 
        array (
          'mailable' => 
          array (
            'name' => 'mailable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 151,
            'endLine' => 151,
            'startColumn' => 35,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 151,
                'endLine' => 151,
                'startTokenPos' => 585,
                'startFilePos' => 4099,
                'endTokenPos' => 585,
                'endFilePos' => 4102,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 151,
            'endLine' => 151,
            'startColumn' => 46,
            'endColumn' => 61,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if a mailable was not sent based on a truth-test callback.
 *
 * @param  string|\\Closure  $mailable
 * @param  callable|array|string|null  $callback
 * @return void
 */',
        'startLine' => 151,
        'endLine' => 172,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'assertNothingOutgoing' => 
      array (
        'name' => 'assertNothingOutgoing',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that no mailables were sent or queued to be sent.
 *
 * @return void
 */',
        'startLine' => 179,
        'endLine' => 183,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'assertNothingSent' => 
      array (
        'name' => 'assertNothingSent',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that no mailables were sent.
 *
 * @return void
 */',
        'startLine' => 190,
        'endLine' => 197,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'assertQueued' => 
      array (
        'name' => 'assertQueued',
        'parameters' => 
        array (
          'mailable' => 
          array (
            'name' => 'mailable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 206,
            'endLine' => 206,
            'startColumn' => 34,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 206,
                'endLine' => 206,
                'startTokenPos' => 851,
                'startFilePos' => 5710,
                'endTokenPos' => 851,
                'endFilePos' => 5713,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 206,
            'endLine' => 206,
            'startColumn' => 45,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert if a mailable was queued based on a truth-test callback.
 *
 * @param  string|\\Closure  $mailable
 * @param  callable|array|string|int|null  $callback
 * @return void
 */',
        'startLine' => 206,
        'endLine' => 231,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'assertQueuedTimes' => 
      array (
        'name' => 'assertQueuedTimes',
        'parameters' => 
        array (
          'mailable' => 
          array (
            'name' => 'mailable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 240,
            'endLine' => 240,
            'startColumn' => 39,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'times' => 
          array (
            'name' => 'times',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 240,
                'endLine' => 240,
                'startTokenPos' => 1047,
                'startFilePos' => 6777,
                'endTokenPos' => 1047,
                'endFilePos' => 6777,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 240,
            'endLine' => 240,
            'startColumn' => 50,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert if a mailable was queued a number of times.
 *
 * @param  string  $mailable
 * @param  int  $times
 * @return void
 */',
        'startLine' => 240,
        'endLine' => 252,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'assertQueuedOnce' => 
      array (
        'name' => 'assertQueuedOnce',
        'parameters' => 
        array (
          'mailable' => 
          array (
            'name' => 'mailable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 260,
            'endLine' => 260,
            'startColumn' => 38,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert if a mailable was queued exactly once.
 *
 * @param  string  $mailable
 * @return void
 */',
        'startLine' => 260,
        'endLine' => 263,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'assertNotQueued' => 
      array (
        'name' => 'assertNotQueued',
        'parameters' => 
        array (
          'mailable' => 
          array (
            'name' => 'mailable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 272,
            'endLine' => 272,
            'startColumn' => 37,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 272,
                'endLine' => 272,
                'startTokenPos' => 1168,
                'startFilePos' => 7653,
                'endTokenPos' => 1168,
                'endFilePos' => 7656,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 272,
            'endLine' => 272,
            'startColumn' => 48,
            'endColumn' => 63,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if a mailable was not queued based on a truth-test callback.
 *
 * @param  string|\\Closure  $mailable
 * @param  callable|array|string|null  $callback
 * @return void
 */',
        'startLine' => 272,
        'endLine' => 293,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'assertNothingQueued' => 
      array (
        'name' => 'assertNothingQueued',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that no mailables were queued.
 *
 * @return void
 */',
        'startLine' => 300,
        'endLine' => 307,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'assertSentCount' => 
      array (
        'name' => 'assertSentCount',
        'parameters' => 
        array (
          'count' => 
          array (
            'name' => 'count',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 315,
            'endLine' => 315,
            'startColumn' => 37,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert the total number of mailables that were sent.
 *
 * @param  int  $count
 * @return void
 */',
        'startLine' => 315,
        'endLine' => 323,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'assertQueuedCount' => 
      array (
        'name' => 'assertQueuedCount',
        'parameters' => 
        array (
          'count' => 
          array (
            'name' => 'count',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 331,
            'endLine' => 331,
            'startColumn' => 39,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert the total number of mailables that were queued.
 *
 * @param  int  $count
 * @return void
 */',
        'startLine' => 331,
        'endLine' => 339,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'assertOutgoingCount' => 
      array (
        'name' => 'assertOutgoingCount',
        'parameters' => 
        array (
          'count' => 
          array (
            'name' => 'count',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 347,
            'endLine' => 347,
            'startColumn' => 41,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert the total number of mailables that were sent or queued.
 *
 * @param  int  $count
 * @return void
 */',
        'startLine' => 347,
        'endLine' => 357,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'sent' => 
      array (
        'name' => 'sent',
        'parameters' => 
        array (
          'mailable' => 
          array (
            'name' => 'mailable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 366,
            'endLine' => 366,
            'startColumn' => 26,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 366,
                'endLine' => 366,
                'startTokenPos' => 1598,
                'startFilePos' => 10337,
                'endTokenPos' => 1598,
                'endFilePos' => 10340,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 366,
            'endLine' => 366,
            'startColumn' => 37,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get all of the mailables matching a truth-test callback.
 *
 * @param  string|\\Closure  $mailable
 * @param  callable|null  $callback
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 366,
        'endLine' => 377,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'hasSent' => 
      array (
        'name' => 'hasSent',
        'parameters' => 
        array (
          'mailable' => 
          array (
            'name' => 'mailable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 385,
            'endLine' => 385,
            'startColumn' => 29,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given mailable has been sent.
 *
 * @param  string  $mailable
 * @return bool
 */',
        'startLine' => 385,
        'endLine' => 388,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'queued' => 
      array (
        'name' => 'queued',
        'parameters' => 
        array (
          'mailable' => 
          array (
            'name' => 'mailable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 397,
            'endLine' => 397,
            'startColumn' => 28,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 397,
                'endLine' => 397,
                'startTokenPos' => 1738,
                'startFilePos' => 11193,
                'endTokenPos' => 1738,
                'endFilePos' => 11196,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 397,
            'endLine' => 397,
            'startColumn' => 39,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get all of the queued mailables matching a truth-test callback.
 *
 * @param  string|\\Closure  $mailable
 * @param  callable|null  $callback
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 397,
        'endLine' => 408,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'hasQueued' => 
      array (
        'name' => 'hasQueued',
        'parameters' => 
        array (
          'mailable' => 
          array (
            'name' => 'mailable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 416,
            'endLine' => 416,
            'startColumn' => 31,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given mailable has been queued.
 *
 * @param  string  $mailable
 * @return bool
 */',
        'startLine' => 416,
        'endLine' => 419,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'mailablesOf' => 
      array (
        'name' => 'mailablesOf',
        'parameters' => 
        array (
          'type' => 
          array (
            'name' => 'type',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 427,
            'endLine' => 427,
            'startColumn' => 36,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get all of the mailed mailables for a given type.
 *
 * @param  string  $type
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 427,
        'endLine' => 430,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'queuedMailablesOf' => 
      array (
        'name' => 'queuedMailablesOf',
        'parameters' => 
        array (
          'type' => 
          array (
            'name' => 'type',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 438,
            'endLine' => 438,
            'startColumn' => 42,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get all of the mailed mailables for a given type.
 *
 * @param  string  $type
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 438,
        'endLine' => 441,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'mailer' => 
      array (
        'name' => 'mailer',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 449,
                'endLine' => 449,
                'startTokenPos' => 1967,
                'startFilePos' => 12616,
                'endTokenPos' => 1967,
                'endFilePos' => 12619,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 449,
            'endLine' => 449,
            'startColumn' => 28,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a mailer instance by name.
 *
 * @param  string|null  $name
 * @return \\Illuminate\\Contracts\\Mail\\Mailer
 */',
        'startLine' => 449,
        'endLine' => 454,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'driver' => 
      array (
        'name' => 'driver',
        'parameters' => 
        array (
          'driver' => 
          array (
            'name' => 'driver',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 462,
                'endLine' => 462,
                'startTokenPos' => 2000,
                'startFilePos' => 12878,
                'endTokenPos' => 2000,
                'endFilePos' => 12881,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 462,
            'endLine' => 462,
            'startColumn' => 28,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a mailer driver instance.
 *
 * @param  string|null  $driver
 * @return \\Illuminate\\Contracts\\Mail\\Mailer
 */',
        'startLine' => 462,
        'endLine' => 465,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'to' => 
      array (
        'name' => 'to',
        'parameters' => 
        array (
          'users' => 
          array (
            'name' => 'users',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 473,
            'endLine' => 473,
            'startColumn' => 24,
            'endColumn' => 29,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Begin the process of mailing a mailable class instance.
 *
 * @param  mixed  $users
 * @return \\Illuminate\\Mail\\PendingMail
 */',
        'startLine' => 473,
        'endLine' => 476,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'cc' => 
      array (
        'name' => 'cc',
        'parameters' => 
        array (
          'users' => 
          array (
            'name' => 'users',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 484,
            'endLine' => 484,
            'startColumn' => 24,
            'endColumn' => 29,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Begin the process of mailing a mailable class instance.
 *
 * @param  mixed  $users
 * @return \\Illuminate\\Mail\\PendingMail
 */',
        'startLine' => 484,
        'endLine' => 487,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'bcc' => 
      array (
        'name' => 'bcc',
        'parameters' => 
        array (
          'users' => 
          array (
            'name' => 'users',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 495,
            'endLine' => 495,
            'startColumn' => 25,
            'endColumn' => 30,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Begin the process of mailing a mailable class instance.
 *
 * @param  mixed  $users
 * @return \\Illuminate\\Mail\\PendingMail
 */',
        'startLine' => 495,
        'endLine' => 498,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'raw' => 
      array (
        'name' => 'raw',
        'parameters' => 
        array (
          'text' => 
          array (
            'name' => 'text',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 507,
            'endLine' => 507,
            'startColumn' => 25,
            'endColumn' => 29,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 507,
            'endLine' => 507,
            'startColumn' => 32,
            'endColumn' => 40,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Send a new message with only a raw text part.
 *
 * @param  string  $text
 * @param  \\Closure|string  $callback
 * @return void
 */',
        'startLine' => 507,
        'endLine' => 510,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'send' => 
      array (
        'name' => 'send',
        'parameters' => 
        array (
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 520,
            'endLine' => 520,
            'startColumn' => 26,
            'endColumn' => 30,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 520,
                'endLine' => 520,
                'startTokenPos' => 2150,
                'startFilePos' => 14232,
                'endTokenPos' => 2151,
                'endFilePos' => 14233,
              ),
            ),
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
            'startLine' => 520,
            'endLine' => 520,
            'startColumn' => 33,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 520,
                'endLine' => 520,
                'startTokenPos' => 2158,
                'startFilePos' => 14248,
                'endTokenPos' => 2158,
                'endFilePos' => 14251,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 520,
            'endLine' => 520,
            'startColumn' => 51,
            'endColumn' => 66,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Send a new message using a view.
 *
 * @param  \\Illuminate\\Contracts\\Mail\\Mailable|string|array  $view
 * @param  array  $data
 * @param  \\Closure|string|null  $callback
 * @return mixed|void
 */',
        'startLine' => 520,
        'endLine' => 523,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'sendNow' => 
      array (
        'name' => 'sendNow',
        'parameters' => 
        array (
          'mailable' => 
          array (
            'name' => 'mailable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 533,
            'endLine' => 533,
            'startColumn' => 29,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 533,
                'endLine' => 533,
                'startTokenPos' => 2199,
                'startFilePos' => 14636,
                'endTokenPos' => 2200,
                'endFilePos' => 14637,
              ),
            ),
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
            'startLine' => 533,
            'endLine' => 533,
            'startColumn' => 40,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 533,
                'endLine' => 533,
                'startTokenPos' => 2207,
                'startFilePos' => 14652,
                'endTokenPos' => 2207,
                'endFilePos' => 14655,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 533,
            'endLine' => 533,
            'startColumn' => 58,
            'endColumn' => 73,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Send a new message synchronously using a view.
 *
 * @param  \\Illuminate\\Contracts\\Mail\\Mailable|string|array  $mailable
 * @param  array  $data
 * @param  \\Closure|string|null  $callback
 * @return void
 */',
        'startLine' => 533,
        'endLine' => 536,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'sendMail' => 
      array (
        'name' => 'sendMail',
        'parameters' => 
        array (
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 545,
            'endLine' => 545,
            'startColumn' => 33,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'shouldQueue' => 
          array (
            'name' => 'shouldQueue',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 545,
                'endLine' => 545,
                'startTokenPos' => 2243,
                'startFilePos' => 14975,
                'endTokenPos' => 2243,
                'endFilePos' => 14979,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 545,
            'endLine' => 545,
            'startColumn' => 40,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Send a new message using a view.
 *
 * @param  \\Illuminate\\Contracts\\Mail\\Mailable|string|array  $view
 * @param  bool  $shouldQueue
 * @return mixed|void
 */',
        'startLine' => 545,
        'endLine' => 560,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'queue' => 
      array (
        'name' => 'queue',
        'parameters' => 
        array (
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 569,
            'endLine' => 569,
            'startColumn' => 27,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'queue' => 
          array (
            'name' => 'queue',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 569,
                'endLine' => 569,
                'startTokenPos' => 2334,
                'startFilePos' => 15512,
                'endTokenPos' => 2334,
                'endFilePos' => 15515,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 569,
            'endLine' => 569,
            'startColumn' => 34,
            'endColumn' => 46,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Queue a new message for sending.
 *
 * @param  \\Illuminate\\Contracts\\Mail\\Mailable|string|array  $view
 * @param  \\BackedEnum|string|null  $queue
 * @return mixed
 */',
        'startLine' => 569,
        'endLine' => 584,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'onQueue' => 
      array (
        'name' => 'onQueue',
        'parameters' => 
        array (
          'queue' => 
          array (
            'name' => 'queue',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 593,
            'endLine' => 593,
            'startColumn' => 29,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 593,
            'endLine' => 593,
            'startColumn' => 37,
            'endColumn' => 41,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Queue a new mail message for sending on the given queue.
 *
 * @param  \\BackedEnum|string|null  $queue
 * @param  \\Illuminate\\Contracts\\Mail\\Mailable  $view
 * @return mixed
 */',
        'startLine' => 593,
        'endLine' => 596,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'queueOn' => 
      array (
        'name' => 'queueOn',
        'parameters' => 
        array (
          'queue' => 
          array (
            'name' => 'queue',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 605,
            'endLine' => 605,
            'startColumn' => 29,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 605,
            'endLine' => 605,
            'startColumn' => 37,
            'endColumn' => 41,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Queue a new mail message for sending on the given queue.
 *
 * @param  \\BackedEnum|string|null  $queue
 * @param  \\Illuminate\\Contracts\\Mail\\Mailable  $view
 * @return mixed
 */',
        'startLine' => 605,
        'endLine' => 608,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'later' => 
      array (
        'name' => 'later',
        'parameters' => 
        array (
          'delay' => 
          array (
            'name' => 'delay',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 618,
            'endLine' => 618,
            'startColumn' => 27,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 618,
            'endLine' => 618,
            'startColumn' => 35,
            'endColumn' => 39,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'queue' => 
          array (
            'name' => 'queue',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 618,
                'endLine' => 618,
                'startTokenPos' => 2499,
                'startFilePos' => 16796,
                'endTokenPos' => 2499,
                'endFilePos' => 16799,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 618,
            'endLine' => 618,
            'startColumn' => 42,
            'endColumn' => 54,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Queue a new e-mail message for sending after (n) seconds.
 *
 * @param  \\DateTimeInterface|\\DateInterval|int  $delay
 * @param  \\Illuminate\\Contracts\\Mail\\Mailable|string|array  $view
 * @param  string|null  $queue
 * @return mixed
 */',
        'startLine' => 618,
        'endLine' => 625,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'laterOn' => 
      array (
        'name' => 'laterOn',
        'parameters' => 
        array (
          'queue' => 
          array (
            'name' => 'queue',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 635,
            'endLine' => 635,
            'startColumn' => 29,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'delay' => 
          array (
            'name' => 'delay',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 635,
            'endLine' => 635,
            'startColumn' => 37,
            'endColumn' => 42,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 635,
            'endLine' => 635,
            'startColumn' => 45,
            'endColumn' => 49,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Queue a new e-mail message for sending after (n) seconds on the given queue.
 *
 * @param  string|null  $queue
 * @param  \\DateTimeInterface|\\DateInterval|int  $delay
 * @param  \\Illuminate\\Contracts\\Mail\\Mailable  $view
 * @return mixed
 */',
        'startLine' => 635,
        'endLine' => 638,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'prepareMailableAndCallback' => 
      array (
        'name' => 'prepareMailableAndCallback',
        'parameters' => 
        array (
          'mailable' => 
          array (
            'name' => 'mailable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 647,
            'endLine' => 647,
            'startColumn' => 51,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 647,
            'endLine' => 647,
            'startColumn' => 62,
            'endColumn' => 70,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Infer mailable class using reflection if a typehinted closure is passed to assertion.
 *
 * @param  string|\\Closure  $mailable
 * @param  callable|null  $callback
 * @return array
 */',
        'startLine' => 647,
        'endLine' => 654,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      'forgetMailers' => 
      array (
        'name' => 'forgetMailers',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Forget all of the resolved mailer instances.
 *
 * @return $this
 */',
        'startLine' => 661,
        'endLine' => 666,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'aliasName' => NULL,
      ),
      '__call' => 
      array (
        'name' => '__call',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 675,
            'endLine' => 675,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 675,
            'endLine' => 675,
            'startColumn' => 37,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Handle dynamic method calls to the mailer.
 *
 * @param  string  $method
 * @param  array  $parameters
 * @return mixed
 */',
        'startLine' => 675,
        'endLine' => 678,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Support\\Testing\\Fakes',
        'declaringClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'implementingClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
        'currentClassName' => 'Illuminate\\Support\\Testing\\Fakes\\MailFake',
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