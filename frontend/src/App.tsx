import { Suspense, lazy } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import AppLayout from './components/AppLayout'
import ProtectedRoute from './components/ProtectedRoute'
import { PageLoadingSkeleton } from './components/ui/PageLoadingSkeleton'
import { Toaster } from './components/ui/Toaster'

// Lazy-loaded route components for high-performance code splitting
const Login = lazy(() => import('./pages/Login'))
const RequestQuotePage = lazy(() => import('./features/onboarding/RequestQuotePage'))
const InvitationPage = lazy(() => import('./features/onboarding/InvitationPage'))
const PlatformPage = lazy(() => import('./features/platform/PlatformPage'))
const BillingPage = lazy(() => import('./features/billing/BillingPage'))
const BillingCallbackPage = lazy(() => import('./features/billing/BillingCallbackPage'))
const Dashboard = lazy(() => import('./pages/Dashboard'))
const Placeholder = lazy(() => import('./pages/Placeholder'))

// Commerce & POS
const PosPage = lazy(() => import('./features/pos/PosPage'))
const QuotationsPage = lazy(() => import('./features/sales/QuotationsPage'))
const SalesOrdersPage = lazy(() => import('./features/sales/SalesOrdersPage'))
const InvoicesPage = lazy(() => import('./features/sales/InvoicesPage'))
const ReturnsPage = lazy(() => import('./features/sales/ReturnsPage'))
const CustomerStatementsPage = lazy(() => import('./features/sales/CustomerStatementsPage'))

// Inventory
const ProductsPage = lazy(() => import('./features/inventory/ProductsPage'))
const StockOnHandPage = lazy(() => import('./features/inventory/StockOnHandPage'))
const StockLedgerPage = lazy(() => import('./features/inventory/StockLedgerPage'))
const BatchesPage = lazy(() => import('./features/inventory/BatchesPage'))
const TransfersPage = lazy(() => import('./features/inventory/TransfersPage'))
const CountsPage = lazy(() => import('./features/inventory/CountsPage'))
const AdjustmentsPage = lazy(() => import('./features/inventory/AdjustmentsPage'))
const ValuationPage = lazy(() => import('./features/inventory/ValuationPage'))
const OpeningStockPage = lazy(() => import('./features/inventory/OpeningStockPage'))

// Procurement
const RequisitionsPage = lazy(() => import('./features/procurement/RequisitionsPage'))
const PurchaseOrdersPage = lazy(() => import('./features/procurement/PurchaseOrdersPage'))
const GoodsReceiptsPage = lazy(() => import('./features/procurement/GoodsReceiptsPage'))
const SupplierInvoicesPage = lazy(() => import('./features/procurement/SupplierInvoicesPage'))
const ThreeWayMatchPage = lazy(() => import('./features/procurement/ThreeWayMatchPage'))
const SuppliersPage = lazy(() => import('./features/procurement/SuppliersPage'))

// Warehouse
const PickListsPage = lazy(() => import('./features/warehouse/PickListsPage'))
const DispatchPage = lazy(() => import('./features/warehouse/DispatchPage'))
const DeliveriesPage = lazy(() => import('./features/warehouse/DeliveriesPage'))
const LocationsPage = lazy(() => import('./features/warehouse/LocationsPage'))
const PackingPage = lazy(() => import('./features/warehouse/PackingPage'))

// Customers
const CustomersPage = lazy(() => import('./features/customers/CustomersPage'))
const TiersPage = lazy(() => import('./features/customers/TiersPage'))
const CreditControlPage = lazy(() => import('./features/customers/CreditControlPage'))
const ContactsPage = lazy(() => import('./features/customers/ContactsPage'))

// Finance
const ReceivablesPage = lazy(() => import('./features/finance/ReceivablesPage'))
const PayablesPage = lazy(() => import('./features/finance/PayablesPage'))
const JournalsPage = lazy(() => import('./features/finance/JournalsPage'))
const ChartOfAccountsPage = lazy(() => import('./features/finance/ChartOfAccountsPage'))
const StatementsPage = lazy(() => import('./features/finance/StatementsPage'))
const TaxCentrePage = lazy(() => import('./features/finance/TaxCentrePage'))
const PeriodsPage = lazy(() => import('./features/finance/PeriodsPage'))
const ReconciliationPage = lazy(() => import('./features/finance/ReconciliationPage'))

// Quality
const QuarantinePage = lazy(() => import('./features/quality/QuarantinePage'))
const WastePage = lazy(() => import('./features/quality/WastePage'))
const RecallsPage = lazy(() => import('./features/quality/RecallsPage'))
const ColdChainPage = lazy(() => import('./features/quality/ColdChainPage'))
const PharmacovigilancePage = lazy(() => import('./features/quality/PharmacovigilancePage'))
const LicencesPage = lazy(() => import('./features/quality/LicencesPage'))
const SopsPage = lazy(() => import('./features/quality/SopsPage'))

// People & Reports
const EmployeesPage = lazy(() => import('./features/people/EmployeesPage'))
const PayrollPage = lazy(() => import('./features/people/PayrollPage'))
const LeavePage = lazy(() => import('./features/people/LeavePage'))
const TrainingPage = lazy(() => import('./features/people/training/TrainingPage'))
const TrainingModulePage = lazy(() => import('./features/people/training/TrainingModulePage'))
const ReportsPage = lazy(() => import('./features/reports/ReportsPage'))
const AnalyticsPage = lazy(() => import('./features/reports/AnalyticsPage'))
const ScheduledReportsPage = lazy(() => import('./features/reports/ScheduledReportsPage'))
const ReportInboxPage = lazy(() => import('./features/reports/ReportInboxPage'))

// Admin
const UsersRolesPage = lazy(() => import('./features/admin/UsersRolesPage'))
const PermissionsPage = lazy(() => import('./features/admin/PermissionsPage'))
const BranchesPage = lazy(() => import('./features/admin/BranchesPage'))
const SettingsPage = lazy(() => import('./features/admin/SettingsPage'))
const CompanyProfilePage = lazy(() => import('./features/admin/CompanyProfilePage'))
const SecurityPage = lazy(() => import('./features/admin/SecurityPage'))
const NumberSequencesPage = lazy(() => import('./features/admin/NumberSequencesPage'))
const AuditLogPage = lazy(() => import('./features/admin/AuditLogPage'))
const SystemHealthPage = lazy(() => import('./features/admin/SystemHealthPage'))
const BackupPage = lazy(() => import('./features/admin/BackupPage'))
const SyncCentrePage = lazy(() => import('./features/admin/SyncCentrePage'))
const AlertsPage = lazy(() => import('./features/admin/AlertsPage'))
const DeploymentsPage = lazy(() => import('./features/admin/DeploymentsPage'))
const PayrollBandsPage = lazy(() => import('./features/admin/PayrollBandsPage'))
const PricingRulesPage = lazy(() => import('./features/admin/pricing/PricingRulesPage'))
import { NAV_ITEMS } from './lib/navigation'

function App() {
  return (
    <>
      <Suspense fallback={<PageLoadingSkeleton />}>
        <Routes>
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route path="/login" element={<Login />} />
          {/* Reached without signing in: asking for a quote and the one-time emailed links. */}
          <Route path="/request-quote" element={<RequestQuotePage />} />
          <Route path="/register" element={<InvitationPage />} />
          <Route path="/activate" element={<InvitationPage />} />

          {/* The platform console has its own light frame: no institution chrome. */}
          <Route path="/platform" element={<ProtectedRoute><PlatformPage /></ProtectedRoute>} />

          <Route
            element={
              <ProtectedRoute>
                <AppLayout />
              </ProtectedRoute>
            }
          >
            <Route path="/dashboard" element={<Dashboard />} />

            <Route path="/sell/pos" element={<PosPage />} />
            <Route path="/sell/quotations" element={<QuotationsPage />} />
            <Route path="/sell/sales-orders" element={<SalesOrdersPage />} />
            <Route path="/sell/invoices" element={<InvoicesPage />} />
            <Route path="/sell/returns" element={<ReturnsPage />} />

            <Route path="/inventory/products" element={<ProductsPage />} />
            <Route path="/inventory/stock-on-hand" element={<StockOnHandPage />} />
            <Route path="/inventory/stock-ledger" element={<StockLedgerPage />} />
            <Route path="/inventory/batches" element={<BatchesPage />} />
            <Route path="/inventory/transfers" element={<TransfersPage />} />
            <Route path="/inventory/counts" element={<CountsPage />} />
            <Route path="/inventory/adjustments" element={<AdjustmentsPage />} />
            <Route path="/inventory/valuation" element={<ValuationPage />} />
            <Route path="/inventory/opening-stock" element={<OpeningStockPage />} />

            <Route path="/buy/requisitions" element={<RequisitionsPage />} />
            <Route path="/buy/purchase-orders" element={<PurchaseOrdersPage />} />
            <Route path="/buy/goods-receipts" element={<GoodsReceiptsPage />} />
            <Route path="/buy/supplier-invoices" element={<SupplierInvoicesPage />} />
            <Route path="/buy/three-way-match" element={<ThreeWayMatchPage />} />
            <Route path="/buy/suppliers" element={<SuppliersPage />} />

            <Route path="/warehouse/pick-lists" element={<PickListsPage />} />
            <Route path="/warehouse/dispatch" element={<DispatchPage />} />
            <Route path="/warehouse/deliveries" element={<DeliveriesPage />} />

            <Route path="/customers/list" element={<CustomersPage />} />
            <Route path="/customers/tiers" element={<TiersPage />} />
            <Route path="/customers/credit-control" element={<CreditControlPage />} />

            <Route path="/finance/receivables" element={<ReceivablesPage />} />
            <Route path="/finance/payables" element={<PayablesPage />} />
            <Route path="/finance/journals" element={<JournalsPage />} />
            <Route path="/finance/chart-of-accounts" element={<ChartOfAccountsPage />} />
            <Route path="/finance/statements" element={<StatementsPage />} />
            <Route path="/finance/tax-centre" element={<TaxCentrePage />} />
            <Route path="/finance/periods" element={<PeriodsPage />} />

            <Route path="/quality/quarantine" element={<QuarantinePage />} />
            <Route path="/quality/waste" element={<WastePage />} />
            <Route path="/quality/recalls" element={<RecallsPage />} />

            <Route path="/reports/catalogue" element={<ReportsPage />} />

            <Route path="/people/employees" element={<EmployeesPage />} />
            <Route path="/people/payroll" element={<PayrollPage />} />

            <Route path="/admin/users-roles" element={<UsersRolesPage />} />
            <Route path="/admin/permissions" element={<PermissionsPage />} />
            <Route path="/admin/branches" element={<BranchesPage />} />
            <Route path="/admin/settings" element={<SettingsPage />} />
            <Route path="/admin/company-profile" element={<CompanyProfilePage />} />
            <Route path="/admin/security" element={<SecurityPage />} />
            <Route path="/admin/number-sequences" element={<NumberSequencesPage />} />
            <Route path="/admin/audit-log" element={<AuditLogPage />} />

            <Route path="/warehouse/locations" element={<LocationsPage />} />
            <Route path="/warehouse/packing" element={<PackingPage />} />
            <Route path="/customers/contacts" element={<ContactsPage />} />
            <Route path="/finance/reconciliation" element={<ReconciliationPage />} />
            <Route path="/sell/statements" element={<CustomerStatementsPage />} />
            <Route path="/admin/system-health" element={<SystemHealthPage />} />
            <Route path="/admin/backup" element={<BackupPage />} />
            <Route path="/admin/sync-centre" element={<SyncCentrePage />} />
            <Route path="/admin/alerts" element={<AlertsPage />} />
            <Route path="/admin/deployments" element={<DeploymentsPage />} />
            <Route path="/admin/payroll-bands" element={<PayrollBandsPage />} />
            <Route path="/quality/cold-chain" element={<ColdChainPage />} />
            <Route path="/quality/pharmacovigilance" element={<PharmacovigilancePage />} />
            <Route path="/quality/licences" element={<LicencesPage />} />
            <Route path="/quality/sops" element={<SopsPage />} />
            <Route path="/people/leave" element={<LeavePage />} />
            {/* Client item 17 — the training link sent to new staff is /training. */}
            <Route path="/training" element={<TrainingPage />} />
            <Route path="/training/:moduleKey" element={<TrainingModulePage />} />
            <Route path="/people/training" element={<Navigate to="/training" replace />} />
            <Route path="/reports/analytics" element={<AnalyticsPage />} />
            <Route path="/reports/scheduled" element={<ScheduledReportsPage />} />
            <Route path="/reports/inbox" element={<ReportInboxPage />} />
            <Route path="/admin/pricing-rules" element={<PricingRulesPage />} />
            <Route path="/admin/billing" element={<BillingPage />} />
            <Route path="/billing/callback" element={<BillingCallbackPage />} />

            {/* A module header (/sell, /inventory …) opens its first section. */}
            {NAV_ITEMS.filter((m) => m.children?.length).map((m) => (
              <Route key={m.key} path={m.path} element={<Navigate to={m.children![0].path} replace />} />
            ))}

            <Route path="/:moduleKey" element={<Placeholder />} />
            <Route path="/:moduleKey/:sectionKey" element={<Placeholder />} />
          </Route>
        </Routes>
      </Suspense>
      <Toaster />
    </>
  )
}

export default App
