import { Navigate, Route, Routes } from 'react-router-dom'
import AppLayout from './components/AppLayout'
import ProtectedRoute from './components/ProtectedRoute'
import { Toaster } from './components/ui/Toaster'
import SecurityPage from './features/admin/SecurityPage'
import CreditControlPage from './features/customers/CreditControlPage'
import CustomersPage from './features/customers/CustomersPage'
import JournalsPage from './features/finance/JournalsPage'
import PayablesPage from './features/finance/PayablesPage'
import PeriodsPage from './features/finance/PeriodsPage'
import ReceivablesPage from './features/finance/ReceivablesPage'
import StatementsPage from './features/finance/StatementsPage'
import TaxCentrePage from './features/finance/TaxCentrePage'
import AdjustmentsPage from './features/inventory/AdjustmentsPage'
import BatchesPage from './features/inventory/BatchesPage'
import CountsPage from './features/inventory/CountsPage'
import ProductsPage from './features/inventory/ProductsPage'
import StockLedgerPage from './features/inventory/StockLedgerPage'
import StockOnHandPage from './features/inventory/StockOnHandPage'
import TransfersPage from './features/inventory/TransfersPage'
import EmployeesPage from './features/people/EmployeesPage'
import PayrollPage from './features/people/PayrollPage'
import PosPage from './features/pos/PosPage'
import GoodsReceiptsPage from './features/procurement/GoodsReceiptsPage'
import PurchaseOrdersPage from './features/procurement/PurchaseOrdersPage'
import RequisitionsPage from './features/procurement/RequisitionsPage'
import SupplierInvoicesPage from './features/procurement/SupplierInvoicesPage'
import SuppliersPage from './features/procurement/SuppliersPage'
import RecallsPage from './features/quality/RecallsPage'
import WastePage from './features/quality/WastePage'
import ReportsPage from './features/reports/ReportsPage'
import InvoicesPage from './features/sales/InvoicesPage'
import QuotationsPage from './features/sales/QuotationsPage'
import ReturnsPage from './features/sales/ReturnsPage'
import SalesOrdersPage from './features/sales/SalesOrdersPage'
import DeliveriesPage from './features/warehouse/DeliveriesPage'
import DispatchPage from './features/warehouse/DispatchPage'
import PickListsPage from './features/warehouse/PickListsPage'
import Dashboard from './pages/Dashboard'
import Login from './pages/Login'
import Placeholder from './pages/Placeholder'

function App() {
  return (
    <>
      <Routes>
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
        <Route path="/login" element={<Login />} />

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

          <Route path="/buy/requisitions" element={<RequisitionsPage />} />
          <Route path="/buy/purchase-orders" element={<PurchaseOrdersPage />} />
          <Route path="/buy/goods-receipts" element={<GoodsReceiptsPage />} />
          <Route path="/buy/supplier-invoices" element={<SupplierInvoicesPage />} />
          <Route path="/buy/three-way-match" element={<Navigate to="/buy/supplier-invoices" replace />} />
          <Route path="/buy/suppliers" element={<SuppliersPage />} />

          <Route path="/warehouse/pick-lists" element={<PickListsPage />} />
          <Route path="/warehouse/dispatch" element={<DispatchPage />} />
          <Route path="/warehouse/deliveries" element={<DeliveriesPage />} />

          <Route path="/customers/list" element={<CustomersPage />} />
          <Route path="/customers/credit-control" element={<CreditControlPage />} />

          <Route path="/finance/receivables" element={<ReceivablesPage />} />
          <Route path="/finance/payables" element={<PayablesPage />} />
          <Route path="/finance/journals" element={<JournalsPage />} />
          <Route path="/finance/statements" element={<StatementsPage />} />
          <Route path="/finance/tax-centre" element={<TaxCentrePage />} />
          <Route path="/finance/periods" element={<PeriodsPage />} />

          <Route path="/quality/waste" element={<WastePage />} />
          <Route path="/quality/recalls" element={<RecallsPage />} />

          <Route path="/reports/catalogue" element={<ReportsPage />} />

          <Route path="/people/employees" element={<EmployeesPage />} />
          <Route path="/people/payroll" element={<PayrollPage />} />

          <Route path="/admin/security" element={<SecurityPage />} />

          <Route path="/:moduleKey" element={<Placeholder />} />
          <Route path="/:moduleKey/:sectionKey" element={<Placeholder />} />
        </Route>
      </Routes>
      <Toaster />
    </>
  )
}

export default App
