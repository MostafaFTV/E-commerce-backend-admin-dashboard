import {
  notFound,
  redirect,
} from "next/navigation";

import {
  OrderActions,
} from "@/components/OrderActions";

import {
  AdminOrderRequestError,
  getAdminOrder,
  type AdminOrderAddress,
  type AdminOrderStatus,
} from "@/lib/admin-orders";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "../orders.module.css";

interface OrderPageProps {
  params:
    Promise<{
      orderId:
        string;
    }>;
}

const labels:
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

function date(
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

function addressLines(
  address:
    AdminOrderAddress | null,
): string[] {
  if (!address) {
    return [];
  }

  const name =
    [
      address.first_name,
      address.last_name,
    ]
      .filter(Boolean)
      .join(" ");

  const locality =
    [
      address.city,
      address.state,
      address.postal_code,
    ]
      .filter(Boolean)
      .join(", ");

  return [
    name,
    address.phone,
    address.address_line_1,
    address.address_line_2,
    locality,
    address.country,
  ].filter(
    (
      value,
    ): value is string =>
      typeof value ===
        "string" &&
      value.trim() !== "",
  );
}

export default async function OrderPage({
  params,
}: OrderPageProps) {
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

  const {
    orderId,
  } =
    await params;

  const id =
    Number(orderId);

  if (
    !Number.isInteger(id) ||
    id <= 0
  ) {
    notFound();
  }

  let order;

  try {
    order =
      await getAdminOrder(
        id,
      );
  } catch (error) {
    if (
      error instanceof
        AdminOrderRequestError
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

  const shipping =
    addressLines(
      order.shipping_address,
    );

  const billing =
    addressLines(
      order.billing_address,
    );

  return (
    <section
      className="admin-dashboard"
      dir="rtl"
      lang="fa"
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
            سفارش
          </p>

          <h1>
            {
              order.order_number
            }
          </h1>

          <p
            className="admin-muted"
          >
            ثبت شده در {date(
              order.created_at,
            )}
          </p>
        </div>

        <span
          className={
            styles.statusLarge
          }
        >
          {
            labels[
              order.status
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
            جمع جزئی
          </span>

          <strong>
            {money(
              order.subtotal,
              order.currency,
            )}
          </strong>
        </div>

        <div>
          <span>
            تخفیف
          </span>

          <strong>
            {money(
              order.discount,
              order.currency,
            )}
          </strong>
        </div>

        <div>
          <span>
            ارسال
          </span>

          <strong>
            {money(
              order.shipping_cost,
              order.currency,
            )}
          </strong>
        </div>

        <div>
          <span>
            جمع کل
          </span>

          <strong>
            {money(
              order.total,
              order.currency,
            )}
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
                  اقلام
                </p>

                <h2>
                  اقلام سفارش
                </h2>
              </div>

              <span>
                {
                  order.items
                    ?.length ??
                  0
                }{" "}
                سطر
              </span>
            </div>

            <div
              className={
                styles.itemsTable
              }
            >
              <table>
                <thead>
                  <tr>
                    <th>
                      محصول
                    </th>

                    <th>
                      SKU
                    </th>

                    <th>
                      تعداد
                    </th>

                    <th>
                      واحد
                    </th>

                    <th>
                      جمع
                    </th>
                  </tr>
                </thead>

                <tbody>
                  {(order.items ??
                    []).map(
                    (item) => (
                      <tr
                        key={
                          item.id
                        }
                      >
                        <td>
                          <strong>
                            {
                              item.product_name
                            }
                          </strong>
                        </td>

                        <td>
                          {item.sku ||
                            "—"}
                        </td>

                        <td>
                          {
                            item.quantity
                          }
                        </td>

                        <td>
                          {money(
                            item.unit_price,
                            order.currency,
                          )}
                        </td>

                        <td>
                          <strong>
                            {money(
                              item.total,
                              order.currency,
                            )}
                          </strong>
                        </td>
                      </tr>
                    ),
                  )}
                </tbody>
              </table>
            </div>
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
                  سوابق
                </p>

                <h2>
                  تاریخچه وضعیت
                </h2>
              </div>
            </div>

            <div
              className={
                styles.timeline
              }
            >
              {(order.status_history ??
                [])
                .slice()
                .reverse()
                .map(
                  (
                    history,
                  ) => (
                    <article
                      key={
                        history.id
                      }
                      className={
                        styles.historyItem
                      }
                    >
                      <span
                        className={
                          styles.historyDot
                        }
                      />

                      <div>
                        <strong>
                          {history.to_status
                            ? labels[
                                history
                                  .to_status
                              ]
                            : "تغییر وضعیت"}
                        </strong>

                        <span>
                          {history.changed_by
                            ? `${
                                history
                                  .changed_by
                                  .first_name ??
                                ""
                              } ${
                                history
                                  .changed_by
                                  .last_name ??
                                ""
                              }`.trim() ||
                              "کارمند"
                            : "سیستم"}
                          {" · "}
                          {date(
                            history.created_at,
                          )}
                        </span>

                        {history.note ? (
                          <p>
                            {
                              history.note
                            }
                          </p>
                        ) : null}
                      </div>
                    </article>
                  ),
                )}

              {!order
                .status_history
                ?.length ? (
                <p
                  className="admin-muted"
                >
                  هیچ تغییر وضعیت ثبت نشده است.
                </p>
              ) : null}
            </div>
          </section>
        </div>

        <aside
          className={
            styles.sideColumn
          }
        >
          <OrderActions
            orderId={
              order.id
            }
            status={
              order.status
            }
            canUpdateStatus={admin.permissions.includes(
              "orders.update_status",
            )}
            canCancel={admin.permissions.includes(
              "orders.cancel",
            )}
            canReturn={admin.permissions.includes(
              "orders.return",
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
              مشتری
            </p>

            <h2>
              آدرس ارسال
            </h2>

            <div
              className={
                styles.address
              }
            >
              {shipping.map(
                (line) => (
                  <span
                    key={
                      line
                    }
                  >
                    {line}
                  </span>
                ),
              )}
            </div>
          </section>

          <section
            className={
              styles.card
            }
          >
            <p
              className="admin-eyebrow"
            >
              صورتحساب
            </p>

            <h2>
              آدرس صورتحساب
            </h2>

            <div
              className={
                styles.address
              }
            >
              {(billing.length
                ? billing
                : shipping
              ).map(
                (line) => (
                  <span
                    key={
                      line
                    }
                  >
                    {line}
                  </span>
                ),
              )}
            </div>
          </section>

          <section
            className={
              styles.card
            }
          >
            <p
              className="admin-eyebrow"
            >
              پرداخت
            </p>

            <h2>
              سوابق پرداخت
            </h2>

            <div
              className={
                styles.payments
              }
            >
              {(order.payments ??
                []).map(
                  (
                    payment,
                  ) => (
                    <article
                      key={
                        payment.id
                      }
                    >
                      <div>
                        <strong>
                          {payment.status.replaceAll(
                            "_",
                            " ",
                          )}
                        </strong>

                        <span>
                          {
                            payment.method
                          }
                        </span>
                      </div>

                      <strong>
                        {money(
                          payment.amount,
                          payment.currency,
                        )}
                      </strong>

                      {payment.transaction_id ? (
                        <small>
                          تراکنش: {payment.transaction_id}
                        </small>
                      ) : null}

                      {payment.gateway_reference ? (
                        <small>
                          مرجع: {payment.gateway_reference}
                        </small>
                      ) : null}
                    </article>
                  ),
                )}

              {!order.payments
                ?.length ? (
                <span
                  className="admin-muted"
                >
                  هیچ سابقه پرداختی وجود ندارد.
                </span>
              ) : null}
            </div>
          </section>
        </aside>
      </div>
    </section>
  );
}