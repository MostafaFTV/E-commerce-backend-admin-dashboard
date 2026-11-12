import "server-only";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export interface AdminActivityLogUser {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  roles: string[];
}

export interface AdminActivityLog {
  id: number;
  user: AdminActivityLogUser | null;
  action: string;
  description: string | null;
  properties: unknown;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string | null;
}

export interface ActivityLogPaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface AdminActivityLogList {
  data: AdminActivityLog[];
  meta: ActivityLogPaginationMeta;
}

export interface ActivityLogFilters {
  page?: number;
  search?: string;
  action?: string;
  userId?: number;
  from?: string;
  to?: string;
}

export class AdminActivityLogRequestError
  extends Error {
  constructor(
    public readonly status: number,
    message: string,
  ) {
    super(message);

    this.name =
      "AdminActivityLogRequestError";
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
    throw new AdminActivityLogRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  return token;
}

export async function getAdminActivityLogs(
  filters: ActivityLogFilters = {},
): Promise<AdminActivityLogList> {
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

  if (filters.action) {
    params.set(
      "action",
      filters.action,
    );
  }

  if (filters.userId) {
    params.set(
      "user_id",
      String(
        filters.userId,
      ),
    );
  }

  if (filters.from) {
    params.set(
      "from",
      filters.from,
    );
  }

  if (filters.to) {
    params.set(
      "to",
      filters.to,
    );
  }

  const response =
    await adminBackendFetch(
      `/admin/activity-logs?${params.toString()}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminActivityLogRequestError(
      response.status,
      messageFromPayload(payload) ??
        "دریافت تاریخچه فعالیت‌ها انجام نشد.",
    );
  }

  if (
    !payload ||
    typeof payload !== "object"
  ) {
    throw new AdminActivityLogRequestError(
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
    throw new AdminActivityLogRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return {
    data:
      record.data as
        AdminActivityLog[],

    meta:
      record.meta as
        ActivityLogPaginationMeta,
  };
}

export async function getAdminActivityLog(
  id: number,
): Promise<AdminActivityLog> {
  const token =
    await getToken();

  const response =
    await adminBackendFetch(
      `/admin/activity-logs/${id}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminActivityLogRequestError(
      response.status,
      messageFromPayload(payload) ??
        "دریافت فعالیت انجام نشد.",
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
    throw new AdminActivityLogRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return data as AdminActivityLog;
}
export async function getAdminActivityLogActions():
Promise<string[]> {
  const token =
    await getToken();

  const response =
    await adminBackendFetch(
      "/admin/activity-logs/actions",
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminActivityLogRequestError(
      response.status,
      messageFromPayload(payload) ??
        "دریافت عملیات‌های ثبت‌شده انجام نشد.",
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
    !Array.isArray(data) ||
    !data.every(
      (item) =>
        typeof item === "string",
    )
  ) {
    throw new AdminActivityLogRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return data;
}