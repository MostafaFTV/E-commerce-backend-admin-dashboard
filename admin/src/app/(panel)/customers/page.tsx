import { AdminSelect } from "@/components/AdminSelect";

import Link from "next/link";

import {
  notFound,
  redirect,
} from "next/navigation";

import {
  AdminCustomerRequestError,
  getAdminCustomers,
  type AdminCustomerStatus,
} from "@/lib/admin-customers";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "./customers.module.css";

type SearchParam =
  | string
  | string[]
  | undefined;

interface CustomersPageProps {
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
    AdminCustomerStatus,
    string
  > = {
    active:
      "فعال",

    inactive:
      "غیرفعال",

    suspended:
      "تعلیق‌شده",
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
  firstName:
    string | null,
  lastName:
    string | null,
): string {
  return (
    [
      firstName,
      lastName,
    ]
      .filter(Boolean)
      .join(" ")
      .trim() ||
    "مشتری بدون نام"
  );
}

function formatDate(
  value:
    string | null,
): string {
  if (!value) {
    return "—";
  }

  const parsed =
    new Date(value);

  if (
    Number.isNaN(
      parsed.getTime(),
    )
  ) {
    return value;
  }

  return new Intl.DateTimeFormat(
    "fa-IR",
    {
      dateStyle:
        "medium",
    },
  ).format(parsed);
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
    ? `/customers?${query}`
    : "/customers";
}

export default async function CustomersPage({
  searchParams,
}: CustomersPageProps) {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "customers.view",
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
      await getAdminCustomers({
        search,
        status,
        page,
      });
  } catch (error) {
    if (
      error instanceof
        AdminCustomerRequestError &&
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
            مشتریان
          </h1>
        </div>

        <p
          className="admin-muted"
        >
          حساب مشتریان، سفارش‌ها و آدرس‌های ذخیره‌شده را بررسی کنید.
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
            placeholder="جستجوی نام، ایمیل یا شماره تماس"
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

            {result.meta
              .available_statuses
              .map(
                (
                  value,
                ) => (
                  <option
                    key={
                      value
                    }
                    value={
                      value
                    }
                  >
                    {
                      statusLabels[
                        value
                      ]
                    }
                  </option>
                ),
              )}
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
          status ? (
            <Link
              href="/customers"
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
            result.meta
              .total
          }{" "}
          مشتری
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
                مشتری
              </th>

              <th>
                اطلاعات تماس
              </th>

              <th>
                وضعیت
              </th>

              <th>
                سفارش‌ها
              </th>

              <th>
                آدرس‌ها
              </th>

              <th>
                تاریخ عضویت
              </th>

              <th />
            </tr>
          </thead>

          <tbody>
            {result.data.length ? (
              result.data.map(
                (
                  customer,
                ) => (
                  <tr
                    key={
                      customer.id
                    }
                  >
                    <td>
                      <strong
                        className={
                          styles.customerName
                        }
                      >
                        {customerName(
                          customer.first_name,
                          customer.last_name,
                        )}
                      </strong>

                      <span
                        className={
                          styles.customerId
                        }
                      >
                        مشتری شماره
                        {
                          customer.id
                        }
                      </span>
                    </td>

                    <td>
                      <div
                        className={
                          styles.contact
                        }
                      >
                        <span>
                          {customer.email ||
                            "—"}
                        </span>

                        <span>
                          {customer.phone ||
                            "—"}
                        </span>
                      </div>
                    </td>

                    <td>
                      <span
                        className={
                          styles.status
                        }
                        data-status={
                          customer.status
                        }
                      >
                        {
                          statusLabels[
                            customer.status
                          ]
                        }
                      </span>
                    </td>

                    <td>
                      {
                        customer.orders_count ??
                        0
                      }
                    </td>

                    <td>
                      {
                        customer.addresses_count ??
                        0
                      }
                    </td>

                    <td>
                      {formatDate(
                        customer.created_at,
                      )}
                    </td>

                    <td>
                      <Link
                        href={`/customers/${customer.id}`}
                        className={
                          styles.viewButton
                        }
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
                  colSpan={7}
                  className={
                    styles.empty
                  }
                >
                  مشتری مطابق فیلترهای انتخاب‌شده یافت نشد.
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
                status,
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