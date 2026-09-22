<?php

namespace App\Services\Quality;

/**
 * Starter SOPs for a Kenyan retail and wholesale pharmacy, written so an
 * institution can adopt them in minutes and then make them its own: every
 * section is edited in the app before the first version is issued, and
 * {company} / {branch} are filled in from the institution's details.
 *
 * They are drafts to be reviewed by the superintendent pharmacist, not
 * approved procedures; the issued document says so until it is activated.
 */
class SopTemplates
{
    /** The sections every SOP carries, in print order. */
    public const SECTIONS = [
        'purpose' => 'Purpose',
        'scope' => 'Scope',
        'responsibilities' => 'Responsibilities',
        'procedure' => 'Procedure',
        'records' => 'Records',
        'references' => 'References',
    ];

    /**
     * @var array<string, array{code: string, title: string, category: string, summary: string, sections: array<string, string>}>
     */
    private const TEMPLATES = [
        'receiving' => [
            'code' => 'SOP-001',
            'title' => 'Receiving and inspection of pharmaceutical goods',
            'category' => 'SOP',
            'summary' => 'Checking deliveries against the purchase order, batch and expiry capture, quarantine until released.',
            'sections' => [
                'purpose' => 'To make sure every delivery received by {company} is what was ordered, comes from a licensed supplier, is in good condition and is recorded with its batch and expiry before it can be sold.',
                'scope' => 'All medicines, medical supplies, equipment, nutritional products and reagents delivered to {branch}, including emergency purchases.',
                'responsibilities' => "Stores officer: receives, inspects and records the delivery in PharmaPoint (Goods Receipts).\nPharmacist in charge: releases batches from quality check (QC) and decides on rejected items.\nProcurement officer: follows up short, damaged or wrong deliveries with the supplier.",
                'procedure' => "Confirm the supplier is on the approved list and their PPB licence is valid (PharmaPoint blocks suppliers whose licence has expired).\nMatch the delivery note to the approved purchase order: product, strength, pack size and quantity.\nInspect every carton: seals intact, no damage, no signs of moisture or tampering; cold-chain items arrive with a temperature record between 2 °C and 8 °C.\nRecord the goods receipt in PharmaPoint against the purchase order, entering the quantity delivered, quantity accepted, batch number and expiry date for every line.\nReject items with less than the agreed shelf life (normally 12 months), damaged packs or wrong products; record the reason on the receipt.\nStore accepted goods in the receiving area; they stay in PENDING QC and cannot be sold.\nThe pharmacist checks the batches and releases them in PharmaPoint; only released batches become sellable.\nFile the supplier's delivery note and invoice; record the invoice in Supplier Invoices for the three-way match.",
                'records' => "Goods receipt (GRN) in PharmaPoint with batch and expiry.\nSupplier delivery note and invoice.\nCold-chain temperature record for refrigerated items.\nRejection notes and supplier correspondence.",
                'references' => "Pharmacy and Poisons Board (PPB) Guidelines on Good Distribution Practices.\nPharmacy and Poisons Act, Cap 244.\n{company} procurement SOP.",
            ],
        ],
        'storage' => [
            'code' => 'SOP-002',
            'title' => 'Storage, stock rotation and FEFO',
            'category' => 'SOP',
            'summary' => 'Storage conditions, first-expiry-first-out, segregation of expired and quarantined stock.',
            'sections' => [
                'purpose' => 'To keep stock at {company} in the right conditions and to sell the earliest-expiring batch first, so that nothing expires on the shelf.',
                'scope' => 'All storage areas at {branch}: main warehouse, retail counter, cold room or refrigerators, and the quarantine area.',
                'responsibilities' => "Stores officer: keeps storage areas orderly and checks temperatures twice a day.\nPharmacist in charge: reviews the expiry report each week and approves moves to quarantine.",
                'procedure' => "Store products under their labelled conditions: room temperature below 25 °C, cold chain 2–8 °C, away from direct sunlight and off the floor on pallets or shelves.\nKeep products grouped under their stock headings (Drugs, Topicals, Cold chain, Vaccines, Infusions, Injections, Medical supplies and so on) as set up in PharmaPoint.\nIssue stock first-expiry-first-out (FEFO); PharmaPoint picks the earliest-expiring released batch automatically at the point of sale.\nRecord room and refrigerator temperatures in Cold Chain twice a day; report any reading out of range at once.\nEach week, review Batches & Expiry; move stock expiring within 90 days to the front and flag stock expiring within 30 days to the pharmacist.\nMove expired, damaged or recalled stock to the quarantine area and record it in PharmaPoint; it can never be sold from there.\nKeep the storage areas clean, dry and free of pests (see the cleaning SOP).",
                'records' => "Temperature logs (Cold Chain).\nBatches & Expiry report.\nQuarantine records.",
                'references' => "PPB Guidelines on Good Distribution Practices.\nManufacturers' storage instructions.",
            ],
        ],
        'cold_chain' => [
            'code' => 'SOP-003',
            'title' => 'Cold chain management',
            'category' => 'SOP',
            'summary' => 'Keeping vaccines and cold-chain products at 2–8 °C, monitoring and responding to excursions.',
            'sections' => [
                'purpose' => 'To keep vaccines, insulins and other cold-chain products at 2–8 °C from receipt to sale, and to act quickly when a temperature excursion happens.',
                'scope' => 'All refrigerators, cold boxes and cold-chain products held by {company} at {branch}.',
                'responsibilities' => "Stores officer: records temperatures and packs cold boxes for deliveries.\nPharmacist in charge: assesses excursions and decides whether stock can still be used.",
                'procedure' => "Keep cold-chain products only in the designated pharmaceutical refrigerator, never in a domestic freezer compartment or door shelves.\nRecord the refrigerator temperature in PharmaPoint Cold Chain at opening and closing every day.\nIf a reading is below 2 °C or above 8 °C, record the excursion, move stock to a working refrigerator or cold box, and tell the pharmacist immediately.\nThe pharmacist checks the manufacturer's stability data and either releases the stock or quarantines it in PharmaPoint.\nFor deliveries, pack cold-chain products in a pre-conditioned cold box with ice packs and a thermometer; record the dispatch time.\nDefrost and clean refrigerators monthly, moving stock to a back-up unit first.",
                'records' => "Cold Chain temperature readings and excursions in PharmaPoint.\nQuarantine and release decisions.\nRefrigerator maintenance log.",
                'references' => "PPB Guidelines on Good Distribution Practices.\nWHO guidance on the storage and transport of time- and temperature-sensitive pharmaceutical products.",
            ],
        ],
        'dispensing' => [
            'code' => 'SOP-004',
            'title' => 'Dispensing and patient counselling',
            'category' => 'SOP',
            'summary' => 'Prescription checks, dispensing, labelling, counselling and prescription-only medicines.',
            'sections' => [
                'purpose' => 'To make sure every customer at {company} receives the right medicine, in the right dose, with clear instructions.',
                'scope' => 'All sales of prescription-only and pharmacy medicines at the {branch} retail counter.',
                'responsibilities' => "Pharmacist or pharmaceutical technologist: checks prescriptions, dispenses and counsels.\nCashier: completes the sale in PharmaPoint POS only after the medicine has been checked.",
                'procedure' => "Check that the prescription is valid: patient name, date, prescriber's name and signature, medicine, strength, dose and duration.\nPrescription-only medicines are never sold without a valid prescription.\nCheck for allergies, pregnancy, other medicines and the right dose for age and weight.\nPick the product; PharmaPoint suggests the earliest-expiring batch. Check the product, strength and expiry against the prescription.\nLabel the medicine with the patient's name, dose, frequency, duration and the date.\nCounsel the patient: how and when to take it, common side effects, what to avoid and when to come back.\nRecord the sale in PharmaPoint POS and give the customer a receipt.\nReport any suspected adverse reaction through the pharmacovigilance SOP.",
                'records' => "PharmaPoint sale and receipt.\nPrescription (kept as required for prescription-only medicines).\nADR reports where applicable.",
                'references' => "Pharmacy and Poisons Act, Cap 244.\nPPB Good Pharmacy Practice guidelines.",
            ],
        ],
        'cash' => [
            'code' => 'SOP-005',
            'title' => 'Point of sale, cash and M-Pesa handling',
            'category' => 'SOP',
            'summary' => 'Recording every sale, payment methods, daily cash-up and banking.',
            'sections' => [
                'purpose' => 'To make sure every sale at {company} is recorded and every shilling received is accounted for.',
                'scope' => 'All cash, M-Pesa, card and credit sales at {branch}.',
                'responsibilities' => "Cashier: records every sale in PharmaPoint and hands over the takings at the end of the shift.\nSupervisor or manager: checks the cash-up, banks the cash and investigates differences.",
                'procedure' => "Record every sale in PharmaPoint POS before handing over goods; never sell from memory or on paper.\nFor M-Pesa, enter the transaction code on the payment line; confirm the message before completing the sale.\nGive every customer a printed or shared receipt.\nCredit sales are allowed only to account customers within their credit limit; PharmaPoint blocks sales over the limit.\nPrice changes and discounts above your authority need a supervisor's approval in PharmaPoint.\nAt the end of each shift, count the cash, compare it with the PharmaPoint sales by payment method and record any difference with a reason.\nThe manager banks cash daily and reconciles M-Pesa and bank statements in Payments & Reconciliation.\nVoids and returns need a reason and are reviewed by the manager every day.",
                'records' => "PharmaPoint sales, receipts and payments.\nShift cash-up sheet.\nBank deposit slips and M-Pesa statements.",
                'references' => "{company} finance policy.\nKRA eTIMS requirements.",
            ],
        ],
        'returns' => [
            'code' => 'SOP-006',
            'title' => 'Customer returns',
            'category' => 'SOP',
            'summary' => 'Accepting, inspecting and recording returned goods and refunds or credit notes.',
            'sections' => [
                'purpose' => 'To handle goods returned by customers fairly and safely, so that no unsafe product goes back on the shelf.',
                'scope' => 'Returns from retail and wholesale customers of {company} at {branch}.',
                'responsibilities' => "Counter or sales staff: receive the return and record it in PharmaPoint Returns.\nPharmacist in charge: inspects returned medicines and decides whether they can be restocked.",
                'procedure' => "Accept returns only against a PharmaPoint invoice or receipt; check the item, batch and quantity against it.\nRecord the return in PharmaPoint with the reason for each line (damaged, wrong item, expired, near expiry, customer changed mind and so on) and any remarks.\nReturned medicines are held back from sale until the pharmacist inspects them.\nCold-chain products, opened packs and items that have left the pharmacy's control are not restocked; send them for disposal.\nIssue the refund or credit note from PharmaPoint only after the return is approved.\nReview return reasons monthly to spot recurring quality or service problems.",
                'records' => "Customer return and credit note in PharmaPoint.\nPharmacist's inspection decision.",
                'references' => "PPB Guidelines on Good Distribution Practices.\n{company} returns policy.",
            ],
        ],
        'disposal' => [
            'code' => 'SOP-007',
            'title' => 'Disposal of expired and damaged stock',
            'category' => 'SOP',
            'summary' => 'Quarantine, write-off approval and safe disposal through an approved contractor.',
            'sections' => [
                'purpose' => 'To remove expired, damaged or unusable pharmaceuticals safely and keep an auditable record of what was destroyed.',
                'scope' => 'All expired, damaged, recalled (once closed) and returned-unusable stock at {company}.',
                'responsibilities' => "Stores officer: moves items to quarantine and prepares the disposal list.\nPharmacist in charge and manager: approve the write-off.\nApproved waste contractor: collects and destroys the waste.",
                'procedure' => "Move unusable stock to the quarantine area and record it in PharmaPoint straight away.\nEach month, prepare a disposal record in PharmaPoint Waste & Disposal listing product, batch, quantity and reason.\nThe pharmacist and manager approve the write-off; the stock value is posted to the expense account automatically.\nHand the waste to a NEMA-licensed contractor; obtain a waste transfer note and certificate of destruction.\nNotify PPB where the regulations require it.\nFile the certificate against the disposal record.",
                'records' => "Waste & Disposal record in PharmaPoint.\nWaste transfer note and certificate of destruction.",
                'references' => "PPB Guidelines on Safe Disposal of Pharmaceutical Waste.\nEnvironmental Management and Co-ordination (Waste Management) Regulations.",
            ],
        ],
        'recall' => [
            'code' => 'SOP-008',
            'title' => 'Product recall',
            'category' => 'SOP',
            'summary' => 'Acting on PPB or manufacturer recalls: block, trace, notify customers, return.',
            'sections' => [
                'purpose' => 'To act quickly and completely when a product is recalled by PPB, the manufacturer or the supplier.',
                'scope' => 'All recalls affecting products stocked or sold by {company}.',
                'responsibilities' => "Pharmacist in charge: opens and runs the recall.\nStores officer: finds and quarantines the stock.\nSales staff: contact affected customers.",
                'procedure' => "On receiving a recall notice, open a recall in PharmaPoint Recalls with the affected product and batches.\nPharmaPoint blocks the batches immediately so they cannot be sold.\nFind and move all affected stock to quarantine in every store.\nUse the recall's customer list in PharmaPoint to contact every customer who bought the batches.\nReturn the stock to the supplier or dispose of it as instructed; record the outcome.\nClose the recall with its effectiveness (quantity recovered against quantity received).",
                'records' => "Recall record in PharmaPoint.\nCustomer contact log.\nSupplier return or disposal records.",
                'references' => 'PPB Guidelines on Recall and Withdrawal of Health Products.',
            ],
        ],
        'stock_count' => [
            'code' => 'SOP-009',
            'title' => 'Stock counts',
            'category' => 'SOP',
            'summary' => 'Cycle counts and full counts, variance investigation and approval of adjustments.',
            'sections' => [
                'purpose' => 'To keep the stock in PharmaPoint equal to the stock on the shelves.',
                'scope' => 'All stores at {branch}.',
                'responsibilities' => "Stores officer: carries out the counts.\nManager: reviews variances and approves adjustments.",
                'procedure' => "Count high-value and fast-moving items weekly and everything else at least monthly; do a full count at the end of each financial year.\nStart a stock count in PharmaPoint Counts; count by batch without looking at the system quantity.\nEnter the counted quantities; PharmaPoint shows the variance for each batch.\nRecount any variance above the agreed tolerance before posting.\nInvestigate the cause of confirmed variances (unrecorded sales, receiving errors, theft, breakage).\nThe manager approves the count; adjustments above the approval limit need a second approver.",
                'records' => "Stock count and adjustments in PharmaPoint.\nVariance investigation notes.",
                'references' => '{company} inventory policy.',
            ],
        ],
        'procurement' => [
            'code' => 'SOP-010',
            'title' => 'Procurement and supplier qualification',
            'category' => 'SOP',
            'summary' => 'Requisitions, quotations and competitive bid analysis, purchase orders, approved suppliers.',
            'sections' => [
                'purpose' => 'To buy the right products from licensed suppliers at the best overall value, with a clear record of why each supplier was chosen.',
                'scope' => 'All purchases of stock by {company}.',
                'responsibilities' => "Procurement officer: raises requisitions, requests quotations and prepares the bid analysis.\nManager: approves awards and purchase orders within their authority.\nPharmacist in charge: approves new suppliers.",
                'procedure' => "Buy only from suppliers with a valid PPB licence recorded in PharmaPoint.\nRaise a requisition from the reorder suggestions or a department's request.\nFor significant purchases, request quotations from at least three suppliers in PharmaPoint (Supplier Quotes & CBA).\nRecord each quotation and review the competitive bid analysis: price, delivery time, payment terms and the supplier's record.\nAward to the recommended supplier, or record the reason when choosing another.\nApprove the purchase order in PharmaPoint and send it to the supplier.\nReceive the goods under the receiving SOP and match the supplier invoice before payment.",
                'records' => "Requisitions, quotations, bid analysis and purchase orders in PharmaPoint.\nSupplier licences and approval records.",
                'references' => "PPB Guidelines on Good Distribution Practices.\n{company} procurement policy.",
            ],
        ],
        'cleaning' => [
            'code' => 'SOP-011',
            'title' => 'Cleaning and pest control',
            'category' => 'SOP',
            'summary' => 'Daily, weekly and monthly cleaning of dispensing and storage areas; pest control.',
            'sections' => [
                'purpose' => 'To keep the premises of {company} clean, hygienic and free of pests.',
                'scope' => 'Dispensing, retail, storage and cold-chain areas at {branch}.',
                'responsibilities' => "All staff: keep their work areas clean.\nSupervisor: checks the cleaning schedule is followed.\nPest control contractor: treats the premises on schedule.",
                'procedure' => "Daily: clean counters, dispensing trays and counting equipment; sweep and mop floors; empty bins.\nWeekly: dust shelves and products; clean the refrigerator exterior.\nMonthly: deep-clean storage areas and defrost refrigerators (see the cold chain SOP).\nUse only approved cleaning agents; never store them with medicines.\nArrange pest control at least every quarter and keep the service reports.\nRecord each cleaning task on the cleaning log.",
                'records' => "Cleaning log.\nPest control service reports.",
                'references' => 'PPB premises inspection checklist.',
            ],
        ],
        'pharmacovigilance' => [
            'code' => 'SOP-012',
            'title' => 'Pharmacovigilance: reporting adverse drug reactions',
            'category' => 'SOP',
            'summary' => 'Recognising, recording and reporting suspected adverse reactions and poor-quality products to PPB.',
            'sections' => [
                'purpose' => 'To make sure suspected adverse drug reactions and poor-quality medicines seen at {company} are recorded and reported to PPB.',
                'scope' => 'All staff at {branch} who deal with customers or products.',
                'responsibilities' => "Any staff member: records a customer's complaint of a suspected reaction or poor-quality product.\nPharmacist in charge: completes and submits the report to PPB.",
                'procedure' => "Listen to the customer and record the medicine, batch, the reaction and when it started in PharmaPoint Pharmacovigilance.\nAdvise the customer to seek medical care where the reaction is serious.\nThe pharmacist reviews the report and submits it to PPB through the PvERS portal within the required time.\nFor suspected poor-quality products, quarantine the affected batch in PharmaPoint pending the outcome.\nRecord the PPB reference number against the report.",
                'records' => "ADR and poor-quality product reports in PharmaPoint.\nPPB submission references.",
                'references' => "PPB Guidelines for the National Pharmacovigilance System.\nPPB Pharmacovigilance Electronic Reporting System (PvERS).",
            ],
        ],
    ];

    /**
     * Every template with {company} and {branch} filled in.
     *
     * @return list<array{key: string, code: string, title: string, category: string, summary: string, sections: array<string, string>}>
     */
    public function all(string $company, string $branch): array
    {
        $out = [];
        foreach (array_keys(self::TEMPLATES) as $key) {
            $out[] = $this->find($key, $company, $branch);
        }

        return $out;
    }

    /**
     * @return array{key: string, code: string, title: string, category: string, summary: string, sections: array<string, string>}|null
     */
    public function find(string $key, string $company, string $branch): ?array
    {
        $template = self::TEMPLATES[$key] ?? null;
        if ($template === null) {
            return null;
        }

        $fill = fn (string $text) => strtr($text, ['{company}' => $company, '{branch}' => $branch]);

        return [
            'key' => $key,
            'code' => $template['code'],
            'title' => $template['title'],
            'category' => $template['category'],
            'summary' => $fill($template['summary']),
            'sections' => array_map($fill, $template['sections']),
        ];
    }
}
