import "server-only";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

import {
  getAdminProducts,
  type AdminProduct,
  type LocalizedAdminText,
} from "@/lib/admin-products";

export interface AdminCollectionTranslations {
  en?: string | null;
  fa?: string | null;

  [locale: string]:
    | string
    | null
    | undefined;
}

export interface AdminCollection {
  id: number;

  name:
    | string
    | null;

  name_translations?:
    | AdminCollectionTranslations
    | null;

  slug: string;

  description:
    | string
    | null;

  description_translations?:
    | AdminCollectionTranslations
    | null;

  image:
    | string
    | null;

  is_active:
    boolean;

  sort_order:
    number;

  products_count?:
    | number
    | null;

  products?:
    AdminProduct[];

  created_at:
    | string
    | null;

  updated_at:
    | string
    | null;
}

export interface AdminCollectionOption {
  id: number;

  name:
    LocalizedAdminText;

  slug:
    string;

  is_active:
    boolean;

  products_count?:
    number | null;
}

interface CollectionResponse {
  data?:
    AdminCollection[];

  meta?: {
    current_page?:
      number;

    last_page?:
      number;
  };
}

function payloadMessage(
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
        message?: unknown;
      }
    ).message;

  return typeof message === "string"
    ? message
    : null;
}

export class AdminCollectionsRequestError
  extends Error {
  status:
    number;

  constructor(
    status:
      number,
    message:
      string,
  ) {
    super(message);

    this.name =
      "AdminCollectionsRequestError";

    this.status =
      status;
  }
}

export async function getAdminCollections(
  search = "",
): Promise<AdminCollection[]> {
  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminCollectionsRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const collections:
    AdminCollection[] = [];

  let page =
    1;

  let lastPage =
    1;

  do {
    const query =
      new URLSearchParams({
        page:
          String(page),

        per_page:
          "100",
      });

    const cleanSearch =
      search.trim();

    if (cleanSearch) {
      query.set(
        "search",
        cleanSearch,
      );
    }

    const response =
      await adminBackendFetch(
        `/admin/collections?${query.toString()}`,
        {},
        token,
      );

    const payload =
      await readBackendPayload(
        response,
      );

    if (!response.ok) {
      throw new AdminCollectionsRequestError(
        response.status,
        payloadMessage(
          payload,
        ) ??
          "دریافت کالکشن‌ها انجام نشد.",
      );
    }

    if (
      !payload ||
      typeof payload !== "object" ||
      !Array.isArray(
        (
          payload as CollectionResponse
        ).data,
      )
    ) {
      throw new AdminCollectionsRequestError(
        502,
        "پاسخ دریافتی نامعتبر است.",
      );
    }

    const result =
      payload as CollectionResponse;

    collections.push(
      ...(result.data ?? []),
    );

    lastPage =
      Math.max(
        1,
        result.meta
          ?.last_page ??
          1,
      );

    page +=
      1;
  } while (
    page <=
    lastPage
  );

  const unique =
    new Map<
      number,
      AdminCollection
    >();

  for (
    const collection
    of collections
  ) {
    unique.set(
      collection.id,
      collection,
    );
  }

  return Array.from(
    unique.values(),
  );
}

export async function getAdminCollectionOptions():
Promise<AdminCollectionOption[]> {
  try {
    const collections =
      await getAdminCollections();

    return collections.map(
      (collection) => ({
        id:
          collection.id,

        name:
          collection.name_translations ??
          collection.name,

        slug:
          collection.slug,

        is_active:
          collection.is_active,

        products_count:
          collection.products_count,
      }),
    );
  } catch {
    return [];
  }
}

export async function getAdminCollection(
  collectionId:
    number,
): Promise<AdminCollection> {
  if (
    !Number.isInteger(
      collectionId,
    ) ||
    collectionId <= 0
  ) {
    throw new AdminCollectionsRequestError(
      400,
      "شناسه نامعتبر است.",
    );
  }

  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminCollectionsRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const response =
    await adminBackendFetch(
      `/admin/collections/${collectionId}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminCollectionsRequestError(
      response.status,
      payloadMessage(
        payload,
      ) ??
        "دریافت کالکشن انجام نشد.",
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
    throw new AdminCollectionsRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return data as AdminCollection;
}

export async function getAdminCollectionProductOptions():
Promise<AdminProduct[]> {
  const products:
    AdminProduct[] = [];

  let page =
    1;

  let lastPage =
    1;

  do {
    const response =
      await getAdminProducts({
        page,
        perPage:
          100,
      });

    products.push(
      ...response.data,
    );

    lastPage =
      Math.max(
        1,
        response.meta.last_page,
      );

    page +=
      1;
  } while (
    page <=
    lastPage
  );

  const unique =
    new Map<
      number,
      AdminProduct
    >();

  for (
    const product
    of products
  ) {
    unique.set(
      product.id,
      product,
    );
  }

  return Array.from(
    unique.values(),
  );
}
