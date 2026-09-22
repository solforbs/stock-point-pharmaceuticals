<?php

namespace App\Services\Training;

use App\Console\Commands\CreateAdminUser;

/**
 * The PharmaPoint training pack (client item 17): seven modules, one per
 * job, each with lessons written from the real screens, practice tasks done
 * in the live system and a knowledge check.
 *
 * The curriculum lives here, in code, rather than in the database so it
 * ships with (and is reviewed alongside) the release that changes the
 * screens it describes. The knowledge-check answers are here too, which is
 * the point: forBrowser() strips them, and TrainingController scores every
 * attempt on the server.
 *
 * A task's `verify` rule (see TrainingTaskVerifier) lets the system confirm
 * the trainee really did it; a task without one is self-confirmed.
 */
final class TrainingCatalogue
{
    /** The knowledge-check pass mark, in percent. */
    public const PASS_MARK = 70;

    /**
     * @return list<array{
     *     key: string, title: string, summary: string, audience: string, roles: list<string>,
     *     lessons: list<array{key: string, title: string, summary: string, body: list<string>, steps: list<string>, tips?: list<string>, route?: string, route_label?: string, tour?: string}>,
     *     tasks: list<array{key: string, title: string, instructions: string, checklist: list<string>, route?: string, verify?: array<string, mixed>}>,
     *     quiz: list<array{id: string, question: string, options: list<string>, answer: int, lesson?: string}>
     * }>
     */
    public static function modules(): array
    {
        return [
            self::gettingStarted(),
            self::cashier(),
            self::stores(),
            self::procurement(),
            self::quality(),
            self::finance(),
            self::managers(),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function module(string $key): ?array
    {
        foreach (self::modules() as $module) {
            if ($module['key'] === $key) {
                return $module;
            }
        }

        return null;
    }

    /**
     * A module as the browser may see it: no answers, no verification rules.
     *
     * @param  array<string, mixed>  $module
     * @return array<string, mixed>
     */
    public static function forBrowser(array $module): array
    {
        return [
            'key' => $module['key'],
            'title' => $module['title'],
            'summary' => $module['summary'],
            'audience' => $module['audience'],
            'lessons' => array_map(fn (array $lesson) => [
                'key' => $lesson['key'],
                'title' => $lesson['title'],
                'summary' => $lesson['summary'],
                'body' => $lesson['body'],
                'steps' => $lesson['steps'],
                'tips' => $lesson['tips'] ?? [],
                'route' => $lesson['route'] ?? null,
                'route_label' => $lesson['route_label'] ?? null,
                'tour' => $lesson['tour'] ?? null,
            ], $module['lessons']),
            'tasks' => array_map(fn (array $task) => [
                'key' => $task['key'],
                'title' => $task['title'],
                'instructions' => $task['instructions'],
                'checklist' => $task['checklist'],
                'route' => $task['route'] ?? null,
                'auto_verified' => isset($task['verify']),
            ], $module['tasks']),
            'quiz' => array_map(fn (array $question) => [
                'id' => $question['id'],
                'question' => $question['question'],
                'options' => $question['options'],
            ], $module['quiz']),
        ];
    }

    /**
     * Whether a module is meant for someone holding these roles. A module
     * with no roles is for everyone.
     *
     * @param  array<string, mixed>  $module
     * @param  list<string>  $roles
     */
    public static function isRecommendedFor(array $module, array $roles): bool
    {
        return $module['roles'] === [] || array_intersect($module['roles'], $roles) !== [];
    }

    /** @return array<string, mixed> */
    private static function gettingStarted(): array
    {
        return [
            'key' => 'getting-started',
            'title' => 'Getting started with PharmaPoint',
            'summary' => 'Signing in, finding your way around, searching, messages and alerts, and how practice in the live system works.',
            'audience' => 'Everyone',
            'roles' => [],
            'lessons' => [
                [
                    'key' => 'sign-in',
                    'title' => 'Signing in and your branch',
                    'summary' => 'Your account, your password and the branch you are working in.',
                    'body' => [
                        'Every person has their own account. Never share a password or work on someone else\'s login: every sale, receipt and approval is recorded against the person signed in, and the audit log keeps that record permanently.',
                        'Your manager creates your account with a temporary password. You will usually be asked to choose your own password the first time you sign in. Roles are given per branch, so what you can see and do depends on the branch you are working in.',
                        'If you work in more than one branch, the branch selector in the sidebar switches between them. Everything you post (sales, stock movements, payments) belongs to the branch that is active at the time.',
                    ],
                    'steps' => [
                        'Open the PharmaPoint link your manager sent you and sign in with your username or email and the temporary password.',
                        'Choose a new password if you are asked to. Use at least 12 characters and keep it to yourself.',
                        'Check the branch name in the sidebar. If you work in several branches, switch to the one you are standing in.',
                        'Sign out when you leave the counter, especially on a shared computer.',
                    ],
                    'tips' => ['Too many wrong passwords lock the account for a while. A manager with user administration rights can unlock it.'],
                    'route' => '/dashboard',
                    'route_label' => 'Dashboard',
                    'tour' => 'dashboard',
                ],
                [
                    'key' => 'navigation',
                    'title' => 'Finding your way: sidebar, search and shortcuts',
                    'summary' => 'The workspaces in the sidebar, the universal search (Ctrl+K) and keyboard shortcuts (?).',
                    'body' => [
                        'The sidebar groups the system into workspaces: Sell, Inventory, Buy, Warehouse, Customers, Finance, Quality & Compliance, Reports, People and Admin. Open a workspace to see its screens. You only see the actions your role allows; the server checks every request as well, so a hidden button is never the only protection.',
                        'Press Ctrl+K anywhere to open the universal search: "Search medicines, batches, customers, pages, or commands...". It is the fastest way to reach a product, a customer or a screen.',
                        'Press ? (question mark) when you are not typing in a box to see the keyboard shortcuts. The POS in particular is designed to be used from the keyboard.',
                    ],
                    'steps' => [
                        'Press Ctrl+K, type part of a medicine name and open it from the results.',
                        'Press Ctrl+K again and type the name of a screen, for example "Batches", to jump straight to it.',
                        'Press ? to open the keyboard shortcuts and read the list once.',
                        'Use "Start Guided Product Tour" in the top bar on any screen to get a short walk-through of that screen.',
                    ],
                    'route' => '/dashboard',
                    'route_label' => 'Dashboard',
                    'tour' => 'dashboard',
                ],
                [
                    'key' => 'dashboard',
                    'title' => 'The dashboard, alerts and messages',
                    'summary' => 'Today\'s numbers, approvals waiting for you, the alert bell and messages between colleagues.',
                    'body' => [
                        'The dashboard shows today\'s sales, stock expiring within 90 days, batches waiting for QC, low stock and (for finance roles) what customers owe. "Approvals Waiting" lists only the queues you are allowed to approve: requisitions, purchase orders, stock adjustments, transfers, counts and customer returns.',
                        'The alert bell counts standing alerts: customer invoices falling due, supplier invoices to pay and batches running out of shelf life. Alerts are recomputed every morning; you can mark one as seen, but it only disappears when the underlying problem is fixed.',
                        'The messages icon lets you send a message to one colleague or to everyone in the branch, for example "Till 2 needs a price override". Messages arrive immediately and stay in the inbox until read.',
                    ],
                    'steps' => [
                        'Open the dashboard and find today\'s sales total and the Quick Actions.',
                        'Open the alert bell and read what it lists for your branch.',
                        'Open messages, choose a colleague, write a short subject and message, and send it.',
                    ],
                    'route' => '/dashboard',
                    'route_label' => 'Dashboard',
                    'tour' => 'dashboard',
                ],
                [
                    'key' => 'practice-safely',
                    'title' => 'Practising in the live system',
                    'summary' => 'Practice tasks are done in the real PharmaPoint. Here is how to do that safely.',
                    'body' => [
                        'The practice tasks in this training centre are done in the real system, on real screens, so you learn exactly what you will use at work. When you press "Start task", the system notes the time; when you press "Mark as done", it looks for the record you created after that time and, where it can, marks the task "Verified by the system".',
                        'Because the records are real, agree with your manager before you start: use the training store or customer they tell you to, use small quantities, and let a supervisor void or reverse anything that should not stay in the books. Never practise with a real patient waiting.',
                    ],
                    'steps' => [
                        'Ask your manager which store, customer and products to use for practice.',
                        'Open a practice task, read the checklist, then press "Start task".',
                        'Do the task on the real screen (the "Open this screen" link takes you there).',
                        'Come back, add a note if anything was unclear, and press "Mark as done".',
                    ],
                    'route' => '/training',
                    'route_label' => 'Training centre',
                ],
            ],
            'tasks' => [
                [
                    'key' => 'search',
                    'title' => 'Find a medicine and a screen with Ctrl+K',
                    'instructions' => 'Use the universal search to open a product and then to jump to the Batches & Expiry screen.',
                    'checklist' => ['Opened a product from the Ctrl+K results', 'Reached Batches & Expiry by typing its name in Ctrl+K', 'Opened the keyboard shortcuts list with ?'],
                    'route' => '/dashboard',
                ],
                [
                    'key' => 'message',
                    'title' => 'Send a message to a colleague',
                    'instructions' => 'Send a short message to your supervisor saying you have started your PharmaPoint training.',
                    'checklist' => ['Chose one colleague as the recipient', 'Wrote a subject and a message', 'The message appears in their inbox'],
                    'route' => '/dashboard',
                    'verify' => ['table' => 'user_messages', 'user_column' => 'sender_id', 'label' => 'a message you sent'],
                ],
            ],
            'quiz' => [
                ['id' => 'gs1', 'lesson' => 'sign-in', 'question' => 'A colleague asks to use your login for a few minutes because theirs is locked. What do you do?', 'options' => ['Let them, as long as you stay nearby', 'Refuse: everything is recorded against the person signed in; ask a manager to unlock their account', 'Give them your password and change it tomorrow', 'Sign in for them and let them sell'], 'answer' => 1],
                ['id' => 'gs2', 'lesson' => 'navigation', 'question' => 'Which key opens the universal search for medicines, customers and screens?', 'options' => ['F2', 'Ctrl+K', 'F10', 'Ctrl+P'], 'answer' => 1],
                ['id' => 'gs3', 'lesson' => 'navigation', 'question' => 'A button you expected is not visible on a screen. What is the most likely reason?', 'options' => ['The system is broken', 'Your role in this branch does not include that permission', 'You need to refresh five times', 'The button only appears at night'], 'answer' => 1],
                ['id' => 'gs4', 'lesson' => 'sign-in', 'question' => 'You work in two branches. Where does a sale you post belong?', 'options' => ['Always the head office', 'The branch that is active in the sidebar when you post it', 'The branch you worked in yesterday', 'Both branches'], 'answer' => 1],
                ['id' => 'gs5', 'lesson' => 'dashboard', 'question' => 'An expiry alert is still showing after you marked it as seen. Why?', 'options' => ['Marking as seen deletes it only on Mondays', 'Alerts disappear only when the underlying problem is fixed (the stock is sold, moved or disposed of)', 'You must mark it seen twice', 'Alerts never disappear'], 'answer' => 1],
                ['id' => 'gs6', 'lesson' => 'practice-safely', 'question' => 'How does a practice task become "Verified by the system"?', 'options' => ['Your manager ticks it', 'The system finds the record you created after you pressed "Start task"', 'It happens automatically after a day', 'By answering the quiz'], 'answer' => 1],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function cashier(): array
    {
        return [
            'key' => 'cashier-pos',
            'title' => 'Cashier & POS',
            'summary' => 'Selling at the counter: sale modes, the stock room, searching and scanning, discounts, payment, holds, offline selling, quotations and invoices.',
            'audience' => 'Cashiers, senior cashiers and counter pharmacists',
            'roles' => ['Cashier', 'Senior Cashier', 'Pharmacist', 'Operations Manager'],
            'lessons' => [
                [
                    'key' => 'pos-setup',
                    'title' => 'Before the first sale: sale mode and stock room',
                    'summary' => 'Retail, Wholesale or Dispensing, and which room the stock comes out of.',
                    'body' => [
                        'The ribbon at the top of the POS shows "Sale Mode:" with the modes your branch trades in: Retail for walk-in patients, Wholesale for clinics, hospitals and other pharmacies (a customer account is required), and Dispensing where enabled. Switching mode needs the mode-switch permission, and if the cart already has items you are asked to confirm.',
                        '"Stock Room:" tells the POS which store the medicine is taken from. Only stores marked as sellable can be used. Stock received into the MAIN warehouse cannot be sold at the retail counter until it has been transferred to a sellable store.',
                    ],
                    'steps' => [
                        'Open Sell → POS.',
                        'Check the sale mode pill: Retail for a walk-in customer.',
                        'Check the Stock Room is the counter you are selling from.',
                    ],
                    'tips' => ['If the POS says "No stores in this branch" or "Commerce is disabled for this branch", ask a manager: the branch set-up needs attention.'],
                    'route' => '/sell/pos',
                    'route_label' => 'POS',
                    'tour' => 'pos',
                ],
                [
                    'key' => 'pos-cash-sale',
                    'title' => 'Ringing up a cash or M-Pesa sale',
                    'summary' => 'Scan or search, adjust the line, take payment, print.',
                    'body' => [
                        'The fastest path is shown on the screen: "Scan barcode / F2 → Enter → F10 Tender → Enter to Print." Prices, tax and the batch to sell (first-expired, first-out) are worked out by the server every time the cart changes, so you never type a price.',
                        'The payment drawer has three tabs. "Cash" shows Total Payable, Cash Tendered and Change Due. "Split / M-Pesa" lets you take several payments (Cash, M-Pesa, Bank, Card, Cheque) with an M-Pesa code or reference for each. "On Credit" is only for wholesale customers with a credit account.',
                    ],
                    'steps' => [
                        'Press F2 (or click the search box) and scan the barcode, or type the medicine name and press Enter.',
                        'Change the quantity with F3 and the unit (tablet, strip, box) with F4 if needed.',
                        'Leave the customer as "Walk-in default" for a retail sale, or press F5 to choose a customer account.',
                        'Press F10 ("PROCEED TO PAYMENT"). Enter the cash tendered, or use Split / M-Pesa and type the M-Pesa code.',
                        'Press "COMPLETE SALE". Print or download the receipt, then press Enter for a new sale.',
                    ],
                    'tips' => [
                        'If the POS says a price changed, it shows the new price for you to accept. If it says the quote expired, it re-prices the cart.',
                        'A batch that is expired, recalled or quarantined is never sold; the POS explains which line is blocked.',
                    ],
                    'route' => '/sell/pos',
                    'route_label' => 'POS',
                    'tour' => 'pos',
                ],
                [
                    'key' => 'pos-discounts-holds',
                    'title' => 'Discounts, holds and keyboard shortcuts',
                    'summary' => 'Applying a discount with a reason, parking a cart and resuming it.',
                    'body' => [
                        'Press F6 on a line to give a discount, either as "Disc %" or as a price, with a reason (the reason is required). Your role has a discount limit. A discount above it shows "Applied discounts exceed authority threshold. Approver sign-off needed." and the sale cannot post until someone with discount-approval rights approves it.',
                        'Press F8 to hold the current cart (for example while the customer fetches money) and F9 to see held carts. Resuming a held cart replaces the cart on the screen, so finish or hold the current one first. Ctrl+Del removes a line after a confirmation.',
                    ],
                    'steps' => [
                        'Add a product, press F6, enter a small percentage and a reason.',
                        'Press F8, give the cart a name and hold it.',
                        'Press F9 and resume the held cart.',
                        'Press ? to review every POS shortcut.',
                    ],
                    'route' => '/sell/pos',
                    'route_label' => 'POS',
                    'tour' => 'pos',
                ],
                [
                    'key' => 'pos-offline',
                    'title' => 'When the internet goes down',
                    'summary' => 'Offline selling: what is allowed and what happens when the connection returns.',
                    'body' => [
                        'If the server cannot be reached, the POS switches to offline selling and shows a strip: "Offline — Selling walk-in retail, paid in full, from the price list of …". Only walk-in retail sales paid in full by Cash, M-Pesa or Card are allowed; credit needs the server.',
                        'Offline receipts are numbered OFFLINE-<terminal>-…, and the eTIMS invoice is issued when the sale reaches the server. When the connection returns, waiting sales are sent automatically while their cashier is signed in. Any that cannot post (for example because the stock is no longer there) appear in Admin → Sync Centre for a supervisor.',
                    ],
                    'steps' => [
                        'Notice the offline strip at the top of the POS.',
                        'Sell as usual, but only to walk-in customers paying in full.',
                        'When back online, stay signed in until the "offline sales are waiting to sync" message clears.',
                    ],
                    'route' => '/admin/sync-centre',
                    'route_label' => 'Sync Centre',
                ],
                [
                    'key' => 'quotations-invoices',
                    'title' => 'Quotations, invoices and voids',
                    'summary' => 'Quoting a clinic, turning the quote into a sales order, finding a past sale and voiding one.',
                    'body' => [
                        'Sell → Quotations → "New Quotation" opens "Create Wholesale Quotation": choose the customer account, the fulfilment store and "Valid Until" (7 days by default), add the lines and press "Create Quotation →". The customer\'s price tier is applied automatically, and "Download PDF" gives a copy to send.',
                        'When the customer agrees, open the quotation and press "Accept → sales order". This creates the sales order and confirms it in one step, reserving the stock under the credit check. If the customer is over their credit limit, only someone with credit-override rights can proceed, with a reason.',
                        'Sell → Invoices lists every posted sale, with a PDF button on each. Opening a sale shows "Return items" and, for those with void rights, "Void sale" with a reason of at least five characters. A void reverses the stock and the accounting and can only be done once, in an open financial period.',
                    ],
                    'steps' => [
                        'Open Sell → Quotations and press "New Quotation".',
                        'Choose the customer, the store and the validity date, add two products and create the quotation.',
                        'Open it and press "Download PDF".',
                        'Press "Accept → sales order" when the customer confirms.',
                    ],
                    'route' => '/sell/quotations',
                    'route_label' => 'Quotations',
                    'tour' => 'quotations',
                ],
            ],
            'tasks' => [
                [
                    'key' => 'cash-sale',
                    'title' => 'Ring up a retail cash sale',
                    'instructions' => 'In Retail mode, sell one strip of a practice product to a walk-in customer and take cash. Use the product and store your manager gave you for practice.',
                    'checklist' => ['Sale mode was Retail and the stock room was the counter', 'Found the product with F2 or the barcode scanner', 'Took payment with F10 and entered the cash tendered', 'The sale posted and a receipt was shown'],
                    'route' => '/sell/pos',
                    'verify' => ['table' => 'sales', 'user_column' => 'user_id', 'where' => ['status' => 'POSTED', 'sale_mode' => 'RETAIL'], 'label' => 'a retail sale you posted'],
                ],
                [
                    'key' => 'hold-resume',
                    'title' => 'Hold a cart and resume it',
                    'instructions' => 'Build a cart with two products, hold it with F8, start a new cart, then resume the held one with F9 and clear it.',
                    'checklist' => ['Held a cart with a name', 'Found it under Held Carts (F9)', 'Resumed it and removed the lines with Ctrl+Del'],
                    'route' => '/sell/pos',
                ],
                [
                    'key' => 'quotation',
                    'title' => 'Create a quotation and convert it to a sales order',
                    'instructions' => 'Create a wholesale quotation for the practice customer, download its PDF, then accept it into a sales order.',
                    'checklist' => ['Quotation created with a customer, store and validity date', 'Downloaded the quotation PDF', 'Pressed "Accept → sales order" and the quotation shows CONVERTED'],
                    'route' => '/sell/quotations',
                    'verify' => ['table' => 'quotations', 'user_column' => 'user_id', 'where' => ['status' => ['ACCEPTED', 'CONVERTED']], 'since_column' => 'updated_at', 'label' => 'a quotation you raised and converted'],
                ],
                [
                    'key' => 'reprint',
                    'title' => 'Find a past sale and print its invoice',
                    'instructions' => 'Open Sell → Invoices, filter to today, open one of your sales and download the invoice PDF.',
                    'checklist' => ['Filtered the list by date', 'Opened the sale drawer', 'Downloaded the invoice PDF'],
                    'route' => '/sell/invoices',
                    'verify' => ['table' => 'audit_logs', 'user_column' => 'user_id', 'where' => ['action' => 'INVOICE_PRINTED'], 'since_column' => 'occurred_at', 'label' => 'an invoice you printed'],
                ],
            ],
            'quiz' => [
                ['id' => 'c1', 'lesson' => 'pos-setup', 'question' => 'Stock was just received into the MAIN warehouse. Why can the retail counter not sell it yet?', 'options' => ['It must be sold within a week', 'Only sellable stores can be sold from; the stock must first be transferred to the counter', 'Retail can never sell received stock', 'The price has not been printed'], 'answer' => 1],
                ['id' => 'c2', 'lesson' => 'pos-cash-sale', 'question' => 'Which key sequence rings up a sale fastest?', 'options' => ['F2 search → Enter → F10 tender → Enter', 'F5 → F6 → F8', 'Ctrl+K → Ctrl+P', 'F9 → F3 → F1'], 'answer' => 0],
                ['id' => 'c3', 'lesson' => 'pos-cash-sale', 'question' => 'A customer pays part cash and part M-Pesa. Which payment tab do you use?', 'options' => ['Cash', 'On Credit', 'Split / M-Pesa', 'You must make two sales'], 'answer' => 2],
                ['id' => 'c4', 'lesson' => 'pos-discounts-holds', 'question' => 'What is required whenever you give a line discount with F6?', 'options' => ['Nothing', 'A reason; and approval if it is above your authority', 'The customer\'s ID number', 'A new price list'], 'answer' => 1],
                ['id' => 'c5', 'lesson' => 'pos-offline', 'question' => 'The internet is down. Which sale can you still make?', 'options' => ['A wholesale sale on credit', 'A walk-in retail sale paid in full', 'A sale to any customer on account', 'None at all'], 'answer' => 1],
                ['id' => 'c6', 'lesson' => 'pos-cash-sale', 'question' => 'Who decides which batch of a medicine is sold?', 'options' => ['The cashier picks any batch', 'The system, first-expired first-out, never an expired, recalled or quarantined batch', 'The newest batch always', 'The supplier'], 'answer' => 1],
                ['id' => 'c7', 'lesson' => 'quotations-invoices', 'question' => 'What does "Accept → sales order" on a quotation do?', 'options' => ['Deletes the quotation', 'Creates and confirms a sales order, reserving the stock under the credit check', 'Emails the customer only', 'Posts a cash sale'], 'answer' => 1],
                ['id' => 'c8', 'lesson' => 'quotations-invoices', 'question' => 'What happens when a sale is voided?', 'options' => ['It is deleted from the system', 'Stock and accounting are reversed, the reason is recorded, and it can only be done once', 'Only the receipt is cancelled', 'Nothing until month end'], 'answer' => 1],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function stores(): array
    {
        return [
            'key' => 'stores-receiving',
            'title' => 'Stores & receiving',
            'summary' => 'Receiving deliveries (GRN), batches and expiry, stock on hand and the ledger, transfers between stores, stock counts and adjustments.',
            'audience' => 'Storekeepers and stores supervisors',
            'roles' => ['Storekeeper', 'Operations Manager', 'Pharmacist'],
            'lessons' => [
                [
                    'key' => 'grn',
                    'title' => 'Receiving a delivery (Goods Receipt / GRN)',
                    'summary' => 'Checking a delivery against the purchase order and recording batch, expiry and quantities.',
                    'body' => [
                        'Buy → Goods Receipts → "New goods receipt" opens "New Goods Receipt (GRN)". Choose the approved purchase order and the receiving store; the lines fill in from the PO with Delivered and Accepted equal to the ordered quantity. Correct them to what actually arrived.',
                        'For each line record the Batch no., Expiry, and, where relevant, Mfg date, Temp °C and whether the certificate of analysis (COA) came with it. If you reject anything (damaged, short-dated, wrong item), enter the Rejected quantity and a reason. Accepted plus rejected cannot exceed delivered, and an expiry date in the past is refused.',
                        'Only the accepted quantity becomes stock, and every new batch starts as PENDING QC: it is counted but not free to sell until a pharmacist releases it. Receiving without a PO is only possible as an audited emergency receipt.',
                    ],
                    'steps' => [
                        'Open Buy → Goods Receipts and press "New goods receipt".',
                        'Choose the purchase order and the receiving store.',
                        'For every line, type the batch number and expiry exactly as printed on the pack, and correct Delivered / Accepted / Rejected.',
                        'Give a reason for any rejected quantity.',
                        'Press "Post goods receipt". Tell the pharmacist the batches are waiting for QC.',
                    ],
                    'tips' => ['Type the batch number exactly as on the carton: recalls are traced by batch.'],
                    'route' => '/buy/goods-receipts',
                    'route_label' => 'Goods Receipts',
                    'tour' => 'goods-receipts',
                ],
                [
                    'key' => 'batches-expiry',
                    'title' => 'Batches, expiry and FEFO',
                    'summary' => 'Batch statuses, expiry tiers and why the oldest-expiring stock goes first.',
                    'body' => [
                        'Inventory → Batches & Expiry lists every batch with its status and expiry, with filters for expiry within 30, 90 or 180 days. A batch is PENDING_QC after receipt, RELEASED once QC-approved, and can become QUARANTINED, EXPIRED, RECALLED, RETURNED_TO_SUPPLIER or DISPOSED.',
                        'Sales and transfers take stock first-expired, first-out (FEFO): the batch that expires soonest goes first. Only released batches are free to sell. Check the 90-day list every week and move short-dated stock to where it will sell, or raise it with your supervisor.',
                    ],
                    'steps' => [
                        'Open Inventory → Batches & Expiry.',
                        'Set "Expiring within" to 90 days.',
                        'Open a batch to see its status, store and quantity.',
                        'Open Inventory → Stock on Hand and compare "Free to sell" with "Pending QC" and "Quarantined".',
                    ],
                    'route' => '/inventory/batches',
                    'route_label' => 'Batches & Expiry',
                    'tour' => 'batches',
                ],
                [
                    'key' => 'transfers',
                    'title' => 'Transferring stock between stores',
                    'summary' => 'Create, approve, dispatch and receive, and what happens when quantities differ.',
                    'body' => [
                        'Inventory → Transfers → "New transfer": choose "From Store (Origin)" and "To Store (Destination)", add the batch lines and press "Create transfer". A transfer goes DRAFT → APPROVED → DISPATCHED → RECEIVED.',
                        'Someone other than the requester must approve it; the system refuses self-approval. Dispatch takes the stock out of the origin store (it is then in transit), and the receiving store confirms what arrived. Leaving a received quantity blank means the full amount; receiving less marks the transfer DISCREPANCY, which a supervisor resolves as found at source, found at destination or lost in transit.',
                    ],
                    'steps' => [
                        'Open Inventory → Transfers and press "New transfer".',
                        'Choose the MAIN store as origin and the counter as destination, add a batch line and create it.',
                        'Ask a supervisor to approve it, then press "Dispatch".',
                        'At the destination, press "Receive" and confirm the quantities.',
                    ],
                    'route' => '/inventory/transfers',
                    'route_label' => 'Transfers',
                    'tour' => 'transfers',
                ],
                [
                    'key' => 'counts-adjustments',
                    'title' => 'Stock counts and adjustments',
                    'summary' => 'Blind counts, variance reasons, and adjusting stock for breakage or loss.',
                    'body' => [
                        'Inventory → Counts → "Plan a count": choose the store and optionally limit it to some products, then "Start counting". Counting is blind: the system quantity is hidden so you count what is really there. Enter every line, press "Submit for review", give a reason for each variance, and a supervisor presses "Approve & post variances". Large variances (above KES 10,000 by default) need a second person to approve.',
                        'Inventory → Adjustments → "New adjustment" records breakage, theft, expiry, sampling, correction of an error, donation or cold-chain loss, with a direction (remove or add). Small adjustments post at once; above the approval threshold (KES 10,000 by default) they wait in the approval queue.',
                        'Every movement lands in Inventory → Stock Ledger, which is append-only: nothing is ever edited or deleted, mistakes are corrected with a new entry.',
                    ],
                    'steps' => [
                        'Open Inventory → Counts and press "Plan a count" for one shelf or a few products.',
                        'Press "Start counting" and enter the quantities you physically count.',
                        'Press "Submit for review" and add a reason code for every variance.',
                        'Open Inventory → Stock Ledger and filter by the product to see the movements.',
                    ],
                    'route' => '/inventory/counts',
                    'route_label' => 'Counts',
                    'tour' => 'counts',
                ],
            ],
            'tasks' => [
                [
                    'key' => 'receive',
                    'title' => 'Receive a delivery against a purchase order',
                    'instructions' => 'With your supervisor, post a goods receipt for a practice purchase order, entering batch numbers and expiry dates from the packs.',
                    'checklist' => ['Chose the purchase order and receiving store', 'Entered batch number and expiry for every line', 'Recorded any rejected quantity with a reason', 'Posted the GRN; the batches show PENDING QC'],
                    'route' => '/buy/goods-receipts',
                    'verify' => ['table' => 'goods_receipts', 'user_column' => 'received_by', 'label' => 'a goods receipt you posted'],
                ],
                [
                    'key' => 'transfer',
                    'title' => 'Create a transfer from the main store to the counter',
                    'instructions' => 'Create a transfer of a small quantity from MAIN to a sellable counter store and take it through to dispatch with a supervisor\'s approval.',
                    'checklist' => ['Chose origin and destination stores', 'Added at least one batch line', 'A different person approved it', 'Dispatched (and received at the destination)'],
                    'route' => '/inventory/transfers',
                    'verify' => ['table' => 'stock_transfers', 'user_column' => 'requested_by', 'label' => 'a transfer you created'],
                ],
                [
                    'key' => 'count',
                    'title' => 'Plan and enter a blind stock count',
                    'instructions' => 'Plan a count limited to two or three products, start counting and enter the quantities you find on the shelf.',
                    'checklist' => ['Planned a count for a store', 'Started counting without seeing system quantities', 'Entered every line and submitted for review'],
                    'route' => '/inventory/counts',
                    'verify' => ['table' => 'stock_counts', 'user_column' => 'created_by', 'label' => 'a stock count you planned'],
                ],
                [
                    'key' => 'expiry-review',
                    'title' => 'Review stock expiring within 90 days',
                    'instructions' => 'List batches expiring within 90 days and write in the note which ones you would move or return.',
                    'checklist' => ['Filtered Batches & Expiry to 90 days', 'Checked where each short-dated batch is stored', 'Wrote your recommendation in the note'],
                    'route' => '/inventory/batches',
                ],
            ],
            'quiz' => [
                ['id' => 's1', 'lesson' => 'grn', 'question' => 'After a goods receipt is posted, what status do the new batches have?', 'options' => ['RELEASED, ready to sell', 'PENDING QC, not free to sell until released', 'EXPIRED', 'DISPOSED'], 'answer' => 1],
                ['id' => 's2', 'lesson' => 'grn', 'question' => 'Ten cartons arrive, two are crushed. How do you record it?', 'options' => ['Delivered 10, Accepted 8, Rejected 2 with a reason', 'Accepted 10 and tell someone later', 'Delivered 8, and ignore the rest', 'Refuse to post the GRN'], 'answer' => 0],
                ['id' => 's3', 'lesson' => 'batches-expiry', 'question' => 'What does FEFO mean?', 'options' => ['First entered, first out', 'First expired, first out: the batch expiring soonest goes first', 'Fastest ever, fastest out', 'Full expiry, full order'], 'answer' => 1],
                ['id' => 's4', 'lesson' => 'transfers', 'question' => 'You created a transfer. Can you approve it yourself?', 'options' => ['Yes, always', 'No, the system requires a different person to approve it', 'Only on weekends', 'Only if it is small'], 'answer' => 1],
                ['id' => 's5', 'lesson' => 'transfers', 'question' => 'The destination receives fewer units than were dispatched. What happens?', 'options' => ['The difference is deleted', 'The transfer is marked DISCREPANCY for a supervisor to resolve', 'The origin store gets the stock back automatically', 'Nothing'], 'answer' => 1],
                ['id' => 's6', 'lesson' => 'counts-adjustments', 'question' => 'Why is the stock count blind?', 'options' => ['To save screen space', 'So you count what is really on the shelf instead of copying the system figure', 'Because the system does not know the quantity', 'It is not blind'], 'answer' => 1],
                ['id' => 's7', 'lesson' => 'counts-adjustments', 'question' => 'A wrong entry was made in the stock ledger. How is it fixed?', 'options' => ['Edit the ledger row', 'Delete the row', 'Post a correcting movement (e.g. an adjustment); the ledger is append-only', 'Ask IT to change the database'], 'answer' => 2],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function procurement(): array
    {
        return [
            'key' => 'procurement',
            'title' => 'Procurement',
            'summary' => 'Suppliers, requisitions and reorder suggestions, purchase orders, supplier invoices and the three-way match.',
            'audience' => 'Procurement officers and purchasing managers',
            'roles' => ['Procurement Officer', 'Operations Manager', 'Director'],
            'lessons' => [
                [
                    'key' => 'suppliers',
                    'title' => 'Suppliers and their licences',
                    'summary' => 'Keeping supplier records, terms and PPB licence dates current.',
                    'body' => [
                        'Buy → Suppliers ("Suppliers & Vendors") holds each supplier\'s code, name, PPB licence number and licence expiry date, payment terms and lead time, status (Active, Suspended, Blacklisted), currency and bank details (stored encrypted).',
                        'The system refuses a purchase order to a supplier that is not Active or whose licence has expired, so keep licence dates current: a lapsed licence will stop an urgent order.',
                    ],
                    'steps' => [
                        'Open Buy → Suppliers and search for a supplier.',
                        'Open it and check the PPB licence expiry and payment terms.',
                        'Press "Edit" to correct anything out of date, then "Save changes".',
                    ],
                    'route' => '/buy/suppliers',
                    'route_label' => 'Suppliers',
                    'tour' => 'suppliers',
                ],
                [
                    'key' => 'requisitions',
                    'title' => 'Requisitions and reorder suggestions',
                    'summary' => 'Asking for stock, approving the request and converting it into a purchase order.',
                    'body' => [
                        'Buy → Requisitions has two tabs: "Requisitions" and "Reorder suggestions". The reorder tab lists products below their reorder point; tick them and press "Create requisition from N selected".',
                        'A requisition goes DRAFT → PENDING_APPROVAL → APPROVED (or REJECTED, with a reason) → CONVERTED. "New requisition" takes a "Needed By Date", a justification and product lines; "Submit" sends it for approval. Once approved, "Convert to PO" asks for the supplier, expected date, purchase unit, quantity and price, and creates a purchase order that still needs its own approval.',
                    ],
                    'steps' => [
                        'Open Buy → Requisitions and look at the "Reorder suggestions" tab.',
                        'Create a requisition from two suggestions, set the needed-by date and a justification.',
                        'Press "Submit".',
                        'After approval, press "Convert to PO" and choose an active supplier.',
                    ],
                    'route' => '/buy/requisitions',
                    'route_label' => 'Requisitions',
                    'tour' => 'requisitions',
                ],
                [
                    'key' => 'purchase-orders',
                    'title' => 'Purchase orders',
                    'summary' => 'Raising, approving and sending an order to the supplier.',
                    'body' => [
                        'Buy → Purchase Orders → "New Purchase Order": choose the supplier and expected delivery date and add lines, then "Create Purchase Order". It is saved as DRAFT.',
                        'A manager with PO approval rights presses "Approve". Then "Send to Supplier" marks it SENT and emails it; "Download PO PDF" gives a copy. As goods arrive the PO moves to PARTIALLY_RECEIVED and RECEIVED. Good practice is that the person who raised a PO does not approve it.',
                    ],
                    'steps' => [
                        'Open Buy → Purchase Orders and press "New Purchase Order".',
                        'Choose an active supplier, set the expected date, add lines with quantity and price.',
                        'Create it, and ask a manager to approve.',
                        'Press "Send to Supplier" once approved.',
                    ],
                    'route' => '/buy/purchase-orders',
                    'route_label' => 'Purchase Orders',
                    'tour' => 'purchase-orders',
                ],
                [
                    'key' => 'three-way-match',
                    'title' => 'Supplier invoices and the three-way match',
                    'summary' => 'Recording the supplier\'s invoice and matching it to the PO and what was received.',
                    'body' => [
                        'Buy → Supplier Invoices → "Record supplier invoice": choose the supplier and purchase order, enter the invoice number, invoice date, due date and tax total; the lines load from the PO for you to enter the invoiced quantity and price. The invoice starts UNMATCHED.',
                        'Open it and press "Run three-way match". The system compares the invoice with the PO and with what was actually accepted on the goods receipt. The invoiced quantity may not exceed what was accepted, and a price difference fails only when it is more than 2% per unit and more than KES 500 on the line. A MATCHED invoice becomes a payable for Finance; otherwise it is an EXCEPTION with no payable until it is sorted out.',
                    ],
                    'steps' => [
                        'Open Buy → Supplier Invoices and press "Record supplier invoice".',
                        'Enter the supplier, PO, invoice number and dates exactly as printed.',
                        'Enter the invoiced quantities and prices, then "Record invoice".',
                        'Open the invoice and press "Run three-way match". Investigate any exception.',
                    ],
                    'route' => '/buy/supplier-invoices',
                    'route_label' => 'Supplier Invoices',
                    'tour' => 'supplier-invoices',
                ],
            ],
            'tasks' => [
                [
                    'key' => 'requisition',
                    'title' => 'Raise and submit a requisition',
                    'instructions' => 'Create a requisition from the reorder suggestions (or by hand) and submit it for approval.',
                    'checklist' => ['Set a needed-by date and a justification', 'Added at least one product line', 'Submitted it: status PENDING_APPROVAL'],
                    'route' => '/buy/requisitions',
                    'verify' => ['table' => 'requisitions', 'user_column' => 'requested_by', 'where' => ['status' => ['PENDING_APPROVAL', 'APPROVED', 'CONVERTED']], 'since_column' => 'updated_at', 'label' => 'a requisition you submitted'],
                ],
                [
                    'key' => 'purchase-order',
                    'title' => 'Create a purchase order',
                    'instructions' => 'Create a purchase order for a practice supplier with at least two lines. Do not send it unless your manager asks you to.',
                    'checklist' => ['Chose an active supplier with a valid licence', 'Entered an expected delivery date', 'Added lines with quantity and unit price', 'The PO is saved as DRAFT'],
                    'route' => '/buy/purchase-orders',
                    'verify' => ['table' => 'purchase_orders', 'user_column' => 'created_by', 'label' => 'a purchase order you created'],
                ],
                [
                    'key' => 'match',
                    'title' => 'Record a supplier invoice and match it',
                    'instructions' => 'For a purchase order that has been received, record the supplier\'s invoice and run the three-way match until it shows MATCHED.',
                    'checklist' => ['Recorded the invoice number and dates exactly as printed', 'Entered invoiced quantities and prices', 'Ran the three-way match', 'The invoice shows MATCHED (or you explained the exception in the note)'],
                    'route' => '/buy/supplier-invoices',
                    'verify' => ['table' => 'audit_logs', 'user_column' => 'user_id', 'where' => ['action' => 'INVOICE_MATCHED'], 'since_column' => 'occurred_at', 'label' => 'a supplier invoice you matched'],
                ],
            ],
            'quiz' => [
                ['id' => 'p1', 'lesson' => 'suppliers', 'question' => 'Why was a purchase order to a regular supplier refused?', 'options' => ['The PO was too large', 'The supplier is not Active or its PPB licence has expired', 'It was raised after 5pm', 'The supplier has too many orders'], 'answer' => 1],
                ['id' => 'p2', 'lesson' => 'requisitions', 'question' => 'What is the order of a requisition\'s life?', 'options' => ['APPROVED → DRAFT → CONVERTED', 'DRAFT → PENDING_APPROVAL → APPROVED → CONVERTED', 'CONVERTED → APPROVED → DRAFT', 'PENDING → RECEIVED → PAID'], 'answer' => 1],
                ['id' => 'p3', 'lesson' => 'requisitions', 'question' => 'Where do you find products that have fallen below their reorder point?', 'options' => ['Finance → Payables', 'Buy → Requisitions → Reorder suggestions', 'Admin → Settings', 'Sell → Invoices'], 'answer' => 1],
                ['id' => 'p4', 'lesson' => 'purchase-orders', 'question' => 'A requisition was converted to a purchase order. Can the PO be sent straight away?', 'options' => ['Yes, conversion approves it', 'No, the PO still needs its own approval before "Send to Supplier"', 'Only by email', 'Only if it is under KES 1,000'], 'answer' => 1],
                ['id' => 'p5', 'lesson' => 'three-way-match', 'question' => 'What three things does the three-way match compare?', 'options' => ['Invoice, purchase order and goods receipt', 'Invoice, bank statement and receipt', 'PO, requisition and budget', 'Supplier, customer and product'], 'answer' => 0],
                ['id' => 'p6', 'lesson' => 'three-way-match', 'question' => 'The supplier invoiced 100 units but only 90 were accepted on the GRN. What happens?', 'options' => ['It matches anyway', 'It becomes an EXCEPTION: invoiced quantity may not exceed what was accepted', 'The GRN is changed to 100', 'The extra 10 are added to stock'], 'answer' => 1],
                ['id' => 'p7', 'lesson' => 'three-way-match', 'question' => 'What does a MATCHED supplier invoice create?', 'options' => ['A customer invoice', 'A payable for Finance to pay', 'A new purchase order', 'A stock adjustment'], 'answer' => 1],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function quality(): array
    {
        return [
            'key' => 'pharmacist-quality',
            'title' => 'Pharmacist & quality',
            'summary' => 'QC release and quarantine, the cold chain, recalls, customer returns and disposal, and adverse drug reaction reports.',
            'audience' => 'Pharmacists and quality staff',
            'roles' => ['Pharmacist', 'Operations Manager'],
            'lessons' => [
                [
                    'key' => 'qc-release',
                    'title' => 'QC release and quarantine',
                    'summary' => 'Deciding whether received stock may be sold, and blocking stock that may not.',
                    'body' => [
                        'Quality & Compliance → Quarantine has two tabs, "Quarantined" and "Pending QC". Newly received batches wait under Pending QC. After checking the batch (packaging, labelling, expiry, certificate of analysis, temperature on arrival), press "Release": "Released stock becomes free to sell (FEFO order)."',
                        'Press "Quarantine" (with a reason) to block a batch you have doubts about. "Quarantined stock is blocked from every sale until released again." A quarantined batch can later be released, recalled or disposed of. Releasing and quarantining need the quality-release permission and are recorded in the audit log.',
                    ],
                    'steps' => [
                        'Open Quality & Compliance → Quarantine and choose the "Pending QC" tab.',
                        'Check the batch number and expiry against the physical pack and the GRN.',
                        'Press "Release" if it passes, or "Quarantine" with a reason if it does not.',
                    ],
                    'route' => '/quality/quarantine',
                    'route_label' => 'Quarantine',
                    'tour' => 'quarantine',
                ],
                [
                    'key' => 'cold-chain',
                    'title' => 'Cold chain readings and excursions',
                    'summary' => 'Recording temperatures and handling a reading outside the safe window.',
                    'body' => [
                        'Quality & Compliance → Cold Chain shows each store\'s window, for example 2–8 °C for a cold store and 15–25 °C for other stores unless the store\'s storage condition says otherwise. Record readings with "Record reading": store, temperature, humidity, source (manual or data logger) and the time taken (blank means now).',
                        'A reading below the minimum or above the maximum opens an excursion. It stays OPEN, even after temperatures return to normal, until someone with review rights presses "Take under review" and then closes it with an impact assessment and a decision: no impact, quarantine every released batch in the store, or stock disposed.',
                    ],
                    'steps' => [
                        'Open Quality & Compliance → Cold Chain.',
                        'In "Record reading", choose the fridge or store, type the temperature from the thermometer and press "Record reading".',
                        'Open the "Excursions" tab and check nothing is left OPEN.',
                    ],
                    'route' => '/quality/cold-chain',
                    'route_label' => 'Cold Chain',
                    'tour' => 'cold-chain',
                ],
                [
                    'key' => 'recalls',
                    'title' => 'Recalls',
                    'summary' => 'From the manufacturer\'s or PPB\'s notice to closing the recall.',
                    'body' => [
                        'Quality & Compliance → Recalls → "Initiate recall": choose the source (Manufacturer, PPB or Internal), the external reference, the reason and the batches in scope, then "Initiate and trace". The system traces which customers received those batches.',
                        'Follow the "Next step" card in order: "Block batches" (they become RECALLED and can no longer be sold), "Mark customers notified" (print the customer letters), "Reconcile", "Set disposition" (destroy or return to supplier) and finally "Close recall" once the stock has been disposed of or returned.',
                    ],
                    'steps' => [
                        'Open Quality & Compliance → Recalls and read an existing recall, or the empty screen.',
                        'Note the Next step card and the Customer letters button.',
                        'Only initiate a real recall when there is a real notice.',
                    ],
                    'route' => '/quality/recalls',
                    'route_label' => 'Recalls',
                    'tour' => 'recalls',
                ],
                [
                    'key' => 'returns-waste',
                    'title' => 'Customer returns and disposal',
                    'summary' => 'Inspecting returned items, choosing what happens to them, and writing off stock with witnesses.',
                    'body' => [
                        'A customer return starts from the sale (Sell → Invoices → open the sale → "Return items") and is created as DRAFT. The pharmacist inspects each line and sets a disposition: Quarantine (the default), Resaleable (needs quality-release rights), Destroy or Reject (not refunded), saves each line, then presses "Post return", which issues the credit note. If any line is Destroy, a witness who is a different person must be named.',
                        'Quality & Compliance → Waste & Disposal writes stock off: "New disposal" with the store, reason (expired, damaged, recalled, excursion, contaminated) and lines, then, in the disposal, the method, contractor, certificate and PPB references and two different witnesses before "Post disposal (write off)".',
                    ],
                    'steps' => [
                        'Open Sell → Returns and open a DRAFT customer return.',
                        'For each line choose the disposition and press "Save".',
                        'Name a second person as witness if anything is to be destroyed, then press "Post return".',
                    ],
                    'route' => '/sell/returns',
                    'route_label' => 'Returns',
                    'tour' => 'returns',
                ],
                [
                    'key' => 'adr',
                    'title' => 'Adverse drug reaction reports',
                    'summary' => 'Recording a suspected reaction for pharmacovigilance.',
                    'body' => [
                        'Quality & Compliance → Pharmacovigilance → "New ADR report": suspected product and batch, patient initials, age and sex, the reaction, onset date, seriousness, outcome, action taken and reporter. A report goes DRAFT → Submit → SUBMITTED → Close report → CLOSED, and can carry the PPB PViMS reference once reported.',
                    ],
                    'steps' => [
                        'Open Quality & Compliance → Pharmacovigilance.',
                        'Review the fields of "New ADR report" so you know what to ask the patient.',
                    ],
                    'route' => '/quality/pharmacovigilance',
                    'route_label' => 'Pharmacovigilance',
                    'tour' => 'pharmacovigilance',
                ],
            ],
            'tasks' => [
                [
                    'key' => 'reading',
                    'title' => 'Record a cold chain reading',
                    'instructions' => 'Read the thermometer on the vaccine fridge (or the store you are responsible for) and record it.',
                    'checklist' => ['Chose the right store', 'Entered the temperature as read, and humidity if available', 'Checked whether the reading is within the window shown on the store card'],
                    'route' => '/quality/cold-chain',
                    'verify' => ['table' => 'cold_chain_readings', 'user_column' => 'recorded_by', 'label' => 'a cold chain reading you recorded'],
                ],
                [
                    'key' => 'release',
                    'title' => 'Release a batch from Pending QC',
                    'instructions' => 'With the stores team, inspect a batch waiting under Pending QC and release it if it passes.',
                    'checklist' => ['Compared batch number and expiry with the pack and the GRN', 'Checked the certificate of analysis and arrival temperature where relevant', 'Released the batch (or quarantined it with a reason)'],
                    'route' => '/quality/quarantine',
                    'verify' => ['table' => 'product_batches', 'user_column' => 'qc_released_by', 'since_column' => 'qc_released_at', 'label' => 'a batch you released'],
                ],
                [
                    'key' => 'return',
                    'title' => 'Inspect a customer return',
                    'instructions' => 'Open a DRAFT customer return and set and save a disposition for each line.',
                    'checklist' => ['Set a disposition on every line', 'Chose Resaleable only for stock you are sure is fit to sell', 'Named a different witness if anything is to be destroyed'],
                    'route' => '/sell/returns',
                    'verify' => ['table' => 'customer_returns', 'user_column' => 'inspected_by', 'since_column' => 'updated_at', 'label' => 'a customer return you inspected'],
                ],
            ],
            'quiz' => [
                ['id' => 'q1', 'lesson' => 'qc-release', 'question' => 'What does releasing a batch from Pending QC do?', 'options' => ['Sends it back to the supplier', 'Makes it free to sell, in FEFO order', 'Deletes it', 'Moves it to another store'], 'answer' => 1],
                ['id' => 'q2', 'lesson' => 'qc-release', 'question' => 'You suspect a released batch is counterfeit. What do you do first in the system?', 'options' => ['Sell it quickly', 'Quarantine it with a reason: it is then blocked from every sale', 'Change its expiry date', 'Nothing until the supplier replies'], 'answer' => 1],
                ['id' => 'q3', 'lesson' => 'cold-chain', 'question' => 'A cold store (window 2–8 °C) reads 9.5 °C. What happens?', 'options' => ['Nothing, it is close enough', 'An excursion opens and stays open until reviewed and closed with a decision', 'The fridge is deleted', 'Stock is automatically disposed of'], 'answer' => 1],
                ['id' => 'q4', 'lesson' => 'cold-chain', 'question' => 'Temperatures are back to normal. Is the excursion finished?', 'options' => ['Yes, it closes itself', 'No, it stays OPEN until someone takes it under review and closes it with an impact assessment', 'Only after a week', 'Only if you delete the high reading'], 'answer' => 1],
                ['id' => 'q5', 'lesson' => 'recalls', 'question' => 'What does "Block batches" do in a recall?', 'options' => ['Hides the recall', 'Marks the batches RECALLED so they can no longer be sold', 'Notifies customers', 'Closes the recall'], 'answer' => 1],
                ['id' => 'q6', 'lesson' => 'returns-waste', 'question' => 'A returned item is to be destroyed. What does the system require before posting?', 'options' => ['Nothing extra', 'A witness who is a different person', 'The customer\'s signature', 'A new sale'], 'answer' => 1],
                ['id' => 'q7', 'lesson' => 'returns-waste', 'question' => 'What is the default disposition for a returned line?', 'options' => ['Resaleable', 'Quarantine', 'Destroy', 'Reject'], 'answer' => 1],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function finance(): array
    {
        return [
            'key' => 'finance',
            'title' => 'Finance',
            'summary' => 'Receivables and customer receipts, payables and supplier payments, reconciliation, journals and closing a period.',
            'audience' => 'Finance officers, directors and auditors',
            'roles' => ['Finance Officer', 'Director', 'Auditor'],
            'lessons' => [
                [
                    'key' => 'receivables',
                    'title' => 'Receivables and customer receipts',
                    'summary' => 'Who owes what, and recording money received.',
                    'body' => [
                        'Finance → Receivables shows each customer\'s balance aged into Current, 1–30, 31–60, 61–90 and 90+ days. Press "Record customer payment" (or "Receive" on a row): choose the customer, method and reference and the amount ("Full" fills the balance), then "Post receipt".',
                        'By default the money is allocated to the oldest invoices first; tick "Allocate to specific invoices" to choose. Allocations must add up to the receipt and can never exceed what an invoice still owes. An M-Pesa receipt needs its transaction code, and the same code is recognised as the same receipt, so a double entry cannot happen.',
                        'Sell → Customer Statements produces a statement for any date range with the running balance and ageing, as a PDF to send to the customer.',
                    ],
                    'steps' => [
                        'Open Finance → Receivables and find a customer with a balance.',
                        'Press "Receive", enter the method, reference and amount.',
                        'Leave the oldest-first allocation, or tick "Allocate to specific invoices" and choose.',
                        'Press "Post receipt".',
                    ],
                    'route' => '/finance/receivables',
                    'route_label' => 'Receivables',
                    'tour' => 'receivables',
                ],
                [
                    'key' => 'payables',
                    'title' => 'Payables and supplier payments',
                    'summary' => 'Paying suppliers for matched invoices.',
                    'body' => [
                        'Finance → Payables lists what is owed to suppliers. Only supplier invoices that passed the three-way match appear here. Press "Pay" on a row to open "Record supplier payment": method (bank, M-Pesa, cash or cheque), reference and amount, then "Post payment (Dr AP / Cr Bank)". A payment can never exceed the balance owed.',
                    ],
                    'steps' => [
                        'Open Finance → Payables and sort by due date.',
                        'Press "Pay" on an invoice that is due.',
                        'Enter the method, reference and amount, and post the payment.',
                    ],
                    'route' => '/finance/payables',
                    'route_label' => 'Payables',
                    'tour' => 'payables',
                ],
                [
                    'key' => 'reconciliation',
                    'title' => 'Payments and reconciliation',
                    'summary' => 'Ticking receipts off against the bank or M-Pesa statement.',
                    'body' => [
                        'Finance → Payments & Reconciliation lists receipts, unreconciled by default, with filters for dates and method. Tick the receipts that appear on the statement, press "Reconcile selected (n)", enter the statement reference and statement date, and press "Reconcile".',
                        'A reversed or already-reconciled payment cannot be reconciled. If you made a mistake, "Unreconcile" puts a receipt back, with a reason.',
                    ],
                    'steps' => [
                        'Open Finance → Payments & Reconciliation and set the method to M-Pesa and the dates to yesterday.',
                        'Compare with the M-Pesa statement and tick the receipts that match.',
                        'Press "Reconcile selected", enter the statement reference and date, and press "Reconcile".',
                    ],
                    'route' => '/finance/reconciliation',
                    'route_label' => 'Payments & Reconciliation',
                    'tour' => 'reconciliation',
                ],
                [
                    'key' => 'journals-periods',
                    'title' => 'Journals and closing a period',
                    'summary' => 'Manual journals, reversals and the month-end close.',
                    'body' => [
                        'Most accounting is posted automatically by sales, receipts, goods receipts and payments. For anything else, Finance → Journals → "New Journal Entry" posts a manual journal; it must show "Balanced" before "Post Journal" works. Journals are never edited: a mistake is corrected with "Reverse Journal", which posts an opposite journal and keeps both.',
                        'Finance → Periods lists the financial periods. "Close period" runs a checklist first and refuses (CLOSE_CHECKLIST_FAILED) if any journal is unbalanced or the stock ledger disagrees with the accounts. Once closed, nothing more can post into that period, so close only when the month is complete and reconciled.',
                    ],
                    'steps' => [
                        'Open Finance → Journals and open a posted journal to see its lines.',
                        'Open Finance → Periods and check which period is OPEN.',
                        'Do not close a period during training.',
                    ],
                    'route' => '/finance/periods',
                    'route_label' => 'Periods',
                    'tour' => 'periods',
                ],
            ],
            'tasks' => [
                [
                    'key' => 'receipt',
                    'title' => 'Record a customer receipt',
                    'instructions' => 'Record a practice receipt against the practice customer\'s oldest invoice, using a reference your manager gives you.',
                    'checklist' => ['Chose the customer and payment method', 'Entered the reference (M-Pesa code for M-Pesa)', 'Allocated to the right invoice(s)', 'Posted the receipt'],
                    'route' => '/finance/receivables',
                    // Till payments share the payments table; only a receipt posted in Finance is audited as PAYMENT_RECEIVED.
                    'verify' => ['table' => 'audit_logs', 'user_column' => 'user_id', 'where' => ['action' => 'PAYMENT_RECEIVED'], 'since_column' => 'occurred_at', 'label' => 'a customer receipt you posted'],
                ],
                [
                    'key' => 'reconcile',
                    'title' => 'Reconcile receipts against a statement',
                    'instructions' => 'Reconcile yesterday\'s M-Pesa or bank receipts against the statement.',
                    'checklist' => ['Filtered to the method and dates on the statement', 'Ticked only receipts that appear on the statement', 'Entered the statement reference and date'],
                    'route' => '/finance/reconciliation',
                    'verify' => ['table' => 'payments', 'user_column' => 'reconciled_by', 'since_column' => 'reconciled_at', 'label' => 'a receipt you reconciled'],
                ],
                [
                    'key' => 'ageing',
                    'title' => 'Review receivables ageing and send a statement',
                    'instructions' => 'Find the customer with the largest 90+ day balance and produce their statement PDF for this month.',
                    'checklist' => ['Found the 90+ column in Receivables', 'Produced the customer statement for the month', 'Wrote in the note what you would do next (call, credit hold)'],
                    'route' => '/sell/statements',
                ],
            ],
            'quiz' => [
                ['id' => 'f1', 'lesson' => 'receivables', 'question' => 'You post a receipt without choosing invoices. How is it allocated?', 'options' => ['To the newest invoice', 'To the oldest invoices first', 'It is left unallocated forever', 'Randomly'], 'answer' => 1],
                ['id' => 'f2', 'lesson' => 'receivables', 'question' => 'The same M-Pesa code is entered twice. What happens?', 'options' => ['Two receipts are posted', 'It is recognised as the same receipt, so it is not double-counted', 'The customer is refunded', 'The system crashes'], 'answer' => 1],
                ['id' => 'f3', 'lesson' => 'payables', 'question' => 'Why is a supplier invoice not showing in Payables?', 'options' => ['Payables only shows cash suppliers', 'It has not passed the three-way match yet', 'It is too small', 'Payables shows only last year'], 'answer' => 1],
                ['id' => 'f4', 'lesson' => 'reconciliation', 'question' => 'What do you need to reconcile selected receipts?', 'options' => ['The customer\'s phone number', 'A statement reference and statement date', 'The director\'s password', 'Nothing'], 'answer' => 1],
                ['id' => 'f5', 'lesson' => 'journals-periods', 'question' => 'A manual journal was posted with the wrong account. How do you correct it?', 'options' => ['Edit the journal', 'Delete it', 'Reverse it (an opposite journal is posted) and post the correct one', 'Close the period'], 'answer' => 2],
                ['id' => 'f6', 'lesson' => 'journals-periods', 'question' => 'What happens after a period is closed?', 'options' => ['Nothing more can be posted into it', 'It reopens next day', 'Sales are deleted', 'Stock is recounted'], 'answer' => 0],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function managers(): array
    {
        return [
            'key' => 'managers-admin',
            'title' => 'Managers & administrators',
            'summary' => 'Users and roles, approvals and separation of duties, reports and scheduled reports, the audit log and the training of your staff.',
            'audience' => 'Directors, operations managers and system administrators',
            'roles' => ['Director', 'Operations Manager', 'System Administrator', CreateAdminUser::SUPER_ADMINISTRATOR],
            'lessons' => [
                [
                    'key' => 'users-roles',
                    'title' => 'Users and roles',
                    'summary' => 'Giving a new staff member an account and the right roles in the right branch.',
                    'body' => [
                        'Admin → Users & Roles → "New user": name, username, email, phone and a temporary password of at least 12 characters, with "Must change password at next sign-in" ticked. "Role assignments by branch" is a grid of branches and roles: tick the role in each branch where the person works, then "Create user".',
                        'Roles are sets of permissions (Admin → Permissions shows the full matrix). Give people the role for their job, not more. Editing a user lets you deactivate them (never delete: their history must stay), reset multi-factor authentication and unlock a locked account. You cannot deactivate your own account.',
                    ],
                    'steps' => [
                        'Open Admin → Users & Roles and press "New user".',
                        'Fill in the details and a temporary password; keep "Must change password at next sign-in" ticked.',
                        'Tick the role (e.g. Cashier) in the branch where they will work, and create the user.',
                        'Send them the training link from the Training centre.',
                    ],
                    'route' => '/admin/users-roles',
                    'route_label' => 'Users & Roles',
                    'tour' => 'users-roles',
                ],
                [
                    'key' => 'approvals',
                    'title' => 'Approvals and separation of duties',
                    'summary' => 'What waits for your approval and why a second person matters.',
                    'body' => [
                        'The dashboard\'s "Approvals Waiting" shows the queues you can act on: requisitions and purchase orders pending approval, stock adjustments above the threshold, draft transfers, counts in review and draft customer returns. The POS also asks for approval when a discount exceeds the cashier\'s authority.',
                        'The system refuses self-approval of transfers, leave, payroll and large count variances, and requires two different witnesses for disposals and destroyed returns. For requisitions, purchase orders and stock adjustments it relies on you: make it a rule that the person who raised it does not approve it.',
                    ],
                    'steps' => [
                        'Open the dashboard and look at "Approvals Waiting".',
                        'Open a waiting item and read it fully before approving; reject with a reason if in doubt.',
                    ],
                    'route' => '/dashboard',
                    'route_label' => 'Dashboard',
                    'tour' => 'dashboard',
                ],
                [
                    'key' => 'reports',
                    'title' => 'Reports, analytics and scheduled reports',
                    'summary' => 'Getting numbers out: running, exporting and scheduling reports.',
                    'body' => [
                        'Reports → Report Catalogue lists the reports your role may see; "Run Report" shows it and "Export CSV" downloads it (exports are recorded in the audit log). Reports → Analytics has charts over a date range.',
                        'Reports → Scheduled Reports → "New schedule" emails a report as CSV to up to 20 recipients daily (the previous day), weekly (the seven days to yesterday) or monthly (the previous calendar month). A schedule runs with its creator\'s permissions; "Run and email now" sends it immediately.',
                    ],
                    'steps' => [
                        'Open Reports → Report Catalogue, run a sales report for last week and export it.',
                        'Open Reports → Scheduled Reports and create a weekly schedule to yourself.',
                        'Press "Run and email now" to check it arrives.',
                    ],
                    'route' => '/reports/catalogue',
                    'route_label' => 'Report Catalogue',
                    'tour' => 'reports-catalogue',
                ],
                [
                    'key' => 'audit-alerts',
                    'title' => 'The audit log, alerts and settings',
                    'summary' => 'Seeing who did what, and keeping the system\'s rules current.',
                    'body' => [
                        'Admin → Audit Log records every significant action with who, when, from where and the before/after values; filter by action, entity, text and dates. Admin → Alerts lists every standing alert (customer invoices, supplier invoices, shelf life) and "Scan now" recomputes them.',
                        'Admin → Settings holds the business rules, such as the approval thresholds for adjustments and count variances. Settings are versioned, and a branch value overrides the organisation value.',
                    ],
                    'steps' => [
                        'Open Admin → Audit Log and filter by today\'s date.',
                        'Open one entry and read its before and after values.',
                        'Open Admin → Settings and find the adjustment approval threshold.',
                    ],
                    'route' => '/admin/audit-log',
                    'route_label' => 'Audit Log',
                    'tour' => 'audit-log',
                ],
                [
                    'key' => 'training-staff',
                    'title' => 'Training your staff',
                    'summary' => 'Sending the training link, following progress and reading feedback.',
                    'body' => [
                        'The Training centre has a "Training link" to copy and send to new staff: they sign in with the account you created and land on their modules. Modules recommended for each person follow their roles, and everyone does "Getting started".',
                        'The "Staff progress" tab shows every person against every module: lessons read, practice tasks done (and how many the system verified), best knowledge-check score and the date each module was completed. A completion certificate can be printed for each completed module. The "Feedback" tab shows ratings and what trainees found unclear: use it to coach.',
                    ],
                    'steps' => [
                        'Open the Training centre and copy the training link.',
                        'Open "Staff progress" and find anyone who has not started.',
                        'Read the "Feedback" tab and follow up on anything unclear.',
                    ],
                    'route' => '/training',
                    'route_label' => 'Training centre',
                ],
            ],
            'tasks' => [
                [
                    'key' => 'create-user',
                    'title' => 'Create an account for a new staff member',
                    'instructions' => 'Create a user for a new (or practice) staff member with a temporary password and one role in one branch, then send them the training link.',
                    'checklist' => ['Temporary password of at least 12 characters, change at next sign-in ticked', 'Only the role their job needs, in the right branch', 'Sent them the training link'],
                    'route' => '/admin/users-roles',
                    'verify' => ['table' => 'audit_logs', 'user_column' => 'user_id', 'where' => ['action' => 'USER_CREATED'], 'since_column' => 'occurred_at', 'label' => 'a user account you created'],
                ],
                [
                    'key' => 'schedule-report',
                    'title' => 'Schedule a weekly report',
                    'instructions' => 'Create a weekly scheduled report (for example sales) emailed to yourself.',
                    'checklist' => ['Chose the report and weekly frequency', 'Chose the day and time', 'Added yourself as a recipient and saved'],
                    'route' => '/reports/scheduled',
                    'verify' => ['table' => 'scheduled_reports', 'user_column' => 'created_by', 'label' => 'a scheduled report you created'],
                ],
                [
                    'key' => 'export-report',
                    'title' => 'Run and export a report',
                    'instructions' => 'Run a report from the catalogue for last week and export it as CSV.',
                    'checklist' => ['Ran the report with a date range', 'Exported it as CSV', 'Opened the file'],
                    'route' => '/reports/catalogue',
                    'verify' => ['table' => 'audit_logs', 'user_column' => 'user_id', 'where' => ['action' => 'REPORT_EXPORTED'], 'since_column' => 'occurred_at', 'label' => 'a report you exported'],
                ],
                [
                    'key' => 'review-training',
                    'title' => 'Review your team\'s training',
                    'instructions' => 'Open the Staff progress tab, find who has not finished Getting started, and read the feedback.',
                    'checklist' => ['Found staff who have not started', 'Read the feedback tab', 'Wrote in the note what you will follow up'],
                    'route' => '/training',
                ],
            ],
            'quiz' => [
                ['id' => 'm1', 'lesson' => 'users-roles', 'question' => 'A cashier leaves the company. What do you do with their account?', 'options' => ['Delete it', 'Deactivate it, so their history stays and they can no longer sign in', 'Give it to the new cashier', 'Leave it active'], 'answer' => 1],
                ['id' => 'm2', 'lesson' => 'users-roles', 'question' => 'A new storekeeper works only in the Lodwar branch. How do you give access?', 'options' => ['Tick every role in every branch', 'Tick the Storekeeper role in the Lodwar branch only', 'Make them a Super Administrator', 'Share your login'], 'answer' => 1],
                ['id' => 'm3', 'lesson' => 'approvals', 'question' => 'For which of these does the system itself refuse self-approval?', 'options' => ['Stock transfers', 'Purchase orders', 'Requisitions', 'Stock adjustments'], 'answer' => 0],
                ['id' => 'm4', 'lesson' => 'approvals', 'question' => 'What should you do when approving something you raised yourself is technically possible?', 'options' => ['Approve it quickly', 'Have a different person approve it: separation of duties', 'Ignore it', 'Delete it'], 'answer' => 1],
                ['id' => 'm5', 'lesson' => 'reports', 'question' => 'A weekly scheduled report covers which period?', 'options' => ['The coming week', 'The seven days up to yesterday', 'The whole year', 'Only today'], 'answer' => 1],
                ['id' => 'm6', 'lesson' => 'audit-alerts', 'question' => 'Where do you see who changed a customer\'s credit limit and what it was before?', 'options' => ['Admin → Audit Log', 'Sell → POS', 'Buy → Suppliers', 'People → Leave'], 'answer' => 0],
                ['id' => 'm7', 'lesson' => 'training-staff', 'question' => 'How does a new staff member get to their training?', 'options' => ['You copy the training link, they sign in with the account you created', 'They register themselves', 'They email the software vendor', 'Training is only on paper'], 'answer' => 0],
            ],
        ];
    }
}
