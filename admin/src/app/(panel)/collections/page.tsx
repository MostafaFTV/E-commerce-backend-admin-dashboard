import Link from "next/link";

import {
  notFound,
  redirect,
} from "next/navigation";

import {
  CollectionDeleteButton,
} from "@/components/CollectionDeleteButton";

import {
  AdminCollectionsRequestError,
  getAdminCollections,
  type AdminCollection,
} from "@/lib/admin-collections";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "./collections.module.css";

type SearchParam =
  | string
  | string[]
  | undefined;

interface CollectionsPageProps {
  searchParams:
    Promise<
      Record<
        string,
        SearchParam
      >
    >;
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
  collection:
    AdminCollection,
): string {
  return (
    collection.name_translations?.en?.trim() ||
    collection.name?.trim() ||
    collection.slug
  );
}

function persianName(
  collection:
    AdminCollection,
): string {
  return (
    collection
      .name_translations
      ?.fa
      ?.trim() ??
    ""
  );
}

function displayName(
  collection:
    AdminCollection,
): string {
  return (
    persianName(collection) ||
    englishName(collection)
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

export default async function CollectionsPage({
  searchParams,
}: CollectionsPageProps) {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "collections.view",
    )
  ) {
    notFound();
  }

  const params =
    await searchParams;

  const search =
    firstParam(
      params.search,
    ).trim();

  const canCreate =
    admin.permissions.includes(
      "collections.create",
    );

  const canUpdate =
    admin.permissions.includes(
      "collections.update",
    );

  const canDelete =
    admin.permissions.includes(
      "collections.delete",
    );

  let collections:
    AdminCollection[] = [];

  let loadError:
    string | null =
      null;

  try {
    collections =
      await getAdminCollections(
        search,
      );
  } catch (
    error
  ) {
    loadError =
      error instanceof
        AdminCollectionsRequestError
        ? error.message
        : "بارگذاری مجموعه‌ها انجام نشد.";
  }

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
            مجموعه‌ها
          </h1>
        </div>

        <div
          className={
            styles.headerSide
          }
        >
          <p className="admin-muted">
            محصولات را در مجموعه‌های منتخب فروشگاه گروه‌بندی کنید.
          </p>

          {canCreate ? (
            <Link
              href="/collections/new"
              className="admin-button admin-button-primary"
            >
              ایجاد مجموعه
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
            placeholder="جست‌وجوی مجموعه‌ها..."
            aria-label="جست‌وجوی مجموعه‌ها"
          />

          <button
            type="submit"
            className="admin-button admin-button-primary"
          >
            جست‌وجو
          </button>

          {search ? (
            <Link
              href="/collections"
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
              collections.length
            }
          </strong>

          <span>
            مجموعه
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
            بارگذاری مجموعه‌ها انجام نشد.
          </strong>

          <p>
            {loadError}
          </p>
        </div>
      ) : collections.length ===
        0 ? (
        <div
          className={
            styles.state
          }
        >
          <strong>
            مجموعه‌ای پیدا نشد.
          </strong>

          <p>
            {search
              ? "مجموعه‌ای با این عبارت پیدا نشد."
              : "هنوز مجموعه‌ای ثبت نشده است."}
          </p>
        </div>
      ) : (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <thead>
              <tr>
                <th>
                  مجموعه
                </th>

                <th>
                  تعداد محصولات
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
              {collections.map(
                (collection) => {
                  const en =
                    englishName(
                      collection,
                    );

                  const fa =
                    persianName(
                      collection,
                    );

                  return (
                    <tr
                      key={
                        collection.id
                      }
                    >
                      <td>
                        <div
                          className={
                            styles.collectionCell
                          }
                        >
                          <div>
                            <strong
                              dir={
                                fa
                                  ? "rtl"
                                  : "ltr"
                              }
                            >
                              {displayName(
                                collection,
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
                                collection.slug
                              }
                            </small>
                          </div>
                        </div>
                      </td>

                      <td>
                        {
                          collection.products_count ??
                          collection.products
                            ?.length ??
                          0
                        }
                      </td>

                      <td>
                        <span
                          className={
                            collection.is_active
                              ? styles.activeBadge
                              : styles.inactiveBadge
                          }
                        >
                          {collection.is_active
                            ? "فعال"
                            : "غیرفعال"}
                        </span>
                      </td>

                      <td>
                        {
                          collection.sort_order
                        }
                      </td>

                      <td>
                        {formatDate(
                          collection.updated_at,
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
                                href={`/collections/${collection.id}/edit`}
                                className="admin-button"
                                aria-label={`ویرایش ${displayName(
                                  collection,
                                )}`}
                              >
                                ویرایش
                              </Link>
                            ) : null}

                            {canDelete ? (
                              <CollectionDeleteButton
                                collectionId={
                                  collection.id
                                }
                                collectionName={
                                  displayName(
                                    collection,
                                  )
                                }
                                collectionNameDirection={
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