import {
  redirect,
} from "next/navigation";

import {
  InventoryHealth,
  OrdersTrendChart,
  StatusDistribution,
} from "@/components/AdminDashboardCharts";

import {
  getAdminDashboard,
} from "@/lib/admin-dashboard";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "./dashboard.module.css";

function number(
  value: number,
): string {
  return new Intl.NumberFormat(
    "fa-IR",
  ).format(value);
}

function money(
  value: number,
): string {
  return new Intl.NumberFormat(
    "fa-IR",
    {
      maximumFractionDigits: 0,
    },
  ).format(value);
}

const statusLabels: Record<string, string> = {
  pending_payment: "در انتظار پرداخت",
  paid: "پرداخت‌شده",
  processing: "در حال پردازش",
  shipped: "ارسال‌شده",
  delivered: "تحویل‌شده",
  cancelled: "لغوشده",
  returned: "مرجوع‌شده",
  active: "فعال",
  inactive: "غیرفعال",
  draft: "پیش‌نویس",
  published: "منتشرشده",
  archived: "بایگانی‌شده",
  visible: "قابل‌نمایش",
  hidden: "مخفی",
  in_stock: "موجود",
  out_of_stock: "ناموجود",
  created: "ایجاد",
  updated: "ویرایش",
  deleted: "حذف",
  restored: "بازیابی",
  login: "ورود",
  logout: "خروج",
};

function humanize(
  value: string,
): string {
  if (statusLabels[value]) {
    return statusLabels[value];
  }

  return value
    .replace(
      /_/g,
      " ",
    )
    .replace(
      /\b\w/g,
      (character) =>
        character.toUpperCase(),
    );
}

function activityTime(
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
    "fa-IR-u-ca-persian",
    {
      day: "2-digit",
      month: "short",
      hour: "2-digit",
      minute: "2-digit",
    },
  ).format(date);
}

export default async function DashboardPage() {
  const [
    admin,
    dashboard,
  ] =
    await Promise.all([
      getCurrentAdmin(),
      getAdminDashboard(),
    ]);

  if (!admin) {
    redirect("/login");
  }

  const cards:
    {
      label: string;
      value: string;
      detail?: string;
    }[] = [];

  if (dashboard?.orders) {
    cards.push({
      label:
        "کل سفارش‌ها",
      value:
        number(
          dashboard
            .orders
            .total,
        ),
      detail:
        "تمام سفارش‌های ثبت‌شده",
    });

    const pending =
      dashboard
        .orders
        .statuses[
          "pending_payment"
        ] ?? 0;

    cards.push({
      label:
        "در انتظار پرداخت",
      value:
        number(pending),
      detail:
        "در انتظار پرداخت مشتری",
    });

    if (
      dashboard.access
        .order_financials &&
      dashboard.orders
        .paid_orders !==
        undefined
    ) {
      cards.push({
        label:
          "سفارش‌های پرداخت‌شده",
        value:
          number(
            dashboard
              .orders
              .paid_orders,
          ),
        detail:
          "سفارش‌های دارای پرداخت موفق",
      });
    }
  }

  if (
    dashboard?.customers
  ) {
    cards.push({
      label:
        "مشتریان",
      value:
        number(
          dashboard
            .customers
            .total,
        ),
      detail:
        `${
          number(
            dashboard
              .customers
              .new_last_30_days,
          )
        } مشتری جدید در ۳۰ روز گذشته`,
    });
  }

  if (
    dashboard?.products
  ) {
    cards.push({
      label:
        "محصولات",
      value:
        number(
          dashboard
            .products
            .total,
        ),
      detail:
        "محصولات قابل‌نمایش",
    });
  }

  if (
    dashboard?.inventory
  ) {
    cards.push({
      label:
        "موجودی کم",
      value:
        number(
          dashboard
            .inventory
            .low_stock,
        ),
      detail:
        "اقلام انبار",
    });

    cards.push({
      label:
        "ناموجود",
      value:
        number(
          dashboard
            .inventory
            .out_of_stock,
        ),
      detail:
        "نیازمند بررسی",
    });
  }

  if (
    dashboard?.categories
  ) {
    cards.push({
      label:
        "دسته‌بندی‌ها",
      value:
        number(
          dashboard
            .categories
            .total,
        ),
    });
  }

  if (
    dashboard?.collections
  ) {
    cards.push({
      label:
        "کالکشن‌ها",
      value:
        number(
          dashboard
            .collections
            .total,
        ),
    });
  }

  const hasOperationalData =
    Boolean(
      dashboard &&
        (
          dashboard.orders ||
          dashboard.customers ||
          dashboard.products ||
          dashboard.inventory ||
          dashboard.categories ||
          dashboard.collections ||
          dashboard.activity_logs
        ),
    );

  return (
    <section
      dir="rtl"
      lang="fa"
      className="admin-dashboard"
    >
      <header
        className="admin-page-header"
      >
        <div>
          <p
            className="admin-eyebrow"
          >
            داشبورد
          </p>

          <h1>
            خوش آمدید،{" "}
            {admin.first_name}
          </h1>
        </div>

        <p
          className="admin-muted"
        >
          نمای کلی عملیات براساس سطح دسترسی شما.
        </p>
      </header>

      {!dashboard ? (
        <section
          className={
            styles.state
          }
        >
          <strong>
            اطلاعات داشبورد در دسترس نیست
          </strong>

          <p>
            دریافت اطلاعات داشبورد ناموفق بود.
          </p>
        </section>
      ) : !hasOperationalData ? (
        <section
          className={
            styles.state
          }
        >
          <strong>
            دسترسی به گزارش‌ها ندارید
          </strong>

          <p>
            سطح دسترسی شما شامل شاخص‌های مدیریتی داشبورد نیست.
          </p>
        </section>
      ) : (
        <>
          {cards.length ? (
            <div
              className={
                styles.metrics
              }
            >
              {cards.map(
                (
                  card,
                  index,
                ) => (
                  <article
                    key={`${card.label}-${index}`}
                    className={
                      styles.metric
                    }
                  >
                    <span>
                      {card.label}
                    </span>

                    <strong>
                      {card.value}
                    </strong>

                    <small>
                      {card.detail ?? "\u00A0"}
                    </small>
                  </article>
                ),
              )}
            </div>
          ) : null}

          {dashboard.access
            .order_financials &&
          dashboard.orders
            ?.paid_revenue
            ?.length ? (
            <section
              className={
                styles.revenueSection
              }
            >
              <div
                className={
                  styles.sectionHeading
                }
              >
                <div>
                  <p
                    className="admin-eyebrow"
                  >
                    مالی
                  </p>

                  <h2>
                    درآمد پرداخت‌شده
                  </h2>
                </div>

                <span>
                  فقط پرداخت‌های موفق
                </span>
              </div>

              <div
                className={
                  styles.revenueGrid
                }
              >
                {dashboard.orders
                  .paid_revenue
                  .map(
                    (
                      item,
                    ) => (
                      <article
                        key={
                          item.currency
                        }
                        className={
                          styles.revenueCard
                        }
                      >
                        <span>
                          {
                            item.currency
                          }
                        </span>

                        <strong>
                          {money(
                            item.total,
                          )}
                        </strong>
                      </article>
                    ),
                  )}
              </div>
            </section>
          ) : null}

          <div
            className={
              styles.charts
            }
          >
            {dashboard.orders ? (
              <article
                className={
                  styles.panel
                }
              >
                <div
                  className={
                    styles.sectionHeading
                  }
                >
                  <div>
                    <p
                      className="admin-eyebrow"
                    >
                      سفارش‌ها
                    </p>

                    <h2>
                      روند شش‌ماهه
                    </h2>
                  </div>

                  <span>
                    تعداد سفارش‌ها
                  </span>
                </div>

                <OrdersTrendChart
                  points={
                    dashboard
                      .orders
                      .monthly
                  }
                />
              </article>
            ) : null}

            {dashboard.orders ? (
              <article
                className={
                  styles.panel
                }
              >
                <div
                  className={
                    styles.sectionHeading
                  }
                >
                  <div>
                    <p
                      className="admin-eyebrow"
                    >
                      سفارش‌ها
                    </p>

                    <h2>
                      توزیع وضعیت سفارش‌ها
                    </h2>
                  </div>
                </div>

                <StatusDistribution
                  statuses={
                    dashboard
                      .orders
                      .statuses
                  }
                />
              </article>
            ) : null}

            {dashboard.inventory ? (
              <article
                className={
                  styles.panel
                }
              >
                <div
                  className={
                    styles.sectionHeading
                  }
                >
                  <div>
                    <p
                      className="admin-eyebrow"
                    >
                      انبار
                    </p>

                    <h2>
                      وضعیت موجودی
                    </h2>
                  </div>

                  <span>
                    {number(
                      dashboard
                        .inventory
                        .available_units,
                    )}{" "}
                    واحد قابل‌فروش
                  </span>
                </div>

                <InventoryHealth
                  inventory={
                    dashboard
                      .inventory
                  }
                />

                <div
                  className={
                    styles.inventoryNumbers
                  }
                >
                  <div>
                    <span>
                      موجودی فیزیکی
                    </span>

                    <strong>
                      {number(
                        dashboard
                          .inventory
                          .physical_units,
                      )}
                    </strong>
                  </div>

                  <div>
                    <span>
                      رزروشده
                    </span>

                    <strong>
                      {number(
                        dashboard
                          .inventory
                          .reserved_units,
                      )}
                    </strong>
                  </div>

                  <div>
                    <span>
                      قابل‌فروش
                    </span>

                    <strong>
                      {number(
                        dashboard
                          .inventory
                          .available_units,
                      )}
                    </strong>
                  </div>
                </div>
              </article>
            ) : null}

            {dashboard.products ? (
              <article
                className={
                  styles.panel
                }
              >
                <div
                  className={
                    styles.sectionHeading
                  }
                >
                  <div>
                    <p
                      className="admin-eyebrow"
                    >
                      محصولات
                    </p>

                    <h2>
                      وضعیت محصولات
                    </h2>
                  </div>
                </div>

                <div
                  className={
                    styles.statusCards
                  }
                >
                  {Object.entries(
                    dashboard
                      .products
                      .statuses,
                  ).map(
                    ([
                      status,
                      count,
                    ]) => (
                      <div
                        key={
                          status
                        }
                      >
                        <span>
                          {humanize(
                            status,
                          )}
                        </span>

                        <strong>
                          {number(
                            count,
                          )}
                        </strong>
                      </div>
                    ),
                  )}
                </div>
              </article>
            ) : null}
          </div>

          {dashboard.activity_logs
            ?.length ? (
            <section
              className={
                styles.activity
              }
            >
              <div
                className={
                  styles.sectionHeading
                }
              >
                <div>
                  <p
                    className="admin-eyebrow"
                  >
                    فعالیت‌ها
                  </p>

                  <h2>
                    آخرین فعالیت‌های مدیریتی
                  </h2>
                </div>
              </div>

              <div
                className={
                  styles.activityList
                }
              >
                {dashboard
                  .activity_logs
                  .map(
                    (
                      item,
                    ) => (
                      <article
                        key={
                          item.id
                        }
                      >
                        <div>
                          <strong>
                            {humanize(
                              item.action,
                            )}
                          </strong>

                          <span>
                            {item.description ??
                              "فعالیت مدیریتی"}
                          </span>
                        </div>

                        <div
                          className={
                            styles.activityMeta
                          }
                        >
                          <span>
                            {item.user
                              ?.name ||
                              "سیستم"}
                          </span>

                          <time>
                            {activityTime(
                              item.created_at,
                            )}
                          </time>
                        </div>
                      </article>
                    ),
                  )}
              </div>
            </section>
          ) : null}
        </>
      )}
    </section>
  );
}