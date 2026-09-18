<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class KenyanPharmaStockSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organisation::first();
        if (! $org) {
            throw new RuntimeException('Organisation not found. Run OrganisationSeeder first.');
        }

        $branch = Branch::where('organisation_id', $org->id)->first();
        $admin = User::where('email', 'admin@example.com')->first();
        $userId = $admin->id ?? 1;

        $stores = Store::where('branch_id', $branch->id)->get()->keyBy('code');
        $suppliers = Supplier::where('organisation_id', $org->id)->get()->keyBy('code');

        $mainStore = $stores['MAIN'];
        $retailStore = $stores['RETAIL'];
        $qtnStore = $stores['QTN'];

        $today = now()->startOfDay();

        // Product Catalog items to seed inventory for
        $inventoryConfig = [
            // 1. Amoxicillin / Clavulanate 1g - Healthy stock + 1 batch expiring in 25 days
            [
                'product_code' => 'A0019',
                'supplier_code' => 'COSMOS',
                'unit_cost' => 200.00,
                'batches' => [
                    [
                        'batch_number' => 'BN-ACN-2026-01',
                        'status' => 'RELEASED',
                        'mfg_date' => Carbon::parse('2026-01-10'),
                        'expiry_date' => Carbon::parse('2028-01-10'),
                        'balances' => [
                            ['store' => $mainStore, 'qty' => 150],
                            ['store' => $retailStore, 'qty' => 30],
                        ],
                    ],
                    [
                        'batch_number' => 'BN-ACN-2024-88',
                        'status' => 'RELEASED',
                        'mfg_date' => Carbon::parse('2024-10-01'),
                        'expiry_date' => $today->copy()->addDays(25), // Expiring in 25d
                        'balances' => [
                            ['store' => $mainStore, 'qty' => 45],
                        ],
                    ],
                ],
            ],

            // 2. Action Analgesic Tabs - Healthy stock + 1 batch expiring in 45 days
            [
                'product_code' => 'A0029',
                'supplier_code' => 'LABALLIED',
                'unit_cost' => 250.00,
                'batches' => [
                    [
                        'batch_number' => 'BN-ACT-2026-10',
                        'status' => 'RELEASED',
                        'mfg_date' => Carbon::parse('2026-02-15'),
                        'expiry_date' => Carbon::parse('2028-02-15'),
                        'balances' => [
                            ['store' => $mainStore, 'qty' => 200],
                            ['store' => $retailStore, 'qty' => 50],
                        ],
                    ],
                    [
                        'batch_number' => 'BN-ACT-2024-99',
                        'status' => 'RELEASED',
                        'mfg_date' => Carbon::parse('2024-11-01'),
                        'expiry_date' => $today->copy()->addDays(45), // Expiring in 45d
                        'balances' => [
                            ['store' => $retailStore, 'qty' => 35],
                        ],
                    ],
                ],
            ],

            // 3. Albendazole 400mg Suspension - Essential paediatric dewormer + 1 batch expiring in 15 days
            [
                'product_code' => 'A0003',
                'supplier_code' => 'DAWA',
                'unit_cost' => 28.00,
                'batches' => [
                    [
                        'batch_number' => 'BN-ABZ-2026-04',
                        'status' => 'RELEASED',
                        'mfg_date' => Carbon::parse('2026-03-01'),
                        'expiry_date' => Carbon::parse('2028-03-01'),
                        'balances' => [
                            ['store' => $mainStore, 'qty' => 300],
                            ['store' => $retailStore, 'qty' => 80],
                        ],
                    ],
                    [
                        'batch_number' => 'BN-ABZ-2024-50',
                        'status' => 'RELEASED',
                        'mfg_date' => Carbon::parse('2024-09-01'),
                        'expiry_date' => $today->copy()->addDays(15), // Expiring in 15d
                        'balances' => [
                            ['store' => $mainStore, 'qty' => 25],
                        ],
                    ],
                ],
            ],

            // 4. Actifed Cough & Wet Syrup - 1 batch expiring in 75 days
            [
                'product_code' => 'A0027',
                'supplier_code' => 'HARLEYS',
                'unit_cost' => 310.00,
                'batches' => [
                    [
                        'batch_number' => 'BN-ACTF-2026-02',
                        'status' => 'RELEASED',
                        'mfg_date' => Carbon::parse('2026-01-20'),
                        'expiry_date' => Carbon::parse('2027-12-31'),
                        'balances' => [
                            ['store' => $mainStore, 'qty' => 100],
                            ['store' => $retailStore, 'qty' => 25],
                        ],
                    ],
                    [
                        'batch_number' => 'BN-ACTF-2024-67',
                        'status' => 'RELEASED',
                        'mfg_date' => Carbon::parse('2024-10-15'),
                        'expiry_date' => $today->copy()->addDays(75), // Expiring in 75d
                        'balances' => [
                            ['store' => $mainStore, 'qty' => 20],
                        ],
                    ],
                ],
            ],

            // 5. Acinet 1.2g Injection - Batch pending Quality Control (Pending QC KPI)
            [
                'product_code' => 'A0014',
                'supplier_code' => 'COSMOS',
                'unit_cost' => 115.00,
                'batches' => [
                    [
                        'batch_number' => 'BN-INJ-2026-QC',
                        'status' => 'PENDING_QC',
                        'mfg_date' => Carbon::parse('2026-08-01'),
                        'expiry_date' => Carbon::parse('2028-08-01'),
                        'balances' => [
                            ['store' => $mainStore, 'qty' => 120],
                        ],
                    ],
                ],
            ],

            // 6. Actilosa Caps 10s - Quarantined stock (Attention: Batches in quarantine)
            [
                'product_code' => 'A0028',
                'supplier_code' => 'MRL',
                'unit_cost' => 450.00,
                'batches' => [
                    [
                        'batch_number' => 'BN-ACTL-2026-QTN',
                        'status' => 'QUARANTINED',
                        'mfg_date' => Carbon::parse('2026-05-10'),
                        'expiry_date' => Carbon::parse('2027-05-10'),
                        'balances' => [
                            ['store' => $qtnStore, 'qty' => 40, 'quarantined' => 40],
                        ],
                    ],
                ],
            ],

            // 7. Acepar Caps 10s - Expired batch on hand (Attention: Expired batches on hand)
            [
                'product_code' => 'A0010',
                'supplier_code' => 'BIODEAL',
                'unit_cost' => 140.00,
                'batches' => [
                    [
                        'batch_number' => 'BN-ACP-2024-EXP',
                        'status' => 'EXPIRED',
                        'mfg_date' => Carbon::parse('2024-03-01'),
                        'expiry_date' => $today->copy()->subDays(30), // Expired 30 days ago
                        'balances' => [
                            ['store' => $mainStore, 'qty' => 15],
                        ],
                    ],
                ],
            ],

            // 8. Acinet 156 Syrup - Low stock line (Below reorder point of 25)
            [
                'product_code' => 'A0015',
                'supplier_code' => 'COSMOS',
                'unit_cost' => 105.00,
                'batches' => [
                    [
                        'batch_number' => 'BN-ACN156-2026-01',
                        'status' => 'RELEASED',
                        'mfg_date' => Carbon::parse('2026-01-01'),
                        'expiry_date' => Carbon::parse('2027-12-31'),
                        'balances' => [
                            ['store' => $mainStore, 'qty' => 10], // 10 < 25
                        ],
                    ],
                ],
            ],

            // 9. 3D Cream 20g - Low stock line (Below reorder point of 20)
            [
                'product_code' => '30001',
                'supplier_code' => 'MRL',
                'unit_cost' => 75.00,
                'batches' => [
                    [
                        'batch_number' => 'BN-3D-2026-01',
                        'status' => 'RELEASED',
                        'mfg_date' => Carbon::parse('2026-01-01'),
                        'expiry_date' => Carbon::parse('2027-12-31'),
                        'balances' => [
                            ['store' => $retailStore, 'qty' => 8], // 8 < 20
                        ],
                    ],
                ],
            ],
        ];

        DB::transaction(function () use ($inventoryConfig, $org, $branch, $suppliers, $userId, $today) {
            foreach ($inventoryConfig as $item) {
                $product = Product::where('organisation_id', $org->id)
                    ->where('code', (string) $item['product_code'])
                    ->first();

                if (! $product) {
                    continue;
                }

                $supplier = $suppliers[$item['supplier_code']] ?? null;
                $supplierId = $supplier?->id;
                $unitCost = $item['unit_cost'];

                foreach ($item['batches'] as $batchData) {
                    $batch = ProductBatch::updateOrCreate(
                        [
                            'organisation_id' => $org->id,
                            'product_id' => $product->id,
                            'batch_number' => $batchData['batch_number'],
                            'supplier_id' => $supplierId,
                        ],
                        [
                            'expiry_date' => $batchData['expiry_date']->toDateString(),
                            'manufacture_date' => $batchData['mfg_date']->toDateString(),
                            'unit_cost' => $unitCost,
                            'landed_unit_cost' => $unitCost,
                            'status' => $batchData['status'],
                            'qc_released_by' => $batchData['status'] === 'RELEASED' ? $userId : null,
                            'qc_released_at' => $batchData['status'] === 'RELEASED' ? $today : null,
                        ]
                    );

                    foreach ($batchData['balances'] as $bal) {
                        $store = $bal['store'];
                        $qty = $bal['qty'];
                        $quarantined = $bal['quarantined'] ?? 0;

                        // Create / Update Stock Balance
                        StockBalance::updateOrCreate(
                            [
                                'product_id' => $product->id,
                                'batch_id' => $batch->id,
                                'store_id' => $store->id,
                            ],
                            [
                                'qty_on_hand' => $qty,
                                'qty_reserved' => 0,
                                'qty_quarantined' => $quarantined,
                                'wac' => $unitCost,
                                'last_movement_at' => now(),
                            ]
                        );

                        // Create / Update Immutable Stock Ledger Entry
                        $sourceDocId = (string) Str::uuid();
                        $ledgerExists = StockLedger::where('batch_id', $batch->id)
                            ->where('store_id', $store->id)
                            ->where('txn_type', 'OPENING_BALANCE')
                            ->exists();

                        if (! $ledgerExists) {
                            StockLedger::create([
                                'id' => (string) Str::uuid(),
                                'organisation_id' => $org->id,
                                'branch_id' => $branch->id,
                                'store_id' => $store->id,
                                'product_id' => $product->id,
                                'batch_id' => $batch->id,
                                'txn_type' => 'OPENING_BALANCE',
                                'qty_base' => $qty,
                                'unit_cost' => $unitCost,
                                'total_cost' => bcmul((string) $qty, (string) $unitCost, 4),
                                'source_doc_type' => 'OPENING_BALANCE',
                                'source_doc_id' => $sourceDocId,
                                'user_id' => $userId,
                                'txn_datetime' => $today->copy()->subDays(5),
                                'created_at' => now(),
                            ]);
                        }
                    }
                }
            }
        });
    }
}
