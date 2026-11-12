import "server-only";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export type AdminCustomerStatus =
  | "active"
  | "inactive"
  | "suspended";

export interface AdminCustomerListItem {
  id: number;

  first_name:
    string | null;

  last_name:
    string | null;

  email:
    string | null;

  phone:
    string | null;

  status:
    AdminCustomerStatus;

  email_verified_at?:
    string | null;

  phone_verified_at?:
    string | null;

  addresses_count?:
    number;

  orders_count?:
    number;

  created_at:
    string | null;

  updated_at:
    string | null;
}

export interface AdminCustomerAddress {
  id:
    number;

  title:
    string | null;

  recipient_name:
    string | null;

  phone:
    string | null;

  address:
    string | null;

  city:
    string | null;

  state:
    string | null;

  postal_code:
    string | null;

  country:
    string | null;

  is_default:
    boolean;
}

export interface AdminCustomerOrder {
  id:
    number;

  order_number:
    string;

  status:
    string;

  subtotal:
    number;

  discount:
    number;

  shipping_cost:
    number;

  tax:
    number;

  total:
    number;

  currency:
    string;

  created_at:
    string | null;
}

export interface AdminCustomerPreferredCategory {
  id: number;
  slug: string;
  name_en: string | null;
  name_fa: string | null;
}

export interface AdminCustomer
  extends AdminCustomerListItem {
  birth_date: string | null;
  marketing_sms: boolean;
  preferred_category_ids: number[];
  preferred_categories: AdminCustomerPreferredCategory[];

  addresses:
    AdminCustomerAddress[];

  orders:
    AdminCustomerOrder[];
}

export interface AdminCustomersMeta {
  current_page:
    number;

  last_page:
    number;

  per_page:
    number;

  total:
    number;

  available_statuses:
    AdminCustomerStatus[];
}

export interface AdminCustomersResult {
  data:
    AdminCustomerListItem[];

  meta:
    AdminCustomersMeta;
}

export interface AdminCustomerQuery {
  search?:
    string;

  status?:
    string;

  page?:
    number;
}

export class AdminCustomerRequestError
  extends Error {
  constructor(
    public readonly status:
      number,
    message:
      string,
  ) {
    super(message);

    this.name =
      "AdminCustomerRequestError";
  }
}

function payloadMessage(
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

export async function getAdminCustomers(
  query:
    AdminCustomerQuery = {},
): Promise<AdminCustomersResult> {
  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminCustomerRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const params =
    new URLSearchParams();

  const search =
    query.search?.trim();

  if (search) {
    params.set(
      "search",
      search,
    );
  }

  if (query.status) {
    params.set(
      "status",
      query.status,
    );
  }

  params.set(
    "page",
    String(
      query.page ?? 1,
    ),
  );

  const response =
    await adminBackendFetch(
      `/admin/customers?${params.toString()}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminCustomerRequestError(
      response.status,
      payloadMessage(
        payload,
      ) ??
        "دریافت مشتریان انجام نشد.",
    );
  }

  const result =
    payload as {
      data?:
        AdminCustomerListItem[];

      meta?: {
        current_page?:
          number;

        last_page?:
          number;

        per_page?:
          number;

        total?:
          number;

        available_statuses?:
          AdminCustomerStatus[];
      };
    } | null;

  if (
    !result ||
    !Array.isArray(
      result.data,
    )
  ) {
    throw new AdminCustomerRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return {
    data:
      result.data,

    meta: {
      current_page:
        result.meta
          ?.current_page ??
        query.page ??
        1,

      last_page:
        result.meta
          ?.last_page ??
        1,

      per_page:
        result.meta
          ?.per_page ??
        20,

      total:
        result.meta
          ?.total ??
        result.data.length,

      available_statuses:
        result.meta
          ?.available_statuses ??
        [
          "active",
          "inactive",
          "suspended",
        ],
    },
  };
}

export async function getAdminCustomer(
  customerId:
    number,
): Promise<AdminCustomer> {
  if (
    !Number.isInteger(
      customerId,
    ) ||
    customerId <= 0
  ) {
    throw new AdminCustomerRequestError(
      400,
      "شناسه نامعتبر است.",
    );
  }

  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminCustomerRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const response =
    await adminBackendFetch(
      `/admin/customers/${customerId}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminCustomerRequestError(
      response.status,
      payloadMessage(
        payload,
      ) ??
        "دریافت مشتری انجام نشد.",
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
    throw new AdminCustomerRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return data as
    AdminCustomer;
}