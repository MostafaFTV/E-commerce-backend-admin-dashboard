import "server-only";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export interface AdminDashboardAccess {
  orders: boolean;
  order_financials: boolean;
  customers: boolean;
  products: boolean;
  inventory: boolean;
  categories: boolean;
  collections: boolean;
  activity_logs: boolean;
}

export interface AdminDashboardMonthlyOrder {
  key: string;
  label: string;
  orders: number;
}

export interface AdminDashboardRevenue {
  currency: string;
  total: number;
}

export interface AdminDashboardOrders {
  total: number;
  statuses: Record<
    string,
    number
  >;
  monthly:
    AdminDashboardMonthlyOrder[];
  paid_revenue?:
    AdminDashboardRevenue[];
  paid_orders?: number;
}

export interface AdminDashboardCustomers {
  total: number;
  new_last_30_days: number;
}

export interface AdminDashboardProducts {
  total: number;
  statuses: Record<
    string,
    number
  >;
}

export interface AdminDashboardInventory {
  records: number;
  physical_units: number;
  reserved_units: number;
  available_units: number;
  healthy: number;
  low_stock: number;
  out_of_stock: number;
}

export interface AdminDashboardCount {
  total: number;
}

export interface AdminDashboardActivity {
  id: number;
  action: string;
  description:
    string | null;
  created_at:
    string | null;
  user:
    | {
        id: number;
        name: string;
      }
    | null;
}

export interface AdminDashboardData {
  generated_at: string;
  access:
    AdminDashboardAccess;
  orders?:
    AdminDashboardOrders;
  customers?:
    AdminDashboardCustomers;
  products?:
    AdminDashboardProducts;
  inventory?:
    AdminDashboardInventory;
  categories?:
    AdminDashboardCount;
  collections?:
    AdminDashboardCount;
  activity_logs?:
    AdminDashboardActivity[];
}

interface AdminDashboardResponse {
  data?:
    AdminDashboardData;
}

export async function getAdminDashboard():
Promise<AdminDashboardData | null> {
  const token =
    await getAdminToken();

  if (!token) {
    return null;
  }

  try {
    const response =
      await adminBackendFetch(
        "/admin/dashboard",
        {},
        token,
      );

    if (!response.ok) {
      return null;
    }

    const payload =
      (await readBackendPayload(
        response,
      )) as
        AdminDashboardResponse | null;

    return payload?.data ?? null;
  } catch {
    return null;
  }
}