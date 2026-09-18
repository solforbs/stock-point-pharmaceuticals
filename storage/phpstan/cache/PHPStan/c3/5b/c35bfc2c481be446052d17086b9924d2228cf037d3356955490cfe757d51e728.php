<?php declare(strict_types = 1);

// ftm-C:\xampp\htdocs\pharmacy_erp\app\Exceptions\ApiErrorMap.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      'c89ac82fe4ef0374394b6ec76fbb75c6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Exceptions',
         'uses' => 
        array (
          'invalidpaymentstatusexception' => 'App\\Services\\Finance\\InvalidPaymentStatusException',
          'noopenperiodexception' => 'App\\Services\\Finance\\NoOpenPeriodException',
          'paymentperiodclosedexception' => 'App\\Services\\Finance\\PaymentPeriodClosedException',
          'unbalancedjournalexception' => 'App\\Services\\Finance\\UnbalancedJournalException',
          'batchnotsellableexception' => 'App\\Services\\Inventory\\BatchNotSellableException',
          'insufficientstockexception' => 'App\\Services\\Inventory\\InsufficientStockException',
          'invalidbatchtransitionexception' => 'App\\Services\\Inventory\\InvalidBatchTransitionException',
          'invalidcountstatusexception' => 'App\\Services\\Inventory\\InvalidCountStatusException',
          'invalidtransferstatusexception' => 'App\\Services\\Inventory\\InvalidTransferStatusException',
          'secondapproverrequiredexception' => 'App\\Services\\Inventory\\SecondApproverRequiredException',
          'invalidpayrollstatusexception' => 'App\\Services\\Payroll\\InvalidPayrollStatusException',
          'pricechangedexception' => 'App\\Services\\Pricing\\PriceChangedException',
          'quoteexpiredexception' => 'App\\Services\\Pricing\\QuoteExpiredException',
          'quotenotfoundexception' => 'App\\Services\\Pricing\\QuoteNotFoundException',
          'invalidrequisitionstatusexception' => 'App\\Services\\Procurement\\InvalidRequisitionStatusException',
          'supplieroverpaymentexception' => 'App\\Services\\Procurement\\SupplierOverpaymentException',
          'invalidrecallstatusexception' => 'App\\Services\\Quality\\InvalidRecallStatusException',
          'invalidwastestatusexception' => 'App\\Services\\Quality\\InvalidWasteStatusException',
          'approvalrequiredexception' => 'App\\Services\\Sales\\ApprovalRequiredException',
          'creditholdexception' => 'App\\Services\\Sales\\CreditHoldException',
          'creditlimitexceededexception' => 'App\\Services\\Sales\\CreditLimitExceededException',
          'invaliddeliverynotestatusexception' => 'App\\Services\\Sales\\InvalidDeliveryNoteStatusException',
          'invalidpickingliststatusexception' => 'App\\Services\\Sales\\InvalidPickingListStatusException',
          'invalidquotationstatusexception' => 'App\\Services\\Sales\\InvalidQuotationStatusException',
          'invalidreturnstatusexception' => 'App\\Services\\Sales\\InvalidReturnStatusException',
          'invalidsalesorderstatusexception' => 'App\\Services\\Sales\\InvalidSalesOrderStatusException',
          'ordercreditlimitexceededexception' => 'App\\Services\\Sales\\OrderCreditLimitExceededException',
          'orderoncreditholdexception' => 'App\\Services\\Sales\\OrderOnCreditHoldException',
          'paymentmismatchexception' => 'App\\Services\\Sales\\PaymentMismatchException',
          'salealreadyvoidedexception' => 'App\\Services\\Sales\\SaleAlreadyVoidedException',
          'voidperiodclosedexception' => 'App\\Services\\Sales\\VoidPeriodClosedException',
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
        ),
         'className' => 'App\\Exceptions\\ApiErrorMap',
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
      'c7b0020d25778f03fb855947564cc8f7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Exceptions',
         'uses' => 
        array (
          'invalidpaymentstatusexception' => 'App\\Services\\Finance\\InvalidPaymentStatusException',
          'noopenperiodexception' => 'App\\Services\\Finance\\NoOpenPeriodException',
          'paymentperiodclosedexception' => 'App\\Services\\Finance\\PaymentPeriodClosedException',
          'unbalancedjournalexception' => 'App\\Services\\Finance\\UnbalancedJournalException',
          'batchnotsellableexception' => 'App\\Services\\Inventory\\BatchNotSellableException',
          'insufficientstockexception' => 'App\\Services\\Inventory\\InsufficientStockException',
          'invalidbatchtransitionexception' => 'App\\Services\\Inventory\\InvalidBatchTransitionException',
          'invalidcountstatusexception' => 'App\\Services\\Inventory\\InvalidCountStatusException',
          'invalidtransferstatusexception' => 'App\\Services\\Inventory\\InvalidTransferStatusException',
          'secondapproverrequiredexception' => 'App\\Services\\Inventory\\SecondApproverRequiredException',
          'invalidpayrollstatusexception' => 'App\\Services\\Payroll\\InvalidPayrollStatusException',
          'pricechangedexception' => 'App\\Services\\Pricing\\PriceChangedException',
          'quoteexpiredexception' => 'App\\Services\\Pricing\\QuoteExpiredException',
          'quotenotfoundexception' => 'App\\Services\\Pricing\\QuoteNotFoundException',
          'invalidrequisitionstatusexception' => 'App\\Services\\Procurement\\InvalidRequisitionStatusException',
          'supplieroverpaymentexception' => 'App\\Services\\Procurement\\SupplierOverpaymentException',
          'invalidrecallstatusexception' => 'App\\Services\\Quality\\InvalidRecallStatusException',
          'invalidwastestatusexception' => 'App\\Services\\Quality\\InvalidWasteStatusException',
          'approvalrequiredexception' => 'App\\Services\\Sales\\ApprovalRequiredException',
          'creditholdexception' => 'App\\Services\\Sales\\CreditHoldException',
          'creditlimitexceededexception' => 'App\\Services\\Sales\\CreditLimitExceededException',
          'invaliddeliverynotestatusexception' => 'App\\Services\\Sales\\InvalidDeliveryNoteStatusException',
          'invalidpickingliststatusexception' => 'App\\Services\\Sales\\InvalidPickingListStatusException',
          'invalidquotationstatusexception' => 'App\\Services\\Sales\\InvalidQuotationStatusException',
          'invalidreturnstatusexception' => 'App\\Services\\Sales\\InvalidReturnStatusException',
          'invalidsalesorderstatusexception' => 'App\\Services\\Sales\\InvalidSalesOrderStatusException',
          'ordercreditlimitexceededexception' => 'App\\Services\\Sales\\OrderCreditLimitExceededException',
          'orderoncreditholdexception' => 'App\\Services\\Sales\\OrderOnCreditHoldException',
          'paymentmismatchexception' => 'App\\Services\\Sales\\PaymentMismatchException',
          'salealreadyvoidedexception' => 'App\\Services\\Sales\\SaleAlreadyVoidedException',
          'voidperiodclosedexception' => 'App\\Services\\Sales\\VoidPeriodClosedException',
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
        ),
         'className' => 'App\\Exceptions\\ApiErrorMap',
         'functionName' => 'toResponse',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Exceptions',
           'uses' => 
          array (
            'invalidpaymentstatusexception' => 'App\\Services\\Finance\\InvalidPaymentStatusException',
            'noopenperiodexception' => 'App\\Services\\Finance\\NoOpenPeriodException',
            'paymentperiodclosedexception' => 'App\\Services\\Finance\\PaymentPeriodClosedException',
            'unbalancedjournalexception' => 'App\\Services\\Finance\\UnbalancedJournalException',
            'batchnotsellableexception' => 'App\\Services\\Inventory\\BatchNotSellableException',
            'insufficientstockexception' => 'App\\Services\\Inventory\\InsufficientStockException',
            'invalidbatchtransitionexception' => 'App\\Services\\Inventory\\InvalidBatchTransitionException',
            'invalidcountstatusexception' => 'App\\Services\\Inventory\\InvalidCountStatusException',
            'invalidtransferstatusexception' => 'App\\Services\\Inventory\\InvalidTransferStatusException',
            'secondapproverrequiredexception' => 'App\\Services\\Inventory\\SecondApproverRequiredException',
            'invalidpayrollstatusexception' => 'App\\Services\\Payroll\\InvalidPayrollStatusException',
            'pricechangedexception' => 'App\\Services\\Pricing\\PriceChangedException',
            'quoteexpiredexception' => 'App\\Services\\Pricing\\QuoteExpiredException',
            'quotenotfoundexception' => 'App\\Services\\Pricing\\QuoteNotFoundException',
            'invalidrequisitionstatusexception' => 'App\\Services\\Procurement\\InvalidRequisitionStatusException',
            'supplieroverpaymentexception' => 'App\\Services\\Procurement\\SupplierOverpaymentException',
            'invalidrecallstatusexception' => 'App\\Services\\Quality\\InvalidRecallStatusException',
            'invalidwastestatusexception' => 'App\\Services\\Quality\\InvalidWasteStatusException',
            'approvalrequiredexception' => 'App\\Services\\Sales\\ApprovalRequiredException',
            'creditholdexception' => 'App\\Services\\Sales\\CreditHoldException',
            'creditlimitexceededexception' => 'App\\Services\\Sales\\CreditLimitExceededException',
            'invaliddeliverynotestatusexception' => 'App\\Services\\Sales\\InvalidDeliveryNoteStatusException',
            'invalidpickingliststatusexception' => 'App\\Services\\Sales\\InvalidPickingListStatusException',
            'invalidquotationstatusexception' => 'App\\Services\\Sales\\InvalidQuotationStatusException',
            'invalidreturnstatusexception' => 'App\\Services\\Sales\\InvalidReturnStatusException',
            'invalidsalesorderstatusexception' => 'App\\Services\\Sales\\InvalidSalesOrderStatusException',
            'ordercreditlimitexceededexception' => 'App\\Services\\Sales\\OrderCreditLimitExceededException',
            'orderoncreditholdexception' => 'App\\Services\\Sales\\OrderOnCreditHoldException',
            'paymentmismatchexception' => 'App\\Services\\Sales\\PaymentMismatchException',
            'salealreadyvoidedexception' => 'App\\Services\\Sales\\SaleAlreadyVoidedException',
            'voidperiodclosedexception' => 'App\\Services\\Sales\\VoidPeriodClosedException',
            'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          ),
           'className' => 'App\\Exceptions\\ApiErrorMap',
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
      'C:\\xampp\\htdocs\\pharmacy_erp\\app\\Exceptions\\ApiErrorMap.php' => '84f2cada4431517b93a1c8390792642edb9cb1540af42e3b651e2aaf1b6a8b1b',
    ),
  ),
));