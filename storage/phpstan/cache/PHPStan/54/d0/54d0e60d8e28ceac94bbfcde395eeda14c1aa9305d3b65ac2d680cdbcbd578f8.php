<?php declare(strict_types = 1);

// ftm-C:\xampp\htdocs\pharmacy_erp\app\Services\Sales\DispatchService.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      'd39aaf6711ea93fcddf7625116e45a9d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Sales',
         'uses' => 
        array (
          'accountsreceivable' => 'App\\Models\\AccountsReceivable',
          'auditlog' => 'App\\Models\\AuditLog',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'deliverynote' => 'App\\Models\\DeliveryNote',
          'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
          'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'payment' => 'App\\Models\\Payment',
          'paymentallocation' => 'App\\Models\\PaymentAllocation',
          'pickinglist' => 'App\\Models\\PickingList',
          'pickinglistline' => 'App\\Models\\PickingListLine',
          'productuom' => 'App\\Models\\ProductUom',
          'sale' => 'App\\Models\\Sale',
          'saleline' => 'App\\Models\\SaleLine',
          'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
          'salesorderline' => 'App\\Models\\SalesOrderLine',
          'stockbalance' => 'App\\Models\\StockBalance',
          'stockreservation' => 'App\\Models\\StockReservation',
          'journalposter' => 'App\\Services\\Finance\\JournalPoster',
          'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
          'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
          'etimsservice' => 'App\\Services\\Tax\\EtimsService',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
        ),
         'className' => 'App\\Services\\Sales\\InvalidDeliveryNoteStatusException',
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
      'a7887a23ac51ac5e8fc33936e4aaff74' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Sales',
         'uses' => 
        array (
          'accountsreceivable' => 'App\\Models\\AccountsReceivable',
          'auditlog' => 'App\\Models\\AuditLog',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'deliverynote' => 'App\\Models\\DeliveryNote',
          'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
          'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'payment' => 'App\\Models\\Payment',
          'paymentallocation' => 'App\\Models\\PaymentAllocation',
          'pickinglist' => 'App\\Models\\PickingList',
          'pickinglistline' => 'App\\Models\\PickingListLine',
          'productuom' => 'App\\Models\\ProductUom',
          'sale' => 'App\\Models\\Sale',
          'saleline' => 'App\\Models\\SaleLine',
          'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
          'salesorderline' => 'App\\Models\\SalesOrderLine',
          'stockbalance' => 'App\\Models\\StockBalance',
          'stockreservation' => 'App\\Models\\StockReservation',
          'journalposter' => 'App\\Services\\Finance\\JournalPoster',
          'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
          'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
          'etimsservice' => 'App\\Services\\Tax\\EtimsService',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
        ),
         'className' => 'App\\Services\\Sales\\DispatchService',
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
      '0d979b085510ec80022439b76229aeda' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Sales',
         'uses' => 
        array (
          'accountsreceivable' => 'App\\Models\\AccountsReceivable',
          'auditlog' => 'App\\Models\\AuditLog',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'deliverynote' => 'App\\Models\\DeliveryNote',
          'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
          'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'payment' => 'App\\Models\\Payment',
          'paymentallocation' => 'App\\Models\\PaymentAllocation',
          'pickinglist' => 'App\\Models\\PickingList',
          'pickinglistline' => 'App\\Models\\PickingListLine',
          'productuom' => 'App\\Models\\ProductUom',
          'sale' => 'App\\Models\\Sale',
          'saleline' => 'App\\Models\\SaleLine',
          'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
          'salesorderline' => 'App\\Models\\SalesOrderLine',
          'stockbalance' => 'App\\Models\\StockBalance',
          'stockreservation' => 'App\\Models\\StockReservation',
          'journalposter' => 'App\\Services\\Finance\\JournalPoster',
          'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
          'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
          'etimsservice' => 'App\\Services\\Tax\\EtimsService',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
        ),
         'className' => 'App\\Services\\Sales\\DispatchService',
         'functionName' => '__construct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Services\\Sales',
           'uses' => 
          array (
            'accountsreceivable' => 'App\\Models\\AccountsReceivable',
            'auditlog' => 'App\\Models\\AuditLog',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'deliverynote' => 'App\\Models\\DeliveryNote',
            'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
            'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'payment' => 'App\\Models\\Payment',
            'paymentallocation' => 'App\\Models\\PaymentAllocation',
            'pickinglist' => 'App\\Models\\PickingList',
            'pickinglistline' => 'App\\Models\\PickingListLine',
            'productuom' => 'App\\Models\\ProductUom',
            'sale' => 'App\\Models\\Sale',
            'saleline' => 'App\\Models\\SaleLine',
            'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
            'salesorderline' => 'App\\Models\\SalesOrderLine',
            'stockbalance' => 'App\\Models\\StockBalance',
            'stockreservation' => 'App\\Models\\StockReservation',
            'journalposter' => 'App\\Services\\Finance\\JournalPoster',
            'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
            'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
            'etimsservice' => 'App\\Services\\Tax\\EtimsService',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
          ),
           'className' => 'App\\Services\\Sales\\DispatchService',
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
      '86bbbf2d3f370dba21d32c976d5977a4' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Sales',
         'uses' => 
        array (
          'accountsreceivable' => 'App\\Models\\AccountsReceivable',
          'auditlog' => 'App\\Models\\AuditLog',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'deliverynote' => 'App\\Models\\DeliveryNote',
          'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
          'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'payment' => 'App\\Models\\Payment',
          'paymentallocation' => 'App\\Models\\PaymentAllocation',
          'pickinglist' => 'App\\Models\\PickingList',
          'pickinglistline' => 'App\\Models\\PickingListLine',
          'productuom' => 'App\\Models\\ProductUom',
          'sale' => 'App\\Models\\Sale',
          'saleline' => 'App\\Models\\SaleLine',
          'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
          'salesorderline' => 'App\\Models\\SalesOrderLine',
          'stockbalance' => 'App\\Models\\StockBalance',
          'stockreservation' => 'App\\Models\\StockReservation',
          'journalposter' => 'App\\Services\\Finance\\JournalPoster',
          'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
          'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
          'etimsservice' => 'App\\Services\\Tax\\EtimsService',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
        ),
         'className' => 'App\\Services\\Sales\\DispatchService',
         'functionName' => 'createFromPickingList',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Services\\Sales',
           'uses' => 
          array (
            'accountsreceivable' => 'App\\Models\\AccountsReceivable',
            'auditlog' => 'App\\Models\\AuditLog',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'deliverynote' => 'App\\Models\\DeliveryNote',
            'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
            'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'payment' => 'App\\Models\\Payment',
            'paymentallocation' => 'App\\Models\\PaymentAllocation',
            'pickinglist' => 'App\\Models\\PickingList',
            'pickinglistline' => 'App\\Models\\PickingListLine',
            'productuom' => 'App\\Models\\ProductUom',
            'sale' => 'App\\Models\\Sale',
            'saleline' => 'App\\Models\\SaleLine',
            'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
            'salesorderline' => 'App\\Models\\SalesOrderLine',
            'stockbalance' => 'App\\Models\\StockBalance',
            'stockreservation' => 'App\\Models\\StockReservation',
            'journalposter' => 'App\\Services\\Finance\\JournalPoster',
            'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
            'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
            'etimsservice' => 'App\\Services\\Tax\\EtimsService',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
          ),
           'className' => 'App\\Services\\Sales\\DispatchService',
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
      '9b1090083691330aac0d8b1df498e589' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Sales',
         'uses' => 
        array (
          'accountsreceivable' => 'App\\Models\\AccountsReceivable',
          'auditlog' => 'App\\Models\\AuditLog',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'deliverynote' => 'App\\Models\\DeliveryNote',
          'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
          'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'payment' => 'App\\Models\\Payment',
          'paymentallocation' => 'App\\Models\\PaymentAllocation',
          'pickinglist' => 'App\\Models\\PickingList',
          'pickinglistline' => 'App\\Models\\PickingListLine',
          'productuom' => 'App\\Models\\ProductUom',
          'sale' => 'App\\Models\\Sale',
          'saleline' => 'App\\Models\\SaleLine',
          'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
          'salesorderline' => 'App\\Models\\SalesOrderLine',
          'stockbalance' => 'App\\Models\\StockBalance',
          'stockreservation' => 'App\\Models\\StockReservation',
          'journalposter' => 'App\\Services\\Finance\\JournalPoster',
          'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
          'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
          'etimsservice' => 'App\\Services\\Tax\\EtimsService',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
        ),
         'className' => 'App\\Services\\Sales\\DispatchService',
         'functionName' => 'dispatch',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Services\\Sales',
           'uses' => 
          array (
            'accountsreceivable' => 'App\\Models\\AccountsReceivable',
            'auditlog' => 'App\\Models\\AuditLog',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'deliverynote' => 'App\\Models\\DeliveryNote',
            'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
            'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'payment' => 'App\\Models\\Payment',
            'paymentallocation' => 'App\\Models\\PaymentAllocation',
            'pickinglist' => 'App\\Models\\PickingList',
            'pickinglistline' => 'App\\Models\\PickingListLine',
            'productuom' => 'App\\Models\\ProductUom',
            'sale' => 'App\\Models\\Sale',
            'saleline' => 'App\\Models\\SaleLine',
            'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
            'salesorderline' => 'App\\Models\\SalesOrderLine',
            'stockbalance' => 'App\\Models\\StockBalance',
            'stockreservation' => 'App\\Models\\StockReservation',
            'journalposter' => 'App\\Services\\Finance\\JournalPoster',
            'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
            'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
            'etimsservice' => 'App\\Services\\Tax\\EtimsService',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
          ),
           'className' => 'App\\Services\\Sales\\DispatchService',
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
      '458e855dc6fcd7bbd1f33c00375a92b0' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Sales',
         'uses' => 
        array (
          'accountsreceivable' => 'App\\Models\\AccountsReceivable',
          'auditlog' => 'App\\Models\\AuditLog',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'deliverynote' => 'App\\Models\\DeliveryNote',
          'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
          'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'payment' => 'App\\Models\\Payment',
          'paymentallocation' => 'App\\Models\\PaymentAllocation',
          'pickinglist' => 'App\\Models\\PickingList',
          'pickinglistline' => 'App\\Models\\PickingListLine',
          'productuom' => 'App\\Models\\ProductUom',
          'sale' => 'App\\Models\\Sale',
          'saleline' => 'App\\Models\\SaleLine',
          'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
          'salesorderline' => 'App\\Models\\SalesOrderLine',
          'stockbalance' => 'App\\Models\\StockBalance',
          'stockreservation' => 'App\\Models\\StockReservation',
          'journalposter' => 'App\\Services\\Finance\\JournalPoster',
          'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
          'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
          'etimsservice' => 'App\\Services\\Tax\\EtimsService',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
        ),
         'className' => 'App\\Services\\Sales\\DispatchService',
         'functionName' => 'confirmDelivery',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Services\\Sales',
           'uses' => 
          array (
            'accountsreceivable' => 'App\\Models\\AccountsReceivable',
            'auditlog' => 'App\\Models\\AuditLog',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'deliverynote' => 'App\\Models\\DeliveryNote',
            'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
            'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'payment' => 'App\\Models\\Payment',
            'paymentallocation' => 'App\\Models\\PaymentAllocation',
            'pickinglist' => 'App\\Models\\PickingList',
            'pickinglistline' => 'App\\Models\\PickingListLine',
            'productuom' => 'App\\Models\\ProductUom',
            'sale' => 'App\\Models\\Sale',
            'saleline' => 'App\\Models\\SaleLine',
            'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
            'salesorderline' => 'App\\Models\\SalesOrderLine',
            'stockbalance' => 'App\\Models\\StockBalance',
            'stockreservation' => 'App\\Models\\StockReservation',
            'journalposter' => 'App\\Services\\Finance\\JournalPoster',
            'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
            'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
            'etimsservice' => 'App\\Services\\Tax\\EtimsService',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
          ),
           'className' => 'App\\Services\\Sales\\DispatchService',
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
      '423c419c1811a9855fb8cb5804ba995d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Sales',
         'uses' => 
        array (
          'accountsreceivable' => 'App\\Models\\AccountsReceivable',
          'auditlog' => 'App\\Models\\AuditLog',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'deliverynote' => 'App\\Models\\DeliveryNote',
          'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
          'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'payment' => 'App\\Models\\Payment',
          'paymentallocation' => 'App\\Models\\PaymentAllocation',
          'pickinglist' => 'App\\Models\\PickingList',
          'pickinglistline' => 'App\\Models\\PickingListLine',
          'productuom' => 'App\\Models\\ProductUom',
          'sale' => 'App\\Models\\Sale',
          'saleline' => 'App\\Models\\SaleLine',
          'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
          'salesorderline' => 'App\\Models\\SalesOrderLine',
          'stockbalance' => 'App\\Models\\StockBalance',
          'stockreservation' => 'App\\Models\\StockReservation',
          'journalposter' => 'App\\Services\\Finance\\JournalPoster',
          'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
          'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
          'etimsservice' => 'App\\Services\\Tax\\EtimsService',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
        ),
         'className' => 'App\\Services\\Sales\\DispatchService',
         'functionName' => 'buildDeliveryNoteLine',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Services\\Sales',
           'uses' => 
          array (
            'accountsreceivable' => 'App\\Models\\AccountsReceivable',
            'auditlog' => 'App\\Models\\AuditLog',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'deliverynote' => 'App\\Models\\DeliveryNote',
            'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
            'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'payment' => 'App\\Models\\Payment',
            'paymentallocation' => 'App\\Models\\PaymentAllocation',
            'pickinglist' => 'App\\Models\\PickingList',
            'pickinglistline' => 'App\\Models\\PickingListLine',
            'productuom' => 'App\\Models\\ProductUom',
            'sale' => 'App\\Models\\Sale',
            'saleline' => 'App\\Models\\SaleLine',
            'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
            'salesorderline' => 'App\\Models\\SalesOrderLine',
            'stockbalance' => 'App\\Models\\StockBalance',
            'stockreservation' => 'App\\Models\\StockReservation',
            'journalposter' => 'App\\Services\\Finance\\JournalPoster',
            'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
            'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
            'etimsservice' => 'App\\Services\\Tax\\EtimsService',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
          ),
           'className' => 'App\\Services\\Sales\\DispatchService',
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
      '644b3f4082174c5b0220e4aa0d12281d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Sales',
         'uses' => 
        array (
          'accountsreceivable' => 'App\\Models\\AccountsReceivable',
          'auditlog' => 'App\\Models\\AuditLog',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'deliverynote' => 'App\\Models\\DeliveryNote',
          'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
          'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'payment' => 'App\\Models\\Payment',
          'paymentallocation' => 'App\\Models\\PaymentAllocation',
          'pickinglist' => 'App\\Models\\PickingList',
          'pickinglistline' => 'App\\Models\\PickingListLine',
          'productuom' => 'App\\Models\\ProductUom',
          'sale' => 'App\\Models\\Sale',
          'saleline' => 'App\\Models\\SaleLine',
          'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
          'salesorderline' => 'App\\Models\\SalesOrderLine',
          'stockbalance' => 'App\\Models\\StockBalance',
          'stockreservation' => 'App\\Models\\StockReservation',
          'journalposter' => 'App\\Services\\Finance\\JournalPoster',
          'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
          'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
          'etimsservice' => 'App\\Services\\Tax\\EtimsService',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
        ),
         'className' => 'App\\Services\\Sales\\DispatchService',
         'functionName' => 'consumeReservation',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Services\\Sales',
           'uses' => 
          array (
            'accountsreceivable' => 'App\\Models\\AccountsReceivable',
            'auditlog' => 'App\\Models\\AuditLog',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'deliverynote' => 'App\\Models\\DeliveryNote',
            'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
            'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'payment' => 'App\\Models\\Payment',
            'paymentallocation' => 'App\\Models\\PaymentAllocation',
            'pickinglist' => 'App\\Models\\PickingList',
            'pickinglistline' => 'App\\Models\\PickingListLine',
            'productuom' => 'App\\Models\\ProductUom',
            'sale' => 'App\\Models\\Sale',
            'saleline' => 'App\\Models\\SaleLine',
            'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
            'salesorderline' => 'App\\Models\\SalesOrderLine',
            'stockbalance' => 'App\\Models\\StockBalance',
            'stockreservation' => 'App\\Models\\StockReservation',
            'journalposter' => 'App\\Services\\Finance\\JournalPoster',
            'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
            'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
            'etimsservice' => 'App\\Services\\Tax\\EtimsService',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
          ),
           'className' => 'App\\Services\\Sales\\DispatchService',
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
      'b5de010a32e16b6902581a8c9b486d09' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Sales',
         'uses' => 
        array (
          'accountsreceivable' => 'App\\Models\\AccountsReceivable',
          'auditlog' => 'App\\Models\\AuditLog',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'deliverynote' => 'App\\Models\\DeliveryNote',
          'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
          'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'payment' => 'App\\Models\\Payment',
          'paymentallocation' => 'App\\Models\\PaymentAllocation',
          'pickinglist' => 'App\\Models\\PickingList',
          'pickinglistline' => 'App\\Models\\PickingListLine',
          'productuom' => 'App\\Models\\ProductUom',
          'sale' => 'App\\Models\\Sale',
          'saleline' => 'App\\Models\\SaleLine',
          'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
          'salesorderline' => 'App\\Models\\SalesOrderLine',
          'stockbalance' => 'App\\Models\\StockBalance',
          'stockreservation' => 'App\\Models\\StockReservation',
          'journalposter' => 'App\\Services\\Finance\\JournalPoster',
          'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
          'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
          'etimsservice' => 'App\\Services\\Tax\\EtimsService',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
        ),
         'className' => 'App\\Services\\Sales\\DispatchService',
         'functionName' => 'postPayments',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Services\\Sales',
           'uses' => 
          array (
            'accountsreceivable' => 'App\\Models\\AccountsReceivable',
            'auditlog' => 'App\\Models\\AuditLog',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'deliverynote' => 'App\\Models\\DeliveryNote',
            'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
            'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'payment' => 'App\\Models\\Payment',
            'paymentallocation' => 'App\\Models\\PaymentAllocation',
            'pickinglist' => 'App\\Models\\PickingList',
            'pickinglistline' => 'App\\Models\\PickingListLine',
            'productuom' => 'App\\Models\\ProductUom',
            'sale' => 'App\\Models\\Sale',
            'saleline' => 'App\\Models\\SaleLine',
            'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
            'salesorderline' => 'App\\Models\\SalesOrderLine',
            'stockbalance' => 'App\\Models\\StockBalance',
            'stockreservation' => 'App\\Models\\StockReservation',
            'journalposter' => 'App\\Services\\Finance\\JournalPoster',
            'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
            'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
            'etimsservice' => 'App\\Services\\Tax\\EtimsService',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
          ),
           'className' => 'App\\Services\\Sales\\DispatchService',
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
      'd36c9280b02e787cb70b8ff3d2505e38' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Sales',
         'uses' => 
        array (
          'accountsreceivable' => 'App\\Models\\AccountsReceivable',
          'auditlog' => 'App\\Models\\AuditLog',
          'customer' => 'App\\Models\\Customer',
          'customercredit' => 'App\\Models\\CustomerCredit',
          'deliverynote' => 'App\\Models\\DeliveryNote',
          'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
          'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
          'numbersequence' => 'App\\Models\\NumberSequence',
          'payment' => 'App\\Models\\Payment',
          'paymentallocation' => 'App\\Models\\PaymentAllocation',
          'pickinglist' => 'App\\Models\\PickingList',
          'pickinglistline' => 'App\\Models\\PickingListLine',
          'productuom' => 'App\\Models\\ProductUom',
          'sale' => 'App\\Models\\Sale',
          'saleline' => 'App\\Models\\SaleLine',
          'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
          'salesorderline' => 'App\\Models\\SalesOrderLine',
          'stockbalance' => 'App\\Models\\StockBalance',
          'stockreservation' => 'App\\Models\\StockReservation',
          'journalposter' => 'App\\Services\\Finance\\JournalPoster',
          'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
          'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
          'etimsservice' => 'App\\Services\\Tax\\EtimsService',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
        ),
         'className' => 'App\\Services\\Sales\\DispatchService',
         'functionName' => 'postCredit',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Services\\Sales',
           'uses' => 
          array (
            'accountsreceivable' => 'App\\Models\\AccountsReceivable',
            'auditlog' => 'App\\Models\\AuditLog',
            'customer' => 'App\\Models\\Customer',
            'customercredit' => 'App\\Models\\CustomerCredit',
            'deliverynote' => 'App\\Models\\DeliveryNote',
            'deliverynoteline' => 'App\\Models\\DeliveryNoteLine',
            'deliverynotelinebatchallocation' => 'App\\Models\\DeliveryNoteLineBatchAllocation',
            'numbersequence' => 'App\\Models\\NumberSequence',
            'payment' => 'App\\Models\\Payment',
            'paymentallocation' => 'App\\Models\\PaymentAllocation',
            'pickinglist' => 'App\\Models\\PickingList',
            'pickinglistline' => 'App\\Models\\PickingListLine',
            'productuom' => 'App\\Models\\ProductUom',
            'sale' => 'App\\Models\\Sale',
            'saleline' => 'App\\Models\\SaleLine',
            'salelinebatchallocation' => 'App\\Models\\SaleLineBatchAllocation',
            'salesorderline' => 'App\\Models\\SalesOrderLine',
            'stockbalance' => 'App\\Models\\StockBalance',
            'stockreservation' => 'App\\Models\\StockReservation',
            'journalposter' => 'App\\Services\\Finance\\JournalPoster',
            'salesjournalmapper' => 'App\\Services\\Finance\\SalesJournalMapper',
            'stockledgerservice' => 'App\\Services\\Inventory\\StockLedgerService',
            'etimsservice' => 'App\\Services\\Tax\\EtimsService',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
          ),
           'className' => 'App\\Services\\Sales\\DispatchService',
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
      'C:\\xampp\\htdocs\\pharmacy_erp\\app\\Services\\Sales\\DispatchService.php' => 'a9f34474aba56a488c2d6682020dc9554579d44854d7430b372cf0bbeffb3859',
    ),
  ),
));