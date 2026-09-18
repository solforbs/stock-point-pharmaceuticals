<?php declare(strict_types = 1);

// osfsl-C:\xampp\htdocs\pharmacy_erp\database\seeders\MrlPricelistSeeder.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Database\Seeders\MrlPricelistSeeder
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-2e08412ad1266835870f0a3da898d45ae48d1edbc96ed4b81bb011fbc6385821-8.3.31-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Database\\Seeders\\MrlPricelistSeeder',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/database/seeders/MrlPricelistSeeder.php',
      ),
    ),
    'namespace' => 'Database\\Seeders',
    'name' => 'Database\\Seeders\\MrlPricelistSeeder',
    'shortName' => 'MrlPricelistSeeder',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * First 100 catalogue lines from Medina Remedies Limited\'s 2026 pricelist
 * (MRL_PRICELIST_2026.pdf), in the exact order and at the exact prices
 * printed there.
 *
 * Each pricelist line is the supplier\'s own atomic sellable/purchasable
 * unit (a bottle, tube, box, vial, or blister strip, as they price it) —
 * not a loose tablet/ml/gram. Base UOM here is therefore EA ("each"),
 * one row per code, and default_price is the pricelist figure verbatim
 * (never derived or rounded), so every seeded price traces 1:1 back to
 * the source document.
 *
 * This is a wholesale-distributor catalogue import, not a retail price
 * list: it deliberately does NOT add loose-unit (per-tablet/per-ml) UOM
 * rows or set retail/dispensing prices, and it does NOT set tax_code_id
 * (VAT/exemption status is a tax-compliance decision for the business,
 * not something to guess here). Both are natural follow-ups once that
 * data exists.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 31,
    'endLine' => 217,
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
      'ITEMS' => 
      array (
        'declaringClassName' => 'Database\\Seeders\\MrlPricelistSeeder',
        'implementingClassName' => 'Database\\Seeders\\MrlPricelistSeeder',
        'name' => 'ITEMS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[[\'30001\', \'3D CREAM 20GM\', \'98.26\'], [\'30002\', \'32912 CURAPOR SURGICAL DRESSING 7CM*5CM\', \'3275.40\'], [\'30003\', \'32914 CURAPOR SURGICAL DRESSING STERILE 10CM*15CM\', \'76.43\'], [\'30004\', \'32916 CURAPOR SURGICAL DRESSING STERILE 10CM*25CM\', \'120.10\'], [\'90001\', \'9-VIT(MULTIVITAMIN INFUSION)\', \'545.90\'], [\'A0001\', \'ABIDEC DROPS 25ML 1S\', \'1664.43\'], [\'A0002\', \'ABIDEC MULTIVITAMINE 150ML 1S\', \'1866.98\'], [\'A0003\', \'ABZ SUSPENSION 400MG 10ML\', \'38.21\'], [\'A0004\', \'ABZ TABS 400MG 1S\', \'28.39\'], [\'A0010\', \'ACEPAR CAPSULES 10S\', \'191.47\'], [\'A0011\', \'ACEPAR MR TABS 10S\', \'272.95\'], [\'A0012\', \'ACETAZOLAMIDE 250MG TABS *112\', \'2999.72\'], [\'A0013\', \'ACICLOVIR 250MG FOR SOLN VIALS 5S\', \'4367.20\'], [\'A0014\', \'ACINET 1.2G INJECTION 1S\', \'152.85\'], [\'A0015\', \'ACINET 156 SRY SYRUP 100ML\', \'139.75\'], [\'A0016\', \'ACINET 228/5ML DRY SYRUP 100ML\', \'152.85\'], [\'A0017\', \'ACINET 457 SRY SYRUP 100ML\', \'262.03\'], [\'A0019\', \'ACINET TABS 1G *10\', \'262.03\'], [\'A0020\', \'ACLOSARA MR TABS 10S\', \'338.46\'], [\'A0021\', \'ACLOSARA P TABS 10S\', \'239.54\'], [\'A0024\', \'ACNESOL CREAM 25GM\', \'175.66\'], [\'A0027\', \'ACTIFED COUGH AND WET SYRUP 100ML\', \'402.59\'], [\'A0028\', \'ACTILOSA CAPS 10`S\', \'600.49\'], [\'A0029\', \'ACTION ANALGESIC TABS *100\', \'339.55\'], [\'A0030\', \'ACTION TABLETS 110S\', \'338.65\'], [\'A0032\', \'ACTRAPID VIAL IU/10ML\', \'567.74\'], [\'A0033\', \'ACULAR 0.5% EYE DROPS 5ML\', \'1277.41\'], [\'A0036\', \'ADALAT LA 30MG TABS *28\', \'1675.25\'], [\'A0037\', \'ADALAT RETARD 20MG TABS 60S\', \'3257.06\'], [\'A0038\', \'ADDYZOA CAPSULES 20`S\', \'1517.60\'], [\'A0041\', \'ADOL 250MG SUPP\', \'289.33\'], [\'A0042\', \'ADOL SUPPOSITORIES 125MG 10S\', \'262.03\'], [\'A0044\', \'ADRENALINE INJECTION 1S\', \'7.53\'], [\'A0051\', \'AERIUS ORAL SOL 0.5MG/ML 60ML\', \'1152.19\'], [\'A0052\', \'AERIUS ORAL SOL150ML\', \'1156.46\'], [\'A0053\', \'AERIUS TABS 20`S TUR\', \'538.26\'], [\'A0054\', \'AERIUS TABS 30S\', \'2609.40\'], [\'A0055\', \'AERO CHAMBER WITH INFANT MASK\', \'4001.45\'], [\'A0057\', \'AEROVENT INHALER 20DOSES\', \'1116.91\'], [\'A0058\', \'AIRTAL 100MG TABLETS 40S\', \'3493.89\'], [\'A0063\', \'ALBENDAZOLE SUSP 10ML\', \'16.92\'], [\'A0065\', \'ALCOF SYRUP 100ML\', \'32.75\'], [\'A0067\', \'ALDACTONE TABS 25MG 100S\', \'2467.47\'], [\'A0069\', \'ALDOMET 250MG TABS 30`S\', \'567.74\'], [\'A0070\', \'ALDOMET 500MG TABS *30\', \'1089.50\'], [\'A0072\', \'ALLERFAST 120MG TABS *100\', \'2680.37\'], [\'A0073\', \'ALLERGO COMOD EYE DROPS 10ML\', \'907.47\'], [\'A0074\', \'ALLOPURINOL 100MG *30(LOGOUT)\', \'185.61\'], [\'A0075\', \'ALLOPURINOL 100MG TABS 28S\', \'305.70\'], [\'A0076\', \'ALLOPURINOL 300MG *30 (ALEZE)\', \'299.15\'], [\'A0077\', \'ALLOPURINOL 300MG TABS 28S\', \'414.88\'], [\'A0078\', \'ALLOPURINOL TABS 100MG *30(ALEZE)\', \'157.22\'], [\'A0080\', \'ALLUCID SUSPENSION 100ML\', \'80.11\'], [\'A0082\', \'ALPHA PLUS MILK 1 400G\', \'1061.23\'], [\'A0083\', \'ALPHA PLUS MILK 2 400G\', \'1088.52\'], [\'A0085\', \'ALTACEF 125MG/5ML 50ML\', \'463.08\'], [\'A0086\', \'ALTACEF 500MG TABLETS 10S\', \'980.87\'], [\'A0087\', \'ALTRINE 10MG CAPS *100\', \'104.09\'], [\'A0088\', \'ALTRINE SYRUP 5MG/ML\', \'75.22\'], [\'A0089\', \'ALUGEL SUSPENSION 100ML\', \'81.94\'], [\'A0090\', \'ALZOLAM 0.25 TABS`S 100\', \'2456.55\'], [\'A0091\', \'ALZOLAM 0.5MG *100\', \'3568.00\'], [\'A0092\', \'AMARYL M TABS 2MG 30`S\', \'1200.98\'], [\'A0093\', \'AMARYL TABLETS 2MG 30S\', \'1793.61\'], [\'A0094\', \'AMARYL TABS 1MG 30`S\', \'586.84\'], [\'A0095\', \'AMARYL TABS 4MG 30s\', \'3218.96\'], [\'A0098\', \'AMIKACIN 500MG INJECTION\', \'54.48\'], [\'A0099\', \'AMINOGARD SATCHETS 5G 10S\', \'933.49\'], [\'A0101\', \'AMINOSTERIL KE-10% 500ML\', \'2653.07\'], [\'A0102\', \'AMINOSTRIL N-HEPA 8% 500ML\', \'1957.38\'], [\'A0105\', \'AMITRIPTYLINE 25MG TABS 100S\', \'261.77\'], [\'A0106\', \'METRONIDAZOLE (AMIZOLE) GEL 20G\', \'222.73\'], [\'A0108\', \'AMLONG 10MG TABS 30S\', \'644.16\'], [\'A0109\', \'AMLONG 5MG TABS 30S\', \'371.33\'], [\'A0111\', \'AMLOSUN TABLETS 10MG 30S\', \'1549.26\'], [\'A0112\', \'AMLOSUN TABLETS 5MG 30S\', \'573.85\'], [\'A0113\', \'AMLOZAAR H TABS 30S\', \'1495.77\'], [\'A0114\', \'AMLOZEST 10MG TABS 30S\', \'680.27\'], [\'A0115\', \'AMLOZEST 5MG TABS 30S\', \'453.52\'], [\'A0116\', \'AMOKLAVIN-BID 1G TABS 14S - COAMOXCLAV\', \'382.13\'], [\'A0119\', \'AMOXICILLIN 500MG CAPS 100S\', \'262.03\'], [\'A0125\', \'AMOXICILLIN/CLAV 1.2 INJ\', \'92.96\'], [\'A0128\', \'AMOXIL CAPS 500MG 100S\', \'2408.67\'], [\'A0129\', \'AMOXIL SYRUP 125MG/5ML 100ML\', \'419.80\'], [\'A0132\', \'AMOXYCLAV 375MG *10\', \'113.55\'], [\'A0135\', \'AMPICLOX 500MG 100S(GENERIC)\', \'376.67\'], [\'A0136\', \'AMPICLOX 500MG CAPSULES 100S\', \'1700.15\'], [\'A0137\', \'AMPICLOX DRY SYRUP 100ML DAWA\', \'60.06\'], [\'A0138\', \'AMPICLOX DRY SYRUP 60ML DAWA\', \'42.45\'], [\'A0139\', \'AMPICLOX NEONATAL DROPS 10ML (GENERIC)\', \'45.86\'], [\'A0140\', \'AMPICLOX NEONATAL DROPS 8ML\', \'309.35\'], [\'A0141\', \'AMPICLOX SYRUP 250MG 100ML\', \'72.49\'], [\'A0142\', \'AMTEL 40/5MG TABS 30`S\', \'2167.66\'], [\'A0143\', \'AMTEL 80/5 TABS *30\', \'2329.90\'], [\'A0144\', \'ANDOLEX-C ORAL RINSE 200ML\', \'1567.33\'], [\'A0145\', \'ANDOLEX-C SPRAY 30ML\', \'1448.82\'], [\'A0146\', \'ANGIZAAR 50MG TABLETS 30`S\', \'251.11\'], [\'A0147\', \'ANGIZAAR H TABS 30`S\', \'234.74\'], [\'A0150\', \'ANOMEX SUPPOS 5S\', \'289.33\'], [\'A0151\', \'ANTACID SUSP 100ML\', \'30.57\']]',
          'attributes' => 
          array (
            'startLine' => 36,
            'endLine' => 137,
            'startTokenPos' => 59,
            'startFilePos' => 1325,
            'endTokenPos' => 1161,
            'endFilePos' => 7079,
          ),
        ),
        'docComment' => '/**
 * @var list<array{0: string, 1: string, 2: string}> [code, description, price]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 137,
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
        'startLine' => 139,
        'endLine' => 149,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Database\\Seeders',
        'declaringClassName' => 'Database\\Seeders\\MrlPricelistSeeder',
        'implementingClassName' => 'Database\\Seeders\\MrlPricelistSeeder',
        'currentClassName' => 'Database\\Seeders\\MrlPricelistSeeder',
        'aliasName' => NULL,
      ),
      'seedItems' => 
      array (
        'name' => 'seedItems',
        'parameters' => 
        array (
          'organisationId' => 
          array (
            'name' => 'organisationId',
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
            'startLine' => 159,
            'endLine' => 159,
            'startColumn' => 38,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'eaUomId' => 
          array (
            'name' => 'eaUomId',
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
            'startLine' => 159,
            'endLine' => 159,
            'startColumn' => 62,
            'endColumn' => 76,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'items' => 
          array (
            'name' => 'items',
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
            'startLine' => 159,
            'endLine' => 159,
            'startColumn' => 79,
            'endColumn' => 90,
            'parameterIndex' => 2,
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
 * Inserts the catalogue lines that are not there yet (by code) and their
 * base EA unit row, 500 at a time. Existing products are never touched,
 * exactly like firstOrCreate, but ~6,000 lines load in seconds instead of
 * the ~12,000 round trips a per-row loop costs.
 *
 * @param  list<array{0: string, 1: string, 2: string}>  $items  [code, description, price]
 */',
        'startLine' => 159,
        'endLine' => 216,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Database\\Seeders',
        'declaringClassName' => 'Database\\Seeders\\MrlPricelistSeeder',
        'implementingClassName' => 'Database\\Seeders\\MrlPricelistSeeder',
        'currentClassName' => 'Database\\Seeders\\MrlPricelistSeeder',
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