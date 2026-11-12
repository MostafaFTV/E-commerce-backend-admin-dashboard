import { AdminSelect } from "@/components/AdminSelect";

import Link from "next/link";

import {
  notFound,
  redirect,
} from "next/navigation";

import {
  AdminInventoryRequestError,
  getAdminInventoryProducts,
  type AdminInventoryProduct,
  type InventoryLocalizedValue,
} from "@/lib/admin-inventory";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "./inventory.module.css";

type SearchParam =
  | string
  | string[]
  | undefined;

interface InventoryPageProps {
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
    return value[0] ?? "";
  }

  return value ?? "";
}

function localizedText(
  value:
    InventoryLocalizedValue,
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
      (Object.values(
        value,
      ).find(
        (
          item,
        ): item is string =>
          typeof item ===
            "string" &&
          item.trim() !== "",
      ) ??
      "")
    );
  }

  return "";
}

function statusLabel(
  product:
    AdminInventoryProduct,
) {
  switch (
    product.stock_status
  ) {
    case "not_configured":
      return {
        label:
          "تنظیم‌نشده",

        tone:
          "neutral",
      };

    case "out_of_stock":
      return {
        label:
          "ناموجود",

        tone:
          "danger",
      };

    case "low_stock":
      return {
        label:
          "کم‌موجود",

        tone:
          "warning",
      };

    default:
      return {
        label:
          "موجود",

        tone:
          "success",
      };
  }
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
    return value;
  }

  return new Intl.DateTimeFormat(
    "fa-IR-u-ca-gregory",
    {
      dateStyle:
        "medium",
    },
  ).format(date);
}

function pageHref(
  page:
    number,
  search:
    string,
  stockStatus:
    string,
): string {
  const params =
    new URLSearchParams();

  if (search) {
    params.set(
      "search",
      search,
    );
  }

  if (stockStatus) {
    params.set(
      "status",
      stockStatus,
    );
  }

  if (page > 1) {
    params.set(
      "page",
      String(page),
    );
  }

  const query =
    params.toString();

  return query
    ? `/inventory?${query}`
    : "/inventory";
}

export default async function InventoryPage({
  searchParams,
}: InventoryPageProps) {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "inventory.view",
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

  const stockStatus =
    firstParam(
      params.status,
    );

  const requestedPage =
    Number(
      firstParam(
        params.page,
      ) || "1",
    );

  const page =
    Number.isInteger(
      requestedPage,
    ) &&
    requestedPage > 0
      ? requestedPage
      : 1;

  let result;

  try {
    result =
      await getAdminInventoryProducts({
        search,
        stockStatus,
        page,
        perPage: 25,
      });
  } catch (error) {
    if (
      error instanceof
        AdminInventoryRequestError &&
      error.status === 401
    ) {
      redirect(
        "/login",
      );
    }

    throw error;
  }

  return (
    <section
      className={`admin-dashboard ${styles.page}`}
      dir="rtl"
      lang="fa"
    >
      <header
        className="admin-page-header"
      >
        <div>
          <p
            className="admin-eyebrow"
          >
            کاتالوگ
          </p>

          <h1>
            موجودی انبار
          </h1>
        </div>

        <p
          className="admin-muted"
        >
          موجودی کالاها را بررسی کنید.
          موجودی هر تنوع از صفحه همان محصول مدیریت می‌شود.
        </p>
      </header>

      <div
        className={
          styles.toolbar
        }
      >
        <form
          method="get"
          className={
            styles.filters
          }
        >
          <div
            className={
              styles.searchField
            }
          >
            <input
              type="search"
              name="search"
              dir="rtl"
              defaultValue={
                search
              }
              placeholder="جست‌وجوی محصول، شناسه کالا یا برند"
              aria-label="جست‌وجوی محصول، شناسه کالا یا برند"
            />
          </div>

          <AdminSelect
            name="status"
            dir="rtl"
            emptyLabel="همه وضعیت‌ها"
            defaultValue={
              stockStatus
            }
          >
            <option value="">
              همه وضعیت‌ها
            </option>

            <option value="in_stock">
              موجود
            </option>

            <option value="low_stock">
              کم‌موجود
            </option>

            <option value="out_of_stock">
              ناموجود
            </option>

            <option value="not_configured">
              تنظیم‌نشده
            </option>
          </AdminSelect>

          <button
            type="submit"
            className="admin-button admin-button-primary"
          >
            اعمال فیلترها

            <span
              aria-hidden="true"
            >
              ←
            </span>
          </button>

          {search ||
          stockStatus ? (
            <Link
              href="/inventory"
              className={
                styles.clearButton
              }
            >
              پاک‌کردن فیلترها
            </Link>
          ) : null}
        </form>

        <span
          className={
            styles.total
          }
        >
          <bdi dir="ltr">{result.meta.total}</bdi>{" "}
          محصول
        </span>
      </div>

      <div
        className={styles.tableFrame}
        role="region"
        aria-label="فهرست موجودی محصولات"
        tabIndex={0}
      >
        <table
          className={
            styles.table
          }
        >
          <thead>
            <tr>
              <th>
                محصول
              </th>

              <th>
                تنوع‌ها
              </th>

              <th>
                موجودی فیزیکی
              </th>

              <th>
                رزروشده
              </th>

              <th>
                قابل‌عرضه
              </th>

              <th>
                وضعیت
              </th>

              <th>
                آخرین به‌روزرسانی
              </th>

              <th />
            </tr>
          </thead>

          <tbody>
            {result.data.length ? (
              result.data.map(
                (product) => {
                  const state =
                    statusLabel(
                      product,
                    );

                  return (
                    <tr
                      key={
                        product.id
                      }
                    >
                      <td>
                        <div
                          className={
                            styles.productCell
                          }
                        >
                          <strong
                            dir={
                              typeof product.name === "object" &&
                              product.name?.fa?.trim()
                                ? "rtl"
                                : "ltr"
                            }
                          >
                            {localizedText(
                              product.name,
                            ) ||
                              "محصول بدون نام"}
                          </strong>

                          <span dir="ltr">
                            {product.brand ||
                              product.slug}
                          </span>
                        </div>
                      </td>

                      <td dir="ltr">
                        {
                          product.variants_count
                        }
                      </td>

                      <td dir="ltr">
                        {
                          product.quantity
                        }
                      </td>

                      <td dir="ltr">
                        <span
                          className={
                            styles.reservedValue
                          }
                        >
                          {
                            product.reserved_quantity
                          }
                        </span>
                      </td>

                      <td dir="ltr">
                        <strong
                          className={
                            styles.availableValue
                          }
                        >
                          {
                            product.available_quantity
                          }
                        </strong>
                      </td>

                      <td>
                        <span
                          className={
                            styles.status
                          }
                          data-tone={
                            state.tone
                          }
                        >
                          <span
                            className={
                              styles.statusDot
                            }
                          />

                          {
                            state.label
                          }
                        </span>
                      </td>

                      <td dir="ltr">
                        {formatDate(
                          product.updated_at,
                        )}
                      </td>

                      <td>
                        <Link
                          href={`/inventory/products/${product.id}`}
                          className="admin-button"
                        >
                          مشاهده موجودی

                          <span
                            aria-hidden="true"
                          >
                            ←
                          </span>
                        </Link>
                      </td>
                    </tr>
                  );
                },
              )
            ) : (
              <tr>
                <td
                  colSpan={8}
                  className={
                    styles.empty
                  }
                >
                  محصولی با فیلترهای انتخاب‌شده پیدا نشد.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {result.meta.last_page >
      1 ? (
        <nav
          className={styles.pagination}
          aria-label="صفحه‌بندی موجودی"
        >
          {result.meta.current_page >
          1 ? (
            <Link
              href={pageHref(
                result.meta
                  .current_page -
                  1,
                search,
                stockStatus,
              )}
            >
              قبلی →
            </Link>
          ) : (
            <span />
          )}

          <span>
            صفحه{" "}
            <bdi dir="ltr">{
              result.meta
                .current_page
            }</bdi>{" "}
            از{" "}
            <bdi dir="ltr">{
              result.meta
                .last_page
            }</bdi>
          </span>

          {result.meta.current_page <
          result.meta.last_page ? (
            <Link
              href={pageHref(
                result.meta
                  .current_page +
                  1,
                search,
                stockStatus,
              )}
            >
              بعدی ←
            </Link>
          ) : (
            <span />
          )}
        </nav>
      ) : null}
    </section>
  );
}