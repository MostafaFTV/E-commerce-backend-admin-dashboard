import "server-only";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export type AdminOrderStatus =
  | "pending_payment"
  | "paid"
  | "processing"
  | "shipped"
  | "delivered"
  | "cancelled"
  | "returned";

export interface AdminOrderAddress {
  first_name?: string | null;
  last_name?: string | null;
  phone?: string | null;
  country?: string | null;
  state?: string | null;
  city?: string | null;
  postal_code?: string | null;
  address_line_1?: string | null;
  address_line_2?: string | null;

  [key: string]:
    unknown;
}

export interface AdminOrderItem {
  id: number;
  product_id: number;
  product_variant_id:
    number | null;
  product_name:
    string;
  sku:
    string | null;
  quantity:
    number;
  unit_price:
    number;
  total:
    number;
}

export interface AdminOrderPayment {
  id: number;
  order_id:
    number;
  transaction_id?:
    string | null;
  gateway_reference?:
    string | null;
  amount:
    number;
  currency:
    string;
  method:
    string;
  status:
    string;
  paid_at?:
    string | null;
  created_at?:
    string | null;
}

export interface AdminOrderHistory {
  id: number;

  from_status:
    AdminOrderStatus | null;

  to_status:
    AdminOrderStatus | null;

  changed_by:
    | {
        id: number;
        first_name:
          string | null;
        last_name:
          string | null;
      }
    | null;

  note:
    string | null;

  created_at:
    string | null;
}

export interface AdminOrder {
  id: number;

  order_number:
    string;

  status:
    AdminOrderStatus;

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

  shipping_method?:
    | {
        id: number;
        name:
          unknown;
        price:
          number;
        estimated_days:
          number | null;
      }
    | null;

  shipping_address:
    AdminOrderAddress;

  billing_address:
    AdminOrderAddress | null;

  items?:
    AdminOrderItem[];

  payments?:
    AdminOrderPayment[];

  status_history?:
    AdminOrderHistory[];

  created_at:
    string | null;

  updated_at:
    string | null;
}

export interface AdminOrderMeta {
  current_page:
    number;

  last_page:
    number;

  per_page:
    number;

  total:
    number;
}

export interface AdminOrderList {
  data:
    AdminOrder[];

  meta:
    AdminOrderMeta;
}

export interface AdminOrderQuery {
  orderNumber?:
    string;

  status?:
    string;

  userId?:
    number;

  page?:
    number;

  perPage?:
    number;
}

export class AdminOrderRequestError
  extends Error {
  constructor(
    public readonly status:
      number,
    message:
      string,
  ) {
    super(message);

    this.name =
      "AdminOrderRequestError";
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

export async function getAdminOrders(
  query:
    AdminOrderQuery = {},
): Promise<AdminOrderList> {
  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminOrderRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const params =
    new URLSearchParams();

  const orderNumber =
    query.orderNumber?.trim();

  if (orderNumber) {
    params.set(
      "order_number",
      orderNumber,
    );
  }

  if (query.status) {
    params.set(
      "status",
      query.status,
    );
  }

  if (
    query.userId &&
    query.userId > 0
  ) {
    params.set(
      "user_id",
      String(
        query.userId,
      ),
    );
  }

  params.set(
    "page",
    String(
      query.page ?? 1,
    ),
  );

  params.set(
    "per_page",
    String(
      query.perPage ?? 20,
    ),
  );

  const response =
    await adminBackendFetch(
      `/admin/orders?${params.toString()}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminOrderRequestError(
      response.status,
      payloadMessage(
        payload,
      ) ??
        "دریافت سفارش‌ها انجام نشد.",
    );
  }

  const result =
    payload as {
      data?:
        AdminOrder[];

      meta?:
        Partial<AdminOrderMeta>;
    } | null;

  if (
    !result ||
    !Array.isArray(
      result.data,
    )
  ) {
    throw new AdminOrderRequestError(
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
        query.perPage ??
        20,

      total:
        result.meta
          ?.total ??
        result.data.length,
    },
  };
}

export async function getAdminOrder(
  orderId:
    number,
): Promise<AdminOrder> {
  if (
    !Number.isInteger(
      orderId,
    ) ||
    orderId <= 0
  ) {
    throw new AdminOrderRequestError(
      400,
      "شناسه نامعتبر است.",
    );
  }

  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminOrderRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const response =
    await adminBackendFetch(
      `/admin/orders/${orderId}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminOrderRequestError(
      response.status,
      payloadMessage(
        payload,
      ) ??
        "دریافت سفارش انجام نشد.",
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
    throw new AdminOrderRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return data as
    AdminOrder;
}