<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\pharmacy_erp\database\seeders\RoleSeeder.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Database\Seeders\RoleSeeder
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.31-18b6956983872dd63309d4499994220fd81e5e7a5fedd3768b92d40672758dec',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Database\\Seeders\\RoleSeeder',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/database/seeders/RoleSeeder.php',
      ),
    ),
    'namespace' => 'Database\\Seeders',
    'name' => 'Database\\Seeders\\RoleSeeder',
    'shortName' => 'RoleSeeder',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 9,
    'endLine' => 79,
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
      'ROLE_PERMISSIONS' => 
      array (
        'declaringClassName' => 'Database\\Seeders\\RoleSeeder',
        'implementingClassName' => 'Database\\Seeders\\RoleSeeder',
        'name' => 'ROLE_PERMISSIONS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'Director\' => [\'sale.view\', \'product.view\', \'stock.view\', \'supplier.view\', \'finance.ar.view\', \'finance.ap.view\', \'report.view\', \'report.financial.view\', \'admin.settings\', \'sale.discount.approve\', \'stock.adjust.approve\', \'po.approve\', \'period.close\', \'recall.initiate\', \'waste.approve\', \'customer.credit.override\', \'payroll.view\', \'audit.view\'], \'Operations Manager\' => [\'sale.view\', \'product.view\', \'product.edit\', \'customer.manage\', \'supplier.view\', \'supplier.manage\', \'warehouse.pick\', \'warehouse.dispatch\', \'finance.ar.view\', \'sale.create\', \'sale.void\', \'sale.discount.approve\', \'sale.mode.switch\', \'stock.view\', \'stock.adjust\', \'stock.adjust.approve\', \'stock.count.post\', \'stock.transfer.create\', \'stock.transfer.approve\', \'stock.transfer.dispatch\', \'stock.transfer.receive\', \'stock.count.enter\', \'stock.fefo.override\', \'requisition.view\', \'requisition.create\', \'requisition.approve\', \'po.create\', \'po.approve\', \'grn.create\', \'quality.release\', \'recall.initiate\', \'waste.approve\', \'return.create\', \'return.post\', \'supplier.return\', \'report.view\', \'report.financial.view\'], \'Pharmacist\' => [\'sale.view\', \'product.view\', \'requisition.view\', \'requisition.create\', \'sale.create\', \'stock.view\', \'stock.fefo.override\', \'quality.release\', \'grn.qc.release\', \'recall.initiate\', \'waste.approve\', \'return.post\', \'stock.adjust\'], \'Senior Cashier\' => [\'sale.view\', \'product.view\', \'payment.record\', \'return.create\', \'sale.create\', \'sale.void\', \'sale.discount.apply\', \'sale.mode.switch\', \'stock.view\'], \'Cashier\' => [\'sale.view\', \'product.view\', \'sale.create\', \'sale.discount.apply\', \'stock.view\'], \'Storekeeper\' => [\'product.view\', \'warehouse.pick\', \'warehouse.dispatch\', \'stock.view\', \'stock.adjust\', \'stock.count.post\', \'stock.count.enter\', \'stock.transfer.create\', \'stock.transfer.dispatch\', \'stock.transfer.receive\', \'requisition.view\', \'requisition.create\', \'grn.create\', \'product.create\', \'return.create\', \'supplier.return\'], \'Procurement Officer\' => [\'product.view\', \'supplier.view\', \'supplier.manage\', \'stock.view\', \'requisition.view\', \'requisition.approve\', \'supplier.return\', \'report.view\', \'po.create\', \'grn.create\', \'invoice.match\', \'product.create\'], \'Finance Officer\' => [\'sale.view\', \'product.view\', \'supplier.view\', \'finance.ar.view\', \'finance.ap.view\', \'customer.credit.override\', \'report.view\', \'payment.record\', \'journal.post\', \'journal.reverse\', \'invoice.match\', \'tax.etims.manage\', \'payroll.view\', \'payroll.process\', \'report.financial.view\', \'product.cost.view\'], \'Auditor\' => [\'sale.view\', \'product.view\', \'supplier.view\', \'finance.ar.view\', \'finance.ap.view\', \'audit.view\', \'report.view\', \'report.financial.view\', \'stock.view\', \'product.cost.view\', \'payroll.view\'], \'System Administrator\' => [\'admin.users\', \'admin.settings\', \'audit.view\']]',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 64,
            'startTokenPos' => 42,
            'startFilePos' => 626,
            'endTokenPos' => 554,
            'endFilePos' => 3877,
          ),
        ),
        'docComment' => '/**
 * [NEW-RECOMMENDATION] The blueprint (Part 18.3) names these ten roles and
 * the permission catalogue, plus the separation-of-duties principle, but
 * leaves the exact role -> permission matrix as a business decision for
 * the owner. This mapping is a reasonable starting point, not a source
 * fact — review and adjust per Part 32\'s "discount authority matrix" step.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 64,
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
        'startLine' => 66,
        'endLine' => 78,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Database\\Seeders',
        'declaringClassName' => 'Database\\Seeders\\RoleSeeder',
        'implementingClassName' => 'Database\\Seeders\\RoleSeeder',
        'currentClassName' => 'Database\\Seeders\\RoleSeeder',
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