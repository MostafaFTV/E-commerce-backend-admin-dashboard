import "server-only";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export type AdminAttributeType =
  | "text"
  | "color"
  | "number"
  | "select";

export interface AdminLocalizedText {
  en?: string | null;
  fa?: string | null;
}

export interface AdminAttributeValue {
  id: number;
  attribute_id: number;

  /* Persian-first display label used throughout the Admin UI. */
  value: string;

  /*
   * Complete translation map.
   */
  translations:
    AdminLocalizedText;
}

export interface AdminAttributeOption {
  id: number;

  /* Persian-first display label used throughout the Admin UI. */
  name: string;

  /*
   * Complete translation map.
   */
  translations:
    AdminLocalizedText;

  slug: string;
  type: AdminAttributeType;
  values: AdminAttributeValue[];
}

interface BackendAttributeValue {
  id: number;
  attribute_id: number;

  value:
    | string
    | AdminLocalizedText;
}

interface BackendAttributeOption {
  id: number;

  name:
    | string
    | AdminLocalizedText;

  slug: string;
  type: AdminAttributeType;

  values?:
    BackendAttributeValue[];
}

interface AdminAttributesResponse {
  data?:
    BackendAttributeOption[];
}

function localizedMap(
  value:
    | string
    | AdminLocalizedText
    | null
    | undefined,
): AdminLocalizedText {
  if (
    typeof value ===
    "string"
  ) {
    const text =
      value.trim();

    if (!text) {
      return {};
    }

    const isPersian =
      /[\u0600-\u06FF]/u.test(
        text,
      );

    return isPersian
      ? {
          fa: text,
        }
      : {
          en: text,
        };
  }

  if (!value) {
    return {};
  }

  return {
    en:
      typeof value.en ===
      "string"
        ? value.en
        : null,

    fa:
      typeof value.fa ===
      "string"
        ? value.fa
        : null,
  };
}

function displayText(
  value:
    | string
    | AdminLocalizedText
    | null
    | undefined,
): string {
  const translations =
    localizedMap(value);

  const persian =
    translations.fa
      ?.trim();

  if (persian) {
    return persian;
  }

  const english =
    translations.en
      ?.trim();

  if (english) {
    return english;
  }

  return "بدون عنوان";
}

function normalizeValue(
  value:
    BackendAttributeValue,
): AdminAttributeValue {
  return {
    id:
      value.id,

    attribute_id:
      value.attribute_id,

    value:
      displayText(
        value.value,
      ),

    translations:
      localizedMap(
        value.value,
      ),
  };
}

function normalizeAttribute(
  attribute:
    BackendAttributeOption,
): AdminAttributeOption {
  return {
    id:
      attribute.id,

    name:
      displayText(
        attribute.name,
      ),

    translations:
      localizedMap(
        attribute.name,
      ),

    slug:
      attribute.slug,

    type:
      attribute.type,

    values:
      Array.isArray(
        attribute.values,
      )
        ? attribute.values.map(
            normalizeValue,
          )
        : [],
  };
}

export async function getAdminAttributeOptions():
Promise<AdminAttributeOption[]> {
  const token =
    await getAdminToken();

  if (!token) {
    return [];
  }

  try {
    const response =
      await adminBackendFetch(
        "/admin/attributes",
        {},
        token,
      );

    if (!response.ok) {
      return [];
    }

    const payload =
      await readBackendPayload(
        response,
      ) as
        AdminAttributesResponse
        | null;

    if (
      !payload ||
      !Array.isArray(
        payload.data,
      )
    ) {
      return [];
    }

    return payload.data.map(
      normalizeAttribute,
    );
  } catch {
    return [];
  }
}