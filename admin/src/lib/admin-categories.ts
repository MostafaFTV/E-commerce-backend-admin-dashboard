import "server-only";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

import type {
  LocalizedAdminText,
} from "@/lib/admin-products";

export interface AdminCategoryTranslations {
  en?: string | null;
  fa?: string | null;

  [locale: string]:
    | string
    | null
    | undefined;
}

export interface AdminCategory {
  id: number;

  parent_id:
    | number
    | null;

  name:
    | string
    | null;

  name_translations?:
    | AdminCategoryTranslations
    | null;

  slug: string;

  description:
    | string
    | null;

  description_translations?:
    | AdminCategoryTranslations
    | null;

  image:
    | string
    | null;

  is_active:
    boolean;

  sort_order:
    number;

  created_at:
    | string
    | null;

  updated_at:
    | string
    | null;
}

export interface AdminCategoryOption {
  id: number;

  name:
    LocalizedAdminText;

  slug:
    string;
}

interface CategoryCollectionResponse {
  data?:
    AdminCategory[];

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
    (payload as {
      message?: unknown;
    }).message;

  return typeof message === "string"
    ? message
    : null;
}

export class AdminCategoriesRequestError
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
      "AdminCategoriesRequestError";

    this.status =
      status;
  }
}

export async function getAdminCategories():
Promise<AdminCategory[]> {
  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminCategoriesRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const categories:
    AdminCategory[] = [];

  let page =
    1;

  let lastPage =
    1;

  do {
    const response =
      await adminBackendFetch(
        `/admin/categories?page=${page}`,
        {},
        token,
      );

    const payload =
      await readBackendPayload(
        response,
      );

    if (!response.ok) {
      throw new AdminCategoriesRequestError(
        response.status,
        payloadMessage(
          payload,
        ) ??
          "دریافت دسته‌بندی‌ها انجام نشد.",
      );
    }

    if (
      !payload ||
      typeof payload !== "object" ||
      !Array.isArray(
        (
          payload as
            CategoryCollectionResponse
        ).data,
      )
    ) {
      throw new AdminCategoriesRequestError(
        502,
        "پاسخ دریافتی نامعتبر است.",
      );
    }

    const result =
      payload as
        CategoryCollectionResponse;

    categories.push(
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
      AdminCategory
    >();

  for (
    const category
    of categories
  ) {
    unique.set(
      category.id,
      category,
    );
  }

  return Array.from(
    unique.values(),
  );
}

export async function getAdminCategoryOptions():
Promise<AdminCategoryOption[]> {
  try {
    const categories =
      await getAdminCategories();

    return categories.map(
      (category) => ({
        id:
          category.id,

        name:
          category.name_translations ??
          category.name,

        slug:
          category.slug,
      }),
    );
  } catch {
    return [];
  }
}
export async function getAdminCategory(
  categoryId:
    number,
): Promise<AdminCategory> {
  if (
    !Number.isInteger(
      categoryId,
    ) ||
    categoryId <= 0
  ) {
    throw new AdminCategoriesRequestError(
      400,
      "شناسه نامعتبر است.",
    );
  }

  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminCategoriesRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const response =
    await adminBackendFetch(
      `/admin/categories/${categoryId}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminCategoriesRequestError(
      response.status,
      payloadMessage(
        payload,
      ) ??
        "دریافت دسته‌بندی انجام نشد.",
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
    throw new AdminCategoriesRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return data as AdminCategory;
}