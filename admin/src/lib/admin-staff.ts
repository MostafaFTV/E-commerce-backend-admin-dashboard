import "server-only";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export type AdminStaffStatus =
  | "active"
  | "inactive"
  | "suspended";

export type AdminStaffRole =
  | "admin"
  | "accountant"
  | "support";

export interface AdminStaffUser {
  id:
    number;

  first_name:
    string;

  last_name:
    string;

  email:
    string;

  phone:
    string | null;

  status:
    AdminStaffStatus;

  roles:
    string[];

  email_verified_at:
    string | null;

  phone_verified_at:
    string | null;

  created_at:
    string | null;

  updated_at:
    string | null;
}

export interface AdminStaffPagination {
  current_page:
    number;

  last_page:
    number;

  per_page:
    number;

  total:
    number;
}

export interface AdminStaffList {
  data:
    AdminStaffUser[];

  meta:
    AdminStaffPagination;
}

export class AdminStaffRequestError
  extends Error {
  constructor(
    public readonly status:
      number,
    message:
      string,
  ) {
    super(message);

    this.name =
      "AdminStaffRequestError";
  }
}

function messageFromPayload(
  payload:
    unknown,
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
        message?:
          unknown;
      }
    ).message;

  return typeof message ===
    "string"
    ? message
    : null;
}

export async function getAdminStaff({
  search = "",
  status = "",
  role = "",
  page = 1,
}: {
  search?:
    string;

  status?:
    string;

  role?:
    string;

  page?:
    number;
} = {}): Promise<AdminStaffList> {
  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminStaffRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const params =
    new URLSearchParams();

  if (search) {
    params.set(
      "search",
      search,
    );
  }

  if (status) {
    params.set(
      "status",
      status,
    );
  }

  if (role) {
    params.set(
      "role",
      role,
    );
  }

  if (page > 1) {
    params.set(
      "page",
      String(page),
    );
  }

  const query =
    params.toString();

  const response =
    await adminBackendFetch(
      `/admin/users${
        query
          ? `?${query}`
          : ""
      }`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminStaffRequestError(
      response.status,
      messageFromPayload(
        payload,
      ) ??
        "دریافت کاربران پنل انجام نشد.",
    );
  }

  if (
    !payload ||
    typeof payload !== "object"
  ) {
    throw new AdminStaffRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  const record =
    payload as {
      data?:
        unknown;

      meta?:
        unknown;
    };

  if (
    !Array.isArray(
      record.data,
    ) ||
    !record.meta ||
    typeof record.meta !==
      "object"
  ) {
    throw new AdminStaffRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return {
    data:
      record.data as
        AdminStaffUser[],

    meta:
      record.meta as
        AdminStaffPagination,
  };
}

export async function getAdminStaffUser(
  id:
    number,
): Promise<AdminStaffUser> {
  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminStaffRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const response =
    await adminBackendFetch(
      `/admin/users/${id}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminStaffRequestError(
      response.status,
      messageFromPayload(
        payload,
      ) ??
        "دریافت کاربر پنل انجام نشد.",
    );
  }

  const data =
    payload &&
    typeof payload ===
      "object"
      ? (
          payload as {
            data?:
              unknown;
          }
        ).data
      : null;

  if (
    !data ||
    typeof data !== "object"
  ) {
    throw new AdminStaffRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return data as
    AdminStaffUser;
}