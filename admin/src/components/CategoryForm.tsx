"use client";

import { AdminSelect } from "@/components/AdminSelect";

import {
  FormEvent,
  useMemo,
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

import type {
  AdminCategory,
} from "@/lib/admin-categories";

import styles from "./CategoryForm.module.css";

interface CategoryFormProps {
  categories:
    AdminCategory[];

  initialCategory?:
    AdminCategory | null;
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

function categoryDisplayName(
  category:
    AdminCategory,
): string {
  return (
    category
      .name_translations
      ?.fa
      ?.trim() ||
    category
      .name_translations
      ?.en
      ?.trim() ||
    category.name?.trim() ||
    category.slug
  );
}

function descendantIds(
  categories:
    AdminCategory[],
  categoryId:
    number,
): Set<number> {
  const result =
    new Set<number>();

  const queue = [
    categoryId,
  ];

  while (
    queue.length >
    0
  ) {
    const current =
      queue.shift();

    if (
      current ===
      undefined
    ) {
      continue;
    }

    const children =
      categories.filter(
        (category) =>
          category.parent_id ===
          current,
      );

    for (
      const child
      of children
    ) {
      if (
        result.has(
          child.id,
        )
      ) {
        continue;
      }

      result.add(
        child.id,
      );

      queue.push(
        child.id,
      );
    }
  }

  return result;
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

export function CategoryForm({
  categories,
  initialCategory =
    null,
}: CategoryFormProps) {
  const router =
    useRouter();

  const editing =
    Boolean(
      initialCategory,
    );

  const [
    nameEn,
    setNameEn,
  ] =
    useState(
      initialCategory
        ?.name_translations
        ?.en ??
        initialCategory
          ?.name ??
        "",
    );

  const [
    nameFa,
    setNameFa,
  ] =
    useState(
      initialCategory
        ?.name_translations
        ?.fa ??
        "",
    );

  const [
    descriptionEn,
    setDescriptionEn,
  ] =
    useState(
      initialCategory
        ?.description_translations
        ?.en ??
        initialCategory
          ?.description ??
        "",
    );

  const [
    descriptionFa,
    setDescriptionFa,
  ] =
    useState(
      initialCategory
        ?.description_translations
        ?.fa ??
        "",
    );

  const [
    slug,
    setSlug,
  ] =
    useState(
      initialCategory
        ?.slug ??
        "",
    );

  const [
    image,
    setImage,
  ] =
    useState(
      initialCategory
        ?.image ??
        "",
    );

  const [
    parentId,
    setParentId,
  ] =
    useState(
      initialCategory
        ?.parent_id
        ?.toString() ??
        "",
    );

  const [
    sortOrder,
    setSortOrder,
  ] =
    useState(
      (
        initialCategory
          ?.sort_order ??
        0
      ).toString(),
    );

  const [
    isActive,
    setIsActive,
  ] =
    useState(
      initialCategory
        ?.is_active ??
        true,
    );

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

  const blockedParents =
    useMemo(
      () => {
        if (
          !initialCategory
        ) {
          return new Set<
            number
          >();
        }

        const ids =
          descendantIds(
            categories,
            initialCategory.id,
          );

        ids.add(
          initialCategory.id,
        );

        return ids;
      },
      [
        categories,
        initialCategory,
      ],
    );

  const parentOptions =
    useMemo(
      () =>
        categories
          .filter(
            (category) =>
              !blockedParents.has(
                category.id,
              ),
          )
          .sort(
            (
              left,
              right,
            ) =>
              left.sort_order -
                right.sort_order ||
              left.id -
                right.id,
          ),
      [
        categories,
        blockedParents,
      ],
    );

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
        "واردکردن نام فارسی و انگلیسی دسته‌بندی الزامی است.",
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
        "ترتیب باید عدد صحیحی بزرگ‌تر یا مساوی صفر باشد.",
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

      parent_id:
        parentId
          ? Number(
              parentId,
            )
          : null,

      sort_order:
        order,

      is_active:
        isActive,
    };

    setSubmitting(
      true,
    );

    try {
      const url =
        editing &&
        initialCategory
          ? `/api/categories/${initialCategory.id}`
          : "/api/categories";

      const response =
        await fetch(
          url,
          {
            method:
              editing
                ? "PATCH"
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
            "ذخیره دسته‌بندی انجام نشد.",
        );

        return;
      }

      router.push(
        "/categories",
      );

      router.refresh();
    } catch {
      setError(
        "ذخیره دسته‌بندی انجام نشد.",
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
              نام دسته‌بندی
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
              htmlFor="category-name-en"
            >
              نام به انگلیسی
            </label>

            <input
              id="category-name-en"
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
              htmlFor="category-name-fa"
            >
              نام فارسی
            </label>

            <input
              id="category-name-fa"
              type="text"
              dir="rtl"
              lang="fa"
              placeholder="برای نمونه: ظروف پذیرایی"
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
            htmlFor="category-slug"
          >
            نامک
          </label>

          <input
            id="category-slug"
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
              htmlFor="category-description-en"
            >
              توضیحات به انگلیسی
            </label>

            <textarea
              id="category-description-en"
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
              htmlFor="category-description-fa"
            >
              توضیحات فارسی
            </label>

            <textarea
              id="category-description-fa"
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
              ساختار
            </span>

            <h2>
              والد و ترتیب
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
              htmlFor="category-parent"
            >
              دسته والد
            </label>

            <AdminSelect
              id="category-parent"
              dir="rtl"
              emptyLabel="بدون دسته والد (ریشه)"
              value={
                parentId
              }
              onChange={
                (event) =>
                  setParentId(
                    event
                      .target
                      .value,
                  )
              }
            >
              <option value="">
                بدون دسته والد (ریشه)
              </option>

              {parentOptions.map(
                (category) => (
                  <option
                    key={
                      category.id
                    }
                    value={
                      category.id
                    }
                  >
                    {categoryDisplayName(
                      category,
                    )}
                  </option>
                ),
              )}
            </AdminSelect>
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="category-sort-order"
            >
              ترتیب نمایش
            </label>

            <input
              id="category-sort-order"
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
              رسانه
            </span>

            <h2>
              تصویر دسته‌بندی
            </h2>
          </div>
        </div>

        <div
          className={
            styles.field
          }
        >
          <label
            htmlFor="category-image"
          >
            نشانی تصویر
          </label>

          <input
            id="category-image"
            type="text"
            dir="ltr"
            value={
              image
            }
            placeholder="/storage/categories/example.jpg"
            onChange={
              (event) =>
                setImage(
                  event
                    .target
                    .value,
                )
            }
          />

          <small>
            نشانی داخلی یا پیوند تصویر با HTTP(S) پذیرفته می‌شود.
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
            styles.statusRow
          }
        >
          <div>
            <strong>
              فعال‌بودن دسته‌بندی
            </strong>

            <p>
              دسته‌های غیرفعال همچنان در پنل قابل‌مدیریت هستند،
              اما در ساختار عمومی فروشگاه نمایش داده نمی‌شوند.
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
                "/categories",
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
              : "ایجاد دسته‌بندی"}
        </button>
      </div>
    </form>
  );
}