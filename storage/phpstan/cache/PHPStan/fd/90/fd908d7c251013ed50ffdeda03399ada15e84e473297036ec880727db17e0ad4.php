<?php declare(strict_types = 1);

// ftm-C:\xampp\htdocs\pharmacy_erp\tests\Feature\Api\ScheduledReportHttpTest.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      '7c2c25a26e76a9cfe0a7b801420b4ab4' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Feature\\Api',
         'uses' => 
        array (
          'scheduledreportmail' => 'App\\Mail\\ScheduledReportMail',
          'auditlog' => 'App\\Models\\AuditLog',
          'scheduledreport' => 'App\\Models\\ScheduledReport',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'mail' => 'Illuminate\\Support\\Facades\\Mail',
          'sanctum' => 'Laravel\\Sanctum\\Sanctum',
          'buildsblueprintworld' => 'Tests\\Support\\BuildsBlueprintWorld',
          'testcase' => 'Tests\\TestCase',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '7f9d5940788a4af50c2bdbcec6b81f83' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Support',
         'uses' => 
        array (
          'branch' => 'App\\Models\\Branch',
          'chartofaccount' => 'App\\Models\\ChartOfAccount',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'customertier' => 'App\\Models\\CustomerTier',
          'goodsreceipt' => 'App\\Models\\GoodsReceipt',
          'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
          'journalentryline' => 'App\\Models\\JournalEntryLine',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'organisation' => 'App\\Models\\Organisation',
          'pricebreak' => 'App\\Models\\PriceBreak',
          'pricelist' => 'App\\Models\\PriceList',
          'product' => 'App\\Models\\Product',
          'productbatch' => 'App\\Models\\ProductBatch',
          'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
          'productprice' => 'App\\Models\\ProductPrice',
          'productuom' => 'App\\Models\\ProductUom',
          'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
          'sale' => 'App\\Models\\Sale',
          'stockbalance' => 'App\\Models\\StockBalance',
          'store' => 'App\\Models\\Store',
          'supplier' => 'App\\Models\\Supplier',
          'taxcode' => 'App\\Models\\TaxCode',
          'taxrate' => 'App\\Models\\TaxRate',
          'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
          'user' => 'App\\Models\\User',
          'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
          'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
          'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'permission' => 'Spatie\\Permission\\Models\\Permission',
          'role' => 'Spatie\\Permission\\Models\\Role',
          'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
         'traitData' => 
        array (
          0 => 'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php',
          1 => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
          2 => 'Tests\\Support\\BuildsBlueprintWorld',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'cc7323640262e45bfddf7af1e71a5f6b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Support',
         'uses' => 
        array (
          'branch' => 'App\\Models\\Branch',
          'chartofaccount' => 'App\\Models\\ChartOfAccount',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'customertier' => 'App\\Models\\CustomerTier',
          'goodsreceipt' => 'App\\Models\\GoodsReceipt',
          'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
          'journalentryline' => 'App\\Models\\JournalEntryLine',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'organisation' => 'App\\Models\\Organisation',
          'pricebreak' => 'App\\Models\\PriceBreak',
          'pricelist' => 'App\\Models\\PriceList',
          'product' => 'App\\Models\\Product',
          'productbatch' => 'App\\Models\\ProductBatch',
          'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
          'productprice' => 'App\\Models\\ProductPrice',
          'productuom' => 'App\\Models\\ProductUom',
          'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
          'sale' => 'App\\Models\\Sale',
          'stockbalance' => 'App\\Models\\StockBalance',
          'store' => 'App\\Models\\Store',
          'supplier' => 'App\\Models\\Supplier',
          'taxcode' => 'App\\Models\\TaxCode',
          'taxrate' => 'App\\Models\\TaxRate',
          'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
          'user' => 'App\\Models\\User',
          'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
          'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
          'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'permission' => 'Spatie\\Permission\\Models\\Permission',
          'role' => 'Spatie\\Permission\\Models\\Role',
          'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'buildWorld',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Support',
           'uses' => 
          array (
            'branch' => 'App\\Models\\Branch',
            'chartofaccount' => 'App\\Models\\ChartOfAccount',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'customertier' => 'App\\Models\\CustomerTier',
            'goodsreceipt' => 'App\\Models\\GoodsReceipt',
            'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
            'journalentryline' => 'App\\Models\\JournalEntryLine',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'organisation' => 'App\\Models\\Organisation',
            'pricebreak' => 'App\\Models\\PriceBreak',
            'pricelist' => 'App\\Models\\PriceList',
            'product' => 'App\\Models\\Product',
            'productbatch' => 'App\\Models\\ProductBatch',
            'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
            'productprice' => 'App\\Models\\ProductPrice',
            'productuom' => 'App\\Models\\ProductUom',
            'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
            'sale' => 'App\\Models\\Sale',
            'stockbalance' => 'App\\Models\\StockBalance',
            'store' => 'App\\Models\\Store',
            'supplier' => 'App\\Models\\Supplier',
            'taxcode' => 'App\\Models\\TaxCode',
            'taxrate' => 'App\\Models\\TaxRate',
            'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
            'user' => 'App\\Models\\User',
            'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
            'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
            'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'permission' => 'Spatie\\Permission\\Models\\Permission',
            'role' => 'Spatie\\Permission\\Models\\Role',
            'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
         'traitData' => 
        array (
          0 => 'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php',
          1 => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
          2 => 'Tests\\Support\\BuildsBlueprintWorld',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'e5045f84d0fe4576787ae305657f17c8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Support',
         'uses' => 
        array (
          'branch' => 'App\\Models\\Branch',
          'chartofaccount' => 'App\\Models\\ChartOfAccount',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'customertier' => 'App\\Models\\CustomerTier',
          'goodsreceipt' => 'App\\Models\\GoodsReceipt',
          'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
          'journalentryline' => 'App\\Models\\JournalEntryLine',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'organisation' => 'App\\Models\\Organisation',
          'pricebreak' => 'App\\Models\\PriceBreak',
          'pricelist' => 'App\\Models\\PriceList',
          'product' => 'App\\Models\\Product',
          'productbatch' => 'App\\Models\\ProductBatch',
          'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
          'productprice' => 'App\\Models\\ProductPrice',
          'productuom' => 'App\\Models\\ProductUom',
          'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
          'sale' => 'App\\Models\\Sale',
          'stockbalance' => 'App\\Models\\StockBalance',
          'store' => 'App\\Models\\Store',
          'supplier' => 'App\\Models\\Supplier',
          'taxcode' => 'App\\Models\\TaxCode',
          'taxrate' => 'App\\Models\\TaxRate',
          'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
          'user' => 'App\\Models\\User',
          'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
          'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
          'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'permission' => 'Spatie\\Permission\\Models\\Permission',
          'role' => 'Spatie\\Permission\\Models\\Role',
          'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'uom',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Support',
           'uses' => 
          array (
            'branch' => 'App\\Models\\Branch',
            'chartofaccount' => 'App\\Models\\ChartOfAccount',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'customertier' => 'App\\Models\\CustomerTier',
            'goodsreceipt' => 'App\\Models\\GoodsReceipt',
            'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
            'journalentryline' => 'App\\Models\\JournalEntryLine',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'organisation' => 'App\\Models\\Organisation',
            'pricebreak' => 'App\\Models\\PriceBreak',
            'pricelist' => 'App\\Models\\PriceList',
            'product' => 'App\\Models\\Product',
            'productbatch' => 'App\\Models\\ProductBatch',
            'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
            'productprice' => 'App\\Models\\ProductPrice',
            'productuom' => 'App\\Models\\ProductUom',
            'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
            'sale' => 'App\\Models\\Sale',
            'stockbalance' => 'App\\Models\\StockBalance',
            'store' => 'App\\Models\\Store',
            'supplier' => 'App\\Models\\Supplier',
            'taxcode' => 'App\\Models\\TaxCode',
            'taxrate' => 'App\\Models\\TaxRate',
            'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
            'user' => 'App\\Models\\User',
            'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
            'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
            'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'permission' => 'Spatie\\Permission\\Models\\Permission',
            'role' => 'Spatie\\Permission\\Models\\Role',
            'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
         'traitData' => 
        array (
          0 => 'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php',
          1 => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
          2 => 'Tests\\Support\\BuildsBlueprintWorld',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'fc654ce0571f7deb95219efb96f171aa' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Support',
         'uses' => 
        array (
          'branch' => 'App\\Models\\Branch',
          'chartofaccount' => 'App\\Models\\ChartOfAccount',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'customertier' => 'App\\Models\\CustomerTier',
          'goodsreceipt' => 'App\\Models\\GoodsReceipt',
          'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
          'journalentryline' => 'App\\Models\\JournalEntryLine',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'organisation' => 'App\\Models\\Organisation',
          'pricebreak' => 'App\\Models\\PriceBreak',
          'pricelist' => 'App\\Models\\PriceList',
          'product' => 'App\\Models\\Product',
          'productbatch' => 'App\\Models\\ProductBatch',
          'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
          'productprice' => 'App\\Models\\ProductPrice',
          'productuom' => 'App\\Models\\ProductUom',
          'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
          'sale' => 'App\\Models\\Sale',
          'stockbalance' => 'App\\Models\\StockBalance',
          'store' => 'App\\Models\\Store',
          'supplier' => 'App\\Models\\Supplier',
          'taxcode' => 'App\\Models\\TaxCode',
          'taxrate' => 'App\\Models\\TaxRate',
          'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
          'user' => 'App\\Models\\User',
          'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
          'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
          'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'permission' => 'Spatie\\Permission\\Models\\Permission',
          'role' => 'Spatie\\Permission\\Models\\Role',
          'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'receive',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Support',
           'uses' => 
          array (
            'branch' => 'App\\Models\\Branch',
            'chartofaccount' => 'App\\Models\\ChartOfAccount',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'customertier' => 'App\\Models\\CustomerTier',
            'goodsreceipt' => 'App\\Models\\GoodsReceipt',
            'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
            'journalentryline' => 'App\\Models\\JournalEntryLine',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'organisation' => 'App\\Models\\Organisation',
            'pricebreak' => 'App\\Models\\PriceBreak',
            'pricelist' => 'App\\Models\\PriceList',
            'product' => 'App\\Models\\Product',
            'productbatch' => 'App\\Models\\ProductBatch',
            'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
            'productprice' => 'App\\Models\\ProductPrice',
            'productuom' => 'App\\Models\\ProductUom',
            'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
            'sale' => 'App\\Models\\Sale',
            'stockbalance' => 'App\\Models\\StockBalance',
            'store' => 'App\\Models\\Store',
            'supplier' => 'App\\Models\\Supplier',
            'taxcode' => 'App\\Models\\TaxCode',
            'taxrate' => 'App\\Models\\TaxRate',
            'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
            'user' => 'App\\Models\\User',
            'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
            'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
            'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'permission' => 'Spatie\\Permission\\Models\\Permission',
            'role' => 'Spatie\\Permission\\Models\\Role',
            'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
         'traitData' => 
        array (
          0 => 'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php',
          1 => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
          2 => 'Tests\\Support\\BuildsBlueprintWorld',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'fd496d656436a3515c5c5f2ebfd3b293' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Support',
         'uses' => 
        array (
          'branch' => 'App\\Models\\Branch',
          'chartofaccount' => 'App\\Models\\ChartOfAccount',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'customertier' => 'App\\Models\\CustomerTier',
          'goodsreceipt' => 'App\\Models\\GoodsReceipt',
          'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
          'journalentryline' => 'App\\Models\\JournalEntryLine',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'organisation' => 'App\\Models\\Organisation',
          'pricebreak' => 'App\\Models\\PriceBreak',
          'pricelist' => 'App\\Models\\PriceList',
          'product' => 'App\\Models\\Product',
          'productbatch' => 'App\\Models\\ProductBatch',
          'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
          'productprice' => 'App\\Models\\ProductPrice',
          'productuom' => 'App\\Models\\ProductUom',
          'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
          'sale' => 'App\\Models\\Sale',
          'stockbalance' => 'App\\Models\\StockBalance',
          'store' => 'App\\Models\\Store',
          'supplier' => 'App\\Models\\Supplier',
          'taxcode' => 'App\\Models\\TaxCode',
          'taxrate' => 'App\\Models\\TaxRate',
          'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
          'user' => 'App\\Models\\User',
          'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
          'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
          'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'permission' => 'Spatie\\Permission\\Models\\Permission',
          'role' => 'Spatie\\Permission\\Models\\Role',
          'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'checkout',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Support',
           'uses' => 
          array (
            'branch' => 'App\\Models\\Branch',
            'chartofaccount' => 'App\\Models\\ChartOfAccount',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'customertier' => 'App\\Models\\CustomerTier',
            'goodsreceipt' => 'App\\Models\\GoodsReceipt',
            'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
            'journalentryline' => 'App\\Models\\JournalEntryLine',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'organisation' => 'App\\Models\\Organisation',
            'pricebreak' => 'App\\Models\\PriceBreak',
            'pricelist' => 'App\\Models\\PriceList',
            'product' => 'App\\Models\\Product',
            'productbatch' => 'App\\Models\\ProductBatch',
            'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
            'productprice' => 'App\\Models\\ProductPrice',
            'productuom' => 'App\\Models\\ProductUom',
            'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
            'sale' => 'App\\Models\\Sale',
            'stockbalance' => 'App\\Models\\StockBalance',
            'store' => 'App\\Models\\Store',
            'supplier' => 'App\\Models\\Supplier',
            'taxcode' => 'App\\Models\\TaxCode',
            'taxrate' => 'App\\Models\\TaxRate',
            'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
            'user' => 'App\\Models\\User',
            'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
            'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
            'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'permission' => 'Spatie\\Permission\\Models\\Permission',
            'role' => 'Spatie\\Permission\\Models\\Role',
            'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
         'traitData' => 
        array (
          0 => 'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php',
          1 => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
          2 => 'Tests\\Support\\BuildsBlueprintWorld',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '2a0e954fee36695ea21e868fd9b8fb05' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Support',
         'uses' => 
        array (
          'branch' => 'App\\Models\\Branch',
          'chartofaccount' => 'App\\Models\\ChartOfAccount',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'customertier' => 'App\\Models\\CustomerTier',
          'goodsreceipt' => 'App\\Models\\GoodsReceipt',
          'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
          'journalentryline' => 'App\\Models\\JournalEntryLine',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'organisation' => 'App\\Models\\Organisation',
          'pricebreak' => 'App\\Models\\PriceBreak',
          'pricelist' => 'App\\Models\\PriceList',
          'product' => 'App\\Models\\Product',
          'productbatch' => 'App\\Models\\ProductBatch',
          'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
          'productprice' => 'App\\Models\\ProductPrice',
          'productuom' => 'App\\Models\\ProductUom',
          'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
          'sale' => 'App\\Models\\Sale',
          'stockbalance' => 'App\\Models\\StockBalance',
          'store' => 'App\\Models\\Store',
          'supplier' => 'App\\Models\\Supplier',
          'taxcode' => 'App\\Models\\TaxCode',
          'taxrate' => 'App\\Models\\TaxRate',
          'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
          'user' => 'App\\Models\\User',
          'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
          'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
          'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'permission' => 'Spatie\\Permission\\Models\\Permission',
          'role' => 'Spatie\\Permission\\Models\\Role',
          'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'saleLine',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Support',
           'uses' => 
          array (
            'branch' => 'App\\Models\\Branch',
            'chartofaccount' => 'App\\Models\\ChartOfAccount',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'customertier' => 'App\\Models\\CustomerTier',
            'goodsreceipt' => 'App\\Models\\GoodsReceipt',
            'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
            'journalentryline' => 'App\\Models\\JournalEntryLine',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'organisation' => 'App\\Models\\Organisation',
            'pricebreak' => 'App\\Models\\PriceBreak',
            'pricelist' => 'App\\Models\\PriceList',
            'product' => 'App\\Models\\Product',
            'productbatch' => 'App\\Models\\ProductBatch',
            'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
            'productprice' => 'App\\Models\\ProductPrice',
            'productuom' => 'App\\Models\\ProductUom',
            'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
            'sale' => 'App\\Models\\Sale',
            'stockbalance' => 'App\\Models\\StockBalance',
            'store' => 'App\\Models\\Store',
            'supplier' => 'App\\Models\\Supplier',
            'taxcode' => 'App\\Models\\TaxCode',
            'taxrate' => 'App\\Models\\TaxRate',
            'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
            'user' => 'App\\Models\\User',
            'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
            'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
            'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'permission' => 'Spatie\\Permission\\Models\\Permission',
            'role' => 'Spatie\\Permission\\Models\\Role',
            'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
         'traitData' => 
        array (
          0 => 'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php',
          1 => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
          2 => 'Tests\\Support\\BuildsBlueprintWorld',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'dd89301642b449db94c064015402a8bc' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Support',
         'uses' => 
        array (
          'branch' => 'App\\Models\\Branch',
          'chartofaccount' => 'App\\Models\\ChartOfAccount',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'customertier' => 'App\\Models\\CustomerTier',
          'goodsreceipt' => 'App\\Models\\GoodsReceipt',
          'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
          'journalentryline' => 'App\\Models\\JournalEntryLine',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'organisation' => 'App\\Models\\Organisation',
          'pricebreak' => 'App\\Models\\PriceBreak',
          'pricelist' => 'App\\Models\\PriceList',
          'product' => 'App\\Models\\Product',
          'productbatch' => 'App\\Models\\ProductBatch',
          'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
          'productprice' => 'App\\Models\\ProductPrice',
          'productuom' => 'App\\Models\\ProductUom',
          'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
          'sale' => 'App\\Models\\Sale',
          'stockbalance' => 'App\\Models\\StockBalance',
          'store' => 'App\\Models\\Store',
          'supplier' => 'App\\Models\\Supplier',
          'taxcode' => 'App\\Models\\TaxCode',
          'taxrate' => 'App\\Models\\TaxRate',
          'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
          'user' => 'App\\Models\\User',
          'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
          'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
          'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'permission' => 'Spatie\\Permission\\Models\\Permission',
          'role' => 'Spatie\\Permission\\Models\\Role',
          'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'ledgerSum',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Support',
           'uses' => 
          array (
            'branch' => 'App\\Models\\Branch',
            'chartofaccount' => 'App\\Models\\ChartOfAccount',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'customertier' => 'App\\Models\\CustomerTier',
            'goodsreceipt' => 'App\\Models\\GoodsReceipt',
            'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
            'journalentryline' => 'App\\Models\\JournalEntryLine',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'organisation' => 'App\\Models\\Organisation',
            'pricebreak' => 'App\\Models\\PriceBreak',
            'pricelist' => 'App\\Models\\PriceList',
            'product' => 'App\\Models\\Product',
            'productbatch' => 'App\\Models\\ProductBatch',
            'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
            'productprice' => 'App\\Models\\ProductPrice',
            'productuom' => 'App\\Models\\ProductUom',
            'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
            'sale' => 'App\\Models\\Sale',
            'stockbalance' => 'App\\Models\\StockBalance',
            'store' => 'App\\Models\\Store',
            'supplier' => 'App\\Models\\Supplier',
            'taxcode' => 'App\\Models\\TaxCode',
            'taxrate' => 'App\\Models\\TaxRate',
            'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
            'user' => 'App\\Models\\User',
            'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
            'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
            'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'permission' => 'Spatie\\Permission\\Models\\Permission',
            'role' => 'Spatie\\Permission\\Models\\Role',
            'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
         'traitData' => 
        array (
          0 => 'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php',
          1 => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
          2 => 'Tests\\Support\\BuildsBlueprintWorld',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '4a2da4d38437a309d237a1b8218ee558' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Support',
         'uses' => 
        array (
          'branch' => 'App\\Models\\Branch',
          'chartofaccount' => 'App\\Models\\ChartOfAccount',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'customertier' => 'App\\Models\\CustomerTier',
          'goodsreceipt' => 'App\\Models\\GoodsReceipt',
          'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
          'journalentryline' => 'App\\Models\\JournalEntryLine',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'organisation' => 'App\\Models\\Organisation',
          'pricebreak' => 'App\\Models\\PriceBreak',
          'pricelist' => 'App\\Models\\PriceList',
          'product' => 'App\\Models\\Product',
          'productbatch' => 'App\\Models\\ProductBatch',
          'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
          'productprice' => 'App\\Models\\ProductPrice',
          'productuom' => 'App\\Models\\ProductUom',
          'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
          'sale' => 'App\\Models\\Sale',
          'stockbalance' => 'App\\Models\\StockBalance',
          'store' => 'App\\Models\\Store',
          'supplier' => 'App\\Models\\Supplier',
          'taxcode' => 'App\\Models\\TaxCode',
          'taxrate' => 'App\\Models\\TaxRate',
          'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
          'user' => 'App\\Models\\User',
          'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
          'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
          'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'permission' => 'Spatie\\Permission\\Models\\Permission',
          'role' => 'Spatie\\Permission\\Models\\Role',
          'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'balance',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Support',
           'uses' => 
          array (
            'branch' => 'App\\Models\\Branch',
            'chartofaccount' => 'App\\Models\\ChartOfAccount',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'customertier' => 'App\\Models\\CustomerTier',
            'goodsreceipt' => 'App\\Models\\GoodsReceipt',
            'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
            'journalentryline' => 'App\\Models\\JournalEntryLine',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'organisation' => 'App\\Models\\Organisation',
            'pricebreak' => 'App\\Models\\PriceBreak',
            'pricelist' => 'App\\Models\\PriceList',
            'product' => 'App\\Models\\Product',
            'productbatch' => 'App\\Models\\ProductBatch',
            'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
            'productprice' => 'App\\Models\\ProductPrice',
            'productuom' => 'App\\Models\\ProductUom',
            'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
            'sale' => 'App\\Models\\Sale',
            'stockbalance' => 'App\\Models\\StockBalance',
            'store' => 'App\\Models\\Store',
            'supplier' => 'App\\Models\\Supplier',
            'taxcode' => 'App\\Models\\TaxCode',
            'taxrate' => 'App\\Models\\TaxRate',
            'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
            'user' => 'App\\Models\\User',
            'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
            'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
            'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'permission' => 'Spatie\\Permission\\Models\\Permission',
            'role' => 'Spatie\\Permission\\Models\\Role',
            'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
         'traitData' => 
        array (
          0 => 'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php',
          1 => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
          2 => 'Tests\\Support\\BuildsBlueprintWorld',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '3dfd03359550094ccf48c56176728013' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Support',
         'uses' => 
        array (
          'branch' => 'App\\Models\\Branch',
          'chartofaccount' => 'App\\Models\\ChartOfAccount',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'customertier' => 'App\\Models\\CustomerTier',
          'goodsreceipt' => 'App\\Models\\GoodsReceipt',
          'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
          'journalentryline' => 'App\\Models\\JournalEntryLine',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'organisation' => 'App\\Models\\Organisation',
          'pricebreak' => 'App\\Models\\PriceBreak',
          'pricelist' => 'App\\Models\\PriceList',
          'product' => 'App\\Models\\Product',
          'productbatch' => 'App\\Models\\ProductBatch',
          'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
          'productprice' => 'App\\Models\\ProductPrice',
          'productuom' => 'App\\Models\\ProductUom',
          'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
          'sale' => 'App\\Models\\Sale',
          'stockbalance' => 'App\\Models\\StockBalance',
          'store' => 'App\\Models\\Store',
          'supplier' => 'App\\Models\\Supplier',
          'taxcode' => 'App\\Models\\TaxCode',
          'taxrate' => 'App\\Models\\TaxRate',
          'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
          'user' => 'App\\Models\\User',
          'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
          'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
          'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'permission' => 'Spatie\\Permission\\Models\\Permission',
          'role' => 'Spatie\\Permission\\Models\\Role',
          'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'trialBalance',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Support',
           'uses' => 
          array (
            'branch' => 'App\\Models\\Branch',
            'chartofaccount' => 'App\\Models\\ChartOfAccount',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'customertier' => 'App\\Models\\CustomerTier',
            'goodsreceipt' => 'App\\Models\\GoodsReceipt',
            'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
            'journalentryline' => 'App\\Models\\JournalEntryLine',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'organisation' => 'App\\Models\\Organisation',
            'pricebreak' => 'App\\Models\\PriceBreak',
            'pricelist' => 'App\\Models\\PriceList',
            'product' => 'App\\Models\\Product',
            'productbatch' => 'App\\Models\\ProductBatch',
            'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
            'productprice' => 'App\\Models\\ProductPrice',
            'productuom' => 'App\\Models\\ProductUom',
            'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
            'sale' => 'App\\Models\\Sale',
            'stockbalance' => 'App\\Models\\StockBalance',
            'store' => 'App\\Models\\Store',
            'supplier' => 'App\\Models\\Supplier',
            'taxcode' => 'App\\Models\\TaxCode',
            'taxrate' => 'App\\Models\\TaxRate',
            'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
            'user' => 'App\\Models\\User',
            'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
            'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
            'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'permission' => 'Spatie\\Permission\\Models\\Permission',
            'role' => 'Spatie\\Permission\\Models\\Role',
            'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
         'traitData' => 
        array (
          0 => 'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php',
          1 => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
          2 => 'Tests\\Support\\BuildsBlueprintWorld',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'cf582ae6eff577f1418c3630781ca0e7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Support',
         'uses' => 
        array (
          'branch' => 'App\\Models\\Branch',
          'chartofaccount' => 'App\\Models\\ChartOfAccount',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'customertier' => 'App\\Models\\CustomerTier',
          'goodsreceipt' => 'App\\Models\\GoodsReceipt',
          'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
          'journalentryline' => 'App\\Models\\JournalEntryLine',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'organisation' => 'App\\Models\\Organisation',
          'pricebreak' => 'App\\Models\\PriceBreak',
          'pricelist' => 'App\\Models\\PriceList',
          'product' => 'App\\Models\\Product',
          'productbatch' => 'App\\Models\\ProductBatch',
          'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
          'productprice' => 'App\\Models\\ProductPrice',
          'productuom' => 'App\\Models\\ProductUom',
          'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
          'sale' => 'App\\Models\\Sale',
          'stockbalance' => 'App\\Models\\StockBalance',
          'store' => 'App\\Models\\Store',
          'supplier' => 'App\\Models\\Supplier',
          'taxcode' => 'App\\Models\\TaxCode',
          'taxrate' => 'App\\Models\\TaxRate',
          'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
          'user' => 'App\\Models\\User',
          'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
          'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
          'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'permission' => 'Spatie\\Permission\\Models\\Permission',
          'role' => 'Spatie\\Permission\\Models\\Role',
          'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'grantPermissions',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Support',
           'uses' => 
          array (
            'branch' => 'App\\Models\\Branch',
            'chartofaccount' => 'App\\Models\\ChartOfAccount',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'customertier' => 'App\\Models\\CustomerTier',
            'goodsreceipt' => 'App\\Models\\GoodsReceipt',
            'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
            'journalentryline' => 'App\\Models\\JournalEntryLine',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'organisation' => 'App\\Models\\Organisation',
            'pricebreak' => 'App\\Models\\PriceBreak',
            'pricelist' => 'App\\Models\\PriceList',
            'product' => 'App\\Models\\Product',
            'productbatch' => 'App\\Models\\ProductBatch',
            'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
            'productprice' => 'App\\Models\\ProductPrice',
            'productuom' => 'App\\Models\\ProductUom',
            'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
            'sale' => 'App\\Models\\Sale',
            'stockbalance' => 'App\\Models\\StockBalance',
            'store' => 'App\\Models\\Store',
            'supplier' => 'App\\Models\\Supplier',
            'taxcode' => 'App\\Models\\TaxCode',
            'taxrate' => 'App\\Models\\TaxRate',
            'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
            'user' => 'App\\Models\\User',
            'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
            'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
            'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'permission' => 'Spatie\\Permission\\Models\\Permission',
            'role' => 'Spatie\\Permission\\Models\\Role',
            'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
         'traitData' => 
        array (
          0 => 'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php',
          1 => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
          2 => 'Tests\\Support\\BuildsBlueprintWorld',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '38309b83762929fa858426cb8877dc1d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Support',
         'uses' => 
        array (
          'branch' => 'App\\Models\\Branch',
          'chartofaccount' => 'App\\Models\\ChartOfAccount',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'customertier' => 'App\\Models\\CustomerTier',
          'goodsreceipt' => 'App\\Models\\GoodsReceipt',
          'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
          'journalentryline' => 'App\\Models\\JournalEntryLine',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'organisation' => 'App\\Models\\Organisation',
          'pricebreak' => 'App\\Models\\PriceBreak',
          'pricelist' => 'App\\Models\\PriceList',
          'product' => 'App\\Models\\Product',
          'productbatch' => 'App\\Models\\ProductBatch',
          'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
          'productprice' => 'App\\Models\\ProductPrice',
          'productuom' => 'App\\Models\\ProductUom',
          'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
          'sale' => 'App\\Models\\Sale',
          'stockbalance' => 'App\\Models\\StockBalance',
          'store' => 'App\\Models\\Store',
          'supplier' => 'App\\Models\\Supplier',
          'taxcode' => 'App\\Models\\TaxCode',
          'taxrate' => 'App\\Models\\TaxRate',
          'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
          'user' => 'App\\Models\\User',
          'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
          'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
          'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'permission' => 'Spatie\\Permission\\Models\\Permission',
          'role' => 'Spatie\\Permission\\Models\\Role',
          'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'grantAuthority',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Support',
           'uses' => 
          array (
            'branch' => 'App\\Models\\Branch',
            'chartofaccount' => 'App\\Models\\ChartOfAccount',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'customertier' => 'App\\Models\\CustomerTier',
            'goodsreceipt' => 'App\\Models\\GoodsReceipt',
            'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
            'journalentryline' => 'App\\Models\\JournalEntryLine',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'organisation' => 'App\\Models\\Organisation',
            'pricebreak' => 'App\\Models\\PriceBreak',
            'pricelist' => 'App\\Models\\PriceList',
            'product' => 'App\\Models\\Product',
            'productbatch' => 'App\\Models\\ProductBatch',
            'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
            'productprice' => 'App\\Models\\ProductPrice',
            'productuom' => 'App\\Models\\ProductUom',
            'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
            'sale' => 'App\\Models\\Sale',
            'stockbalance' => 'App\\Models\\StockBalance',
            'store' => 'App\\Models\\Store',
            'supplier' => 'App\\Models\\Supplier',
            'taxcode' => 'App\\Models\\TaxCode',
            'taxrate' => 'App\\Models\\TaxRate',
            'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
            'user' => 'App\\Models\\User',
            'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
            'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
            'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'permission' => 'Spatie\\Permission\\Models\\Permission',
            'role' => 'Spatie\\Permission\\Models\\Role',
            'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
         'traitData' => 
        array (
          0 => 'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php',
          1 => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
          2 => 'Tests\\Support\\BuildsBlueprintWorld',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '7eb17ced30815996a9bc9d8626c276db' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Support',
         'uses' => 
        array (
          'branch' => 'App\\Models\\Branch',
          'chartofaccount' => 'App\\Models\\ChartOfAccount',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'customertier' => 'App\\Models\\CustomerTier',
          'goodsreceipt' => 'App\\Models\\GoodsReceipt',
          'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
          'journalentryline' => 'App\\Models\\JournalEntryLine',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'organisation' => 'App\\Models\\Organisation',
          'pricebreak' => 'App\\Models\\PriceBreak',
          'pricelist' => 'App\\Models\\PriceList',
          'product' => 'App\\Models\\Product',
          'productbatch' => 'App\\Models\\ProductBatch',
          'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
          'productprice' => 'App\\Models\\ProductPrice',
          'productuom' => 'App\\Models\\ProductUom',
          'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
          'sale' => 'App\\Models\\Sale',
          'stockbalance' => 'App\\Models\\StockBalance',
          'store' => 'App\\Models\\Store',
          'supplier' => 'App\\Models\\Supplier',
          'taxcode' => 'App\\Models\\TaxCode',
          'taxrate' => 'App\\Models\\TaxRate',
          'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
          'user' => 'App\\Models\\User',
          'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
          'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
          'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'permission' => 'Spatie\\Permission\\Models\\Permission',
          'role' => 'Spatie\\Permission\\Models\\Role',
          'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'discountPolicy',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Support',
           'uses' => 
          array (
            'branch' => 'App\\Models\\Branch',
            'chartofaccount' => 'App\\Models\\ChartOfAccount',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'customertier' => 'App\\Models\\CustomerTier',
            'goodsreceipt' => 'App\\Models\\GoodsReceipt',
            'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
            'journalentryline' => 'App\\Models\\JournalEntryLine',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'organisation' => 'App\\Models\\Organisation',
            'pricebreak' => 'App\\Models\\PriceBreak',
            'pricelist' => 'App\\Models\\PriceList',
            'product' => 'App\\Models\\Product',
            'productbatch' => 'App\\Models\\ProductBatch',
            'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
            'productprice' => 'App\\Models\\ProductPrice',
            'productuom' => 'App\\Models\\ProductUom',
            'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
            'sale' => 'App\\Models\\Sale',
            'stockbalance' => 'App\\Models\\StockBalance',
            'store' => 'App\\Models\\Store',
            'supplier' => 'App\\Models\\Supplier',
            'taxcode' => 'App\\Models\\TaxCode',
            'taxrate' => 'App\\Models\\TaxRate',
            'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
            'user' => 'App\\Models\\User',
            'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
            'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
            'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'permission' => 'Spatie\\Permission\\Models\\Permission',
            'role' => 'Spatie\\Permission\\Models\\Role',
            'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
         'traitData' => 
        array (
          0 => 'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php',
          1 => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
          2 => 'Tests\\Support\\BuildsBlueprintWorld',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'ed23ab597fa37dd0c463676bd0bdda7c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Support',
         'uses' => 
        array (
          'branch' => 'App\\Models\\Branch',
          'chartofaccount' => 'App\\Models\\ChartOfAccount',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'customertier' => 'App\\Models\\CustomerTier',
          'goodsreceipt' => 'App\\Models\\GoodsReceipt',
          'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
          'journalentryline' => 'App\\Models\\JournalEntryLine',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'organisation' => 'App\\Models\\Organisation',
          'pricebreak' => 'App\\Models\\PriceBreak',
          'pricelist' => 'App\\Models\\PriceList',
          'product' => 'App\\Models\\Product',
          'productbatch' => 'App\\Models\\ProductBatch',
          'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
          'productprice' => 'App\\Models\\ProductPrice',
          'productuom' => 'App\\Models\\ProductUom',
          'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
          'sale' => 'App\\Models\\Sale',
          'stockbalance' => 'App\\Models\\StockBalance',
          'store' => 'App\\Models\\Store',
          'supplier' => 'App\\Models\\Supplier',
          'taxcode' => 'App\\Models\\TaxCode',
          'taxrate' => 'App\\Models\\TaxRate',
          'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
          'user' => 'App\\Models\\User',
          'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
          'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
          'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'permission' => 'Spatie\\Permission\\Models\\Permission',
          'role' => 'Spatie\\Permission\\Models\\Role',
          'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'tierBWithBreaks',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Support',
           'uses' => 
          array (
            'branch' => 'App\\Models\\Branch',
            'chartofaccount' => 'App\\Models\\ChartOfAccount',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'customertier' => 'App\\Models\\CustomerTier',
            'goodsreceipt' => 'App\\Models\\GoodsReceipt',
            'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
            'journalentryline' => 'App\\Models\\JournalEntryLine',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'organisation' => 'App\\Models\\Organisation',
            'pricebreak' => 'App\\Models\\PriceBreak',
            'pricelist' => 'App\\Models\\PriceList',
            'product' => 'App\\Models\\Product',
            'productbatch' => 'App\\Models\\ProductBatch',
            'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
            'productprice' => 'App\\Models\\ProductPrice',
            'productuom' => 'App\\Models\\ProductUom',
            'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
            'sale' => 'App\\Models\\Sale',
            'stockbalance' => 'App\\Models\\StockBalance',
            'store' => 'App\\Models\\Store',
            'supplier' => 'App\\Models\\Supplier',
            'taxcode' => 'App\\Models\\TaxCode',
            'taxrate' => 'App\\Models\\TaxRate',
            'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
            'user' => 'App\\Models\\User',
            'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
            'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
            'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'permission' => 'Spatie\\Permission\\Models\\Permission',
            'role' => 'Spatie\\Permission\\Models\\Role',
            'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
         'traitData' => 
        array (
          0 => 'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php',
          1 => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
          2 => 'Tests\\Support\\BuildsBlueprintWorld',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '2dab4d528ae5d458a5a2472b36a7e442' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Support',
         'uses' => 
        array (
          'branch' => 'App\\Models\\Branch',
          'chartofaccount' => 'App\\Models\\ChartOfAccount',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'customertier' => 'App\\Models\\CustomerTier',
          'goodsreceipt' => 'App\\Models\\GoodsReceipt',
          'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
          'journalentryline' => 'App\\Models\\JournalEntryLine',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'organisation' => 'App\\Models\\Organisation',
          'pricebreak' => 'App\\Models\\PriceBreak',
          'pricelist' => 'App\\Models\\PriceList',
          'product' => 'App\\Models\\Product',
          'productbatch' => 'App\\Models\\ProductBatch',
          'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
          'productprice' => 'App\\Models\\ProductPrice',
          'productuom' => 'App\\Models\\ProductUom',
          'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
          'sale' => 'App\\Models\\Sale',
          'stockbalance' => 'App\\Models\\StockBalance',
          'store' => 'App\\Models\\Store',
          'supplier' => 'App\\Models\\Supplier',
          'taxcode' => 'App\\Models\\TaxCode',
          'taxrate' => 'App\\Models\\TaxRate',
          'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
          'user' => 'App\\Models\\User',
          'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
          'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
          'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'permission' => 'Spatie\\Permission\\Models\\Permission',
          'role' => 'Spatie\\Permission\\Models\\Role',
          'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'vat16',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Support',
           'uses' => 
          array (
            'branch' => 'App\\Models\\Branch',
            'chartofaccount' => 'App\\Models\\ChartOfAccount',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'customertier' => 'App\\Models\\CustomerTier',
            'goodsreceipt' => 'App\\Models\\GoodsReceipt',
            'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
            'journalentryline' => 'App\\Models\\JournalEntryLine',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'organisation' => 'App\\Models\\Organisation',
            'pricebreak' => 'App\\Models\\PriceBreak',
            'pricelist' => 'App\\Models\\PriceList',
            'product' => 'App\\Models\\Product',
            'productbatch' => 'App\\Models\\ProductBatch',
            'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
            'productprice' => 'App\\Models\\ProductPrice',
            'productuom' => 'App\\Models\\ProductUom',
            'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
            'sale' => 'App\\Models\\Sale',
            'stockbalance' => 'App\\Models\\StockBalance',
            'store' => 'App\\Models\\Store',
            'supplier' => 'App\\Models\\Supplier',
            'taxcode' => 'App\\Models\\TaxCode',
            'taxrate' => 'App\\Models\\TaxRate',
            'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
            'user' => 'App\\Models\\User',
            'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
            'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
            'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'permission' => 'Spatie\\Permission\\Models\\Permission',
            'role' => 'Spatie\\Permission\\Models\\Role',
            'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
         'traitData' => 
        array (
          0 => 'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php',
          1 => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
          2 => 'Tests\\Support\\BuildsBlueprintWorld',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'd72e12f1f27fc1844bc499673aeb2b4b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Support',
         'uses' => 
        array (
          'branch' => 'App\\Models\\Branch',
          'chartofaccount' => 'App\\Models\\ChartOfAccount',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'customertier' => 'App\\Models\\CustomerTier',
          'goodsreceipt' => 'App\\Models\\GoodsReceipt',
          'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
          'journalentryline' => 'App\\Models\\JournalEntryLine',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'organisation' => 'App\\Models\\Organisation',
          'pricebreak' => 'App\\Models\\PriceBreak',
          'pricelist' => 'App\\Models\\PriceList',
          'product' => 'App\\Models\\Product',
          'productbatch' => 'App\\Models\\ProductBatch',
          'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
          'productprice' => 'App\\Models\\ProductPrice',
          'productuom' => 'App\\Models\\ProductUom',
          'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
          'sale' => 'App\\Models\\Sale',
          'stockbalance' => 'App\\Models\\StockBalance',
          'store' => 'App\\Models\\Store',
          'supplier' => 'App\\Models\\Supplier',
          'taxcode' => 'App\\Models\\TaxCode',
          'taxrate' => 'App\\Models\\TaxRate',
          'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
          'user' => 'App\\Models\\User',
          'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
          'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
          'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'permission' => 'Spatie\\Permission\\Models\\Permission',
          'role' => 'Spatie\\Permission\\Models\\Role',
          'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'quoteRequest',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Support',
           'uses' => 
          array (
            'branch' => 'App\\Models\\Branch',
            'chartofaccount' => 'App\\Models\\ChartOfAccount',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'customertier' => 'App\\Models\\CustomerTier',
            'goodsreceipt' => 'App\\Models\\GoodsReceipt',
            'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
            'journalentryline' => 'App\\Models\\JournalEntryLine',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'organisation' => 'App\\Models\\Organisation',
            'pricebreak' => 'App\\Models\\PriceBreak',
            'pricelist' => 'App\\Models\\PriceList',
            'product' => 'App\\Models\\Product',
            'productbatch' => 'App\\Models\\ProductBatch',
            'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
            'productprice' => 'App\\Models\\ProductPrice',
            'productuom' => 'App\\Models\\ProductUom',
            'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
            'sale' => 'App\\Models\\Sale',
            'stockbalance' => 'App\\Models\\StockBalance',
            'store' => 'App\\Models\\Store',
            'supplier' => 'App\\Models\\Supplier',
            'taxcode' => 'App\\Models\\TaxCode',
            'taxrate' => 'App\\Models\\TaxRate',
            'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
            'user' => 'App\\Models\\User',
            'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
            'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
            'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'permission' => 'Spatie\\Permission\\Models\\Permission',
            'role' => 'Spatie\\Permission\\Models\\Role',
            'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
         'traitData' => 
        array (
          0 => 'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php',
          1 => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
          2 => 'Tests\\Support\\BuildsBlueprintWorld',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '0f8b973dcdf3cd0da4192f67d4936a39' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Support',
         'uses' => 
        array (
          'branch' => 'App\\Models\\Branch',
          'chartofaccount' => 'App\\Models\\ChartOfAccount',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'customertier' => 'App\\Models\\CustomerTier',
          'goodsreceipt' => 'App\\Models\\GoodsReceipt',
          'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
          'journalentryline' => 'App\\Models\\JournalEntryLine',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'organisation' => 'App\\Models\\Organisation',
          'pricebreak' => 'App\\Models\\PriceBreak',
          'pricelist' => 'App\\Models\\PriceList',
          'product' => 'App\\Models\\Product',
          'productbatch' => 'App\\Models\\ProductBatch',
          'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
          'productprice' => 'App\\Models\\ProductPrice',
          'productuom' => 'App\\Models\\ProductUom',
          'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
          'sale' => 'App\\Models\\Sale',
          'stockbalance' => 'App\\Models\\StockBalance',
          'store' => 'App\\Models\\Store',
          'supplier' => 'App\\Models\\Supplier',
          'taxcode' => 'App\\Models\\TaxCode',
          'taxrate' => 'App\\Models\\TaxRate',
          'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
          'user' => 'App\\Models\\User',
          'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
          'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
          'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'permission' => 'Spatie\\Permission\\Models\\Permission',
          'role' => 'Spatie\\Permission\\Models\\Role',
          'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'quoteLine',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Support',
           'uses' => 
          array (
            'branch' => 'App\\Models\\Branch',
            'chartofaccount' => 'App\\Models\\ChartOfAccount',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'customertier' => 'App\\Models\\CustomerTier',
            'goodsreceipt' => 'App\\Models\\GoodsReceipt',
            'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
            'journalentryline' => 'App\\Models\\JournalEntryLine',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'organisation' => 'App\\Models\\Organisation',
            'pricebreak' => 'App\\Models\\PriceBreak',
            'pricelist' => 'App\\Models\\PriceList',
            'product' => 'App\\Models\\Product',
            'productbatch' => 'App\\Models\\ProductBatch',
            'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
            'productprice' => 'App\\Models\\ProductPrice',
            'productuom' => 'App\\Models\\ProductUom',
            'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
            'sale' => 'App\\Models\\Sale',
            'stockbalance' => 'App\\Models\\StockBalance',
            'store' => 'App\\Models\\Store',
            'supplier' => 'App\\Models\\Supplier',
            'taxcode' => 'App\\Models\\TaxCode',
            'taxrate' => 'App\\Models\\TaxRate',
            'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
            'user' => 'App\\Models\\User',
            'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
            'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
            'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'permission' => 'Spatie\\Permission\\Models\\Permission',
            'role' => 'Spatie\\Permission\\Models\\Role',
            'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
         'traitData' => 
        array (
          0 => 'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php',
          1 => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
          2 => 'Tests\\Support\\BuildsBlueprintWorld',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '453f89d67ea6815e2e291584e8694db7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Support',
         'uses' => 
        array (
          'branch' => 'App\\Models\\Branch',
          'chartofaccount' => 'App\\Models\\ChartOfAccount',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'customertier' => 'App\\Models\\CustomerTier',
          'goodsreceipt' => 'App\\Models\\GoodsReceipt',
          'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
          'journalentryline' => 'App\\Models\\JournalEntryLine',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'organisation' => 'App\\Models\\Organisation',
          'pricebreak' => 'App\\Models\\PriceBreak',
          'pricelist' => 'App\\Models\\PriceList',
          'product' => 'App\\Models\\Product',
          'productbatch' => 'App\\Models\\ProductBatch',
          'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
          'productprice' => 'App\\Models\\ProductPrice',
          'productuom' => 'App\\Models\\ProductUom',
          'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
          'sale' => 'App\\Models\\Sale',
          'stockbalance' => 'App\\Models\\StockBalance',
          'store' => 'App\\Models\\Store',
          'supplier' => 'App\\Models\\Supplier',
          'taxcode' => 'App\\Models\\TaxCode',
          'taxrate' => 'App\\Models\\TaxRate',
          'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
          'user' => 'App\\Models\\User',
          'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
          'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
          'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'permission' => 'Spatie\\Permission\\Models\\Permission',
          'role' => 'Spatie\\Permission\\Models\\Role',
          'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'accountBalance',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Support',
           'uses' => 
          array (
            'branch' => 'App\\Models\\Branch',
            'chartofaccount' => 'App\\Models\\ChartOfAccount',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'customertier' => 'App\\Models\\CustomerTier',
            'goodsreceipt' => 'App\\Models\\GoodsReceipt',
            'goodsreceiptline' => 'App\\Models\\GoodsReceiptLine',
            'journalentryline' => 'App\\Models\\JournalEntryLine',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'organisation' => 'App\\Models\\Organisation',
            'pricebreak' => 'App\\Models\\PriceBreak',
            'pricelist' => 'App\\Models\\PriceList',
            'product' => 'App\\Models\\Product',
            'productbatch' => 'App\\Models\\ProductBatch',
            'productdiscountpolicy' => 'App\\Models\\ProductDiscountPolicy',
            'productprice' => 'App\\Models\\ProductPrice',
            'productuom' => 'App\\Models\\ProductUom',
            'rolediscountauthority' => 'App\\Models\\RoleDiscountAuthority',
            'sale' => 'App\\Models\\Sale',
            'stockbalance' => 'App\\Models\\StockBalance',
            'store' => 'App\\Models\\Store',
            'supplier' => 'App\\Models\\Supplier',
            'taxcode' => 'App\\Models\\TaxCode',
            'taxrate' => 'App\\Models\\TaxRate',
            'unitofmeasure' => 'App\\Models\\UnitOfMeasure',
            'user' => 'App\\Models\\User',
            'goodsreceiptservice' => 'App\\Services\\Procurement\\GoodsReceiptService',
            'checkoutservice' => 'App\\Services\\Sales\\CheckoutService',
            'chartofaccountsseeder' => 'Database\\Seeders\\ChartOfAccountsSeeder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'permission' => 'Spatie\\Permission\\Models\\Permission',
            'role' => 'Spatie\\Permission\\Models\\Role',
            'permissionregistrar' => 'Spatie\\Permission\\PermissionRegistrar',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Tests\\Support\\BuildsBlueprintWorld',
         'traitData' => 
        array (
          0 => 'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php',
          1 => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
          2 => 'Tests\\Support\\BuildsBlueprintWorld',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '3cb25800fdfc771aef4780fd2c3d553b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Feature\\Api',
         'uses' => 
        array (
          'scheduledreportmail' => 'App\\Mail\\ScheduledReportMail',
          'auditlog' => 'App\\Models\\AuditLog',
          'scheduledreport' => 'App\\Models\\ScheduledReport',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'mail' => 'Illuminate\\Support\\Facades\\Mail',
          'sanctum' => 'Laravel\\Sanctum\\Sanctum',
          'buildsblueprintworld' => 'Tests\\Support\\BuildsBlueprintWorld',
          'testcase' => 'Tests\\TestCase',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'setUp',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Feature\\Api',
           'uses' => 
          array (
            'scheduledreportmail' => 'App\\Mail\\ScheduledReportMail',
            'auditlog' => 'App\\Models\\AuditLog',
            'scheduledreport' => 'App\\Models\\ScheduledReport',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'mail' => 'Illuminate\\Support\\Facades\\Mail',
            'sanctum' => 'Laravel\\Sanctum\\Sanctum',
            'buildsblueprintworld' => 'Tests\\Support\\BuildsBlueprintWorld',
            'testcase' => 'Tests\\TestCase',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '947854573696f1130acc1f7f21cc8f63' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Feature\\Api',
         'uses' => 
        array (
          'scheduledreportmail' => 'App\\Mail\\ScheduledReportMail',
          'auditlog' => 'App\\Models\\AuditLog',
          'scheduledreport' => 'App\\Models\\ScheduledReport',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'mail' => 'Illuminate\\Support\\Facades\\Mail',
          'sanctum' => 'Laravel\\Sanctum\\Sanctum',
          'buildsblueprintworld' => 'Tests\\Support\\BuildsBlueprintWorld',
          'testcase' => 'Tests\\TestCase',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'tearDown',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Feature\\Api',
           'uses' => 
          array (
            'scheduledreportmail' => 'App\\Mail\\ScheduledReportMail',
            'auditlog' => 'App\\Models\\AuditLog',
            'scheduledreport' => 'App\\Models\\ScheduledReport',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'mail' => 'Illuminate\\Support\\Facades\\Mail',
            'sanctum' => 'Laravel\\Sanctum\\Sanctum',
            'buildsblueprintworld' => 'Tests\\Support\\BuildsBlueprintWorld',
            'testcase' => 'Tests\\TestCase',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'f51a6b2f1c541a4d5bdf503e161d2da6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Feature\\Api',
         'uses' => 
        array (
          'scheduledreportmail' => 'App\\Mail\\ScheduledReportMail',
          'auditlog' => 'App\\Models\\AuditLog',
          'scheduledreport' => 'App\\Models\\ScheduledReport',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'mail' => 'Illuminate\\Support\\Facades\\Mail',
          'sanctum' => 'Laravel\\Sanctum\\Sanctum',
          'buildsblueprintworld' => 'Tests\\Support\\BuildsBlueprintWorld',
          'testcase' => 'Tests\\TestCase',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'test_a_schedule_is_created_with_its_next_run_and_the_command_mails_it_when_due',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Feature\\Api',
           'uses' => 
          array (
            'scheduledreportmail' => 'App\\Mail\\ScheduledReportMail',
            'auditlog' => 'App\\Models\\AuditLog',
            'scheduledreport' => 'App\\Models\\ScheduledReport',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'mail' => 'Illuminate\\Support\\Facades\\Mail',
            'sanctum' => 'Laravel\\Sanctum\\Sanctum',
            'buildsblueprintworld' => 'Tests\\Support\\BuildsBlueprintWorld',
            'testcase' => 'Tests\\TestCase',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '2f93250074053be34625c29c68ee6ca8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Feature\\Api',
         'uses' => 
        array (
          'scheduledreportmail' => 'App\\Mail\\ScheduledReportMail',
          'auditlog' => 'App\\Models\\AuditLog',
          'scheduledreport' => 'App\\Models\\ScheduledReport',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'mail' => 'Illuminate\\Support\\Facades\\Mail',
          'sanctum' => 'Laravel\\Sanctum\\Sanctum',
          'buildsblueprintworld' => 'Tests\\Support\\BuildsBlueprintWorld',
          'testcase' => 'Tests\\TestCase',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'test_run_now_sends_immediately_and_a_failure_is_recorded_not_thrown',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Feature\\Api',
           'uses' => 
          array (
            'scheduledreportmail' => 'App\\Mail\\ScheduledReportMail',
            'auditlog' => 'App\\Models\\AuditLog',
            'scheduledreport' => 'App\\Models\\ScheduledReport',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'mail' => 'Illuminate\\Support\\Facades\\Mail',
            'sanctum' => 'Laravel\\Sanctum\\Sanctum',
            'buildsblueprintworld' => 'Tests\\Support\\BuildsBlueprintWorld',
            'testcase' => 'Tests\\TestCase',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'ddf1113b3497e7f6fd6fd1def6ad6a2f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Tests\\Feature\\Api',
         'uses' => 
        array (
          'scheduledreportmail' => 'App\\Mail\\ScheduledReportMail',
          'auditlog' => 'App\\Models\\AuditLog',
          'scheduledreport' => 'App\\Models\\ScheduledReport',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'mail' => 'Illuminate\\Support\\Facades\\Mail',
          'sanctum' => 'Laravel\\Sanctum\\Sanctum',
          'buildsblueprintworld' => 'Tests\\Support\\BuildsBlueprintWorld',
          'testcase' => 'Tests\\TestCase',
        ),
         'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
         'functionName' => 'test_schedules_are_validated_edited_deactivated_and_deleted',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Tests\\Feature\\Api',
           'uses' => 
          array (
            'scheduledreportmail' => 'App\\Mail\\ScheduledReportMail',
            'auditlog' => 'App\\Models\\AuditLog',
            'scheduledreport' => 'App\\Models\\ScheduledReport',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'mail' => 'Illuminate\\Support\\Facades\\Mail',
            'sanctum' => 'Laravel\\Sanctum\\Sanctum',
            'buildsblueprintworld' => 'Tests\\Support\\BuildsBlueprintWorld',
            'testcase' => 'Tests\\TestCase',
          ),
           'className' => 'Tests\\Feature\\Api\\ScheduledReportHttpTest',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
    ),
    1 => 
    array (
      'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Feature\\Api\\ScheduledReportHttpTest.php' => '6de8206e069591d3fd1d0cb530367cea4704f9fff80471d5c57ef1d495144043',
      'C:\\xampp\\htdocs\\pharmacy_erp\\tests\\Support\\BuildsBlueprintWorld.php' => '9571e4870d8dc0829fd60abb8b10b58c810ad909ffcc82d06843a0ad7fc52be3',
    ),
  ),
));