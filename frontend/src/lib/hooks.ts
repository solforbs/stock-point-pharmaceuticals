import { useQuery } from '@tanstack/react-query'
import { apiGet } from './api'
import type { AdminBranch, AdminRole, CustomerTier, DashboardSummary, DosageForm, Paginated, PermissionGroup, PriceList, Product, ProductCategory, ProductStock, PurchaseOrder, StorageCondition, Store, Supplier, TaxCode, Uom, UserRef } from './types'

export function useUsers(q = '') {
  return useQuery({ queryKey: ['users', q], queryFn: () => apiGet<UserRef[]>('/api/users', { q }), staleTime: 60_000 })
}

export function useProductCategories() {
  return useQuery({ queryKey: ['product-categories'], queryFn: () => apiGet<ProductCategory[]>('/api/product-categories'), staleTime: 10 * 60_000 })
}

export function usePurchaseOrder(id: string | null | undefined) {
  return useQuery({ queryKey: ['purchase-orders', id], queryFn: () => apiGet<PurchaseOrder>(`/api/purchase-orders/${id}`), enabled: !!id })
}

export function useStores() {
  return useQuery({ queryKey: ['stores'], queryFn: () => apiGet<Store[]>('/api/stores'), staleTime: 5 * 60_000 })
}

export function useDosageForms() {
  return useQuery({ queryKey: ['dosage-forms'], queryFn: () => apiGet<DosageForm[]>('/api/dosage-forms'), staleTime: 10 * 60_000 })
}

export function useStorageConditions() {
  return useQuery({ queryKey: ['storage-conditions'], queryFn: () => apiGet<StorageCondition[]>('/api/storage-conditions'), staleTime: 10 * 60_000 })
}

export function useUoms() {
  return useQuery({ queryKey: ['uoms'], queryFn: () => apiGet<Uom[]>('/api/uoms'), staleTime: 10 * 60_000 })
}

export function useTaxCodes() {
  return useQuery({ queryKey: ['tax-codes'], queryFn: () => apiGet<TaxCode[]>('/api/tax-codes'), staleTime: 10 * 60_000 })
}

export function useCustomerTiers() {
  return useQuery({ queryKey: ['customer-tiers'], queryFn: () => apiGet<CustomerTier[]>('/api/customer-tiers'), staleTime: 10 * 60_000 })
}

export function useSuppliers(q = '') {
  return useQuery({
    queryKey: ['suppliers', q],
    queryFn: () => apiGet<Paginated<Supplier>>('/api/suppliers', { q, per_page: 100 }),
    staleTime: 60_000,
  })
}

export function useProduct(id: string | null | undefined) {
  return useQuery({ queryKey: ['products', id], queryFn: () => apiGet<Product>(`/api/products/${id}`), enabled: !!id })
}

export function useProductStock(id: string | null | undefined) {
  return useQuery({
    queryKey: ['products', id, 'stock'],
    queryFn: () => apiGet<ProductStock>(`/api/products/${id}/stock`),
    enabled: !!id,
    staleTime: 15_000,
  })
}

export function useDashboardSummary() {
  return useQuery({ queryKey: ['dashboard', 'summary'], queryFn: () => apiGet<DashboardSummary>('/api/dashboard/summary'), staleTime: 30_000 })
}

export function useAdminRoles(enabled = true) {
  return useQuery({ queryKey: ['admin', 'roles'], queryFn: () => apiGet<AdminRole[]>('/api/admin/roles'), enabled, staleTime: 60_000 })
}

export function usePermissionCatalogue(enabled = true) {
  return useQuery({ queryKey: ['admin', 'permissions'], queryFn: () => apiGet<PermissionGroup[]>('/api/admin/permissions'), enabled, staleTime: 10 * 60_000 })
}

export function useAdminBranches(enabled = true) {
  return useQuery({ queryKey: ['admin', 'branches'], queryFn: () => apiGet<AdminBranch[]>('/api/admin/branches'), enabled, staleTime: 60_000 })
}

export function usePriceLists(enabled = true) {
  return useQuery({ queryKey: ['price-lists'], queryFn: () => apiGet<PriceList[]>('/api/price-lists'), enabled, staleTime: 60_000 })
}
