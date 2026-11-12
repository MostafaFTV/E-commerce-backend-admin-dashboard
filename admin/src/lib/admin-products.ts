import "server-only";

import {
  adminBackendAssetUrl,
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export type LocalizedAdminText =
  | string
  | {
      en?: string | null;
      fa?: string | null;

      [locale: string]:
        | string
        | null
        | undefined;
    }
  | null;

export type AdminProductStatus =
  | "draft"
  | "active"
  | "inactive";

export type AdminVariantStatus =
  | "active"
  | "made_to_order"
  | "sold"
  | "inactive";

export interface AdminProductCategory {
  id: number;

  name:
    LocalizedAdminText;

  slug: string;
}

export interface AdminProductCollection {
  id: number;

  name:
    LocalizedAdminText;

  slug: string;
}

export interface AdminProductImage {
  id: number;

  product_id?:
    number | null;

  product_variant_id?:
    number | null;

  attribute_value_id?:
    number | null;

  image_path:
    string | null;

  image_url?:
    string;

  alt_text:
    string | null;

  sort_order:
    number;

  is_primary:
    boolean;
}

export interface AdminVariantAttribute {
  id: number;

  name:
    LocalizedAdminText;

  slug: string;

  type:
    string;
}

export interface AdminVariantAttributeValue {
  id: number;

  attribute_id:
    number;

  value:
    LocalizedAdminText;

  attribute?:
    AdminVariantAttribute | null;
}

export interface AdminVariantInventory {
  id:
    number;

  quantity:
    number;

  reserved_quantity:
    number;

  low_stock_threshold:
    number;

  available_quantity:
    number;

  available:
    boolean;
}

export interface AdminProductVariant {
  id: number;

  product_id:
    number;

  sku:
    string;

  price:
    number | string | null;

  effective_price:
    number;

  status:
    AdminVariantStatus | string;

  is_purchasable?:
    boolean;

  sort_order:
    number;

  attribute_values:
    AdminVariantAttributeValue[];

  images:
    AdminProductImage[];

  inventory:
    AdminVariantInventory | null;

  created_at?:
    string | null;

  updated_at?:
    string | null;
}

export interface AdminProduct {
  id: number;

  category_id:
    number | null;

  name:
    LocalizedAdminText;

  slug:
    string;

  short_description:
    LocalizedAdminText;

  description:
    LocalizedAdminText;

  brand:
    string | null;

  base_price?:
    number | null;

  price:
    number;

  discount_percentage?:
    number | null;

  min_price:
    number;

  max_price:
    number;

  has_price_range:
    boolean;

  status:
    | AdminProductStatus
    | string
    | null;

  is_featured:
    boolean;

  category?:
    AdminProductCategory | null;

  collections?:
    AdminProductCollection[];

  images?:
    AdminProductImage[];

  variants?:
    AdminProductVariant[];

  total_inventory:
    number | null;

  available_inventory:
    number | null;

  created_at:
    string | null;

  updated_at:
    string | null;
}

export interface AdminProductPaginationMeta {
  current_page:
    number;

  from:
    number | null;

  last_page:
    number;

  per_page:
    number;

  to:
    number | null;

  total:
    number;
}

export interface AdminProductCollectionResponse {
  data:
    AdminProduct[];

  links?: {
    first?:
      string | null;

    last?:
      string | null;

    prev?:
      string | null;

    next?:
      string | null;
  };

  meta:
    AdminProductPaginationMeta;
}

interface AdminProductDetailResponse {
  data?:
    AdminProduct;
}

export interface AdminProductListParams {
  search?:
    string;

  categoryId?:
    number | null;

  status?:
    AdminProductStatus | "";

  page?:
    number;

  perPage?:
    number;
}

export class AdminProductsRequestError
  extends Error
{
  constructor(
    public readonly status:
      number,

    message:
      string,
  ) {
    super(
      message,
    );

    this.name =
      "AdminProductsRequestError";
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

export async function getAdminProducts(
  params:
    AdminProductListParams = {},
): Promise<AdminProductCollectionResponse> {
  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminProductsRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const query =
    new URLSearchParams();

  const search =
    params.search
      ?.trim();

  if (search) {
    query.set(
      "search",
      search,
    );
  }

  if (
    params.categoryId &&
    params.categoryId > 0
  ) {
    query.set(
      "category_id",
      String(
        params.categoryId,
      ),
    );
  }

  if (
    params.status
  ) {
    query.set(
      "status",
      params.status,
    );
  }

  query.set(
    "page",
    String(
      Math.max(
        1,
        params.page ?? 1,
      ),
    ),
  );

  query.set(
    "per_page",
    String(
      Math.max(
        1,
        params.perPage ??
          15,
      ),
    ),
  );

  const response =
    await adminBackendFetch(
      `/admin/products?${query.toString()}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (
    !response.ok
  ) {
    throw new AdminProductsRequestError(
      response.status,
      payloadMessage(
        payload,
      ) ??
        "دریافت محصولات انجام نشد.",
    );
  }

  if (
    !payload ||
    typeof payload !==
      "object" ||
    !Array.isArray(
      (
        payload as {
          data?:
            unknown;
        }
      ).data,
    )
  ) {
    throw new AdminProductsRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return payload as
    AdminProductCollectionResponse;
}

export async function getAdminProduct(
  productId:
    number,
): Promise<AdminProduct> {
  if (
    !Number.isInteger(
      productId,
    ) ||
    productId <= 0
  ) {
    throw new AdminProductsRequestError(
      400,
      "شناسه نامعتبر است.",
    );
  }

  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminProductsRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const response =
    await adminBackendFetch(
      `/admin/products/${productId}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (
    !response.ok
  ) {
    throw new AdminProductsRequestError(
      response.status,
      payloadMessage(
        payload,
      ) ??
        "دریافت محصول انجام نشد.",
    );
  }

  if (
    !payload ||
    typeof payload !==
      "object"
  ) {
    throw new AdminProductsRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  const data =
    (
      payload as
        AdminProductDetailResponse
    ).data;

  if (
    !data ||
    typeof data !==
      "object" ||
    !Number.isInteger(
      data.id,
    )
  ) {
    throw new AdminProductsRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return data;
}
function orderAdminProductMedia(
  images:
    AdminProductImage[],
): AdminProductImage[] {
  /*
   * Older products may have sort_order
   * scoped independently per gallery:
   *
   * Black: 0,1,2
   * Steel: 0,1,2
   * Navy: 0,1,2
   *
   * In that legacy case, ID represents
   * the original upload sequence.
   *
   * Once an admin explicitly reorders
   * and saves the product, sort_order
   * becomes one unique global sequence
   * and that saved sequence wins.
   */
  const sortOrders =
    images.map(
      (image) =>
        Number(
          image.sort_order,
        ),
    );

  const hasDuplicateSortOrders =
    new Set(
      sortOrders,
    ).size !==
    sortOrders.length;

  return [
    ...images,
  ].sort(
    (a, b) => {
      if (
        hasDuplicateSortOrders
      ) {
        return a.id - b.id;
      }

      return (
        a.sort_order -
          b.sort_order ||
        a.id - b.id
      );
    },
  );
}
export async function getAdminProductMedia(
  productId:
    number,
): Promise<AdminProductImage[]> {
  if (
    !Number.isInteger(
      productId,
    ) ||
    productId <= 0
  ) {
    throw new AdminProductsRequestError(
      400,
      "شناسه نامعتبر است.",
    );
  }

  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminProductsRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const response =
    await adminBackendFetch(
      `/admin/products/${productId}/images`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminProductsRequestError(
      response.status,
      payloadMessage(payload) ??
        "دریافت تصاویر محصول انجام نشد.",
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

  if (!Array.isArray(data)) {
    throw new AdminProductsRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  const images =
    (
      data as
        AdminProductImage[]
    ).map(
      (image) => ({
        ...image,

        image_url:
          adminBackendAssetUrl(
            image.image_path,
          ),
      }),
    );

  return orderAdminProductMedia(
    images,
  );
}