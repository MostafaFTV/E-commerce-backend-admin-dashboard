import "server-only";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export type InventoryLocalizedValue =
  | string
  | null
  | {
      [locale: string]:
        | string
        | null
        | undefined;
    };

export interface AdminInventoryAttribute {
  id: number;

  value:
    InventoryLocalizedValue;

  attribute:
    | {
        id: number;

        name:
          InventoryLocalizedValue;

        slug:
          string;
      }
    | null;
}

export interface AdminInventoryMovement {
  id: number;

  type:
    string;

  quantity_delta:
    number;

  reserved_delta:
    number;

  note:
    | string
    | null;

  order:
    | {
        id: number;

        order_number:
          string;
      }
    | null;

  order_item_id:
    | number
    | null;

  created_by:
    | {
        id: number;

        email:
          string;
      }
    | null;

  created_at:
    string | null;
}

export interface AdminInventory {
  id: number;

  product_variant_id:
    number;

  quantity:
    number;

  reserved_quantity:
    number;

  available_quantity:
    number;

  low_stock_threshold:
    number;

  low_stock:
    boolean;

  variant:
    | {
        id: number;

        sku:
          string;

        price:
          number | null;

        status:
          string | null;

        attributes:
          AdminInventoryAttribute[];
      }
    | null;

  product:
    | {
        id: number;

        name:
          InventoryLocalizedValue;

        slug:
          string;

        brand:
          string | null;
      }
    | null;

  movements?:
    AdminInventoryMovement[];

  created_at:
    string | null;

  updated_at:
    string | null;
}

export interface AdminInventoryMeta {
  current_page:
    number;

  last_page:
    number;

  per_page:
    number;

  total:
    number;
}

export interface AdminInventoryList {
  data:
    AdminInventory[];

  meta:
    AdminInventoryMeta;
}

export interface AdminInventoryProductVariant {
  id: number;

  inventory_configured:
    boolean;

  inventory_id:
    number | null;

  sku:
    string;

  status:
    string | null;

  quantity:
    number | null;

  reserved_quantity:
    number | null;

  available_quantity:
    number | null;

  low_stock_threshold:
    number | null;

  low_stock:
    boolean;

  attributes:
    AdminInventoryAttribute[];
}

export interface AdminInventoryProduct {
  id: number;

  name:
    InventoryLocalizedValue;

  slug:
    string;

  brand:
    string | null;

  variants_count:
    number;

  configured_variants_count:
    number;

  unconfigured_variants_count:
    number;

  quantity:
    number;

  reserved_quantity:
    number;

  available_quantity:
    number;

  low_stock_variants_count:
    number;

  out_of_stock_variants_count:
    number;

  stock_status:
    | "in_stock"
    | "low_stock"
    | "out_of_stock"
    | "not_configured";

  variants:
    AdminInventoryProductVariant[];

  updated_at:
    string | null;
}

export interface AdminInventoryProductList {
  data:
    AdminInventoryProduct[];

  meta:
    AdminInventoryMeta;
}

export interface AdminInventoryQuery {
  search?:
    string;

  stockStatus?:
    string;

  page?:
    number;

  perPage?:
    number;
}

interface BackendInventoryListResponse {
  data?:
    AdminInventory[];

  meta?:
    Partial<AdminInventoryMeta>;
}

interface BackendProductListResponse {
  data?:
    AdminInventoryProduct[];

  meta?:
    Partial<AdminInventoryMeta>;
}

export class AdminInventoryRequestError
  extends Error {
  constructor(
    public readonly status:
      number,
    message:
      string,
  ) {
    super(message);

    this.name =
      "AdminInventoryRequestError";
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

function inventoryQueryString(
  query:
    AdminInventoryQuery,
): string {
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

  if (
    query.stockStatus
  ) {
    params.set(
      "stock_status",
      query.stockStatus,
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
      query.perPage ?? 25,
    ),
  );

  return params.toString();
}

export async function getAdminInventories(
  query:
    AdminInventoryQuery = {},
): Promise<AdminInventoryList> {
  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminInventoryRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const response =
    await adminBackendFetch(
      `/admin/inventory?${inventoryQueryString(
        query,
      )}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminInventoryRequestError(
      response.status,
      payloadMessage(
        payload,
      ) ??
        "دریافت موجودی انبار انجام نشد.",
    );
  }

  const result =
    payload as
      BackendInventoryListResponse | null;

  if (
    !result ||
    !Array.isArray(
      result.data,
    )
  ) {
    throw new AdminInventoryRequestError(
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
        25,

      total:
        result.meta
          ?.total ??
        result.data.length,
    },
  };
}

export async function getAdminInventory(
  inventoryId:
    number,
): Promise<AdminInventory> {
  if (
    !Number.isInteger(
      inventoryId,
    ) ||
    inventoryId <= 0
  ) {
    throw new AdminInventoryRequestError(
      400,
      "شناسه نامعتبر است.",
    );
  }

  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminInventoryRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const response =
    await adminBackendFetch(
      `/admin/inventory/${inventoryId}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminInventoryRequestError(
      response.status,
      payloadMessage(
        payload,
      ) ??
        "دریافت موجودی انبار انجام نشد.",
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
    throw new AdminInventoryRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return data as
    AdminInventory;
}

export async function getAdminInventoryProducts(
  query:
    AdminInventoryQuery = {},
): Promise<AdminInventoryProductList> {
  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminInventoryRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const response =
    await adminBackendFetch(
      `/admin/inventory/products?${inventoryQueryString(
        query,
      )}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminInventoryRequestError(
      response.status,
      payloadMessage(
        payload,
      ) ??
        "دریافت محصولات انبار انجام نشد.",
    );
  }

  const result =
    payload as
      BackendProductListResponse | null;

  if (
    !result ||
    !Array.isArray(
      result.data,
    )
  ) {
    throw new AdminInventoryRequestError(
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
        25,

      total:
        result.meta
          ?.total ??
        result.data.length,
    },
  };
}

export async function getAdminInventoryProduct(
  productId:
    number,
): Promise<AdminInventoryProduct> {
  if (
    !Number.isInteger(
      productId,
    ) ||
    productId <= 0
  ) {
    throw new AdminInventoryRequestError(
      400,
      "شناسه نامعتبر است.",
    );
  }

  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminInventoryRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const response =
    await adminBackendFetch(
      `/admin/inventory/products/${productId}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminInventoryRequestError(
      response.status,
      payloadMessage(
        payload,
      ) ??
        "دریافت موجودی محصول انجام نشد.",
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
    throw new AdminInventoryRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return data as
    AdminInventoryProduct;
}