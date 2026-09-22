import {
  Activity,
  BadgeCheck,
  BarChart3,
  Boxes,
  ClipboardCheck,
  FileText,
  GraduationCap,
  Landmark,
  PackageSearch,
  Receipt,
  ShieldCheck,
  Snowflake,
  Store,
  Truck,
  Users,
  WifiOff,
  type LucideIcon,
} from 'lucide-react'

/**
 * The public site's copy in one place, so the pages stay layout and the words
 * stay editable. Everything here describes what the system actually does.
 */

export type Highlight = { icon: LucideIcon; title: string; body: string }

/** The four things a pharmacy owner cares about before anything else. */
export const HEADLINE_POINTS: Highlight[] = [
  {
    icon: Store,
    title: 'Sell at the counter and to the trade',
    body: 'One till for retail walk-ins and wholesale invoices, with tiered price lists, discount approval and M-Pesa or cash settlement.',
  },
  {
    icon: WifiOff,
    title: 'Keeps selling when the internet does not',
    body: 'The till holds a price pack and takes sales offline, then syncs every receipt the moment the line comes back. Nothing is lost.',
  },
  {
    icon: Snowflake,
    title: 'Batch, expiry and cold chain, properly',
    body: 'Every unit carries its batch and expiry. FEFO is enforced on picking, quarantine is real, and cold-chain excursions are recorded.',
  },
  {
    icon: BadgeCheck,
    title: 'Built for Kenyan compliance',
    body: 'PPB premises and practice licences, county permits, 16% VAT, KRA eTIMS-ready invoicing and an audit trail that names who did what.',
  },
]

export type Module = { key: string; icon: LucideIcon; name: string; summary: string; points: string[] }

/** What is inside, grouped the way the software is grouped. */
export const MODULES: Module[] = [
  {
    key: 'sales',
    icon: Receipt,
    name: 'Sales',
    summary: 'The counter, the trade desk and everything the customer signs.',
    points: [
      'Touch point of sale with holds, shortcuts and offline selling',
      'Quotations that convert to sales orders and invoices',
      'Customer tiers, price lists, promotions and credit limits',
      'Returns, voids and credit notes with a reason on every one',
    ],
  },
  {
    key: 'inventory',
    icon: Boxes,
    name: 'Stock',
    summary: 'Know what you hold, where it sits and when it dies.',
    points: [
      'Stock grouped under Drugs, Cold chain, Vaccines, Supplies and more',
      'Batch and expiry tracking with FEFO picking and overrides on record',
      'Reorder points, low-stock alerts and expiry risk by tier',
      'Counts, adjustments and branch-to-branch transfers with approval',
    ],
  },
  {
    key: 'procurement',
    icon: PackageSearch,
    name: 'Procurement',
    summary: 'From the requisition to the invoice you are willing to pay.',
    points: [
      'Requisitions, purchase orders and supplier management',
      'Requests for quotation and a competitive bid analysis that explains its award',
      'Goods receipts that capture trade price, discount and the new selling price',
      'Three-way match: order against delivery against invoice, before payment',
    ],
  },
  {
    key: 'warehouse',
    icon: Truck,
    name: 'Warehouse',
    summary: 'Picking, dispatch and delivery that can be proven.',
    points: [
      'Picking lists allocated by batch, then packed and dispatched',
      'Delivery by vehicle, motorbike, hand or customer pickup — recorded either way',
      'Delivery notes and gate passes printed on your letterhead',
      'Transfers between branches with dispatch and receipt confirmation',
    ],
  },
  {
    key: 'finance',
    icon: Landmark,
    name: 'Finance',
    summary: 'The books close because the books were always posted.',
    points: [
      'Receivables, payables, statements and ageing',
      'Journals posted automatically from sales, receipts and receipts of goods',
      'M-Pesa and till reconciliation, VAT return and period close checklist',
      'Payroll bands, leave and staff costs',
    ],
  },
  {
    key: 'quality',
    icon: ClipboardCheck,
    name: 'Quality & compliance',
    summary: 'What an inspector asks for, already filed.',
    points: [
      'Twelve starter SOPs to adopt, edit and issue as version-controlled documents',
      'Licence register with renewal reminders for the premises and every pharmacist',
      'Recalls, waste disposal, adverse drug reaction reports and cold-chain logs',
      'An audit log of every posting, void, discount and approval',
    ],
  },
  {
    key: 'reports',
    icon: BarChart3,
    name: 'Reports',
    summary: 'Sixty-odd reports, and the ones you need arriving by themselves.',
    points: [
      'Sales, margin, stock, procurement, finance and management reports',
      'Scheduled to run at midday or close of business and emailed to you',
      'A report inbox to proof-read and mark each run verified or flagged',
      'Branch comparison, ABC analysis and a one-row KPI scorecard',
    ],
  },
  {
    key: 'people',
    icon: GraduationCap,
    name: 'People & training',
    summary: 'New staff learn the system inside the system.',
    points: [
      'Seven training courses with lessons, practice tasks and knowledge checks',
      'Tasks verified against what the trainee really did, not what they ticked',
      'Certificates on passing and a completion table for management',
      'Roles and permissions per branch, down to the single button',
    ],
  },
]

export type Step = { number: string; title: string; body: string }

export const HOW_IT_WORKS: Step[] = [
  { number: '01', title: 'We set up your institution', body: 'Branches, stores, users and roles, your product list loaded with its opening stock, and your prices as they stand today.' },
  { number: '02', title: 'Your team is trained inside the system', body: 'Each person works through the courses for their role and practises on real screens before the doors open.' },
  { number: '03', title: 'You go live', body: 'Sell, receive, transfer and bank as normal. The books post themselves and the reports arrive without being asked for.' },
  { number: '04', title: 'You grow', body: 'Add a branch, a store or a till and everything — stock, prices, permissions, reports — separates and consolidates on its own.' },
]

export type Faq = { question: string; answer: string }

export const FAQS: Faq[] = [
  {
    question: 'What happens when the internet goes down?',
    answer:
      'The till keeps selling. It holds a recent price pack and records each sale locally, then uploads them in order when the connection returns. Stock and the books catch up automatically, and you can see exactly which sales were made offline.',
  },
  {
    question: 'Is it ready for eTIMS and KRA?',
    answer:
      'Every sale is recorded with the detail KRA requires and the system is built to transmit to eTIMS. It runs in recording mode until you are ready, then switches to live transmission without changing how anyone works.',
  },
  {
    question: 'Can it handle more than one branch?',
    answer:
      'Yes. Stock, prices, users and reports are separate per branch and consolidate for the owner. Transfers move stock between branches with a dispatch and a receipt, so nothing moves unrecorded.',
  },
  {
    question: 'Who can see what?',
    answer:
      'Every screen, figure and button is gated by a permission attached to a role, and roles are assigned per branch. A cashier sees the till; a director sees the money. The audit log records who did what, and when.',
  },
  {
    question: 'What about our data?',
    answer:
      'Your institution is isolated from every other institution on the platform at the database level. Backups run before every update, and you can export your own data at any time.',
  },
  {
    question: 'How long does it take to start?',
    answer:
      'A single pharmacy with a clean product list can be live in days. The longest part is always the opening stock count — we import it from a spreadsheet, so the counting is the work, not the typing.',
  },
]

export const TRUST_POINTS: Highlight[] = [
  { icon: ShieldCheck, title: 'Isolated per institution', body: 'Your data is scoped to your institution on every single query.' },
  { icon: Activity, title: 'Audited end to end', body: 'Postings, voids, discounts and approvals all name the person behind them.' },
  { icon: FileText, title: 'Your documents, your brand', body: 'Invoices, orders and delivery notes carry your letterhead, stamp and signature.' },
  { icon: Users, title: 'Trained, not just installed', body: 'The training pack is part of the product, not an afterthought.' },
]

/** How to reach us, written once and used everywhere. */
export const PHONE = '0759 900 802'
export const PHONE_HREF = 'tel:+254759900802'
export const WHATSAPP_HREF = 'https://wa.me/254759900802'
export const EMAIL = 'solforbs@gmail.com'
export const EMAIL_HREF = 'mailto:solforbs@gmail.com'

/** The public pages, in the order they appear in the header. */
export const SITE_NAV = [
  { to: '/home', label: 'Home' },
  { to: '/features', label: 'Features' },
  { to: '/pricing', label: 'Pricing' },
  { to: '/about', label: 'About' },
  { to: '/contact', label: 'Contact' },
]
