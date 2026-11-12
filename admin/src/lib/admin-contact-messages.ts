import "server-only";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export const contactMessageStatuses = [
  "new",
  "read",
  "resolved",
] as const;

export type ContactMessageStatus =
  (typeof contactMessageStatuses)[number];

export interface AdminContactMessage {
  id: number;
  full_name: string;
  contact: string;
  subject:
    | "product"
    | "order"
    | "returns"
    | "press"
    | "other";
  message: string;
  locale: "en" | "fa";
  status: ContactMessageStatus;
  created_at: string | null;
  updated_at: string | null;
}

export interface ContactMessagePaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface AdminContactMessageList {
  data: AdminContactMessage[];
  meta: ContactMessagePaginationMeta;
}

export interface ContactMessageFilters {
  page?: number;
  search?: string;
  status?: ContactMessageStatus;
}

export class AdminContactMessageRequestError
  extends Error {
  constructor(
    public readonly status: number,
    message: string,
  ) {
    super(message);

    this.name =
      "AdminContactMessageRequestError";
  }
}

function messageFromPayload(
  payload: unknown,
): string | null {
  if (
    !payload ||
    typeof payload !== "object"
  ) {
    return null;
  }

  const message =
    (
      payload as {
        message?: unknown;
      }
    ).message;

  return typeof message === "string"
    ? message
    : null;
}

async function getToken():
Promise<string> {
  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminContactMessageRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  return token;
}

export async function getAdminContactMessages(
  filters: ContactMessageFilters = {},
): Promise<AdminContactMessageList> {
  const token =
    await getToken();

  const params =
    new URLSearchParams();

  params.set(
    "page",
    String(
      filters.page ?? 1,
    ),
  );

  params.set(
    "per_page",
    "25",
  );

  if (filters.search) {
    params.set(
      "search",
      filters.search,
    );
  }

  if (filters.status) {
    params.set(
      "status",
      filters.status,
    );
  }

  const response =
    await adminBackendFetch(
      `/admin/contact-messages?${params.toString()}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminContactMessageRequestError(
      response.status,
      messageFromPayload(payload) ??
        "دریافت پیام‌های تماس انجام نشد.",
    );
  }

  if (
    !payload ||
    typeof payload !== "object"
  ) {
    throw new AdminContactMessageRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  const record =
    payload as {
      data?: unknown;
      meta?: unknown;
    };

  if (
    !Array.isArray(record.data) ||
    !record.meta ||
    typeof record.meta !== "object"
  ) {
    throw new AdminContactMessageRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return {
    data:
      record.data as
        AdminContactMessage[],

    meta:
      record.meta as
        ContactMessagePaginationMeta,
  };
}

export async function getAdminContactMessage(
  id: number,
): Promise<AdminContactMessage> {
  const token =
    await getToken();

  const response =
    await adminBackendFetch(
      `/admin/contact-messages/${id}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminContactMessageRequestError(
      response.status,
      messageFromPayload(payload) ??
        "دریافت پیام تماس انجام نشد.",
    );
  }

  const data =
    payload &&
    typeof payload === "object"
      ? (
          payload as {
            data?: unknown;
          }
        ).data
      : null;

  if (
    !data ||
    typeof data !== "object"
  ) {
    throw new AdminContactMessageRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return data as AdminContactMessage;
}