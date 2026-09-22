import {
  BarChart3,
  Boxes,
  LayoutDashboard,
  Settings,
  ShieldCheck,
  ShoppingCart,
  Truck,
  Users,
  UserSquare2,
  Wallet,
  Warehouse,
  type LucideIcon,
} from 'lucide-react'

export type NavItem = {
  key: string
  label: string
  icon: LucideIcon
  path: string
  /** platformOnly: pages that act on the whole platform, shown to platform administrators only. */
  children?: { key: string; label: string; path: string; platformOnly?: boolean }[]
}

/** Hides the platform-only pages (backups, deployment, system health) from institution users. */
export function withPlatformItems(items: NavItem[], isPlatformAdmin: boolean): NavItem[] {
  if (isPlatformAdmin) return items
  return items.map((item) => (item.children ? { ...item, children: item.children.filter((child) => !child.platformOnly) } : item))
}

// Mirrors the blueprint's Part 2.2 information architecture — 11 top-level
// workspaces, sub-navigation for the ones visited weekly rather than daily.
export const NAV_ITEMS: NavItem[] = [
  { key: 'dashboard', label: 'Dashboard', icon: LayoutDashboard, path: '/dashboard' },
  {
    key: 'sell', label: 'Sales', icon: ShoppingCart, path: '/sell',
    children: [
      { key: 'pos', label: 'POS', path: '/sell/pos' },
      { key: 'quotations', label: 'Quotations', path: '/sell/quotations' },
      { key: 'sales-orders', label: 'Sales Orders', path: '/sell/sales-orders' },
      { key: 'invoices', label: 'Invoices', path: '/sell/invoices' },
      { key: 'returns', label: 'Returns', path: '/sell/returns' },
      { key: 'statements', label: 'Customer Statements', path: '/sell/statements' },
    ],
  },
  {
    key: 'inventory', label: 'Inventory', icon: Boxes, path: '/inventory',
    children: [
      { key: 'products', label: 'Products', path: '/inventory/products' },
      { key: 'stock-on-hand', label: 'Stock on Hand', path: '/inventory/stock-on-hand' },
      { key: 'stock-ledger', label: 'Stock Ledger', path: '/inventory/stock-ledger' },
      { key: 'batches', label: 'Batches & Expiry', path: '/inventory/batches' },
      { key: 'transfers', label: 'Transfers', path: '/inventory/transfers' },
      { key: 'counts', label: 'Counts', path: '/inventory/counts' },
      { key: 'adjustments', label: 'Adjustments', path: '/inventory/adjustments' },
      { key: 'valuation', label: 'Valuation', path: '/inventory/valuation' },
      { key: 'opening-stock', label: 'Opening Stock', path: '/inventory/opening-stock' },
    ],
  },
  {
    key: 'buy', label: 'Procurement', icon: Truck, path: '/buy',
    children: [
      { key: 'requisitions', label: 'Requisitions', path: '/buy/requisitions' },
      { key: 'supplier-quotes', label: 'Supplier Quotes & CBA', path: '/buy/supplier-quotes' },
      { key: 'purchase-orders', label: 'Purchase Orders', path: '/buy/purchase-orders' },
      { key: 'goods-receipts', label: 'Goods Receipts', path: '/buy/goods-receipts' },
      { key: 'supplier-invoices', label: 'Supplier Invoices', path: '/buy/supplier-invoices' },
      { key: 'three-way-match', label: 'Three-Way Match', path: '/buy/three-way-match' },
      { key: 'suppliers', label: 'Suppliers', path: '/buy/suppliers' },
    ],
  },
  {
    key: 'warehouse', label: 'Warehouse', icon: Warehouse, path: '/warehouse',
    children: [
      { key: 'pick-lists', label: 'Pick Lists', path: '/warehouse/pick-lists' },
      { key: 'packing', label: 'Packing', path: '/warehouse/packing' },
      { key: 'dispatch', label: 'Dispatch', path: '/warehouse/dispatch' },
      { key: 'deliveries', label: 'Deliveries', path: '/warehouse/deliveries' },
      { key: 'locations', label: 'Locations', path: '/warehouse/locations' },
    ],
  },
  {
    key: 'customers', label: 'Customers', icon: UserSquare2, path: '/customers',
    children: [
      { key: 'customers', label: 'Customers', path: '/customers/list' },
      { key: 'tiers', label: 'Tiers & Price Lists', path: '/customers/tiers' },
      { key: 'credit-control', label: 'Credit Control', path: '/customers/credit-control' },
      { key: 'contacts', label: 'Contacts', path: '/customers/contacts' },
    ],
  },
  {
    key: 'finance', label: 'Finance', icon: Wallet, path: '/finance',
    children: [
      { key: 'receivables', label: 'Receivables', path: '/finance/receivables' },
      { key: 'payables', label: 'Payables', path: '/finance/payables' },
      { key: 'reconciliation', label: 'Payments & Reconciliation', path: '/finance/reconciliation' },
      { key: 'journals', label: 'Journals', path: '/finance/journals' },
      { key: 'chart-of-accounts', label: 'Chart of Accounts', path: '/finance/chart-of-accounts' },
      { key: 'tax-centre', label: 'Tax Centre', path: '/finance/tax-centre' },
      { key: 'periods', label: 'Periods', path: '/finance/periods' },
      { key: 'financial-statements', label: 'Financial Statements', path: '/finance/statements' },
    ],
  },
  {
    key: 'quality', label: 'Quality & Compliance', icon: ShieldCheck, path: '/quality',
    children: [
      { key: 'cold-chain', label: 'Cold Chain', path: '/quality/cold-chain' },
      { key: 'quarantine', label: 'Quarantine', path: '/quality/quarantine' },
      { key: 'recalls', label: 'Recalls', path: '/quality/recalls' },
      { key: 'waste', label: 'Waste & Disposal', path: '/quality/waste' },
      { key: 'pharmacovigilance', label: 'Pharmacovigilance', path: '/quality/pharmacovigilance' },
      { key: 'licences', label: 'Licences & Certificates', path: '/quality/licences' },
      { key: 'sops', label: 'SOPs/Documents', path: '/quality/sops' },
    ],
  },
  {
    key: 'reports', label: 'Reports', icon: BarChart3, path: '/reports',
    children: [
      { key: 'catalogue', label: 'Report Catalogue', path: '/reports/catalogue' },
      { key: 'analytics', label: 'Analytics', path: '/reports/analytics' },
      { key: 'scheduled', label: 'Scheduled Reports', path: '/reports/scheduled' },
    ],
  },
  {
    key: 'people', label: 'People', icon: Users, path: '/people',
    children: [
      { key: 'employees', label: 'Employees', path: '/people/employees' },
      { key: 'payroll', label: 'Payroll', path: '/people/payroll' },
      { key: 'leave', label: 'Leave', path: '/people/leave' },
    ],
  },
  {
    key: 'admin', label: 'Admin', icon: Settings, path: '/admin',
    children: [
      { key: 'users-roles', label: 'Users & Roles', path: '/admin/users-roles' },
      { key: 'permissions', label: 'Permissions', path: '/admin/permissions' },
      { key: 'branches', label: 'Branches & Stores', path: '/admin/branches' },
      { key: 'settings', label: 'Settings', path: '/admin/settings' },
      { key: 'billing', label: 'Plan & Billing', path: '/admin/billing' },
      { key: 'security', label: 'Security (MFA)', path: '/admin/security' },
      { key: 'pricing-rules', label: 'Pricing Rules', path: '/admin/pricing-rules' },
      { key: 'payroll-bands', label: 'Payroll Bands', path: '/admin/payroll-bands' },
      { key: 'number-sequences', label: 'Number Sequences', path: '/admin/number-sequences' },
      { key: 'audit-log', label: 'Audit Log', path: '/admin/audit-log' },
      { key: 'alerts', label: 'Alerts', path: '/admin/alerts' },
      { key: 'deployments', label: 'CI/CD & Deployments', path: '/admin/deployments', platformOnly: true },
      { key: 'sync-centre', label: 'Sync Centre', path: '/admin/sync-centre' },
      { key: 'system-health', label: 'System Health', path: '/admin/system-health', platformOnly: true },
      { key: 'backup', label: 'Backup', path: '/admin/backup', platformOnly: true },
      { key: 'platform', label: 'Platform console', path: '/platform', platformOnly: true },
    ],
  },
]
