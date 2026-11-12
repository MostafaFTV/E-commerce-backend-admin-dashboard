import "server-only";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export type SupportTicketStatus = "open" | "closed";

export interface AdminSupportTicket {
  id: number;
  subject: string;
  locale: "fa" | "en";
  status: SupportTicketStatus;
  created_at: string | null;
  updated_at: string | null;
  closed_at: string | null;
  last_message_at: string | null;
  last_message: string | null;
  unread_count: number;
  customer?: {
    id: number;
    name: string;
    email: string;
    phone: string | null;
  };
}

export interface SupportTicketMessage {
  id: number;
  sender_role: "customer" | "admin";
  sender_name: string;
  message: string;
  created_at: string | null;
}

export interface SupportTicketMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface AdminSupportTicketList {
  data: AdminSupportTicket[];
  meta: SupportTicketMeta;
}

export interface AdminSupportTicketConversation {
  ticket: AdminSupportTicket;
  messages: SupportTicketMessage[];
  meta: SupportTicketMeta;
}

export class AdminSupportTicketRequestError extends Error {
  constructor(
    public readonly status: number,
    message: string,
  ) {
    super(message);
    this.name = "AdminSupportTicketRequestError";
  }
}

function messageFromPayload(payload: unknown): string | null {
  if (!payload || typeof payload !== "object") return null;
  const message = (payload as { message?: unknown }).message;
  return typeof message === "string" ? message : null;
}

async function getToken(): Promise<string> {
  const token = await getAdminToken();
  if (!token) {
    throw new AdminSupportTicketRequestError(401, "ابتدا وارد حساب مدیریت شوید.");
  }
  return token;
}

export async function getAdminSupportTickets(
  status?: SupportTicketStatus,
  page = 1,
): Promise<AdminSupportTicketList> {
  const token = await getToken();
  const params = new URLSearchParams({
    page: String(page),
    per_page: "25",
  });
  if (status) params.set("status", status);

  const response = await adminBackendFetch(
    `/admin/support-tickets?${params.toString()}`,
    {},
    token,
  );
  const payload = await readBackendPayload(response);

  if (!response.ok) {
    throw new AdminSupportTicketRequestError(
      response.status,
      messageFromPayload(payload) ?? "دریافت تیکت‌های پشتیبانی انجام نشد.",
    );
  }

  if (
    !payload ||
    typeof payload !== "object" ||
    !Array.isArray((payload as { data?: unknown }).data) ||
    !(payload as { meta?: unknown }).meta
  ) {
    throw new AdminSupportTicketRequestError(502, "پاسخ دریافتی نامعتبر است.");
  }

  return payload as AdminSupportTicketList;
}

export async function getAdminSupportTicket(
  id: number,
): Promise<AdminSupportTicketConversation> {
  const token = await getToken();
  const response = await adminBackendFetch(
    `/admin/support-tickets/${id}`,
    {},
    token,
  );
  const payload = await readBackendPayload(response);

  if (!response.ok) {
    throw new AdminSupportTicketRequestError(
      response.status,
      messageFromPayload(payload) ?? "دریافت گفت‌وگو انجام نشد.",
    );
  }

  const data = payload && typeof payload === "object"
    ? (payload as { data?: unknown }).data
    : null;
  if (
    !data ||
    typeof data !== "object" ||
    !Array.isArray((data as { messages?: unknown }).messages)
  ) {
    throw new AdminSupportTicketRequestError(502, "پاسخ دریافتی نامعتبر است.");
  }

  return data as AdminSupportTicketConversation;
}
