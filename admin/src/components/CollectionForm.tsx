"use client";

import {
  FormEvent,
  useMemo,
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

import type {
  AdminCollection,
} from "@/lib/admin-collections";

import type {
  AdminProduct,
  LocalizedAdminText,
} from "@/lib/admin-products";

import styles from "./CollectionForm.module.css";

interface CollectionFormProps {
  products:
    AdminProduct[];

  initialCollection?:
    AdminCollection | null;

  canManageProducts:
    boolean;
}

interface ErrorPayload {
  message?:
    string;

  errors?: {
    [key: string]:
      | string[]
      | undefined;
  };
}

function localizedText(
  value:
    LocalizedAdminText,
): string {
  if (
    typeof value ===
    "string"
  ) {
    return value;
  }

  if (
    value &&
    typeof value ===
      "object"
  ) {
    return (
      value.fa?.trim() ||
      value.en?.trim() ||
      ""
    );
  }

  return "";
}

function localizedDirection(
  value:
    LocalizedAdminText,
): "ltr" | "rtl" {
  return value &&
    typeof value === "object" &&
    value.fa?.trim()
    ? "rtl"
    : "ltr";
}

function firstError(
  errors:
    ErrorPayload["errors"],
): string | null {
  if (!errors) {
    return null;
  }

  for (
    const messages
    of Object.values(
      errors,
    )
  ) {
    if (
      Array.isArray(
        messages,
      ) &&
      messages.length >
        0
    ) {
      return (
        messages[0] ??
        null
      );
    }
  }

  return null;
}

export function CollectionForm({
  products,
  initialCollection =
    null,
  canManageProducts,
}: CollectionFormProps) {
  const router =
    useRouter();

  const editing =
    Boolean(
      initialCollection,
    );

  const [
    nameEn,
    setNameEn,
  ] =
    useState(
      initialCollection
        ?.name_translations
        ?.en ??
      initialCollection
        ?.name ??
      "",
    );

  const [
    nameFa,
    setNameFa,
  ] =
    useState(
      initialCollection
        ?.name_translations
        ?.fa ??
      "",
    );

  const [
    descriptionEn,
    setDescriptionEn,
  ] =
    useState(
      initialCollection
        ?.description_translations
        ?.en ??
      initialCollection
        ?.description ??
      "",
    );

  const [
    descriptionFa,
    setDescriptionFa,
  ] =
    useState(
      initialCollection
        ?.description_translations
        ?.fa ??
      "",
    );

  const [
    slug,
    setSlug,
  ] =
    useState(
      initialCollection
        ?.slug ??
      "",
    );

  const [
    image,
    setImage,
  ] =
    useState(
      initialCollection
        ?.image ??
      "",
    );

  const [
    sortOrder,
    setSortOrder,
  ] =
    useState(
      (
        initialCollection
          ?.sort_order ??
        0
      ).toString(),
    );

  const [
    isActive,
    setIsActive,
  ] =
    useState(
      initialCollection
        ?.is_active ??
      true,
    );

  const [
    selectedProductIds,
    setSelectedProductIds,
  ] =
    useState<number[]>(
      initialCollection
        ?.products
        ?.map(
          (product) =>
            product.id,
        ) ??
      [],
    );

  const [
    productSearch,
    setProductSearch,
  ] =
    useState("");

  const [
    submitting,
    setSubmitting,
  ] =
    useState(false);

  const [
    error,
    setError,
  ] =
    useState<
      string | null
    >(null);

  const visibleProducts =
    useMemo(
      () => {
        const search =
          productSearch
            .trim()
            .toLocaleLowerCase();

        if (!search) {
          return products;
        }

        return products.filter(
          (product) => {
            const localizedName =
              product.name &&
              typeof product.name === "object"
                ? [
                    product.name.fa,
                    product.name.en,
                  ]
                : [product.name];

            const haystack = [
              ...localizedName,
              product.slug,
              String(
                product.id,
              ),
            ]
              .filter(
                (value): value is string =>
                  typeof value === "string",
              )
              .join(" ")
              .toLocaleLowerCase();

            return haystack.includes(
              search,
            );
          },
        );
      },
      [
        products,
        productSearch,
      ],
    );

  function toggleProduct(
    productId:
      number,
  ) {
    setSelectedProductIds(
      (current) =>
        current.includes(
          productId,
        )
          ? current.filter(
              (id) =>
                id !==
                productId,
            )
          : [
              ...current,
              productId,
            ],
    );
  }

  async function handleSubmit(
    event:
      FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    if (submitting) {
      return;
    }

    setError(
      null,
    );

    const cleanEn =
      nameEn.trim();

    const cleanFa =
      nameFa.trim();

    if (
      !cleanEn ||
      !cleanFa
    ) {
      setError(
        "واردکردن نام فارسی و انگلیسی مجموعه الزامی است.",
      );

      return;
    }

    const order =
      Number(
        sortOrder,
      );

    if (
      !Number.isInteger(
        order,
      ) ||
      order < 0
    ) {
      setError(
        "ترتیب نمایش باید عدد صحیحی بزرگ‌تر یا مساوی صفر باشد.",
      );

      return;
    }

    const payload = {
      name: {
        en:
          cleanEn,

        fa:
          cleanFa,
      },

      description:
        descriptionEn.trim() ||
        descriptionFa.trim()
          ? {
              en:
                descriptionEn.trim() ||
                null,

              fa:
                descriptionFa.trim() ||
                null,
            }
          : null,

      slug:
        slug.trim() ||
        null,

      image:
        image.trim() ||
        null,

      sort_order:
        order,

      is_active:
        isActive,

      ...(canManageProducts
        ? {
            product_ids:
              selectedProductIds,
          }
        : {}),
    };

    setSubmitting(
      true,
    );

    try {
      const url =
        editing &&
        initialCollection
          ? `/api/collections/${initialCollection.id}`
          : "/api/collections";

      const response =
        await fetch(
          url,
          {
            method:
              editing
                ? "PUT"
                : "POST",

            headers: {
              "Content-Type":
                "application/json",
            },

            body:
              JSON.stringify(
                payload,
              ),
          },
        );

      const result =
        (await response
          .json()
          .catch(
            () => null,
          )) as
          | ErrorPayload
          | null;

      if (
        !response.ok
      ) {
        setError(
          firstError(
            result
              ?.errors,
          ) ??
            result
              ?.message ??
            "ذخیره مجموعه انجام نشد.",
        );

        return;
      }

      router.push(
        "/collections",
      );

      router.refresh();
    } catch {
      setError(
        "ذخیره مجموعه انجام نشد.",
      );
    } finally {
      setSubmitting(
        false,
      );
    }
  }

  return (
    <form
      className={
        styles.form
      }
      dir="rtl"
      lang="fa"
      noValidate
      onSubmit={
        handleSubmit
      }
    >
      {error ? (
        <div
          className={
            styles.errorBox
          }
          role="alert"
        >
          {error}
        </div>
      ) : null}

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
            <span>
              مشخصات
            </span>

            <h2>
              نام مجموعه
            </h2>
          </div>

          <p>
            نام فارسی و انگلیسی را وارد کنید.
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
              htmlFor="collection-name-en"
            >
              نام به انگلیسی
            </label>

            <input
              id="collection-name-en"
              type="text"
              dir="ltr"
              lang="en"
              value={
                nameEn
              }
              maxLength={
                150
              }
              required
              onChange={
                (event) =>
                  setNameEn(
                    event
                      .target
                      .value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
            dir="rtl"
          >
            <label
              htmlFor="collection-name-fa"
            >
              نام فارسی
            </label>

            <input
              id="collection-name-fa"
              type="text"
              dir="rtl"
              lang="fa"
              placeholder="برای نمونه: انتخاب ویژه"
              value={
                nameFa
              }
              maxLength={
                150
              }
              required
              onChange={
                (event) =>
                  setNameFa(
                    event
                      .target
                      .value,
                  )
              }
            />
          </div>
        </div>

        <div
          className={
            styles.field
          }
        >
          <label
            htmlFor="collection-slug"
          >
            نامک
          </label>

          <input
            id="collection-slug"
            type="text"
            dir="ltr"
            value={
              slug
            }
            onChange={
              (event) =>
                setSlug(
                  event
                    .target
                    .value
                    .toLowerCase()
                    .replace(
                      /\s+/g,
                      "-",
                    ),
                )
            }
          />

          <small>
            فقط حروف کوچک انگلیسی، عدد و خط تیره وارد کنید.
            برای ساخت خودکار نامک، این فیلد را خالی بگذارید.
          </small>
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
            <span>
              محتوا
            </span>

            <h2>
              توضیحات
            </h2>
          </div>
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
              htmlFor="collection-description-en"
            >
              توضیحات به انگلیسی
            </label>

            <textarea
              id="collection-description-en"
              dir="ltr"
              lang="en"
              value={
                descriptionEn
              }
              onChange={
                (event) =>
                  setDescriptionEn(
                    event
                      .target
                      .value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
            dir="rtl"
          >
            <label
              htmlFor="collection-description-fa"
            >
              توضیحات فارسی
            </label>

            <textarea
              id="collection-description-fa"
              lang="fa"
              value={
                descriptionFa
              }
              onChange={
                (event) =>
                  setDescriptionFa(
                    event
                      .target
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
            <span>
              مدیریت
            </span>

            <h2>
              ترتیب و تصویر
            </h2>
          </div>
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
              htmlFor="collection-sort-order"
            >
              ترتیب نمایش
            </label>

            <input
              id="collection-sort-order"
              type="number"
              dir="ltr"
              min="0"
              step="1"
              value={
                sortOrder
              }
              onChange={
                (event) =>
                  setSortOrder(
                    event
                      .target
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
            <label
              htmlFor="collection-image"
            >
              نشانی تصویر
            </label>

            <input
              id="collection-image"
              type="text"
              dir="ltr"
              value={
                image
              }
              placeholder="/storage/collections/example.jpg"
              onChange={
                (event) =>
                  setImage(
                    event
                      .target
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
            <span>
              محصولات
            </span>

            <h2>
              محصولات مجموعه
            </h2>
          </div>

          <p>
            محصولاتی را که به این مجموعه تعلق دارند انتخاب کنید.
          </p>
        </div>

        {canManageProducts ? (
          <>
            <div
              className={
                styles.productTools
              }
            >
              <input
                type="search"
                dir="rtl"
                className={
                  styles.productSearch
                }
                value={
                  productSearch
                }
                placeholder="جست‌وجوی محصولات..."
                aria-label="جست‌وجوی محصولات"
                onChange={
                  (event) =>
                    setProductSearch(
                      event
                        .target
                        .value,
                    )
                }
              />

              <span
                className={
                  styles.productCount
                }
              >
                {
                  selectedProductIds.length
                }{" "}
                محصول انتخاب‌شده
              </span>
            </div>

            {visibleProducts.length >
            0 ? (
              <div
                className={
                  styles.productList
                }
              >
                {visibleProducts.map(
                  (product) => {
                    const checked =
                      selectedProductIds.includes(
                        product.id,
                      );

                    return (
                      <label
                        key={
                          product.id
                        }
                        className={
                          checked
                            ? `${styles.productOption} ${styles.productOptionActive}`
                            : styles.productOption
                        }
                      >
                        <input
                          type="checkbox"
                          checked={
                            checked
                          }
                          onChange={
                            () =>
                              toggleProduct(
                                product.id,
                              )
                          }
                        />

                        <span
                          className={
                            styles.productMeta
                          }
                        >
                          <strong
                            dir={localizedDirection(
                              product.name,
                            )}
                          >
                            {localizedText(
                              product.name,
                            ) ||
                              product.slug}
                          </strong>

                          <span
                            dir="ltr"
                          >
                            #
                            {product.id} ·{" "}
                            {product.slug}
                            {" · "}
                            {product.status ??
                              "نامشخص"}
                          </span>
                        </span>
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
                {productSearch
                  ? "محصولی با این عبارت پیدا نشد."
                  : "در حال حاضر محصولی برای انتخاب وجود ندارد."}
              </p>
            )}
          </>
        ) : (
          <p
            className={
              styles.emptyNote
            }
          >
            اجازه مشاهده محصولات را ندارید؛ ارتباط محصولات فعلی با این مجموعه حفظ می‌شود.
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
            styles.statusRow
          }
        >
          <div>
            <strong>
              فعال‌بودن مجموعه
            </strong>

            <p>
              مجموعه‌های غیرفعال همچنان در پنل قابل‌مدیریت هستند،
              اما در فروشگاه عمومی نمایش داده نمی‌شوند.
            </p>
          </div>

          <label
            className={
              styles.switch
            }
          >
            <input
              type="checkbox"
              checked={
                isActive
              }
              onChange={
                (event) =>
                  setIsActive(
                    event
                      .target
                      .checked,
                  )
              }
            />

            <span>
              {isActive
                ? "فعال"
                : "غیرفعال"}
            </span>
          </label>
        </div>
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
                "/collections",
              )
          }
        >
          انصراف
        </button>

        <button
          type="submit"
          className="admin-button admin-button-primary"
          disabled={
            submitting
          }
        >
          {submitting
            ? "در حال ذخیره..."
            : editing
              ? "ذخیره تغییرات"
              : "ایجاد مجموعه"}
        </button>
      </div>
    </form>
  );
}