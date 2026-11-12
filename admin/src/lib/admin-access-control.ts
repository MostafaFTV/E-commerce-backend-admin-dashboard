import "server-only";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export interface AdminPermissionSummary {
  id: number;
  name: string;
  slug: string;
  description: string | null;
}

export interface AdminRole {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  users_count: number;
  permissions: AdminPermissionSummary[];
  created_at: string | null;
  updated_at: string | null;
}

export interface AdminPermission {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  roles_count: number;
  roles?: {
    id: number;
    name: string;
    slug: string;
  }[];
  created_at: string | null;
  updated_at: string | null;
}

export interface AccessPaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface AdminRoleList {
  data: AdminRole[];
  meta: AccessPaginationMeta;
}

export interface AdminPermissionList {
  data: AdminPermission[];
  meta: AccessPaginationMeta;
}

export class AdminAccessControlRequestError
  extends Error {
  constructor(
    public readonly status: number,
    message: string,
  ) {
    super(message);

    this.name =
      "AdminAccessControlRequestError";
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

  return typeof message ===
    "string"
    ? message
    : null;
}

async function getToken(): Promise<string> {
  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminAccessControlRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  return token;
}

async function fetchCollection<T>(
  path: string,
): Promise<{
  data: T[];
  meta: AccessPaginationMeta;
}> {
  const token =
    await getToken();

  const response =
    await adminBackendFetch(
      path,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminAccessControlRequestError(
      response.status,
      messageFromPayload(payload) ??
        "دریافت اطلاعات دسترسی انجام نشد.",
    );
  }

  if (
    !payload ||
    typeof payload !== "object"
  ) {
    throw new AdminAccessControlRequestError(
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
    throw new AdminAccessControlRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return {
    data: record.data as T[],
    meta:
      record.meta as
        AccessPaginationMeta,
  };
}

async function fetchItem<T>(
  path: string,
): Promise<T> {
  const token =
    await getToken();

  const response =
    await adminBackendFetch(
      path,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminAccessControlRequestError(
      response.status,
      messageFromPayload(payload) ??
        "دریافت جزئیات دسترسی انجام نشد.",
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
    throw new AdminAccessControlRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return data as T;
}

export async function getAdminRoles(
  page = 1,
): Promise<AdminRoleList> {
  return fetchCollection<AdminRole>(
    `/admin/roles?page=${page}`,
  );
}

export async function getAdminRole(
  id: number,
): Promise<AdminRole> {
  return fetchItem<AdminRole>(
    `/admin/roles/${id}`,
  );
}

export async function getAdminPermissions(
  page = 1,
): Promise<AdminPermissionList> {
  return fetchCollection<AdminPermission>(
    `/admin/permissions?page=${page}`,
  );
}

export async function getAdminPermission(
  id: number,
): Promise<AdminPermission> {
  return fetchItem<AdminPermission>(
    `/admin/permissions/${id}`,
  );
}

export async function getAllAdminPermissions():
Promise<AdminPermission[]> {
  const first =
    await getAdminPermissions(1);

  const permissions = [
    ...first.data,
  ];

  for (
    let page = 2;
    page <= first.meta.last_page;
    page += 1
  ) {
    const result =
      await getAdminPermissions(page);

    permissions.push(
      ...result.data,
    );
  }

  return permissions;
}