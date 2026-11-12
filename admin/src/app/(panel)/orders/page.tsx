import { AdminSelect } from "@/components/AdminSelect";

import Link from "next/link";

import {
  notFound,
  redirect,
} from "next/navigation";

import {
  AdminOrderRequestError,
  getAdminOrders,
  type AdminOrder,
  type AdminOrderStatus,
} from "@/lib/admin-orders";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "./orders.module.css";

type SearchParam =
  | string
  | string[]
  | undefined;

interface OrdersPageProps {
  searchParams:
    Promise<
      Record<
        string,
        SearchParam
      >
    >;
}

const statusLabels:
  Record<
    AdminOrderStatus,
    string
  > = {
    pending_payment:
      "در انتظار پرداخت",

    paid:
      "پرداخت شده",

    processing:
      "در حال انجام",

    shipped:
      "ارسال شده",

    delivered:
      "تحویل داده‌شده",

    cancelled:
      "لغو شده",

    returned:
      "مرجوعی",
  };

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

function customerName(
  order:
    AdminOrder,
): string {
  const first =
    order
      .shipping_address
      ?.first_name
      ?.trim() ?? "";

  const last =
    order
      .shipping_address
      ?.last_name
      ?.trim() ?? "";

  return (
    `${first} ${last}`.trim() ||
    "مشتری"
  );
}

function paymentStatus(
  order:
    AdminOrder,
): string {
  const payments =
    order.payments ?? [];

  if (!payments.length) {
    return "بدون پرداخت";
  }

  return [
    ...payments,
  ].sort(
    (a, b) =>
      b.id - a.id,
  )[0].status.replaceAll(
    "_",
    " ",
  );
}

function money(
  amount:
    number,
  currency:
    string,
): string {
  return `${new Intl.NumberFormat(
    "fa-IR",
    {
      maximumFractionDigits: 2,
    },
  ).format(amount)} ${currency}`;
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
    "fa-IR",
    {
      dateStyle:
        "medium",

      timeStyle:
        "short",
    },
  ).format(date);
}

function statusTone(
  status:
    AdminOrderStatus,
): string {
  if (
    status ===
      "cancelled" ||
    status ===
      "returned"
  ) {
    return "danger";
  }

  if (
    status ===
      "pending_payment"
  ) {
    return "warning";
  }

  if (
    status ===
      "delivered"
  ) {
    return "success";
  }

  return "active";
}

function pageHref(
  page:
    number,
  search:
    string,
  status:
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

  if (status) {
    params.set(
      "status",
      status,
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
    ? `/orders?${query}`
    : "/orders";
}

export default async function OrdersPage({
  searchParams,
}: OrdersPageProps) {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "orders.view",
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

  const status =
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
      await getAdminOrders({
        orderNumber:
          search,

        status,

        page,

        perPage:
          20,
      });
  } catch (error) {
    if (
      error instanceof
        AdminOrderRequestError &&
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
      className="admin-dashboard"
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
            فروش
          </p>

          <h1>
            سفارش‌ها
          </h1>
        </div>

        <p
          className="admin-muted"
        >
          بازبینی سفارش‌ها، پرداخت‌ها و وضعیت تحویل.
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
          <input
            type="search"
            name="search"
            defaultValue={
              search
            }
            placeholder="جست‌وجوی شماره سفارش"
            aria-label="جست‌وجوی شماره سفارش"
          />

          <AdminSelect
            name="status"
            defaultValue={
              status
            }
          >
            <option value="">
              همه وضعیت‌ها
            </option>

            {Object.entries(
              statusLabels,
            ).map(
              ([
                value,
                label,
              ]) => (
                <option
                  key={
                    value
                  }
                  value={
                    value
                  }
                >
                  {label}
                </option>
              ),
            )}
          </AdminSelect>

          <button
            type="submit"
            className="admin-button admin-button-primary"
          >
            اعمال فیلتر
          </button>

          {search ||
          status ? (
            <Link
              href="/orders"
              className={
                styles.clear
              }
            >
              پاک‌کردن
            </Link>
          ) : null}
        </form>

        <span
          className={
            styles.count
          }
        >
          {result.meta.total}{" "}
          سفارش
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
                سفارش
              </th>

              <th>
                مشتری
              </th>

              <th>
                اقلام
              </th>

              <th>
                پرداخت
              </th>

              <th>
                جمع کل
              </th>

              <th>
                وضعیت
              </th>

              <th>
                تاریخ
              </th>

              <th />
            </tr>
          </thead>

          <tbody>
            {result.data.length ? (
              result.data.map(
                (order) => (
                  <tr
                    key={
                      order.id
                    }
                  >
                    <td>
                      <strong
                        className={
                          styles.orderNumber
                        }
                      >
                        {
                          order.order_number
                        }
                      </strong>
                    </td>

                    <td>
                      <div
                        className={
                          styles.customer
                        }
                      >
                        <strong>
                          {customerName(
                            order,
                          )}
                        </strong>

                        <span>
                          {order
                            .shipping_address
                            ?.phone ||
                            "—"}
                        </span>
                      </div>
                    </td>

                    <td>
                      {
                        order.items
                          ?.length ??
                        0
                      }
                    </td>

                    <td>
                      <span
                        className={
                          styles.payment
                        }
                      >
                        {paymentStatus(
                          order,
                        )}
                      </span>
                    </td>

                    <td>
                      <strong>
                        {money(
                          order.total,
                          order.currency,
                        )}
                      </strong>
                    </td>

                    <td>
                      <span
                        className={
                          styles.status
                        }
                        data-tone={statusTone(
                          order.status,
                        )}
                      >
                        <span
                          className={
                            styles.dot
                          }
                        />

                        {
                          statusLabels[
                            order.status
                          ]
                        }
                      </span>
                    </td>

                    <td>
                      {formatDate(
                        order.created_at,
                      )}
                    </td>

                    <td>
                      <Link
                        href={`/orders/${order.id}`}
                        className="admin-button"
                      >
                        مشاهده
                      </Link>
                    </td>
                  </tr>
                ),
              )
            ) : (
              <tr>
                <td
                  colSpan={8}
                  className={
                    styles.empty
                  }
                >
                  هیچ سفارشی با فیلترهای انتخاب‌شده مطابقت ندارد.
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
                status,
              )}
            >
              ← قبلی
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
                status,
              )}
            >
              بعدی →
            </Link>
          ) : null}
        </nav>
      ) : null}
    </section>
  );
}