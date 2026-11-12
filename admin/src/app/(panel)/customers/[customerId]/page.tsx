import { adminStatusLabel } from "@/lib/admin-persian-labels";
import Link from "next/link";

import {
  notFound,
  redirect,
} from "next/navigation";

import {
  CustomerEditor,
} from "@/components/CustomerEditor";

import {
  AdminCustomerRequestError,
  getAdminCustomer,
  type AdminCustomerStatus,
} from "@/lib/admin-customers";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "../customers.module.css";

interface CustomerPageProps {
  params:
    Promise<{
      customerId:
        string;
    }>;
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

function fullName(
  first:
    string | null,
  last:
    string | null,
): string {
  return (
    [
      first,
      last,
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

      timeStyle:
        "short",
    },
  ).format(parsed);
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

function humanStatus(
  value:
    string,
): string { return adminStatusLabel(value); }

export default async function CustomerPage({
  params,
}: CustomerPageProps) {
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

  const {
    customerId,
  } =
    await params;

  const id =
    Number(
      customerId,
    );

  if (
    !Number.isInteger(id) ||
    id <= 0
  ) {
    notFound();
  }

  let customer;

  try {
    customer =
      await getAdminCustomer(
        id,
      );
  } catch (error) {
    if (
      error instanceof
        AdminCustomerRequestError
    ) {
      if (
        error.status ===
        401
      ) {
        redirect(
          "/login",
        );
      }

      if (
        error.status ===
        404
      ) {
        notFound();
      }
    }

    throw error;
  }

  const name =
    fullName(
      customer.first_name,
      customer.last_name,
    );

  return (
    <section
      className="admin-dashboard"
    >
      <header
        className={
          styles.detailHeader
        }
      >
        <div>
          <p
            className="admin-eyebrow"
          >
            مشتری
          </p>

          <h1>
            {name}
          </h1>

          <p
            className="admin-muted"
          >
            مشتری شماره
            {customer.id}
            {" · "}
            تاریخ عضویت{" "}
            {formatDate(
              customer.created_at,
            )}
          </p>
        </div>

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
      </header>

      <div
        className={
          styles.summary
        }
      >
        <div>
          <span>
            سفارش‌ها
          </span>

          <strong>
            {
              customer.orders
                .length
            }
          </strong>
        </div>

        <div>
          <span>
            آدرس‌ها
          </span>

          <strong>
            {
              customer.addresses
                .length
            }
          </strong>
        </div>

        <div>
          <span>
            ایمیل
          </span>

          <strong>
            {customer.email_verified_at
              ? "تأییدشده"
              : "تأییدنشده"}
          </strong>
        </div>

        <div>
          <span>
            شماره تماس
          </span>

          <strong>
            {customer.phone_verified_at
              ? "تأییدشده"
              : "تأییدنشده"}
          </strong>
        </div>
      </div>

      <div
        className={
          styles.detailGrid
        }
      >
        <div
          className={
            styles.mainColumn
          }
        >
          <section
            className={
              styles.card
            }
          >
            <div
              className={
                styles.sectionHeader
              }
            >
              <div>
                <p
                  className="admin-eyebrow"
                >
                  سفارش‌ها
                </p>

                <h2>
                  تاریخچه سفارش‌ها
                </h2>
              </div>

              <span>
                {
                  customer.orders
                    .length
                }{" "}
                سفارش
              </span>
            </div>

            {customer.orders
              .length ? (
              <div
                className={
                  styles.ordersTable
                }
              >
                <table>
                  <thead>
                    <tr>
                      <th>
                        سفارش
                      </th>

                      <th>
                        وضعیت
                      </th>

                      <th>
                        مبلغ کل
                      </th>

                      <th>
                        تاریخ
                      </th>

                      <th />
                    </tr>
                  </thead>

                  <tbody>
                    {customer.orders.map(
                      (
                        order,
                      ) => (
                        <tr
                          key={
                            order.id
                          }
                        >
                          <td>
                            <strong>
                              {
                                order.order_number
                              }
                            </strong>
                          </td>

                          <td>
                            {humanStatus(
                              order.status,
                            )}
                          </td>

                          <td>
                            {money(
                              order.total,
                              order.currency,
                            )}
                          </td>

                          <td>
                            {formatDate(
                              order.created_at,
                            )}
                          </td>

                          <td>
                            <Link
                              href={`/orders/${order.id}?returnTo=${encodeURIComponent(`/customers/${customer.id}`)}`}
                              className={
                                styles.inlineLink
                              }
                            >
                              مشاهده
                            </Link>
                          </td>
                        </tr>
                      ),
                    )}
                  </tbody>
                </table>
              </div>
            ) : (
              <p
                className="admin-muted"
              >
                این مشتری هنوز سفارشی ثبت نکرده است.
              </p>
            )}
          </section>

          <section
            className={
              styles.card
            }
          >
            <div
              className={
                styles.sectionHeader
              }
            >
              <div>
                <p
                  className="admin-eyebrow"
                >
                  آدرس‌ها
                </p>

                <h2>
                  آدرس‌های ذخیره‌شده
                </h2>
              </div>
            </div>

            {customer.addresses
              .length ? (
              <div
                className={
                  styles.addressGrid
                }
              >
                {customer.addresses.map(
                  (
                    address,
                  ) => (
                    <article
                      key={
                        address.id
                      }
                      className={
                        styles.addressCard
                      }
                    >
                      <div
                        className={
                          styles.addressTitle
                        }
                      >
                        <strong>
                          {address.title ||
                            "آدرس"}
                        </strong>

                        {address.is_default ? (
                          <span>
                            پیش‌فرض
                          </span>
                        ) : null}
                      </div>

                      {address.recipient_name ? (
                        <p>
                          {
                            address.recipient_name
                          }
                        </p>
                      ) : null}

                      {address.phone ? (
                        <p>
                          {
                            address.phone
                          }
                        </p>
                      ) : null}

                      {address.address ? (
                        <p>
                          {
                            address.address
                          }
                        </p>
                      ) : null}

                      <p>
                        {[
                          address.city,
                          address.state,
                          address.postal_code,
                        ]
                          .filter(
                            Boolean,
                          )
                          .join(
                            ", ",
                          )}
                      </p>

                      {address.country ? (
                        <p>
                          {
                            address.country
                          }
                        </p>
                      ) : null}
                    </article>
                  ),
                )}
              </div>
            ) : (
              <p
                className="admin-muted"
              >
                آدرسی ثبت نشده است.
              </p>
            )}
          </section>
        </div>

        <aside
          className={
            styles.sideColumn
          }
        >
          <section
            className={styles.card}
            dir="rtl"
            lang="fa"
          >
            <div className={styles.sectionHeader}>
              <div>
                <p className="admin-eyebrow">
                  پروفایل مشتری
                </p>
                <h2>پروفایل و علاقه‌مندی‌ها</h2>
              </div>
            </div>

            <dl className={styles.details}>
              <div>
                <dt>تاریخ تولد</dt>
                <dd dir="rtl">
                  {customer.birth_date
                    ? new Intl.DateTimeFormat(
                        "fa-IR-u-ca-persian",
                        {
                          year: "numeric",
                          month: "long",
                          day: "numeric",
                          timeZone: "UTC",
                        },
                      ).format(
                        new Date(
                          `${customer.birth_date}T12:00:00Z`,
                        ),
                      )
                    : "—"}
                </dd>
              </div>

              <div>
                <dt>دریافت پیامک تبلیغاتی</dt>
                <dd>
                  {customer.marketing_sms
                    ? "فعال"
                    : "غیرفعال"}
                </dd>
              </div>

              <div>
                <dt>دسته‌بندی‌های موردعلاقه</dt>
                <dd>
                  {customer.preferred_categories.length
                    ? customer.preferred_categories
                        .map(
                          (category) =>
                            category.name_fa ||
                            category.name_en ||
                            category.slug,
                        )
                        .join("، ")
                    : "انتخاب نشده"}
                </dd>
              </div>
            </dl>
          </section>
          <CustomerEditor
            customer={{
              id:
                customer.id,

              first_name:
                customer.first_name,

              last_name:
                customer.last_name,

              email:
                customer.email,

              phone:
                customer.phone,

              status:
                customer.status,
            }}
            canManage={admin.permissions.includes(
              "customers.manage",
            )}
          />

          <section
            className={
              styles.card
            }
          >
            <p
              className="admin-eyebrow"
            >
              اطلاعات تماس
            </p>

            <h2>
              اطلاعات حساب
            </h2>

            <dl
              className={
                styles.details
              }
            >
              <div>
                <dt>
                  ایمیل
                </dt>

                <dd>
                  {customer.email ||
                    "—"}
                </dd>
              </div>

              <div>
                <dt>
                  شماره تماس
                </dt>

                <dd>
                  {customer.phone ||
                    "—"}
                </dd>
              </div>

              <div>
                <dt>
                  تأیید ایمیل
                </dt>

                <dd>
                  {formatDate(
                    customer.email_verified_at ??
                      null,
                  )}
                </dd>
              </div>

              <div>
                <dt>
                  تأیید شماره تماس
                </dt>

                <dd>
                  {formatDate(
                    customer.phone_verified_at ??
                      null,
                  )}
                </dd>
              </div>

              <div>
                <dt>
                  آخرین به‌روزرسانی
                </dt>

                <dd>
                  {formatDate(
                    customer.updated_at,
                  )}
                </dd>
              </div>
            </dl>
          </section>
        </aside>
      </div>
    </section>
  );
}