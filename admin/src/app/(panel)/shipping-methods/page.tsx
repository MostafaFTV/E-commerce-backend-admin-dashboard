import { AdminSelect } from "@/components/AdminSelect";

import Link from "next/link";

import {
  notFound,
  redirect,
} from "next/navigation";

import {
  ShippingMethodDeleteButton,
} from "@/components/ShippingMethodDeleteButton";

import {
  AdminShippingMethodRequestError,
  getAdminShippingMethods,
} from "@/lib/admin-shipping-methods";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "./shipping-methods.module.css";

type SearchParam =
  | string
  | string[]
  | undefined;

interface PageProps {
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
  return Array.isArray(
    value,
  )
    ? value[0] ?? ""
    : value ?? "";
}

function pageHref(
  page:
    number,
  search:
    string,
  active:
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

  if (active) {
    params.set(
      "active",
      active,
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
    ? `/shipping-methods?${query}`
    : "/shipping-methods";
}

function formatPrice(
  value:
    number,
): string {
  return new Intl.NumberFormat(
    "fa-IR",
    {
      maximumFractionDigits:
        2,
    },
  ).format(value);
}

export default async function ShippingMethodsPage({
  searchParams,
}: PageProps) {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "shipping_methods.view",
    )
  ) {
    notFound();
  }

  const canCreate =
    admin.permissions.includes(
      "shipping_methods.create",
    );

  const canUpdate =
    admin.permissions.includes(
      "shipping_methods.update",
    );

  const canDelete =
    admin.permissions.includes(
      "shipping_methods.delete",
    );

  const params =
    await searchParams;

  const search =
    firstParam(
      params.search,
    ).trim();

  const active =
    firstParam(
      params.active,
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
      await getAdminShippingMethods({
        search,
        active,
        page,
      });
  } catch (error) {
    if (
      error instanceof
        AdminShippingMethodRequestError &&
      error.status ===
        401
    ) {
      redirect(
        "/login",
      );
    }

    throw error;
  }

  return (
    <section
      className="admin-dashboard"
    >
      <header
        className="admin-page-header"
      >
        <div>
          <p
            className="admin-eyebrow"
          >
            عملیات
          </p>

          <h1>
            روش‌های ارسال
          </h1>
        </div>

        <div
          className={
            styles.headerActions
          }
        >
          <p
            className="admin-muted"
          >
            روش‌های ارسال، هزینه‌ها و زمان تقریبی تحویل را مدیریت کنید.
          </p>

          {canCreate ? (
            <Link
              href="/shipping-methods/new"
              className="admin-button admin-button-primary"
            >
              افزودن روش
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
          method="get"
          className={
            styles.filters
          }
        >
          <input
            type="search"
            name="search"
            defaultValue={
              search
            }
            placeholder="جستجوی روش‌های ارسال"
          />

          <AdminSelect
            name="active"
            defaultValue={
              active
            }
          >
            <option value="">
              همه وضعیت‌ها
            </option>

            <option value="1">
              فعال
            </option>

            <option value="0">
              غیرفعال
            </option>
          </AdminSelect>

          <button
            type="submit"
            className={
              styles.filterButton
            }
          >
            اعمال فیلترها
          </button>

          {search ||
          active ? (
            <Link
              href="/shipping-methods"
              className={
                styles.clear
              }
            >
              پاک کردن
            </Link>
          ) : null}
        </form>

        <span
          className={
            styles.count
          }
        >
          {
            result.meta.total
          }{" "}
          روش ارسال
          {""}
        </span>
      </div>

      <div
        className={
          styles.tableFrame
        }
      >
        <table
          className={
            styles.table
          }
        >
          <thead>
            <tr>
              <th>
                روش ارسال
              </th>

              <th>
                قیمت
              </th>

              <th>
                زمان تقریبی تحویل
              </th>

              <th>
                وضعیت
              </th>

              <th>
                سفارش
              </th>

              <th />
            </tr>
          </thead>

          <tbody>
            {result.data.length ? (
              result.data.map(
                (method) => (
                  <tr
                    key={
                      method.id
                    }
                  >
                    <td>
                      <strong
                        className={
                          styles.methodName
                        }
                      >
                        {
                          method.name
                        }
                      </strong>

                      <span
                        className={
                          styles.description
                        }
                      >
                        {method.description ||
                          "—"}
                      </span>
                    </td>

                    <td>
                      <strong>
                        {formatPrice(
                          method.price,
                        )}
                      </strong>
                    </td>

                    <td>
                      {method.estimated_days ||
                        "—"}
                    </td>

                    <td>
                      <span
                        className={
                          styles.status
                        }
                        data-active={
                          method.is_active
                            ? "true"
                            : "false"
                        }
                      >
                        {method.is_active
                          ? "فعال"
                          : "غیرفعال"}
                      </span>
                    </td>

                    <td>
                      {
                        method.sort_order
                      }
                    </td>

                    <td>
                      <div
                        className={
                          styles.actions
                        }
                      >
                        {canUpdate ? (
                          <Link
                            href={`/shipping-methods/${method.id}/edit`}
                            className="admin-button"
                          >
                            ویرایش
                          </Link>
                        ) : null}

                        {canDelete ? (
                          <ShippingMethodDeleteButton
                            methodId={
                              method.id
                            }
                            methodName={
                              method.name
                            }
                          />
                        ) : null}
                      </div>
                    </td>
                  </tr>
                ),
              )
            ) : (
              <tr>
                <td
                  colSpan={6}
                  className={
                    styles.empty
                  }
                >
                  روش ارسالی مطابق فیلترهای انتخاب‌شده یافت نشد.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {result.meta.last_page >
      1 ? (
        <nav
          className="admin-pagination"
        >
          {result.meta.current_page >
          1 ? (
            <Link
              href={pageHref(
                result.meta
                  .current_page -
                  1,
                search,
                active,
              )}
            >
              → قبلی
            </Link>
          ) : null}

          <span>
            صفحه{" "}
            {
              result.meta
                .current_page
            }{" "}
            از{" "}
            {
              result.meta
                .last_page
            }
          </span>

          {result.meta.current_page <
          result.meta.last_page ? (
            <Link
              href={pageHref(
                result.meta
                  .current_page +
                  1,
                search,
                active,
              )}
            >
              بعدی ←
            </Link>
          ) : null}
        </nav>
      ) : null}
    </section>
  );
}