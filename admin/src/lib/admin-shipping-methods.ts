import "server-only";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export interface AdminShippingMethod {
  id:
    number;

  name:
    string;

  description:
    string | null;

  price:
    number;

  estimated_days:
    string | null;

  is_active:
    boolean;

  sort_order:
    number;

  created_at:
    string | null;

  updated_at:
    string | null;
}

export interface AdminShippingMethodPagination {
  current_page:
    number;

  last_page:
    number;

  per_page:
    number;

  total:
    number;
}

export interface AdminShippingMethodList {
  data:
    AdminShippingMethod[];

  meta:
    AdminShippingMethodPagination;
}

export class AdminShippingMethodRequestError
  extends Error {
  constructor(
    public readonly status:
      number,
    message:
      string,
  ) {
    super(message);

    this.name =
      "AdminShippingMethodRequestError";
  }
}

function messageFromPayload(
  payload:
    unknown,
): string | null {
  if (
    !payload ||
    typeof payload !==
      "object"
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

export async function getAdminShippingMethods({
  search = "",
  active = "",
  page = 1,
}: {
  search?:
    string;

  active?:
    string;

  page?:
    number;
} = {}): Promise<AdminShippingMethodList> {
  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminShippingMethodRequestError(
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

  if (
    active === "1" ||
    active === "0"
  ) {
    params.set(
      "is_active",
      active,
    );
  }

  params.set(
    "page",
    String(page),
  );

  params.set(
    "per_page",
    "20",
  );

  const response =
    await adminBackendFetch(
      `/admin/shipping-methods?${params.toString()}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminShippingMethodRequestError(
      response.status,
      messageFromPayload(
        payload,
      ) ??
        "دریافت روش‌های ارسال انجام نشد.",
    );
  }

  if (
    !payload ||
    typeof payload !==
      "object"
  ) {
    throw new AdminShippingMethodRequestError(
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
    throw new AdminShippingMethodRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return {
    data:
      record.data as
        AdminShippingMethod[],

    meta:
      record.meta as
        AdminShippingMethodPagination,
  };
}

export async function getAdminShippingMethod(
  id:
    number,
): Promise<AdminShippingMethod> {
  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminShippingMethodRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const response =
    await adminBackendFetch(
      `/admin/shipping-methods/${id}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminShippingMethodRequestError(
      response.status,
      messageFromPayload(
        payload,
      ) ??
        "دریافت روش ارسال انجام نشد.",
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
    typeof data !==
      "object"
  ) {
    throw new AdminShippingMethodRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return data as
    AdminShippingMethod;
}