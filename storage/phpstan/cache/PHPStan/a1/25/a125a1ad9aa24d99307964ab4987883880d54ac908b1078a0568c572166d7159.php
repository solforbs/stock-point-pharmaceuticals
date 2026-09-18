<?php declare(strict_types = 1);

// osfsl-C:\xampp\htdocs\pharmacy_erp\database\seeders\MrlPricelistRemainderSeeder.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Database\Seeders\MrlPricelistRemainderSeeder
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-61044c32fc553e581dc0b6c98230e6b83b4f02444f2913053a76e598a70cb831-8.3.31-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Database\\Seeders\\MrlPricelistRemainderSeeder',
        'filename' => 'C:/xampp/htdocs/pharmacy_erp/database/seeders/MrlPricelistRemainderSeeder.php',
      ),
    ),
    'namespace' => 'Database\\Seeders',
    'name' => 'Database\\Seeders\\MrlPricelistRemainderSeeder',
    'shortName' => 'MrlPricelistRemainderSeeder',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * The remaining 5,896 catalogue lines from Medina Remedies Limited\'s 2026
 * pricelist (MRL_PRICELIST_2026.pdf), i.e. everything after the first 100
 * lines already seeded by {@see MrlPricelistSeeder}. Data is sourced from
 * database/seeders/data/mrl_pricelist_2026_remainder.json — a machine
 * extraction of the PDF\'s own text layer (code, description, price columns)
 * kept as a data file rather than an inline PHP array purely because of its
 * size (5,896 rows); nothing in the values was hand-typed or derived.
 *
 * Same modelling rules as MrlPricelistSeeder: each line is the supplier\'s
 * own atomic sellable/purchasable unit, so base UOM is EA ("each") and
 * default_price is the pricelist figure verbatim (never derived or
 * rounded). This is a wholesale-distributor catalogue import, not a retail
 * price list: it does not add loose-unit (per-tablet/per-ml) UOM rows, does
 * not set retail/dispensing prices, and does not set tax_code_id, category,
 * manufacturer, or dosage form — all deliberately left for a later,
 * explicitly-requested classification pass.
 *
 * 51 pricelist lines have no price at all (printed as "-" in the source,
 * meaning "not currently priced") and are intentionally NOT seeded, since
 * default_price cannot be guessed:
 *
 * A0543 ATACAND PLUS 16/12.5MG 28S TUR, A0585 ALLNYT TABS 20S,
 * B0256 BIOFREEZE SPRAY 118ML, C0149 CHLORPHENIRAMINE (ALLERCOL) 60ML,
 * C0150 CHLORPHENIRAMINE 100ML (ALLERCOL), C0446 CORCLAV 228 SUSP(AMOXICILLIN&CLAVUNATE;),
 * C0447 CORCLAV 457 SUSP(AMOXICILLIN&CLAVUNATE;), C0611 CUDO FORTE CAPSULES 10S,
 * C0832 CLAVAM 625MG TABS 20S, D0019 DALACIN CAPSULLES 300MG *10,
 * D0192 DYINNOLIFE HINGED KNEE BRACE OPEN PATELA `M`, D0372 DUOSOPT EYE DROPS 5ML,
 * D0484 DINAC GEL 20G, E0183 EXACTIVE VITAL KIT, E0273 EASY START ODF *5,
 * E0359 EMESAFE 4(ONDANSTERON 4 MG)10S, E0360 EMESAFE 8(ONDANSTERON 8 MG)10S,
 * E0387 ESPLUS IT CAPS 30S, F0045 FINGERTIP PULSE OXIMETER MEDI /PROTO 02,
 * F0330 FACE MASKS KIDS 3PLY 50`S, F0359 FLULOC TABS 14`S,
 * I0119 ITOPRIDE 150MG TABS *30, I0124 IRBESARTAN TAB 150MG*10(ARBI),
 * I0173 ICEPACK, L0274 LYSER FORTE 10MG TABS*10, L0327 LOFNAC GEL 50G,
 * L0382 LEVOTHYROXINE (THYRION) 50MG 30S, L0383 LEVOTHYROXINE (THYRION) 100MG 30S,
 * M0389 METFORMIN 500MG TABS 30S, M0434 MEFANAMIC ACID SYRUP 60ML (MEFNAC),
 * N0059 NEUROFEN MIGRAINE MAX ST 684MG TABS 12`S, N0060 NEUROFEN MIGRAINE PAIN 342MG TABS 12`S,
 * N0283 NOVONORM TABS 1MG *30, N0349 NEXAHIST SYRUP 60ML,
 * N0381 NILHEAT/NILBACT MILLINARY BAGS 5*S, O0055 ORACEF DRY SYRUP 100ML -DAWA,
 * O0209 OLMAT H TABS 30S, O0231 ONSETT INJ 5S, O0272 OLYMPIAN ELBOW SUPPORT -L,
 * P0289 P-ALAXIN TABS *12, R0067 RYLES TUBES FG 18, R0202 RELAX LEMON SACHETS 10X5G,
 * S0286 SUCTION CATHETER G18, S0445 SONOSCAPE ULTRASOUND PROBE CONVEX C361 FOR E1,
 * S0446 SONY ULTRASOUND PRINTER UP-X898 M D PIECES, S0461 SANICLIN HAND SANITIZER 200ML,
 * T0298 TELMI 40CT 30S, T0306 TRUST CONDOM CLASSIC 1S, V0153 VINGER-6 ODF*5,
 * V0156 VIT -D3 +FOLIC ACID ODF *5, V0195 VITAPOS EYE OINT 5G.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 56,
    'endLine' => 74,
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
        'startLine' => 58,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Database\\Seeders',
        'declaringClassName' => 'Database\\Seeders\\MrlPricelistRemainderSeeder',
        'implementingClassName' => 'Database\\Seeders\\MrlPricelistRemainderSeeder',
        'currentClassName' => 'Database\\Seeders\\MrlPricelistRemainderSeeder',
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