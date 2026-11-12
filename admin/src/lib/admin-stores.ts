import "server-only";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export type AdminStoreOpeningHours =
  | string[]
  | Record<string, string>
  | null;

export interface AdminStore {
  id:
    number;

  name:
    string;

  name_en:
    string | null;

  slug:
    string;

  description:
    string | null;

  description_en:
    string | null;

  address:
    string;

  address_en:
    string | null;

  city:
    string;

  city_en:
    string | null;

  floor_unit:
    string | null;

  floor_unit_en:
    string | null;

  phone:
    string | null;

  email:
    string | null;

  latitude:
    number | null;

  longitude:
    number | null;

  opening_hours:
    AdminStoreOpeningHours;

  opening_hours_en:
    AdminStoreOpeningHours;

  image:
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

export interface AdminStorePagination {
  current_page:
    number;

  last_page:
    number;

  per_page:
    number;

  total:
    number;
}

export interface AdminStoreList {
  data:
    AdminStore[];

  meta:
    AdminStorePagination;
}

export class AdminStoreRequestError
  extends Error {
  constructor(
    public readonly status:
      number,
    message:
      string,
  ) {
    super(message);

    this.name =
      "AdminStoreRequestError";
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

export async function getAdminStores({
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
} = {}): Promise<AdminStoreList> {
  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminStoreRequestError(
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
      `/admin/stores?${params.toString()}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminStoreRequestError(
      response.status,
      messageFromPayload(
        payload,
      ) ??
        "دریافت شعب انجام نشد.",
    );
  }

  if (
    !payload ||
    typeof payload !==
      "object"
  ) {
    throw new AdminStoreRequestError(
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
    throw new AdminStoreRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return {
    data:
      record.data as
        AdminStore[],

    meta:
      record.meta as
        AdminStorePagination,
  };
}

export async function getAdminStore(
  id:
    number,
): Promise<AdminStore> {
  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminStoreRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const response =
    await adminBackendFetch(
      `/admin/stores/${id}`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminStoreRequestError(
      response.status,
      messageFromPayload(
        payload,
      ) ??
        "دریافت شعبه انجام نشد.",
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
    throw new AdminStoreRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return data as
    AdminStore;
}