import "server-only";
import { adminBackendFetch, getAdminToken, readBackendPayload } from "@/lib/admin-server";

export type CashbackRule = {
  id: number; name?: string; scope?: string; percentage?: number | string;
  cashback_percentage?: number | string; priority?: number; is_active?: boolean;
  starts_at?: string | null; ends_at?: string | null; expires_after_days?: number | null;
  category_ids?: number[]; product_ids?: number[];
  categories?: Array<{id:number}>; products?: Array<{id:number}>;
};
export type CashbackReport = Record<string, unknown>;

export async function cashbackGet(path: string): Promise<unknown> {
  const token = await getAdminToken();
  if (!token) throw new Error("ابتدا وارد پنل مدیریت شوید.");
  const response = await adminBackendFetch(path, { cache: "no-store" }, token);
  const payload = await readBackendPayload(response);
  if (!response.ok) {
    const data = payload as {message?:string} | null;
    throw new Error(data?.message ?? "ارتباط با سرویس Cashback برقرار نشد.");
  }
  return payload;
}
export function extractRules(payload: unknown): CashbackRule[] {
  if (!payload || typeof payload !== "object") return [];
  const body = payload as {data?:unknown};
  if (Array.isArray(body.data)) return body.data as CashbackRule[];
  if (body.data && typeof body.data === "object" && Array.isArray((body.data as {data?:unknown}).data))
    return (body.data as {data:CashbackRule[]}).data;
  return [];
}
