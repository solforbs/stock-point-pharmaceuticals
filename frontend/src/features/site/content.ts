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
  Pill,
  Receipt,
  ShieldCheck,
  Store,
  Truck,
  Users,
  WifiOff,
  type LucideIcon,
} from 'lucide-react'
import { FacebookIcon, InstagramIcon, TikTokIcon, WhatsAppIcon, XIcon, YouTubeIcon, type BrandIcon } from './BrandIcons'

/**
 * The public site's copy in one place, so the pages stay layout and the words
 * stay editable. Everything here describes what the system actually does.
 */

export type Highlight = { icon: LucideIcon; title: string; body: string }

/** The four things a pharmacy owner cares about before anything else. */
export const HEADLINE_POINTS: Highlight[] = [
  {
    icon: Pill,
    title: 'Zero Expired Stock Losses',
    body: 'The till always prompts your cashiers to sell older batches first (FEFO). You get early warnings 90 days before any bottle expires.',
  },
  {
    icon: WifiOff,
    title: 'Sells Without Internet',
    body: 'When Wi-Fi or Safaricom drops, your queue keeps moving. Print receipts and ring up sales offline — everything auto-syncs when reconnected.',
  },
  {
    icon: Store,
    title: 'Automatic M-Pesa Balancing',
    body: 'Say goodbye to missing cash and fake SMS alerts. Every M-Pesa code and cash receipt reconciles automatically into your end-of-day till report.',
  },
  {
    icon: BadgeCheck,
    title: 'Audit & KRA eTIMS Ready',
    body: 'Pre-formatted for PPB pharmacy inspections, batch recall traceability, 16% VAT splits, and instant KRA eTIMS QR receipt generation.',
  },
]

export type ModuleCategory = 'all' | 'pharmacy' | 'finance' | 'operations'

export type Module = {
  key: string
  icon: LucideIcon
  name: string
  summary: string
  points: string[]
  category?: 'pharmacy' | 'finance' | 'operations'
  badge?: string
  role?: string
  href?: string
}

/** What is inside, grouped the way the software is grouped. */
export const MODULES: Module[] = [
  {
    key: 'sales',
    icon: Receipt,
    name: 'Pharmacy POS & Trade Counter',
    summary: 'Fast retail till and trade invoicing with automatic eTIMS QR receipts and offline resilience.',
    category: 'pharmacy',
    badge: 'KRA eTIMS OSCU · Offline Till',
    role: 'Cashiers & Counter Pharmacists',
    href: '/request-quote',
    points: [
      'Works 100% offline: sell normally during internet blackouts; transactions auto-upload when reconnected',
      'Instant KRA eTIMS QR receipt generation directly from your thermal printer or invoice',
      'Dual mode: separate retail walk-ins (strips/packs) and trade buyers with custom wholesale pricing',
      'Direct M-Pesa STK push and transaction code matching with automatic evening till balancing',
      'Barcode scanner shortcuts, prescription dispensing records, and generic alternative suggestions',
    ],
  },
  {
    key: 'inventory',
    icon: Boxes,
    name: 'Stock & FEFO Batch Expiry',
    summary: 'Know exactly what you hold, where it sits, and eliminate expired drug waste before it happens.',
    category: 'pharmacy',
    badge: 'FEFO Expiry Protection',
    role: 'Storekeepers & Inventory Managers',
    href: '/request-quote',
    points: [
      'Inventory categorized under Prescription Drugs, OTC, Cold Chain, Vaccines, and Surgical Supplies',
      'Batch and expiry tracking with automated FEFO picking and audited override logs',
      'Dynamic reorder points, low-stock notifications, and color-coded expiry risk tiers',
      'Blind physical stock counts, stock adjustments, and multi-branch transfers with approval workflows',
    ],
  },
  {
    key: 'procurement',
    icon: PackageSearch,
    name: 'Purchasing & Supplier Sourcing',
    summary: 'Compare distributor quotes, get the best medicine prices, and verify deliveries before paying.',
    category: 'pharmacy',
    badge: 'Multi-Supplier Quotes · Bill Check',
    role: 'Storekeepers & Owners',
    href: '/request-quote',
    points: [
      'Send requests for quotation (RFQs) to multiple distributors (e.g. Harley’s, Laborex) simultaneously',
      'Side-by-side price comparison (CBA) to pick the lowest supplier price and maximize your margins',
      'Record goods received with batch numbers, expiry dates, and free bonus packs',
      'Verify delivery notes against supplier bills before paying so you never overpay',
    ],
  },
  {
    key: 'warehouse',
    icon: Truck,
    name: 'Stock Transfers & Dispatch',
    summary: 'Organized packing lists, temperature records for sensitive drugs, and verifiable deliveries.',
    category: 'pharmacy',
    badge: 'Packing & Delivery Notes',
    role: 'Storekeepers & Dispatch Drivers',
    href: '/request-quote',
    points: [
      'Clear packing lists that tell staff exactly which shelf and batch to pick from',
      'Track deliveries by motorbike, van, or customer pickup with signed proof',
      'Print official delivery notes and gate passes with your pharmacy logo',
      'Inter-branch transfers with digital dispatch and receipt verification',
    ],
  },
  {
    key: 'finance',
    icon: Landmark,
    name: 'Daily Books & KRA eTIMS',
    summary: 'Never spend hours reconciling numbers — your cash, M-Pesa, and supplier debts balance daily.',
    category: 'finance',
    badge: 'KRA eTIMS & Daily Cash Flow',
    role: 'Accountants & Pharmacy Owners',
    href: '/request-quote',
    points: [
      'Real-time profit & loss, customer credit balances, and unpaid supplier bills',
      'Automated daily bookkeeping from every counter sale and inventory purchase',
      'Automatic M-Pesa till reconciliation, 16% VAT calculations, and eTIMS data',
      'Track petty cash, staff payroll, and daily cash in hand from your phone',
    ],
  },
  {
    key: 'quality',
    icon: ClipboardCheck,
    name: 'PPB Inspection & Compliance',
    summary: 'Pre-loaded records, licence renewal alerts, and safety procedures ready for any PPB inspector.',
    category: 'finance',
    badge: 'PPB Licences & 12 SOPs',
    role: 'Superintendent Pharmacists & Owners',
    href: '/request-quote',
    points: [
      '12 pre-loaded Kenyan pharmacy SOPs ready to print or customize for your premises',
      'Licence renewal countdowns for your pharmacy premises and practising staff',
      'Safe medicine disposal registers, product recall logs, and adverse reaction records',
      'Tamper-proof audit logs showing who gave discounts, voids, or changed prices',
    ],
  },
  {
    key: 'reports',
    icon: BarChart3,
    name: 'Business Reports & Analytics',
    summary: 'See your top-selling medicines, gross profit margins, and dead stock in one click.',
    category: 'operations',
    badge: 'Daily & Monthly Reports',
    role: 'Owners & Branch Managers',
    href: '/request-quote',
    points: [
      'See your daily sales, profit margins, and cashier performance across every till',
      'Get automated summary reports delivered straight to your email every evening',
      'Identify slow-moving and fast-selling medicines before you over-order',
      'Track customer debts and know exactly who owes your pharmacy and for how long',
    ],
  },
  {
    key: 'people',
    icon: GraduationCap,
    name: 'Staff Training LMS & Permissions',
    summary: 'New employees learn your facility workflows right inside the system with hands-on practice modules.',
    category: 'operations',
    badge: 'In-App Staff LMS & Roles',
    role: 'HR Managers & Facility Administrators',
    href: '/request-quote',
    points: [
      '7 built-in operational courses with interactive lessons, simulated tasks, and knowledge evaluations',
      'Practical tasks validated against real system actions (e.g., ringing up a sale, receiving goods, stock counts)',
      'Digital certificates awarded upon completion with a facility-wide training progress scorecard',
      'Granular role-based permissions per facility branch down to individual actions and refund limits',
    ],
  },
]

export type Step = { number: string; title: string; body: string }

export const HOW_IT_WORKS: Step[] = [
  { number: '01', title: 'We set up your pharmacy', body: 'Branches, stores, users and roles, your product list loaded with opening stock and batch expiries, and your prices as they stand today.' },
  { number: '02', title: 'Your team is trained inside the system', body: 'Each person works through the courses for their role and practises on real screens before the doors open.' },
  { number: '03', title: 'You go live', body: 'Sell, receive, transfer and bank as normal. The books post themselves and the reports arrive without being asked for.' },
  { number: '04', title: 'You grow', body: 'Add a branch, a store or a till and everything — stock, prices, permissions, reports — separates and consolidates on its own.' },
]

export type Faq = { question: string; answer: string }

export const FAQS: Faq[] = [
  {
    question: 'Do I need every module?',
    answer:
      'No. A single-counter chemist can start with the point of sale and stock, and switch on procurement, warehouse, finance and reports as the business grows. Everyone signs in at the same door and sees only what their permissions open.',
  },
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
      'Your pharmacy is isolated from every other business on the platform at the database level. Backups run before every update, and you can export your own data at any time.',
  },
  {
    question: 'How long does it take to start?',
    answer:
      'A single pharmacy with a clean product list can be live in days. The longest part is always the opening stock count — we import it from a spreadsheet, so the counting is the work, not the typing.',
  },
]

export const TRUST_POINTS: Highlight[] = [
  { icon: ShieldCheck, title: 'Isolated per pharmacy', body: 'Your data is scoped to your business on every single query.' },
  { icon: Activity, title: 'Audited end to end', body: 'Postings, voids, discounts and approvals all name the person behind them.' },
  { icon: FileText, title: 'Your documents, your brand', body: 'Invoices, orders and delivery notes carry your letterhead, stamp and signature.' },
  { icon: Users, title: 'Trained, not just installed', body: 'The training pack is part of the product, not an afterthought.' },
]

/**
 * Social accounts. Each one appears in the header strip and the footer as
 * soon as it has an address — leave it empty and it is simply not shown, so
 * the site never links to a page that does not exist yet.
 */
export const SOCIALS: { label: string; href: string; icon: BrandIcon }[] = [
  { label: 'WhatsApp', href: 'https://wa.me/254759900802', icon: WhatsAppIcon },
  { label: 'Facebook', href: '', icon: FacebookIcon },
  { label: 'Instagram', href: '', icon: InstagramIcon },
  { label: 'TikTok', href: '', icon: TikTokIcon },
  { label: 'X', href: '', icon: XIcon },
  { label: 'YouTube', href: '', icon: YouTubeIcon },
]

/** The slides the front page opens on. */
export type Slide = {
  badge: string
  title: string
  highlight: string
  body: string
  priceLabel: string
  price: string
  image: string
  cta: string
}

export const SLIDES: Slide[] = [
  {
    badge: 'Retail · Wholesale · Dispensing',
    title: 'One system from the',
    highlight: 'shelf to the till',
    body: 'Receive stock by batch, sell it at the counter or to the trade, and keep the books balanced — one product list, one price list, no paper in between.',
    priceLabel: 'Plans from',
    price: 'KES 2,500',
    image: 'dispensary.jpg',
    cta: 'Request a demo',
  },
  {
    badge: 'Sells offline · batch · FEFO',
    title: 'The counter never',
    highlight: 'stops trading',
    body: 'Retail, wholesale and prescription dispensing on one till, batch and expiry behind every pack — and every receipt kept when the line drops.',
    priceLabel: 'Ready for',
    price: 'eTIMS & KRA',
    image: 'pills.jpg',
    cta: 'See the modules',
  },
  {
    badge: 'PPB · county · VAT',
    title: 'Inspection-ready,',
    highlight: 'every single day',
    body: 'Licence renewals counted down, twelve SOPs ready to issue, recalls and disposals recorded, and an audit log naming who did what.',
    priceLabel: 'Setup & training',
    price: 'Included',
    image: 'dispensary.jpg',
    cta: 'Talk to us',
  },
]

/**
 * Where the system is available: everywhere. The list is coverage, not a
 * customer roll — it says where we will set a pharmacy up, which is any
 * town in the country.
 */
export const TOWNS = [
  'Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Eldoret', 'Thika', 'Machakos', 'Meru', 'Nyeri', 'Kakamega',
  'Kisii', 'Kericho', 'Malindi', 'Garissa', 'Kitale', 'Embu', 'Naivasha', 'Bungoma', 'Lodwar', 'Isiolo',
  'Voi', 'Narok', 'Kitui', 'Migori', 'Wajir', 'Nanyuki', 'Homa Bay', 'Busia', 'Kilifi', 'Marsabit',
]

/** How to reach us: Software Enquiries (0725) and Customer Support (0759) */
export const PHONE_SALES = '0725 996 394'
export const PHONE_SALES_HREF = 'tel:+254725996394'
export const WHATSAPP_SALES_HREF = 'https://wa.me/254725996394'

export const PHONE_SUPPORT = '0759 900 802'
export const PHONE_SUPPORT_HREF = 'tel:+254759900802'
export const WHATSAPP_SUPPORT_HREF = 'https://wa.me/254759900802'

export const PHONE = PHONE_SALES
export const PHONE_HREF = PHONE_SALES_HREF
export const WHATSAPP_HREF = WHATSAPP_SALES_HREF
export const EMAIL = 'info@solforbs.com'
export const EMAIL_HREF = 'mailto:info@solforbs.com'

/** The public pages, in the order they appear in the header. */
export const SITE_NAV = [
  { to: '/home', label: 'Home' },
  { to: '/pricing', label: 'Pricing' },
  { to: '/about', label: 'About' },
  { to: '/contact', label: 'Contact' },
]
