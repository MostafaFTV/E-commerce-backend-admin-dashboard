"use client";

import {
  FormEvent,
  useEffect,
  useMemo,
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

import type {
  AdminAttributeOption,
  AdminAttributeType,
  AdminAttributeValue,
  AdminLocalizedText,
} from "@/lib/admin-attributes";

import type {
  AdminBrandOption,
} from "@/lib/admin-brands";

import type {
  AdminCategoryOption,
} from "@/lib/admin-categories";

import type {
  AdminCollectionOption,
} from "@/lib/admin-collections";

import type {
  AdminProduct,
  AdminProductImage,
} from "@/lib/admin-products";

import type {
  ApiErrorPayload,
  ApiValidationErrors,
} from "@/lib/admin-types";

import { SoftSelect } from "@/components/SoftSelect";

import styles from "@/app/(panel)/products/new/new-product.module.css";

type VariantStatus =
  | "active"
  | "made_to_order"
  | "sold"
  | "inactive";

type MediaScope =
  | "general"
  | "color"
  | "variant";

interface VariantDraft {
  key: string;

  serverId?:
    number;

  attributeValueIds:
    number[];

  sku: string;

  price: string;

  status:
    VariantStatus;

  sortOrder:
    number;

  quantity:
    string;

}

interface MediaDraft {
  key: string;

  file:
    File | null;

  previewUrl: string;

  altText: string;

  scope: MediaScope;

  colorValueId:
    number | null;

  variantKey:
    string | null;

  isPrimary:
    boolean;

  serverImageId?:
    number;

  complete?:
    boolean;
}

interface CreatedProductState {
  productId: number;

  variantIdsByKey:
    Record<string, number>;

  finalStatus:
    string;
}

interface ProductResponseVariant {
  id: number;
  sku: string;
}

interface ProductResponseData {
  id: number;

  variants?:
    ProductResponseVariant[];
}

interface ProductResponse {
  data?:
    ProductResponseData;
}

interface MediaUploadResponse {
  data?: Array<{
    id: number;
  }>;
}

interface BrandCreateResponse {
  data?:
    AdminBrandOption;
}

interface RawAttributeValueResponse {
  id: number;
  attribute_id: number;

  value:
    | string
    | AdminLocalizedText;
}

interface RawAttributeResponse {
  id: number;

  name:
    | string
    | AdminLocalizedText;

  slug: string;

  type:
    AdminAttributeType;

  values?:
    RawAttributeValueResponse[];
}

interface AttributeCreateResponse {
  data?:
    RawAttributeResponse;
}

interface AttributeValueCreateResponse {
  data?:
    RawAttributeValueResponse;
}

function attributeTranslations(
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

    return /[\u0600-\u06FF]/u.test(
      text,
    )
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

function attributeDisplayText(
  value:
    | string
    | AdminLocalizedText
    | null
    | undefined,
): string {
  const translations =
    attributeTranslations(
      value,
    );

  return (
    translations.fa
      ?.trim() ||
    translations.en
      ?.trim() ||
    "بدون عنوان"
  );
}

function normalizeCreatedAttributeValue(
  value:
    RawAttributeValueResponse,
): AdminAttributeValue {
  return {
    id:
      value.id,

    attribute_id:
      value.attribute_id,

    value:
      attributeDisplayText(
        value.value,
      ),

    translations:
      attributeTranslations(
        value.value,
      ),
  };
}

function normalizeCreatedAttribute(
  attribute:
    RawAttributeResponse,
): AdminAttributeOption {
  return {
    id:
      attribute.id,

    name:
      attributeDisplayText(
        attribute.name,
      ),

    translations:
      attributeTranslations(
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
            normalizeCreatedAttributeValue,
          )
        : [],
  };
}

interface CreateProductFormProps {
  categories:
    AdminCategoryOption[];

  collections:
    AdminCollectionOption[];

  initialBrands:
    AdminBrandOption[];

  initialAttributes:
    AdminAttributeOption[];

  initialProduct?:
    AdminProduct | null;

  initialMedia?:
    AdminProductImage[];

  canUpdateProduct:
    boolean;
}

function makeKey(): string {
  return [
    Date.now(),
    Math.random()
      .toString(36)
      .slice(2),
  ].join("-");
}

function localizedText(
  value:
    AdminCategoryOption["name"],
): string {
  if (
    typeof value === "string"
  ) {
    return value;
  }

  if (!value) {
    return "بدون عنوان";
  }

  const preferred =
    value.fa ??
    value.en;

  if (
    typeof preferred === "string" &&
    preferred.trim() !== ""
  ) {
    return preferred;
  }

  const fallback =
    Object.values(
      value,
    ).find(
      (item) =>
        typeof item === "string" &&
        item.trim() !== "",
    );

  return typeof fallback ===
    "string"
    ? fallback
    : "بدون عنوان";
}

function firstError(
  errors:
    ApiValidationErrors,
  key: string,
): string | null {
  const messages =
    errors[key];

  if (
    !messages ||
    messages.length === 0
  ) {
    return null;
  }

  return messages[0] ??
    null;
}

function normalizeSkuToken(
  value: string,
): string {
  return value
    .trim()
    .toUpperCase()
    .replace(
      /[^A-Z0-9]+/g,
      "-",
    )
    .replace(
      /^-+|-+$/g,
      "",
    );
}

function combinationKey(
  ids: number[],
): string {
  return [...ids]
    .sort(
      (a, b) =>
        a - b,
    )
    .join("-");
}

async function readJson<T>(
  response: Response,
): Promise<T | null> {
  try {
    return await response
      .json() as T;
  } catch {
    return null;
  }
}

function apiMessage(
  payload:
    ApiErrorPayload | null,
  fallback: string,
): string {
  return payload?.message ??
    fallback;
}

interface TranslationDraft {
  en: string;
  fa: string;
}

function editableTranslations(
  value:
    AdminLocalizedText
    | null
    | undefined,
): TranslationDraft {
  return {
    en:
      value?.en
        ?.trim() ??
      "",

    fa:
      value?.fa
        ?.trim() ??
      "",
  };
}
function productTranslation(
  value:
    | AdminProduct["name"]
    | AdminProduct["short_description"]
    | AdminProduct["description"]
    | undefined,
  locale: "en" | "fa",
): string {
  if (!value) {
    return "";
  }

  if (typeof value === "string") {
    return locale === "en"
      ? value
      : "";
  }

  const translation =
    value[locale];

  return typeof translation === "string"
    ? translation
    : "";
}

function normalizedProductStatus(
  value:
    AdminProduct["status"],
): string {
  if (
    value === "active" ||
    value === "inactive" ||
    value === "draft"
  ) {
    return value;
  }

  return "draft";
}

function normalizedVariantStatus(
  value: string,
): VariantStatus {
  if (
    value === "active" ||
    value === "made_to_order" ||
    value === "sold" ||
    value === "inactive"
  ) {
    return value;
  }

  return "inactive";
}

function initialProductSelections(
  product:
    AdminProduct | null | undefined,
): Record<number, number[]> {
  const result:
    Record<number, number[]> = {};

  for (
    const variant
    of product?.variants ?? []
  ) {
    for (
      const attributeValue
      of variant.attribute_values ?? []
    ) {
      const current =
        result[
          attributeValue.attribute_id
        ] ?? [];

      if (
        !current.includes(
          attributeValue.id,
        )
      ) {
        result[
          attributeValue.attribute_id
        ] = [
          ...current,
          attributeValue.id,
        ];
      }
    }
  }

  return result;
}

function initialProductVariants(
  product:
    AdminProduct | null | undefined,
): VariantDraft[] {
  return (
    product?.variants ?? []
  ).map(
    (
      variant,
      index,
    ) => ({
      key:
        `server-${variant.id}`,

      serverId:
        variant.id,

      attributeValueIds:
        (
          variant.attribute_values ??
          []
        ).map(
          (value) =>
            value.id,
        ),

      sku:
        variant.sku ?? "",

      price:
        variant.price === null ||
        variant.price === undefined
          ? ""
          : String(
              variant.price,
            ),

      status:
        normalizedVariantStatus(
          variant.status,
        ),

      sortOrder:
        Number.isFinite(
          variant.sort_order,
        )
          ? variant.sort_order
          : index,

      quantity:
        String(
          variant.inventory
            ?.quantity ??
          0,
        ),

    }),
  );
}
function mediaDisplayName(
  item:
    MediaDraft,
): string {
  if (item.file) {
    return item.file.name;
  }

  try {
    const url =
      new URL(
        item.previewUrl,
      );

    const filename =
      url.pathname
        .split("/")
        .filter(Boolean)
        .pop();

    if (filename) {
      return decodeURIComponent(
        filename,
      );
    }
  } catch {
    // Keep the fallback below.
  }

  return item.serverImageId
    ? `تصویر شماره ${item.serverImageId}`
    : "تصویر محصول";
}

function initialProductMedia(
  images:
    AdminProductImage[],
): MediaDraft[] {
  return images.map(
    (image) => {
      const scope:
        MediaScope =
          image.product_variant_id
            ? "variant"
            : image.attribute_value_id
              ? "color"
              : "general";

      return {
        key:
          `server-image-${image.id}`,

        file:
          null,

        previewUrl:
          image.image_url ??
          image.image_path ??
          "",

        altText:
          image.alt_text ??
          "",

        scope,

        colorValueId:
          image.attribute_value_id ??
          null,

        variantKey:
          image.product_variant_id
            ? `server-${image.product_variant_id}`
            : null,

        isPrimary:
          Boolean(
            image.is_primary,
          ),

        serverImageId:
          image.id,

        complete:
          false,
      };
    },
  );
}
function productCardMediaLabel(
  items:
    MediaDraft[],
  key:
    string,
): string | null {
  /*
   * General product images have
   * priority for listing cards.
   */
  let cardImages =
    items.filter(
      (item) =>
        item.scope ===
        "general",
    );

  /*
   * If there are no General images,
   * storefront falls back to the
   * first available Color gallery.
   */
  if (
    cardImages.length ===
    0
  ) {
    const firstColorImage =
      items.find(
        (item) =>
          item.scope ===
            "color" &&
          item.colorValueId !==
            null,
      );

    if (firstColorImage) {
      cardImages =
        items
          .filter(
            (item) =>
              item.scope ===
                "color" &&
              item.colorValueId ===
                firstColorImage
                  .colorValueId,
          )
          .slice(
            0,
            2,
          );
    }
  }

  if (
    cardImages.length ===
    0
  ) {
    return null;
  }

  const mainImage =
    cardImages.find(
      (item) =>
        item.isPrimary,
    ) ??
    cardImages[0];

  const hoverImage =
    cardImages[1] ??
    mainImage;

  const isMain =
    mainImage?.key ===
    key;

  const isHover =
    hoverImage?.key ===
    key;

  if (
    isMain &&
    isHover
  ) {
    return "کارت — اصلی / هاور";
  }

  if (isMain) {
    return "کارت — اصلی";
  }

  if (isHover) {
    return "کارت — هاور";
  }

  return null;
}
export function CreateProductForm({
  categories,
  collections,
  initialBrands,
  initialAttributes,
  initialProduct = null,
  initialMedia = [],
  canUpdateProduct,
}: CreateProductFormProps) {
  const router =
    useRouter();

  const isEditMode =
    initialProduct !== null;

  const [
    nameEn,
    setNameEn,
  ] =
    useState("");

  const [
    nameFa,
    setNameFa,
  ] =
    useState("");

  const [
    slug,
    setSlug,
  ] =
    useState("");

  const [
    brand,
    setBrand,
  ] =
    useState("");

  const [
    brands,
    setBrands,
  ] =
    useState(
      initialBrands,
    );

  const [
    newBrandName,
    setNewBrandName,
  ] =
    useState("");

  const [
    addingBrand,
    setAddingBrand,
  ] =
    useState(false);

  const [
    categoryId,
    setCategoryId,
  ] =
    useState("");

  const [
    price,
    setPrice,
  ] =
    useState("");

  const [
    discountPercentage,
    setDiscountPercentage,
  ] =
    useState("");

  const [
    status,
    setStatus,
  ] =
    useState("draft");

  const [
    featured,
    setFeatured,
  ] =
    useState(false);

  const [
    shortEn,
    setShortEn,
  ] =
    useState("");

  const [
    shortFa,
    setShortFa,
  ] =
    useState("");

  const [
    descriptionEn,
    setDescriptionEn,
  ] =
    useState("");

  const [
    descriptionFa,
    setDescriptionFa,
  ] =
    useState("");

  const [
    collectionIds,
    setCollectionIds,
  ] =
    useState<number[]>([]);

  const [
    attributes,
    setAttributes,
  ] =
    useState(
      initialAttributes,
    );

  const [
    selectedValues,
    setSelectedValues,
  ] =
    useState<
      Record<number, number[]>
    >({});

  const [
    newAttributeNameEn,
    setNewAttributeNameEn,
  ] =
    useState("");

  const [
    newAttributeNameFa,
    setNewAttributeNameFa,
  ] =
    useState("");

  const [
    newAttributeSlug,
    setNewAttributeSlug,
  ] =
    useState("");

  const [
    newAttributeType,
    setNewAttributeType,
  ] =
    useState<AdminAttributeType>(
      "select",
    );

  const [
    addingAttribute,
    setAddingAttribute,
  ] =
    useState(false);

  const [
    newValueTextEn,
    setNewValueTextEn,
  ] =
    useState<
      Record<number, string>
    >({});

  const [
    newValueTextFa,
    setNewValueTextFa,
  ] =
    useState<
      Record<number, string>
    >({});

  const [
    addingValueId,
    setAddingValueId,
  ] =
    useState<
      number | null
    >(null);

  const [
    attributeTranslationDrafts,
    setAttributeTranslationDrafts,
  ] =
    useState<
      Record<
        number,
        TranslationDraft
      >
    >({});

  const [
    valueTranslationDrafts,
    setValueTranslationDrafts,
  ] =
    useState<
      Record<
        number,
        TranslationDraft
      >
    >({});

  const [
    savingAttributeTranslationId,
    setSavingAttributeTranslationId,
  ] =
    useState<
      number | null
    >(null);

  const [
    savingValueTranslationId,
    setSavingValueTranslationId,
  ] =
    useState<
      number | null
    >(null);
  const [
    variants,
    setVariants,
  ] =
    useState<
      VariantDraft[]
    >([]);

  const [
    media,
    setMedia,
  ] =
    useState<
      MediaDraft[]
    >([]);

  const [
    errors,
    setErrors,
  ] =
    useState<ApiValidationErrors>(
      {},
    );

  const [
    message,
    setMessage,
  ] =
    useState<
      string | null
    >(null);

  const [
    submitting,
    setSubmitting,
  ] =
    useState(false);

  const [
    createdProduct,
    setCreatedProduct,
  ] =
    useState<
      CreatedProductState | null
    >(null);

  /* TKB_EDIT_PRODUCT_HYDRATION */
  useEffect(
    () => {
      if (!initialProduct) {
        return;
      }

      setNameEn(
        productTranslation(
          initialProduct.name,
          "en",
        ),
      );

      setNameFa(
        productTranslation(
          initialProduct.name,
          "fa",
        ),
      );

      setSlug(
        initialProduct.slug ??
        "",
      );

      setBrand(
        initialProduct.brand ??
        "",
      );

      setCategoryId(
        initialProduct.category_id
          ? String(
              initialProduct.category_id,
            )
          : "",
      );

      setPrice(
        String(
          initialProduct.base_price ??
          initialProduct.price ??
          "",
        ),
      );

      setDiscountPercentage(
        initialProduct.discount_percentage == null
          ? ""
          : String(initialProduct.discount_percentage),
      );

      setStatus(
        normalizedProductStatus(
          initialProduct.status,
        ),
      );

      setFeatured(
        Boolean(
          initialProduct.is_featured,
        ),
      );

      setShortEn(
        productTranslation(
          initialProduct.short_description,
          "en",
        ),
      );

      setShortFa(
        productTranslation(
          initialProduct.short_description,
          "fa",
        ),
      );

      setDescriptionEn(
        productTranslation(
          initialProduct.description,
          "en",
        ),
      );

      setDescriptionFa(
        productTranslation(
          initialProduct.description,
          "fa",
        ),
      );

      setCollectionIds(
        (
          initialProduct.collections ??
          []
        ).map(
          (collection) =>
            collection.id,
        ),
      );

      setSelectedValues(
        initialProductSelections(
          initialProduct,
        ),
      );

      setVariants(
        initialProductVariants(
          initialProduct,
        ),
      );

      setMedia(
        initialProductMedia(
          initialMedia,
        ),
      );

      setCreatedProduct(
        null,
      );
    },
    [
      initialProduct,
      initialMedia,
    ],
  );
  const valueLookup =
    useMemo(
      () => {
        const map =
          new Map<
            number,
            {
              value:
                AdminAttributeValue;

              attribute:
                AdminAttributeOption;
            }
          >();

        for (
          const attribute
          of attributes
        ) {
          for (
            const value
            of attribute.values
          ) {
            map.set(
              value.id,
              {
                value,
                attribute,
              },
            );
          }
        }

        return map;
      },
      [
        attributes,
      ],
    );

  const usedColorValues =
    useMemo(
      () => {
        const ids =
          new Set<number>();

        for (
          const variant
          of variants
        ) {
          for (
            const valueId
            of variant
              .attributeValueIds
          ) {
            const entry =
              valueLookup.get(
                valueId,
              );

            if (
              entry
                ?.attribute
                .type ===
              "color"
            ) {
              ids.add(
                valueId,
              );
            }
          }
        }

        return Array.from(
          ids,
        )
          .map(
            (id) =>
              valueLookup.get(
                id,
              ),
          )
          .filter(
            (
              entry,
            ): entry is {
              value:
                AdminAttributeValue;

              attribute:
                AdminAttributeOption;
            } =>
              Boolean(
                entry,
              ),
          );
      },
      [
        valueLookup,
        variants,
      ],
    );

  function toggleCollection(
    id: number,
  ) {
    setCollectionIds(
      (current) =>
        current.includes(id)
          ? current.filter(
              (
                currentId,
              ) =>
                currentId !==
                id,
            )
          : [
              ...current,
              id,
            ],
    );
  }

  function toggleAttributeValue(
    attributeId:
      number,
    valueId:
      number,
  ) {
    setSelectedValues(
      (current) => {
        const existing =
          current[
            attributeId
          ] ?? [];

        return {
          ...current,

          [attributeId]:
            existing.includes(
              valueId,
            )
              ? existing.filter(
                  (id) =>
                    id !==
                    valueId,
                )
              : [
                  ...existing,
                  valueId,
                ],
        };
      },
    );
  }

  async function addBrand() {
    const name =
      newBrandName.trim();

    if (
      !name ||
      addingBrand
    ) {
      return;
    }

    setAddingBrand(true);
    setMessage(null);

    try {
      const response =
        await fetch(
          "/api/brands",
          {
            method:
              "POST",

            headers: {
              "Content-Type":
                "application/json",
            },

            body:
              JSON.stringify({
                name,
              }),
          },
        );

      const payload =
        await readJson<
          BrandCreateResponse &
          ApiErrorPayload
        >(response);

      if (
        !response.ok ||
        !payload?.data
      ) {
        setMessage(
          apiMessage(
            payload,
            "ایجاد برند انجام نشد.",
          ),
        );

        return;
      }

      setBrands(
        (current) =>
          [
            ...current,
            payload.data!,
          ].sort(
            (a, b) =>
              a.name.localeCompare(
                b.name,
              ),
          ),
      );

      setBrand(
        payload.data.name,
      );

      setNewBrandName("");
    } catch {
      setMessage(
        "ارتباط برای ایجاد برند برقرار نشد.",
      );
    } finally {
      setAddingBrand(false);
    }
  }

  async function addAttribute() {
    const nameEn =
      newAttributeNameEn.trim();

    const nameFa =
      newAttributeNameFa.trim();

    if (
      (
        !nameEn &&
        !nameFa
      ) ||
      addingAttribute
    ) {
      return;
    }

    setAddingAttribute(true);
    setMessage(null);

    try {
      const response =
        await fetch(
          "/api/attributes",
          {
            method:
              "POST",

            headers: {
              "Content-Type":
                "application/json",
            },

            body:
              JSON.stringify({
                name: {
                  en:
                    nameEn ||
                    null,

                  fa:
                    nameFa ||
                    null,
                },

                slug:
                  newAttributeSlug
                    .trim() ||
                  null,

                type:
                  newAttributeType,
              }),
          },
        );

      const payload =
        await readJson<
          AttributeCreateResponse &
          ApiErrorPayload
        >(response);

      if (
        !response.ok ||
        !payload?.data
      ) {
        setMessage(
          apiMessage(
            payload,
            "ایجاد ویژگی انجام نشد.",
          ),
        );

        return;
      }

      const created =
        normalizeCreatedAttribute(
          payload.data,
        );

      setAttributes(
        (current) =>
          [
            ...current,
            created,
          ].sort(
            (a, b) =>
              a.name.localeCompare(
                b.name,
              ),
          ),
      );

      setNewAttributeNameEn(
        "",
      );

      setNewAttributeNameFa(
        "",
      );

      setNewAttributeSlug(
        "",
      );

      setNewAttributeType(
        "select",
      );
    } catch {
      setMessage(
        "ارتباط برای ایجاد ویژگی برقرار نشد.",
      );
    } finally {
      setAddingAttribute(
        false,
      );
    }
  }

  async function addAttributeValue(
    attributeId:
      number,
  ) {
    const valueEn =
      (
        newValueTextEn[
          attributeId
        ] ?? ""
      ).trim();

    const valueFa =
      (
        newValueTextFa[
          attributeId
        ] ?? ""
      ).trim();

    if (
      (
        !valueEn &&
        !valueFa
      ) ||
      addingValueId !== null
    ) {
      return;
    }

    setAddingValueId(
      attributeId,
    );

    setMessage(null);

    try {
      const response =
        await fetch(
          `/api/attributes/${attributeId}/values`,
          {
            method:
              "POST",

            headers: {
              "Content-Type":
                "application/json",
            },

            body:
              JSON.stringify({
                value: {
                  en:
                    valueEn ||
                    null,

                  fa:
                    valueFa ||
                    null,
                },
              }),
          },
        );

      const payload =
        await readJson<
          AttributeValueCreateResponse &
          ApiErrorPayload
        >(response);

      if (
        !response.ok ||
        !payload?.data
      ) {
        setMessage(
          apiMessage(
            payload,
            "ایجاد مقدار ویژگی انجام نشد.",
          ),
        );

        return;
      }

      const createdValue =
        normalizeCreatedAttributeValue(
          payload.data,
        );

      setAttributes(
        (current) =>
          current.map(
            (attribute) =>
              attribute.id ===
              attributeId
                ? {
                    ...attribute,

                    values: [
                      ...attribute.values,
                      createdValue,
                    ],
                  }
                : attribute,
          ),
      );

      setSelectedValues(
        (current) => ({
          ...current,

          [attributeId]: [
            ...(
              current[
                attributeId
              ] ?? []
            ),

            createdValue.id,
          ],
        }),
      );

      setNewValueTextEn(
        (current) => ({
          ...current,

          [attributeId]:
            "",
        }),
      );

      setNewValueTextFa(
        (current) => ({
          ...current,

          [attributeId]:
            "",
        }),
      );
    } catch {
      setMessage(
        "ارتباط برای ایجاد مقدار ویژگی برقرار نشد.",
      );
    } finally {
      setAddingValueId(
        null,
      );
    }
  }

  async function saveAttributeTranslations(
    attributeId:
      number,
  ) {
    const attribute =
      attributes.find(
        (item) =>
          item.id ===
          attributeId,
      );

    if (!attribute) {
      return;
    }

    const draft =
      attributeTranslationDrafts[
        attributeId
      ] ??
      editableTranslations(
        attribute.translations,
      );

    const en =
      draft.en.trim();

    const fa =
      draft.fa.trim();

    if (
      !en &&
      !fa
    ) {
      setMessage(
        "واردکردن حداقل یک ترجمه ضروری است.",
      );

      return;
    }

    setSavingAttributeTranslationId(
      attributeId,
    );

    setMessage(null);

    try {
      const response =
        await fetch(
          `/api/attributes/${attributeId}`,
          {
            method:
              "PATCH",

            headers: {
              "Content-Type":
                "application/json",
            },

            body:
              JSON.stringify({
                name: {
                  en:
                    en ||
                    null,

                  fa:
                    fa ||
                    null,
                },
              }),
          },
        );

      const payload =
        await readJson<
          AttributeCreateResponse &
          ApiErrorPayload
        >(response);

      if (
        !response.ok ||
        !payload?.data
      ) {
        setMessage(
          apiMessage(
            payload,
            "ذخیره ترجمه‌های ویژگی انجام نشد.",
          ),
        );

        return;
      }

      const updated =
        normalizeCreatedAttribute(
          payload.data,
        );

      setAttributes(
        (current) =>
          current.map(
            (item) =>
              item.id ===
              attributeId
                ? updated
                : item,
          ),
      );

      setAttributeTranslationDrafts(
        (current) => ({
          ...current,

          [attributeId]:
            editableTranslations(
              updated
                .translations,
            ),
        }),
      );
    } catch {
      setMessage(
        "ارتباط برای ذخیره ترجمه‌های ویژگی برقرار نشد.",
      );
    } finally {
      setSavingAttributeTranslationId(
        null,
      );
    }
  }

  async function saveAttributeValueTranslations(
    attributeId:
      number,

    valueId:
      number,
  ) {
    const attribute =
      attributes.find(
        (item) =>
          item.id ===
          attributeId,
      );

    const attributeValue =
      attribute
        ?.values.find(
          (item) =>
            item.id ===
            valueId,
        );

    if (
      !attribute ||
      !attributeValue
    ) {
      return;
    }

    const draft =
      valueTranslationDrafts[
        valueId
      ] ??
      editableTranslations(
        attributeValue
          .translations,
      );

    const en =
      draft.en.trim();

    const fa =
      draft.fa.trim();

    if (
      !en &&
      !fa
    ) {
      setMessage(
        "واردکردن حداقل یک ترجمه ضروری است.",
      );

      return;
    }

    setSavingValueTranslationId(
      valueId,
    );

    setMessage(null);

    try {
      const response =
        await fetch(
          `/api/attribute-values/${valueId}`,
          {
            method:
              "PATCH",

            headers: {
              "Content-Type":
                "application/json",
            },

            body:
              JSON.stringify({
                value: {
                  en:
                    en ||
                    null,

                  fa:
                    fa ||
                    null,
                },
              }),
          },
        );

      const payload =
        await readJson<
          AttributeValueCreateResponse &
          ApiErrorPayload
        >(response);

      if (
        !response.ok ||
        !payload?.data
      ) {
        setMessage(
          apiMessage(
            payload,
            "ذخیره ترجمه‌های مقدار ویژگی انجام نشد.",
          ),
        );

        return;
      }

      const updated =
        normalizeCreatedAttributeValue(
          payload.data,
        );

      setAttributes(
        (current) =>
          current.map(
            (item) =>
              item.id ===
              attributeId
                ? {
                    ...item,

                    values:
                      item.values.map(
                        (value) =>
                          value.id ===
                          valueId
                            ? updated
                            : value,
                      ),
                  }
                : item,
          ),
      );

      setValueTranslationDrafts(
        (current) => ({
          ...current,

          [valueId]:
            editableTranslations(
              updated
                .translations,
            ),
        }),
      );
    } catch {
      setMessage(
        "ارتباط برای ذخیره ترجمه‌های مقدار ویژگی برقرار نشد.",
      );
    } finally {
      setSavingValueTranslationId(
        null,
      );
    }
  }
  function generatedSku(
    ids: number[],
  ): string {
    const base =
      normalizeSkuToken(
        slug ||
        nameEn ||
        "PRODUCT",
      ) ||
      "PRODUCT";

    const suffix =
      ids.map(
        (id) => {
          const item =
            valueLookup.get(
              id,
            );

          return (
            normalizeSkuToken(
              item
                ?.value
                .value ??
              "",
            ) ||
            String(id)
          );
        },
      );

    return [
      base,
      ...suffix,
    ].join("-");
  }

  function generateVariants() {
    setMessage(null);

    const groups =
      attributes
        .map(
          (attribute) => ({
            attribute,

            valueIds:
              selectedValues[
                attribute.id
              ] ?? [],
          }),
        )
        .filter(
          (group) =>
            group.valueIds
              .length > 0,
        );

    if (
      groups.length === 0
    ) {
      setMessage(
        "برای ساخت تنوع، ابتدا دست‌کم یک مقدار ویژگی انتخاب کنید.",
      );

      return;
    }

    let combinations:
      number[][] = [
        [],
      ];

    for (
      const group
      of groups
    ) {
      const next:
        number[][] = [];

      for (
        const combination
        of combinations
      ) {
        for (
          const valueId
          of group.valueIds
        ) {
          next.push([
            ...combination,
            valueId,
          ]);
        }
      }

      combinations =
        next;
    }

    const existing =
      new Map<
        string,
        VariantDraft
      >();

    for (
      const variant
      of variants
    ) {
      existing.set(
        combinationKey(
          variant
            .attributeValueIds,
        ),
        variant,
      );
    }

    setVariants(
      combinations.map(
        (
          ids,
          index,
        ) => {
          const previous =
            existing.get(
              combinationKey(
                ids,
              ),
            );

          if (previous) {
            return {
              ...previous,
              sortOrder:
                index,
            };
          }

          return {
            key:
              makeKey(),

            attributeValueIds:
              ids,

            sku:
              generatedSku(
                ids,
              ),

            price:
              "",

            status:
              "active",

            sortOrder:
              index,

            quantity:
              "0",
          };
        },
      ),
    );
  }

  function addManualVariant() {
    setVariants(
      (current) => [
        ...current,

        {
          key:
            makeKey(),

          attributeValueIds:
            [],

          sku:
            "",

          price:
            "",

          status:
            "active",

          sortOrder:
            current.length,

          quantity:
            "0",
        },
      ],
    );
  }

  function updateVariant(
    key: string,
    patch:
      Partial<VariantDraft>,
  ) {
    setVariants(
      (current) =>
        current.map(
          (variant) =>
            variant.key === key
              ? {
                  ...variant,
                  ...patch,
                }
              : variant,
        ),
    );
  }

  function removeVariant(
    key: string,
  ) {
    setVariants(
      (current) =>
        current
          .filter(
            (variant) =>
              variant.key !==
              key,
          )
          .map(
            (
              variant,
              index,
            ) => ({
              ...variant,
              sortOrder:
                index,
            }),
          ),
    );

    setMedia(
      (current) =>
        current.map(
          (item) =>
            item.variantKey ===
            key
              ? {
                  ...item,

                  scope:
                    "general",

                  variantKey:
                    null,

                  isPrimary:
                    false,
                }
              : item,
        ),
    );
  }

  function variantLabel(
    variant:
      VariantDraft,
  ): string {
    const values =
      variant
        .attributeValueIds
        .map(
          (id) => {
            const item =
              valueLookup.get(
                id,
              );

            if (!item) {
              return null;
            }

            return [
              item
                .attribute
                .name,

              item
                .value
                .value,
            ].join(": ");
          },
        )
        .filter(
          (
            value,
          ): value is string =>
            Boolean(value),
        );

    return values.length > 0
      ? values.join(" / ")
      : "بدون ویژگی";
  }

  function addMediaFiles(
    files:
      FileList | null,
  ) {
    if (!files) {
      return;
    }

    const added =
      Array.from(
        files,
      ).map(
        (
          file,
          index,
        ): MediaDraft => ({
          key:
            makeKey(),

          file,

          previewUrl:
            URL.createObjectURL(
              file,
            ),

          altText:
            "",

          scope:
            "general",

          colorValueId:
            null,

          variantKey:
            null,

          isPrimary:
            media.length === 0 &&
            index === 0,
        }),
      );

    setMedia(
      (current) => [
        ...current,
        ...added,
      ],
    );
  }

  function mediaScopeKey(
    item:
      MediaDraft,
  ): string {
    if (
      item.scope ===
      "color"
    ) {
      return `color:${item.colorValueId ?? ""}`;
    }

    if (
      item.scope ===
      "variant"
    ) {
      return `variant:${item.variantKey ?? ""}`;
    }

    return "general";
  }

  function updateMedia(
    key: string,
    patch:
      Partial<MediaDraft>,
  ) {
    setMedia(
      (current) =>
        current.map(
          (item) =>
            item.key === key
              ? {
                  ...item,
                  ...patch,
                }
              : item,
        ),
    );
  }

  function updateMediaScope(
    key: string,
    scope:
      MediaScope,
  ) {
    setMedia(
      (current) =>
        current.map(
          (item) =>
            item.key === key
              ? {
                  ...item,

                  scope,

                  colorValueId:
                    null,

                  variantKey:
                    null,

                  isPrimary:
                    false,
                }
              : item,
        ),
    );
  }

  function setMediaPrimary(
    key: string,
    checked:
      boolean,
  ) {
    setMedia(
      (current) => {
        const target =
          current.find(
            (item) =>
              item.key ===
              key,
          );

        if (!target) {
          return current;
        }

        const scopeKey =
          mediaScopeKey(
            target,
          );

        return current.map(
          (item) => {
            if (
              item.key === key
            ) {
              return {
                ...item,

                isPrimary:
                  checked,
              };
            }

            if (
              checked &&
              mediaScopeKey(
                item,
              ) ===
              scopeKey
            ) {
              return {
                ...item,

                isPrimary:
                  false,
              };
            }

            return item;
          },
        );
      },
    );
  }

  async function removeMedia(
    key:
      string,
  ) {
    const target =
      media.find(
        (item) =>
          item.key ===
          key,
      );

    if (!target) {
      return;
    }

    if (
      target.serverImageId
    ) {
      if (!initialProduct) {
        setMessage(
          "محصول موردنظر در دسترس نیست.",
        );

        return;
      }

      const confirmed =
        window.confirm(
          "این تصویر برای همیشه حذف شود؟",
        );

      if (!confirmed) {
        return;
      }

      const response =
        await fetch(
          `/api/products/${initialProduct.id}/images/${target.serverImageId}`,
          {
            method:
              "DELETE",
          },
        );

      if (!response.ok) {
        const payload =
          await readJson<
            ApiErrorPayload
          >(response);

        setMessage(
          apiMessage(
            payload,
            "حذف تصویر انجام نشد.",
          ),
        );

        return;
      }
    }

    if (target.file) {
      URL.revokeObjectURL(
        target.previewUrl,
      );
    }

    setMedia(
      (current) =>
        current.filter(
          (item) =>
            item.key !==
            key,
        ),
    );
  }

  function moveMedia(
    index: number,
    direction:
      -1 | 1,
  ) {
    setMedia(
      (current) => {
        const targetIndex =
          index +
          direction;

        if (
          targetIndex < 0 ||
          targetIndex >=
            current.length
        ) {
          return current;
        }

        const copy =
          [...current];

        [
          copy[index],
          copy[targetIndex],
        ] = [
          copy[targetIndex],
          copy[index],
        ];

        return copy;
      },
    );
  }

  function validateForm():
    boolean {
    const nextErrors:
      ApiValidationErrors = {};

    if (!categoryId) {
      nextErrors.category_id = [
        "لطفاً یک دسته‌بندی انتخاب کنید.",
      ];
    }

    if (
      nameEn.trim() ===
      ""
    ) {
      nextErrors["name.en"] = [
        "واردکردن نام انگلیسی ضروری است.",
      ];
    }

    if (
      nameFa.trim() ===
      ""
    ) {
      nextErrors["name.fa"] = [
        "واردکردن نام فارسی ضروری است.",
      ];
    }

    if (
      price.trim() ===
      "" ||
      !Number.isFinite(
        Number(price),
      ) ||
      Number(price) < 0
    ) {
      nextErrors.price = [
        "لطفاً قیمت پایه معتبر وارد کنید.",
      ];
    }

    const skuSet =
      new Set<string>();

    variants.forEach(
      (
        variant,
        index,
      ) => {
        const sku =
          variant.sku.trim();

        if (!sku) {
          nextErrors[
            `variants.${index}.sku`
          ] = [
            "واردکردن SKU ضروری است.",
          ];
        } else {
          const normalized =
            sku.toLowerCase();

          if (
            skuSet.has(
              normalized,
            )
          ) {
            nextErrors[
              `variants.${index}.sku`
            ] = [
              "SKU هر تنوع باید یکتا باشد.",
            ];
          }

          skuSet.add(
            normalized,
          );
        }

        if (
          variant.price !==
            "" &&
          (
            !Number.isFinite(
              Number(
                variant.price,
              ),
            ) ||
            Number(
              variant.price,
            ) < 0
          )
        ) {
          nextErrors[
            `variants.${index}.price`
          ] = [
            "لطفاً قیمت معتبر برای تنوع وارد کنید.",
          ];
        }

        const quantity =
          Number(
            variant.quantity ||
            0,
          );


        if (
          !Number.isInteger(
            quantity,
          ) ||
          quantity < 0
        ) {
          nextErrors[
            `variants.${index}.inventory.quantity`
          ] = [
            "تعداد موجودی باید عدد صحیح نامنفی باشد.",
          ];
        }


      },
    );

    for (
      const item
      of media
    ) {
      if (
        item.scope ===
          "color" &&
        !item.colorValueId
      ) {
        nextErrors.media = [
          "برای هر تصویر رنگی باید یک رنگ انتخاب شود.",
        ];
      }

      if (
        item.scope ===
          "variant" &&
        !item.variantKey
      ) {
        nextErrors.media = [
          "برای هر تصویر تنوع باید یک تنوع انتخاب شود.",
        ];
      }
    }

    if (
      media.length > 0 &&
      !canUpdateProduct
    ) {
      nextErrors.media = [
        "آپلود تصویر به مجوز ویرایش محصول نیاز دارد.",
      ];
    }

    setErrors(
      nextErrors,
    );

    return (
      Object.keys(
        nextErrors,
      ).length === 0
    );
  }

  function buildVariantPayload() {
    return variants.map(
      (variant) => ({
        ...(variant.serverId
          ? {
              id:
                variant.serverId,
            }
          : {}),

        sku:
          variant.sku.trim(),

        price:
          variant.price
            .trim() === ""
            ? null
            : Number(
                variant.price,
              ),

        status:
          variant.status,

        sort_order:
          variant.sortOrder,

        attribute_values:
          variant
            .attributeValueIds,

        inventory: {
          quantity:
            Number(
              variant.quantity ||
              0,
            ),

        },
      }),
    );
  }

  function buildProductPayload(
    createStatus:
      string,
  ) {
    const payload:
      Record<
        string,
        unknown
      > = {
      category_id:
        Number(
          categoryId,
        ),

      name: {
        en:
          nameEn.trim(),

        fa:
          nameFa.trim(),
      },

      brand:
        brand ||
        null,

      price:
        Number(price),

      discount_percentage:
        discountPercentage === ""
          ? null
          : Number(discountPercentage),

      status:
        createStatus,

      is_featured:
        featured,

      collections:
        collectionIds,

      variants:
        buildVariantPayload(),
    };

    if (
      slug.trim() !==
      ""
    ) {
      payload.slug =
        slug.trim();
    }

    if (
      shortEn.trim() !==
        "" ||
      shortFa.trim() !==
        ""
    ) {
      payload.short_description =
        {
          en:
            shortEn.trim() ||
            null,

          fa:
            shortFa.trim() ||
            null,
        };
    }

    if (
      descriptionEn
        .trim() !==
        "" ||
      descriptionFa
        .trim() !==
        ""
    ) {
      payload.description =
        {
          en:
            descriptionEn
              .trim() ||
            null,

          fa:
            descriptionFa
              .trim() ||
            null,
        };
    }

    return payload;
  }

  async function createProduct():
    Promise<CreatedProductState> {
    const createStatus =
      media.length > 0
        ? "draft"
        : status;

    const response =
      await fetch(
        "/api/products",
        {
          method:
            "POST",

          headers: {
            "Content-Type":
              "application/json",
          },

          body:
            JSON.stringify(
              buildProductPayload(
                createStatus,
              ),
            ),
        },
      );

    const payload =
      await readJson<
        ProductResponse &
        ApiErrorPayload
      >(response);

    if (
      !response.ok ||
      !payload?.data
    ) {
      setErrors(
        payload?.errors ??
        {},
      );

      throw new Error(
        apiMessage(
          payload,
          "ایجاد محصول انجام نشد.",
        ),
      );
    }

    const variantsBySku =
      new Map<
        string,
        number
      >();

    for (
      const variant
      of payload.data
        .variants ?? []
    ) {
      variantsBySku.set(
        variant.sku,
        variant.id,
      );
    }

    const variantIdsByKey:
      Record<string, number> =
        {};

    for (
      const variant
      of variants
    ) {
      const serverId =
        variantsBySku.get(
          variant.sku.trim(),
        );

      if (!serverId) {
        throw new Error(
          `تنوع ایجادشده با SKU «${variant.sku}» از سرور دریافت نشد.`,
        );
      }

      variantIdsByKey[
        variant.key
      ] =
        serverId;
    }

    const created: CreatedProductState = {
      productId:
        payload.data.id,

      variantIdsByKey,

      finalStatus:
        status,
    };

    setCreatedProduct(
      created,
    );

    return created;
  }

  async function updateProduct():
    Promise<CreatedProductState> {
    if (!initialProduct) {
      throw new Error(
        "اطلاعات محصول برای ویرایش در دسترس نیست.",
      );
    }

    const updatePayload =
      buildProductPayload(
        status,
      );

    if (
      shortEn.trim() === "" &&
      shortFa.trim() === ""
    ) {
      updatePayload.short_description =
        null;
    }

    if (
      descriptionEn.trim() === "" &&
      descriptionFa.trim() === ""
    ) {
      updatePayload.description =
        null;
    }

    const response =
      await fetch(
        `/api/products/${initialProduct.id}`,
        {
          method:
            "PATCH",

          headers: {
            "Content-Type":
              "application/json",
          },

          body:
            JSON.stringify(
              updatePayload,
            ),
        },
      );

    const payload =
      await readJson<
        ProductResponse &
        ApiErrorPayload
      >(response);

    if (
      !response.ok ||
      !payload?.data
    ) {
      setErrors(
        payload?.errors ??
        {},
      );

      throw new Error(
        apiMessage(
          payload,
          "ویرایش محصول انجام نشد.",
        ),
      );
    }

    const variantsBySku =
      new Map<
        string,
        number
      >();

    for (
      const variant
      of payload.data
        .variants ?? []
    ) {
      variantsBySku.set(
        variant.sku,
        variant.id,
      );
    }

    const variantIdsByKey:
      Record<string, number> =
        {};

    for (
      const variant
      of variants
    ) {
      const serverId =
        variant.serverId ??
        variantsBySku.get(
          variant.sku.trim(),
        );

      if (!serverId) {
        throw new Error(
          `تنوع ویرایش‌شده با SKU «${variant.sku}» از سرور دریافت نشد.`,
        );
      }

      variantIdsByKey[
        variant.key
      ] =
        serverId;
    }

    return {
      productId:
        payload.data.id,

      variantIdsByKey,

      finalStatus:
        status,
    };
  }
  async function uploadMedia(
    created:
      CreatedProductState,
  ) {
    let workingMedia =
      media.map(
        (item) => ({
          ...item,
        }),
      );

    /*
     * Upload sequentially.
     *
     * Because backend sort_order is scoped,
     * this preserves the authored order
     * inside each General / Color / Variant
     * gallery.
     */
    for (
      let index = 0;
      index <
      workingMedia.length;
      index += 1
    ) {
      const item =
        workingMedia[index];

      let imageId =
        item.serverImageId;

      if (!imageId) {
        const body =
          new FormData();

        if (!item.file) {
          throw new Error(
            "برای تصویر جدید فایلی انتخاب نشده است.",
          );
        }

        body.append(
          "images[]",
          item.file,
        );

        if (
          item.scope ===
            "color" &&
          item.colorValueId
        ) {
          body.append(
            "attribute_value_id",
            String(
              item.colorValueId,
            ),
          );
        }

        if (
          item.scope ===
            "variant" &&
          item.variantKey
        ) {
          const variantId =
            created
              .variantIdsByKey[
                item.variantKey
              ];

          if (!variantId) {
            throw new Error(
              `برای تصویر «${mediaDisplayName(item)}» تنوع معتبری در سرور پیدا نشد.`,
            );
          }

          body.append(
            "product_variant_id",
            String(
              variantId,
            ),
          );
        }

        const response =
          await fetch(
            `/api/products/${created.productId}/images`,
            {
              method:
                "POST",

              body,
            },
          );

        const payload =
          await readJson<
            MediaUploadResponse &
            ApiErrorPayload
          >(response);

        if (
          !response.ok ||
          !payload?.data?.[0]
        ) {
          throw new Error(
            apiMessage(
              payload,
              `آپلود تصویر «${mediaDisplayName(item)}» انجام نشد.`,
            ),
          );
        }

        imageId =
          payload.data[0].id;

        workingMedia[index] = {
          ...item,
          serverImageId:
            imageId,
        };

        setMedia(
          workingMedia.map(
            (entry) => ({
              ...entry,
            }),
          ),
        );
      }

      if (
        workingMedia[index]
          .complete
      ) {
        continue;
      }

      const metadata:
        Record<
          string,
          unknown
        > = {
        alt_text:
          item.altText
            .trim() ||
          null,

        /*
         * Always send this value.
         * This also allows an existing
         * Primary image to be unset.
         */
        is_primary:
          item.isPrimary,

        /*
         * Media array order is the
         * canonical product image order.
         */
        sort_order:
          index,

        product_variant_id:
          null,

        attribute_value_id:
          null,
      };

      if (
        item.scope ===
        "color"
      ) {
        metadata.attribute_value_id =
          item.colorValueId;

        metadata.product_variant_id =
          null;
      }

      if (
        item.scope ===
        "variant"
      ) {
        const serverVariantId =
          item.variantKey
            ? created
                .variantIdsByKey[
                  item.variantKey
                ]
            : undefined;

        if (!serverVariantId) {
          throw new Error(
            `برای تصویر «${mediaDisplayName(item)}» تنوع معتبری در سرور پیدا نشد.`,
          );
        }

        metadata.product_variant_id =
          serverVariantId;

        metadata.attribute_value_id =
          null;
      }

      const metadataResponse =
        await fetch(
          `/api/products/${created.productId}/images/${imageId}`,
          {
            method:
              "PATCH",

            headers: {
              "Content-Type":
                "application/json",
            },

            body:
              JSON.stringify(
                metadata,
              ),
          },
        );

      const metadataPayload =
        await readJson<
          ApiErrorPayload
        >(
          metadataResponse,
        );

      if (
        !metadataResponse.ok
      ) {
        throw new Error(
          apiMessage(
            metadataPayload,
            `ذخیره اطلاعات تصویر «${mediaDisplayName(item)}» انجام نشد.`,
          ),
        );
      }

      workingMedia[index] = {
        ...workingMedia[
          index
        ],
        complete:
          true,
      };

      setMedia(
        workingMedia.map(
          (entry) => ({
            ...entry,
          }),
        ),
      );
    }
  }

  async function finalizeProduct(
    created:
      CreatedProductState,
  ) {
    if (
      created.finalStatus ===
      "draft"
    ) {
      return;
    }

    const response =
      await fetch(
        `/api/products/${created.productId}`,
        {
          method:
            "PATCH",

          headers: {
            "Content-Type":
              "application/json",
          },

          body:
            JSON.stringify({
              status:
                created
                  .finalStatus,
            }),
        },
      );

    const payload =
      await readJson<
        ApiErrorPayload
      >(response);

    if (!response.ok) {
      throw new Error(
        apiMessage(
          payload,
          "محصول ایجاد شد، اما تنظیم وضعیت نهایی آن ناموفق بود.",
        ),
      );
    }
  }

  async function handleSubmit(
    event:
      FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    if (submitting) {
      return;
    }

    setErrors({});
    setMessage(null);

    if (!createdProduct) {
      if (!validateForm()) {
        return;
      }
    }

    setSubmitting(true);

    try {
      const created =
        isEditMode
          ? await updateProduct()
          : (
              createdProduct ??
              await createProduct()
            );

      if (
        media.length > 0
      ) {
        await uploadMedia(
          created,
        );

        if (!isEditMode) {
          await finalizeProduct(
            created,
          );
        }
      }

      router.push(
        "/products",
      );

      router.refresh();
    } catch (error) {
      setMessage(
        error instanceof Error
          ? error.message
          : "فرآیند ذخیره محصول کامل نشد.",
      );
    } finally {
      setSubmitting(false);
    }
  }

  const categoryError =
    firstError(
      errors,
      "category_id",
    );

  const nameEnError =
    firstError(
      errors,
      "name.en",
    );

  const nameFaError =
    firstError(
      errors,
      "name.fa",
    );

  const slugError =
    firstError(
      errors,
      "slug",
    );

  const brandError =
    firstError(
      errors,
      "brand",
    );

  const priceError =
    firstError(
      errors,
      "price",
    );

  const discountPercentageError =
    firstError(
      errors,
      "discount_percentage",
    );

  const mediaError =
    firstError(
      errors,
      "media",
    );

  return (
    <form
      className={
        styles.form
      }
      dir="rtl"
      lang="fa"
      onSubmit={
        handleSubmit
      }
    >
      {message ? (
        <div
          className={
            styles.formMessage
          }
          role="alert"
        >
          {message}

          {createdProduct ? (
            <span>
              {" "}
              شناسه محصول{" "}
              {
                createdProduct
                  .productId
              }
              {" "}
              به‌صورت پیش‌نویس ایجاد شده است. برای تکمیل تصاویر دوباره ذخیره کنید.
            </span>
          ) : null}
        </div>
      ) : null}

      <nav
        className={
          styles.builderNav
        }
        dir="rtl" lang="fa" aria-label="بخش‌های فرم محصول"
      >
        <a href="#product-basics">
          <b>۰۱</b>
          <span>اطلاعات پایه</span>
        </a>

        <a href="#product-copy">
          <b>۰۲</b>
          <span>محتوای محصول</span>
          <em>انگلیسی / فارسی</em>
        </a>

        <a href="#product-collections">
          <b>۰۳</b>
          <span>کالکشن‌ها</span>
          <em>{collectionIds.length.toLocaleString("fa-IR")}</em>
        </a>

        <a href="#product-attributes">
          <b>۰۴</b>
          <span>ویژگی‌ها</span>
          <em>
            {
              Object.values(
                selectedValues,
              ).reduce(
                (
                  total,
                  ids,
                ) =>
                  total +
                  ids.length,
                0,
              )
            }
          </em>
        </a>

        <a href="#product-variants">
          <b>۰۵</b>
          <span>تنوع‌ها</span>
          <em>{variants.length.toLocaleString("fa-IR")}</em>
        </a>

        <a href="#product-media">
          <b>۰۶</b>
          <span>تصاویر</span>
          <em>{media.length.toLocaleString("fa-IR")}</em>
        </a>
      </nav>
      <section dir="rtl" lang="fa"
        className={
          styles.section
        }
      >
        <div
          className={
            styles.sectionHead
          }
        >
          <div>
            <p
              className={
                styles.sectionEyebrow
              }
            >
              ۰۱
            </p>

            <h2 id="product-basics">
              اطلاعات پایه محصول
            </h2>
          </div>

          <p>
            اطلاعات اصلی محصول، قیمت‌گذاری و وضعیت انتشار را مشخص کنید.
          </p>
        </div>

        <div
          className={
            styles.grid
          }
        >
          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="name-en"
            >
              نام انگلیسی
            </label>

            <input
              id="name-en"
              dir="ltr"
              lang="en"
              value={nameEn}
              onChange={
                (event) =>
                  setNameEn(
                    event.target
                      .value,
                  )
              }
              required
            />

            {nameEnError ? (
              <span
                className={
                  styles.error
                }
              >
                {nameEnError}
              </span>
            ) : null}
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="name-fa"
            >
              نام فارسی
            </label>

            <input
              id="name-fa"
              dir="rtl"
              value={nameFa}
              onChange={
                (event) =>
                  setNameFa(
                    event.target
                      .value,
                  )
              }
              required
            />

            {nameFaError ? (
              <span
                className={
                  styles.error
                }
              >
                {nameFaError}
              </span>
            ) : null}
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="slug"
            >
              اسلاگ
            </label>

            <input
              id="slug"
              dir="ltr"
              value={slug}
              onChange={
                (event) =>
                  setSlug(
                    event.target
                      .value,
                  )
              }
              placeholder="در صورت خالی بودن، خودکار ایجاد می‌شود"
            />

            {slugError ? (
              <span
                className={
                  styles.error
                }
              >
                {slugError}
              </span>
            ) : null}
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="category"
            >
              دسته‌بندی
            </label>

            <SoftSelect
              id="category"
              value={categoryId}
              ariaLabel="دسته‌بندی"
              onChange={
                (value) =>
                  setCategoryId(
                    value,
                  )
              }
              options={[
                {
                  value: "",
                  label:
                    "انتخاب دسته‌بندی",
                },

                ...categories.map(
                  (category) => ({
                    value:
                      String(
                        category.id,
                      ),

                    label:
                      localizedText(
                        category.name,
                      ),
                  }),
                ),
              ]}
            />

            {categoryError ? (
              <span
                className={
                  styles.error
                }
              >
                {categoryError}
              </span>
            ) : null}
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="brand"
            >
              برند
            </label>

            <SoftSelect
              id="brand"
              value={brand}
              ariaLabel="برند"
              onChange={
                (value) =>
                  setBrand(
                    value,
                  )
              }
              options={[
                {
                  value: "",
                  label:
                    "بدون برند",
                },

                ...brands
                  .filter(
                    (item) =>
                      item.is_active,
                  )
                  .map(
                    (item) => ({
                      value:
                        item.name,

                      label:
                        item.name,
                    }),
                  ),
              ]}
            />

            {brandError ? (
              <span
                className={
                  styles.error
                }
              >
                {brandError}
              </span>
            ) : null}
          </div>

          <div
            className={
              styles.inlineField
            }
          >
            <input
              value={
                newBrandName
              }
              onChange={
                (event) =>
                  setNewBrandName(
                    event.target
                      .value,
                  )
              }
              placeholder="نام برند جدید"
            />

            <button
              type="button"
              className="admin-button"
              disabled={
                addingBrand ||
                newBrandName
                  .trim() ===
                  ""
              }
              onClick={
                addBrand
              }
            >
              {addingBrand
                ? "در حال افزودن..."
                : "افزودن برند"}
            </button>
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="price"
            >
              قیمت پایه
            </label>

            <input
              id="price"
              type="number"
              min="0"
              step="0.01"
              value={price}
              onChange={
                (event) =>
                  setPrice(
                    event.target
                      .value,
                  )
              }
              required
            />

            {priceError ? (
              <span
                className={
                  styles.error
                }
              >
                {priceError}
              </span>
            ) : null}
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="discount-percentage"
            >
              تخفیف محصول (درصد)
            </label>

            <input
              id="discount-percentage"
              type="number"
              min="1"
              max="100"
              step="1"
              value={discountPercentage}
              onChange={
                (event) =>
                  setDiscountPercentage(
                    event.target.value,
                  )
              }
              placeholder="بدون تخفیف"
            />

            {discountPercentageError ? (
              <span
                className={
                  styles.error
                }
              >
                {discountPercentageError}
              </span>
            ) : null}
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="status"
            >
              وضعیت انتشار
            </label>

            <SoftSelect
              id="status"
              value={status}
              ariaLabel="وضعیت محصول"
              onChange={
                (value) =>
                  setStatus(
                    value,
                  )
              }
              options={[
                {
                  value:
                    "draft",
                  label:
                    "پیش‌نویس",
                },
                {
                  value:
                    "active",
                  label:
                    "فعال",
                },
                {
                  value:
                    "inactive",
                  label:
                    "غیرفعال",
                },
              ]}
            />
          </div>

          <label
            className={
              styles.checkboxField
            }
          >
            <input
              type="checkbox"
              checked={
                featured
              }
              onChange={
                (event) =>
                  setFeatured(
                    event.target
                      .checked,
                  )
              }
            />

            <span>
              محصول ویژه
            </span>
          </label>
        </div>
      </section>

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.sectionHead
          }
        >
          <div>
            <p
              className={
                styles.sectionEyebrow
              }
            >
              ۰۲
            </p>

            <h2 id="product-copy">
              محتوای محصول
            </h2>
          </div>

          <p>
            توضیحات کوتاه و کامل محصول را به دو زبان ثبت کنید.
          </p>
        </div>

        <div
          className={
            styles.grid
          }
        >
          <div
            className={
              styles.field
            }
          >
            <label>
              توضیح کوتاه انگلیسی
            </label>

            <textarea
              rows={4}
              dir="ltr"
              lang="en"
              value={shortEn}
              onChange={
                (event) =>
                  setShortEn(
                    event.target
                      .value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label>
              توضیح کوتاه فارسی
            </label>

            <textarea
              rows={4}
              dir="rtl"
              value={shortFa}
              onChange={
                (event) =>
                  setShortFa(
                    event.target
                      .value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label>
              توضیحات انگلیسی
            </label>

            <textarea
              rows={8}
              dir="ltr"
              lang="en"
              value={
                descriptionEn
              }
              onChange={
                (event) =>
                  setDescriptionEn(
                    event.target
                      .value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label>
              توضیحات فارسی
            </label>

            <textarea
              rows={8}
              dir="rtl"
              value={
                descriptionFa
              }
              onChange={
                (event) =>
                  setDescriptionFa(
                    event.target
                      .value,
                  )
              }
            />
          </div>
        </div>
      </section>

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.sectionHead
          }
        >
          <div>
            <p
              className={
                styles.sectionEyebrow
              }
            >
              ۰۳
            </p>

            <h2 id="product-collections">
              کالکشن‌ها
            </h2>
          </div>

          <p>
            کالکشن‌هایی را انتخاب کنید که این محصول باید در آن‌ها نمایش داده شود.
          </p>
        </div>

        {collections.length >
        0 ? (
          <div
            className={
              styles.optionGrid
            }
          >
            {collections.map(
              (collection) => {
                const checked =
                  collectionIds
                    .includes(
                      collection.id,
                    );

                return (
                  <label
                    key={
                      collection.id
                    }
                    className={
                      checked
                        ? `${styles.optionCard} ${styles.optionCardActive}`
                        : styles.optionCard
                    }
                  >
                    <input
                      type="checkbox"
                      checked={
                        checked
                      }
                      onChange={
                        () =>
                          toggleCollection(
                            collection.id,
                          )
                      }
                    />

                    <span>
                      {localizedText(
                        collection.name,
                      )}
                    </span>

                    <small>
                      {collection
                        .is_active
                        ? "فعال"
                        : "غیرفعال"}
                    </small>
                  </label>
                );
              },
            )}
          </div>
        ) : (
          <p
            className={
              styles.emptyNote
            }
          >
            در حال حاضر کالکشنی موجود نیست.
          </p>
        )}
      </section>

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.sectionHead
          }
        >
          <div>
            <p
              className={
                styles.sectionEyebrow
              }
            >
              ۰۴
            </p>

            <h2 id="product-attributes">
              ویژگی‌های محصول
            </h2>
          </div>

          <p>
            ویژگی‌ها و مقادیر مربوط به این محصول را انتخاب کنید تا تنوع‌های محصول ساخته شوند.
          </p>
        </div>

        <div
          className={
            styles.attributeCreate
          }
        >
          <input
            value={
              newAttributeNameEn
            }
            onChange={
              (event) =>
                setNewAttributeNameEn(
                  event.target
                    .value,
                )
            }
            lang="en"
            dir="ltr"
            placeholder="نام ویژگی — انگلیسی"
          />

          <input
            value={
              newAttributeNameFa
            }
            onChange={
              (event) =>
                setNewAttributeNameFa(
                  event.target
                    .value,
                )
            }
            lang="fa"
            dir="rtl"
            placeholder="نام ویژگی — فارسی"
          />

          <input
            value={
              newAttributeSlug
            }
            onChange={
              (event) =>
                setNewAttributeSlug(
                  event.target
                    .value,
                )
            }
            dir="ltr"
            placeholder="اسلاگ (اختیاری)"
          />

          <SoftSelect
            value={
              newAttributeType
            }
            ariaLabel="نوع ویژگی"
            onChange={
              (value) =>
                setNewAttributeType(
                  value as
                    AdminAttributeType,
                )
            }
            options={[
              {
                value: "select",
                label: "انتخابی",
              },
              {
                value: "color",
                label: "رنگ",
              },
              {
                value: "text",
                label: "متنی",
              },
              {
                value: "number",
                label: "عددی",
              },
            ]}
          />

          <button
            type="button"
            className="admin-button"
            disabled={
              addingAttribute ||
              (
                newAttributeNameEn
                  .trim() ===
                  "" &&
                newAttributeNameFa
                  .trim() ===
                  ""
              )
            }
            onClick={
              addAttribute
            }
          >
            {addingAttribute
              ? "در حال افزودن..."
              : "افزودن ویژگی"}
          </button>
        </div>

        <div
          className={
            styles.attributeList
          }
        >
          {attributes.map(
            (attribute) => (
              <div
                key={
                  attribute.id
                }
                className={
                  styles.attributeBlock
                }
              >
                <div
                  className={
                    styles.attributeHeader
                  }
                >
                  <div>
                    <strong>
                      {attribute.translations?.fa?.trim() || attribute.name}
                    </strong>

                    <small>
                      {({ select: "انتخابی", color: "رنگ", text: "متنی", number: "عددی" } as Record<string, string>)[attribute.type] ?? attribute.type}
                    </small>
                  </div>

                  <span>
                    {
                      (
                        selectedValues[
                          attribute.id
                        ] ?? []
                      ).length
                    }{" "}
                    انتخاب‌شده
                  </span>
                </div>

                <div
                  className={
                    styles.translationEditor
                  }
                >
                  <div
                    className={
                      styles.translationEditorHead
                    }
                  >
                    <strong>
                      ترجمه‌های ویژگی
                    </strong>

                    <span>
                      <bdi dir="ltr">
                      {attribute.slug}
                      </bdi>
                    </span>
                  </div>

                  <div
                    className={
                      styles.translationEditorGrid
                    }
                  >
                    <input
                      lang="en"
                      dir="ltr"
                      value={
                        (
                          attributeTranslationDrafts[
                            attribute.id
                          ] ??
                          editableTranslations(
                            attribute
                              .translations,
                          )
                        ).en
                      }
                      onChange={
                        (event) =>
                          setAttributeTranslationDrafts(
                            (
                              current,
                            ) => ({
                              ...current,

                              [attribute.id]:
                                {
                                  ...(
                                    current[
                                      attribute.id
                                    ] ??
                                    editableTranslations(
                                      attribute
                                        .translations,
                                    )
                                  ),

                                  en:
                                    event
                                      .target
                                      .value,
                                },
                            }),
                          )
                      }
                      placeholder="انگلیسی"
                    />

                    <input
                      lang="fa"
                      dir="rtl"
                      value={
                        (
                          attributeTranslationDrafts[
                            attribute.id
                          ] ??
                          editableTranslations(
                            attribute
                              .translations,
                          )
                        ).fa
                      }
                      onChange={
                        (event) =>
                          setAttributeTranslationDrafts(
                            (
                              current,
                            ) => ({
                              ...current,

                              [attribute.id]:
                                {
                                  ...(
                                    current[
                                      attribute.id
                                    ] ??
                                    editableTranslations(
                                      attribute
                                        .translations,
                                    )
                                  ),

                                  fa:
                                    event
                                      .target
                                      .value,
                                },
                            }),
                          )
                      }
                      placeholder="فارسی"
                    />

                    <button
                      type="button"
                      className="admin-button"
                      disabled={
                        savingAttributeTranslationId !==
                        null
                      }
                      onClick={
                        () =>
                          saveAttributeTranslations(
                            attribute.id,
                          )
                      }
                    >
                      {savingAttributeTranslationId ===
                      attribute.id
                        ? "در حال ذخیره..."
                        : "ذخیره ترجمه‌ها"}
                    </button>
                  </div>
                </div>
<div
                  className={
                    styles.valueGrid
                  }
                >
                  {attribute.values.map(
                    (value) => {
                      const checked =
                        (
                          selectedValues[
                            attribute.id
                          ] ?? []
                        ).includes(
                          value.id,
                        );

                      return (
                        <label
                          key={
                            value.id
                          }
                          className={
                            checked
                              ? `${styles.valueOption} ${styles.valueOptionActive}`
                              : styles.valueOption
                          }
                        >
                          <input
                            type="checkbox"
                            checked={
                              checked
                            }
                            onChange={
                              () =>
                                toggleAttributeValue(
                                  attribute.id,
                                  value.id,
                                )
                            }
                          />

                          <span>
                            {value.translations?.fa?.trim() || value.value}
                          </span>
                        </label>
                      );
                    },
                  )}
                </div>

                {attribute.values.length >
                0 ? (
                  <details
                    className={
                      styles.translationGroup
                    }
                  >
                    <summary
                      className={
                        styles.translationSummary
                      }
                    >
                      <div>
                        <strong>
                          ترجمه‌های مقادیر
                        </strong>

                        <span>
                          {
                            attribute
                              .values
                              .length
                          }{" "}
                          مقدار
                        </span>
                      </div>

                      <span
                        className={
                          styles.translationChevron
                        }
                        aria-hidden="true"
                      >
                        <svg
                          viewBox="0 0 20 20"
                        >
                          <path
                            d="M5 7.5L10 12.5L15 7.5"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="1.8"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                          />
                        </svg>
                      </span>
                    </summary>

                    <div
                      className={
                        styles.valueTranslationEditors
                      }
                    >
                      {attribute.values.map(
                        (value) => {
                          const draft =
                            valueTranslationDrafts[
                              value.id
                            ] ??
                            editableTranslations(
                              value
                                .translations,
                            );

                          return (
                            <div
                              key={
                                `translation-${value.id}`
                              }
                              className={
                                styles.valueTranslationRow
                              }
                            >
                              <div
                                className={
                                  styles.translationValueLabel
                                }
                              >
                                <strong
                                  dir={
                                    value.translations?.fa?.trim()
                                      ? "rtl"
                                      : "ltr"
                                  }
                                >
                                  {
                                    value.translations?.fa?.trim() ||
                                    value.translations?.en?.trim() ||
                                    value.value
                                  }
                                </strong>
                              </div>

                              <input
                                lang="en"
                                dir="ltr"
                                value={
                                  draft.en
                                }
                                placeholder="انگلیسی"
                                onChange={
                                  (
                                    event,
                                  ) =>
                                    setValueTranslationDrafts(
                                      (
                                        current,
                                      ) => ({
                                        ...current,

                                        [value.id]:
                                          {
                                            ...(
                                              current[
                                                value.id
                                              ] ??
                                              editableTranslations(
                                                value
                                                  .translations,
                                              )
                                            ),

                                            en:
                                              event
                                                .target
                                                .value,
                                          },
                                      }),
                                    )
                                }
                              />

                              <input
                                lang="fa"
                                dir="rtl"
                                value={
                                  draft.fa
                                }
                                placeholder="فارسی"
                                onChange={
                                  (
                                    event,
                                  ) =>
                                    setValueTranslationDrafts(
                                      (
                                        current,
                                      ) => ({
                                        ...current,

                                        [value.id]:
                                          {
                                            ...(
                                              current[
                                                value.id
                                              ] ??
                                              editableTranslations(
                                                value
                                                  .translations,
                                              )
                                            ),

                                            fa:
                                              event
                                                .target
                                                .value,
                                          },
                                      }),
                                    )
                                }
                              />

                              <button
                                type="button"
                                className="admin-button"
                                disabled={
                                  savingValueTranslationId !==
                                  null
                                }
                                onClick={
                                  () =>
                                    saveAttributeValueTranslations(
                                      attribute.id,
                                      value.id,
                                    )
                                }
                              >
                                {savingValueTranslationId ===
                                value.id
                                  ? "در حال ذخیره..."
                                  : "ذخیره"}
                              </button>
                            </div>
                          );
                        },
                      )}
                    </div>
                  </details>
                ) : null}
                <div
                  className={
                    `${styles.inlineField} ${styles.localizedInlineField}`
                  }
                >
                  <input
                    value={
                      newValueTextEn[
                        attribute.id
                      ] ?? ""
                    }
                    onChange={
                      (event) =>
                        setNewValueTextEn(
                          (
                            current,
                          ) => ({
                            ...current,

                            [attribute.id]:
                              event
                                .target
                                .value,
                          }),
                        )
                    }
                    lang="en"
                    dir="ltr"
                    placeholder={`افزودن مقدار انگلیسی برای ${attribute.translations?.fa?.trim() || attribute.name}`}
                  />

                  <input
                    value={
                      newValueTextFa[
                        attribute.id
                      ] ?? ""
                    }
                    onChange={
                      (event) =>
                        setNewValueTextFa(
                          (
                            current,
                          ) => ({
                            ...current,

                            [attribute.id]:
                              event
                                .target
                                .value,
                          }),
                        )
                    }
                    lang="fa"
                    dir="rtl"
                    placeholder="مقدار فارسی"
                  />

                  <button
                    type="button"
                    className="admin-button"
                    disabled={
                      addingValueId !==
                        null ||
                      (
                        (
                          newValueTextEn[
                            attribute.id
                          ] ?? ""
                        )
                          .trim() ===
                          "" &&
                        (
                          newValueTextFa[
                            attribute.id
                          ] ?? ""
                        )
                          .trim() ===
                          ""
                      )
                    }
                    onClick={
                      () =>
                        addAttributeValue(
                          attribute.id,
                        )
                    }
                  >
                    {addingValueId ===
                    attribute.id
                      ? "در حال افزودن..."
                      : "افزودن مقدار"}
                  </button>
                </div>
              </div>
            ),
          )}
        </div>
      </section>

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.sectionHead
          }
        >
          <div>
            <p
              className={
                styles.sectionEyebrow
              }
            >
              ۰۵
            </p>

            <h2 id="product-variants">
              تنوع‌ها و موجودی
            </h2>
          </div>

          <p>
            از مقادیر ویژگی‌های انتخاب‌شده تنوع بسازید یا یک تنوع مستقل به‌صورت دستی اضافه کنید.
          </p>
        </div>

        <div
          className={
            styles.toolbar
          }
        >
          <button
            type="button"
            className="admin-button admin-button-primary"
            onClick={
              generateVariants
            }
          >
            ساخت تنوع‌های ترکیبی
          </button>

          <button
            type="button"
            className="admin-button"
            onClick={
              addManualVariant
            }
          >
            افزودن تنوع دستی
          </button>

          <span>
            {variants.length.toLocaleString("fa-IR")}{" "}
            تنوع
          </span>
        </div>

        <div
          className={
            styles.variantList
          }
        >
          {variants.map(
            (
              variant,
              index,
            ) => {
              const skuError =
                firstError(
                  errors,
                  `variants.${index}.sku`,
                );

              const variantPriceError =
                firstError(
                  errors,
                  `variants.${index}.price`,
                );

              const quantityError =
                firstError(
                  errors,
                  `variants.${index}.inventory.quantity`,
                );


              return (
                <div
                  key={
                    variant.key
                  }
                  className={
                    styles.variantCard
                  }
                >
                  <div
                    className={
                      styles.variantTop
                    }
                  >
                    <div>
                      <strong>
                        تنوع شماره{" "}
                        {(index + 1).toLocaleString("fa-IR")}
                      </strong>

                      <span>
                        {variantLabel(
                          variant,
                        )}
                      </span>
                    </div>

                    <button
                      type="button"
                      className="admin-button"
                      onClick={
                        () =>
                          removeVariant(
                            variant.key,
                          )
                      }
                    >
                      حذف
</button>
                  </div>

                  <div
                    className={
                      styles.variantGrid
                    }
                  >
                    <div
                      className={
                        styles.field
                      }
                    >
                      <label>
                        SKU
                      </label>

                      <input
                        dir="ltr"
                        lang="en"
                        value={
                          variant.sku
                        }
                        onChange={
                          (event) =>
                            updateVariant(
                              variant.key,
                              {
                                sku:
                                  event
                                    .target
                                    .value,
                              },
                            )
                        }
                      />

                      {skuError ? (
                        <span
                          className={
                            styles.error
                          }
                        >
                          {skuError}
                        </span>
                      ) : null}
                    </div>

                    <div
                      className={
                        styles.field
                      }
                    >
                      <label>
                        قیمت اختصاصی تنوع
                      </label>

                      <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={
                          variant.price
                        }
                        placeholder="استفاده از قیمت پایه"
                        onChange={
                          (event) =>
                            updateVariant(
                              variant.key,
                              {
                                price:
                                  event
                                    .target
                                    .value,
                              },
                            )
                        }
                      />

                      {variantPriceError ? (
                        <span
                          className={
                            styles.error
                          }
                        >
                          {variantPriceError}
                        </span>
                      ) : null}
                    </div>

                    <div
                      className={
                        styles.field
                      }
                    >
                      <label>
                        وضعیت تنوع
                      </label>

                      <SoftSelect
                        value={
                          variant.status
                        }
                        ariaLabel="وضعیت تنوع"
                        onChange={
                          (value) =>
                            updateVariant(
                              variant.key,
                              {
                                status:
                                  value as
                                    VariantStatus,
                              },
                            )
                        }
                        options={[
                          {
                            value:
                              "active",
                            label:
                              "فعال",
                          },
                          {
                            value:
                              "made_to_order",
                            label:
                              "ساخت پس از سفارش",
                          },
                          {
                            value:
                              "sold",
                            label:
                              "فروخته‌شده",
                          },
                          {
                            value:
                              "inactive",
                            label:
                              "غیرفعال",
                          },
                        ]}
                      />
                    </div>

                    <div
                      className={
                        styles.field
                      }
                    >
                      <label>
                        تعداد موجودی
                      </label>

                      <input
                        type="number"
                        min="0"
                        step="1"
                        value={
                          variant.quantity
                        }
                        onChange={
                          (event) =>
                            updateVariant(
                              variant.key,
                              {
                                quantity:
                                  event
                                    .target
                                    .value,
                              },
                            )
                        }
                      />

                      {quantityError ? (
                        <span
                          className={
                            styles.error
                          }
                        >
                          {quantityError}
                        </span>
                      ) : null}
                    </div>


                  </div>
                </div>
              );
            },
          )}

          {variants.length ===
          0 ? (
            <p
              className={
                styles.emptyNote
              }
            >
              هنوز تنوعی اضافه نشده است. برای محصولات بدون تنوع، این بخش می‌تواند خالی بماند.
            </p>
          ) : null}
        </div>
      </section>

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.sectionHead
          }
        >
          <div>
            <p
              className={
                styles.sectionEyebrow
              }
            >
              ۰۶
            </p>

            <h2 id="product-media">
              تصاویر محصول
            </h2>
          </div>

          <p>
            تصاویر عمومی، گالری و ترتیب کارت محصول را مشخص می‌کنند. هر تصویر می‌تواند به رنگ یا تنوع مشخصی نیز اختصاص یابد.
          </p>
        </div>

        {!canUpdateProduct ? (
          <p
            className={
              styles.permissionNote
            }
          >
            حساب شما مجوز ایجاد محصول دارد، اما مجوز ویرایش محصول را ندارد؛ بنابراین آپلود تصویر ممکن نیست.
          </p>
        ) : null}

        <div
          className={
            styles.uploadBox
          }
        >
          <input
            id="product-media-file-input"
            className={styles.mediaFileInput}
            aria-label="انتخاب تصاویر محصول"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            multiple
            disabled={
              !canUpdateProduct
            }
            onChange={
              (event) => {
                addMediaFiles(
                  event.target
                    .files,
                );

                event.target.value =
                  "";
              }
            }
          />
          <label
            htmlFor="product-media-file-input"
            className={styles.mediaPickerLabel}
            aria-disabled={!canUpdateProduct}
          >
            انتخاب تصاویر
          </label>

          <span>
            فرمت‌های JPG، PNG یا WEBP مجاز هستند. می‌توانید چند تصویر انتخاب کنید.
          </span>
        </div>

        {mediaError ? (
          <p
            className={
              styles.error
            }
          >
            {mediaError}
          </p>
        ) : null}

        <div
          className={
            styles.mediaList
          }
        >
          {media.map(
            (
              item,
              index,
            ) => (
              <div
                key={
                  item.key
                }
                className={
                  styles.mediaCard
                }
              >
                <div
                  className={
                    styles.mediaPreview
                  }
                >
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={
                      item.previewUrl
                    }
                    alt=""
                  />

                  {productCardMediaLabel(
                    media,
                    item.key,
                  ) ? (
                    <span
                      className={
                        styles.cardMediaBadge
                      }
                    >
                      {productCardMediaLabel(
                        media,
                        item.key,
                      )}
                    </span>
                  ) : null}
                </div>

                <div
                  className={
                    styles.mediaContent
                  }
                >
                  <div
                    className={
                      styles.mediaTitle
                    }
                  >
                    <strong>
                      {mediaDisplayName(item)}
                    </strong>

                    <small>
                      ترتیب{" "}
                      {(index + 1).toLocaleString("fa-IR")}
                      {item.complete
                        ? " · آپلود کامل"
                        : item.serverImageId
                          ? " · فایل آپلود شد"
                          : ""}
                    </small>
                  </div>

                  <div
                    className={
                      styles.mediaGrid
                    }
                  >
                    <div
                      className={
                        styles.field
                      }
                    >
                      <label>
                        گالری
                      </label>

                      <SoftSelect
                        value={
                          item.scope
                        }
                        
                        ariaLabel="گالری"
                        onChange={
                          (value) =>
                            updateMediaScope(
                              item.key,
                              value as
                                MediaScope,
                            )
                        }
                        options={[
                          {
                            value:
                              "general",
                            label:
                              "تصاویر عمومی محصول",
                          },
                          {
                            value:
                              "color",
                            label:
                              "رنگ",
                          },
                          {
                            value:
                              "variant",
                            label:
                              "تنوع",
                          },
                        ]}
                      />
                    </div>

                    {item.scope ===
                    "color" ? (
                      <div
                        className={
                          styles.field
                        }
                      >
                        <label>
                          رنگ
                        </label>

                        <SoftSelect
                          value={
                            item.colorValueId ===
                            null
                              ? ""
                              : String(
                                  item.colorValueId,
                                )
                          }
                          
                          ariaLabel="رنگ"
                          onChange={
                            (value) =>
                              updateMedia(
                                item.key,
                                {
                                  colorValueId:
                                    value ===
                                    ""
                                      ? null
                                      : Number(
                                          value,
                                        ),
                                },
                              )
                          }
                          options={[
                            {
                              value: "",
                              label:
                                "انتخاب رنگ",
                            },

                            ...usedColorValues.map(
                              (
                                entry,
                              ) => ({
                                value:
                                  String(
                                    entry
                                      .value
                                      .id,
                                  ),

                                label:
                                  entry
                                    .value
                                    .value,
                              }),
                            ),
                          ]}
                        />
                      </div>
                    ) : null}

                    {item.scope ===
                    "variant" ? (
                      <div
                        className={
                          styles.field
                        }
                      >
                        <label>
                          تنوع
                        </label>

                        <SoftSelect
                          value={
                            item.variantKey ??
                            ""
                          }
                          
                          ariaLabel="تنوع"
                          onChange={
                            (value) =>
                              updateMedia(
                                item.key,
                                {
                                  variantKey:
                                    value ||
                                    null,
                                },
                              )
                          }
                          options={[
                            {
                              value: "",
                              label:
                                "انتخاب تنوع",
                            },

                            ...variants.map(
                              (
                                variant,
                              ) => ({
                                value:
                                  variant.key,

                                label:
                                  variant.sku ||
                                  variantLabel(
                                    variant,
                                  ),
                              }),
                            ),
                          ]}
                        />
                      </div>
                    ) : null}

                    <div
                      className={
                        styles.field
                      }
                    >
                      <label>
                        متن جایگزین تصویر
                      </label>

                      <input
                        value={
                          item.altText
                        }
                        onChange={
                          (event) =>
                            updateMedia(
                              item.key,
                              {
                                altText:
                                  event
                                    .target
                                    .value,
                              },
                            )
                        }
                      />
                    </div>

                    <label
                      className={
                        styles.checkboxField
                      }
                    >
                      <input
                        type="checkbox"
                        checked={
                          item.isPrimary
                        }
                        onChange={
                          (event) =>
                            setMediaPrimary(
                              item.key,
                              event
                                .target
                                .checked,
                            )
                        }
                      />

                      <span>
                        تصویر اصلی این گالری
                      </span>
                    </label>
                  </div>

                  <div
                    className={
                      styles.mediaActions
                    }
                  >
                    <button
                      type="button"
                      className="admin-button"
                      disabled={index === 0}
                      onClick={
                        () =>
                          moveMedia(
                            index,
                            -1,
                          )
                      }
                    >
                      انتقال به بالا
</button>

                    <button
                      type="button"
                      className="admin-button"
                      disabled={index === media.length - 1}
                      onClick={
                        () =>
                          moveMedia(
                            index,
                            1,
                          )
                      }
                    >
                      انتقال به پایین
</button>

                    <button
                      type="button"
                      className="admin-button"
                      
                      onClick={
                        () =>
                          removeMedia(
                            item.key,
                          )
                      }
                    >
                      حذف
</button>
                  </div>
                </div>
              </div>
            ),
          )}
        </div>

        {media.length ===
        0 ? (
          <p
            className={
              styles.emptyNote
            }
          >
            هنوز تصویری برای محصول انتخاب نشده است.
          </p>
        ) : null}
      </section>

      <div
        className={
          styles.footer
        }
      >
        <button
          type="button"
          className="admin-button"
          disabled={
            submitting
          }
          onClick={
            () =>
              router.push(
                "/products",
              )
          }
        >
                      انصراف
</button>

        <button
          type="submit"
          className="admin-button admin-button-primary"
          disabled={
            submitting ||
            categories.length ===
              0
          }
        >
          {submitting
            ? isEditMode
              ? "در حال ذخیره..."
              : "در حال ایجاد..."
            : isEditMode
              ? "ذخیره تغییرات"
              : createdProduct
                ? "ادامه تکمیل محصول"
                : "ایجاد محصول"}
        </button>
      </div>
    </form>
  );
}
