import Link from "next/link";

import {
  notFound,
  redirect,
} from "next/navigation";

import {
  CategoryDeleteButton,
} from "@/components/CategoryDeleteButton";

import {
  AdminCategoriesRequestError,
  getAdminCategories,
  type AdminCategory,
} from "@/lib/admin-categories";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "./categories.module.css";

type SearchParam =
  | string
  | string[]
  | undefined;

interface CategoriesPageProps {
  searchParams:
    Promise<
      Record<
        string,
        SearchParam
      >
    >;
}

interface CategoryRow {
  category:
    AdminCategory;

  depth:
    number;
}

function firstParam(
  value:
    SearchParam,
): string {
  if (
    Array.isArray(
      value,
    )
  ) {
    return (
      value[0] ??
      ""
    );
  }

  return value ?? "";
}

function englishName(
  category:
    AdminCategory,
): string {
  return (
    category.name_translations?.en?.trim() ||
    category.name?.trim() ||
    category.slug
  );
}

function persianName(
  category:
    AdminCategory,
): string {
  return (
    category
      .name_translations
      ?.fa
      ?.trim() ??
    ""
  );
}

function categoryDisplayName(
  category:
    AdminCategory,
): string {
  return (
    persianName(category) ||
    englishName(category)
  );
}

function sortCategories(
  categories:
    AdminCategory[],
): AdminCategory[] {
  return [
    ...categories,
  ].sort(
    (
      left,
      right,
    ) =>
      left.sort_order -
        right.sort_order ||
      left.id -
        right.id,
  );
}

function hierarchyRows(
  categories:
    AdminCategory[],
): CategoryRow[] {
  const byParent =
    new Map<
      number | null,
      AdminCategory[]
    >();

  const ids =
    new Set(
      categories.map(
        (category) =>
          category.id,
      ),
    );

  for (
    const category
    of categories
  ) {
    const parentId =
      category.parent_id !==
        null &&
      ids.has(
        category.parent_id,
      )
        ? category.parent_id
        : null;

    const siblings =
      byParent.get(
        parentId,
      ) ?? [];

    siblings.push(
      category,
    );

    byParent.set(
      parentId,
      siblings,
    );
  }

  for (
    const [
      parentId,
      children,
    ]
    of byParent
  ) {
    byParent.set(
      parentId,
      sortCategories(
        children,
      ),
    );
  }

  const rows:
    CategoryRow[] = [];

  const visited =
    new Set<number>();

  function walk(
    category:
      AdminCategory,
    depth:
      number,
  ) {
    if (
      visited.has(
        category.id,
      )
    ) {
      return;
    }

    visited.add(
      category.id,
    );

    rows.push({
      category,
      depth,
    });

    const children =
      byParent.get(
        category.id,
      ) ?? [];

    for (
      const child
      of children
    ) {
      walk(
        child,
        depth + 1,
      );
    }
  }

  const roots =
    byParent.get(
      null,
    ) ?? [];

  for (
    const root
    of roots
  ) {
    walk(
      root,
      0,
    );
  }

  for (
    const category
    of sortCategories(
      categories,
    )
  ) {
    if (
      !visited.has(
        category.id,
      )
    ) {
      walk(
        category,
        0,
      );
    }
  }

  return rows;
}

function matchesSearch(
  category:
    AdminCategory,
  search:
    string,
): boolean {
  if (!search) {
    return true;
  }

  const haystack = [
    category.slug,
    category.name,
    category
      .name_translations
      ?.en,
    category
      .name_translations
      ?.fa,
  ]
    .filter(
      (
        value,
      ): value is string =>
        typeof value ===
        "string",
    )
    .join(" ")
    .toLocaleLowerCase();

  return haystack.includes(
    search,
  );
}

function formatDate(
  value:
    string | null,
): string {
  if (!value) {
    return "—";
  }

  const date =
    new Date(value);

  if (
    Number.isNaN(
      date.getTime(),
    )
  ) {
    return "—";
  }

  return new Intl.DateTimeFormat(
    "fa-IR-u-ca-gregory",
    {
      year:
        "numeric",

      month:
        "short",

      day:
        "2-digit",
    },
  ).format(
    date,
  );
}

export default async function CategoriesPage({
  searchParams,
}: CategoriesPageProps) {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "categories.view",
    )
  ) {
    notFound();
  }

  const params =
    await searchParams;

  const search =
    firstParam(
      params.search,
    )
      .trim()
      .toLocaleLowerCase();

  const canCreate =
    admin.permissions.includes(
      "categories.create",
    );

  const canUpdate =
    admin.permissions.includes(
      "categories.update",
    );

  const canDelete =
    admin.permissions.includes(
      "categories.delete",
    );

  let categories:
    AdminCategory[] = [];

  let loadError:
    string | null =
      null;

  try {
    categories =
      await getAdminCategories();
  } catch (
    error
  ) {
    loadError =
      error instanceof
      AdminCategoriesRequestError
        ? error.message
        : "بارگذاری دسته‌بندی‌ها انجام نشد.";
  }

  const allRows =
    hierarchyRows(
      categories,
    );

  const rows =
    allRows.filter(
      ({ category }) =>
        matchesSearch(
          category,
          search,
        ),
    );

  return (
    <section
      className="admin-dashboard"
      dir="rtl"
      lang="fa"
    >
      <header className="admin-page-header">
        <div>
          <p className="admin-eyebrow">
            کاتالوگ
          </p>

          <h1>
            دسته‌بندی‌ها
          </h1>
        </div>

        <div
          className={
            styles.headerSide
          }
        >
          <p className="admin-muted">
            مدیریت ساختار دسته‌بندی‌ها
            برای فروشگاه و محصولات
          </p>

          {canCreate ? (
            <Link
              href="/categories/new"
              className="admin-button admin-button-primary"
            >
              ایجاد دسته‌بندی
            </Link>
          ) : null}
        </div>
      </header>

      <div
        className={
          styles.toolbar
        }
      >
        <form
          className={
            styles.searchForm
          }
          method="get"
        >
          <input
            type="search"
            name="search"
            defaultValue={
              firstParam(
                params.search,
              )
            }
            placeholder="جست‌وجوی دسته‌بندی‌ها..."
            aria-label="جست‌وجوی دسته‌بندی‌ها"
          />

          <button
            type="submit"
            className="admin-button admin-button-primary"
          >
            جست‌وجو
          </button>

          {search ? (
            <Link
              href="/categories"
              className="admin-button"
            >
              پاک‌کردن
            </Link>
          ) : null}
        </form>

        <div
          className={
            styles.count
          }
        >
          <strong>
            {
              rows.length
            }
          </strong>

          <span>
            دسته‌بندی
          </span>
        </div>
      </div>

      {loadError ? (
        <div
          className={
            styles.state
          }
        >
          <strong>
            بارگذاری دسته‌بندی‌ها انجام نشد.
          </strong>

          <p>
            {loadError}
          </p>
        </div>
      ) : rows.length ===
        0 ? (
        <div
          className={
            styles.state
          }
        >
          <strong>
            دسته‌بندی‌ای پیدا نشد.
          </strong>

          <p>
            {search
              ? "دسته‌بندی‌ای با این عبارت پیدا نشد."
              : "هنوز دسته‌بندی‌ای ثبت نشده است."}
          </p>
        </div>
      ) : (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <thead>
              <tr>
                <th>
                  دسته‌بندی
                </th>

                <th>
                  دسته والد
                </th>

                <th>
                  وضعیت
                </th>

                <th>
                  ترتیب
                </th>

                <th>
                  آخرین به‌روزرسانی
                </th>

                {canUpdate || canDelete ? (
                  <th
                    className={
                      styles.actionsHead
                    }
                  >
                    عملیات
                  </th>
                ) : null}
              </tr>
            </thead>

            <tbody>
              {rows.map(
                ({
                  category,
                  depth,
                }) => {
                  const parent =
                    category.parent_id
                      ? categories.find(
                          (item) =>
                            item.id ===
                            category.parent_id,
                        )
                      : null;

                  const en =
                    englishName(
                      category,
                    );

                  const fa =
                    persianName(
                      category,
                    );

                  return (
                    <tr
                      key={
                        category.id
                      }
                    >
                      <td>
                        <div
                          className={
                            styles.categoryCell
                          }
                          style={{
                            paddingInlineStart:
                              `${depth * 22}px`,
                          }}
                        >
                          {depth >
                          0 ? (
                            <span
                              className={
                                styles.branch
                              }
                              aria-hidden="true"
                            >
                              ↳
                            </span>
                          ) : null}

                          <div>
                            <strong
                              dir={
                                fa
                                  ? "rtl"
                                  : "ltr"
                              }
                            >
                              {categoryDisplayName(
                                category,
                              )}
                            </strong>

                            {fa && en !== fa ? (
                              <span
                                dir="ltr"
                                lang="en"
                              >
                                {en}
                              </span>
                            ) : null}

                            <small dir="ltr">
                              {
                                category.slug
                              }
                            </small>
                          </div>
                        </div>
                      </td>

                      <td
                        dir={
                          !parent ||
                          persianName(parent)
                            ? "rtl"
                            : "ltr"
                        }
                        lang={
                          !parent ||
                          persianName(parent)
                            ? "fa"
                            : "en"
                        }
                      >
                        {parent
                          ? categoryDisplayName(
                              parent,
                            )
                          : "ریشه"}
                      </td>

                      <td>
                        <span
                          className={
                            category.is_active
                              ? styles.activeBadge
                              : styles.inactiveBadge
                          }
                        >
                          {category.is_active
                            ? "فعال"
                            : "غیرفعال"}
                        </span>
                      </td>

                      <td>
                        {
                          category.sort_order
                        }
                      </td>

                      <td>
                        {formatDate(
                          category.updated_at,
                        )}
                      </td>

                      {canUpdate ||
                      canDelete ? (
                        <td
                          className={
                            styles.actionsCell
                          }
                        >
                          <div
                            className={
                              styles.rowActions
                            }
                          >
                            {canUpdate ? (
                              <Link
                                href={`/categories/${category.id}/edit`}
                                className="admin-button"
                                aria-label={`ویرایش ${categoryDisplayName(
                                  category,
                                )}`}
                              >
                                ویرایش
                              </Link>
                            ) : null}

                            {canDelete ? (
                              <CategoryDeleteButton
                                categoryId={
                                  category.id
                                }
                                categoryName={
                                categoryDisplayName(
                                  category,
                                )
                                }
                                categoryNameDirection={
                                fa
                                  ? "rtl"
                                  : "ltr"
                                }
                              />
                            ) : null}
                          </div>
                        </td>
                      ) : null}
                    </tr>
                  );
                },
              )}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}