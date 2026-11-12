import {
  notFound,
  redirect,
} from "next/navigation";

import {
  InventoryControls,
} from "@/components/InventoryControls";

import {
  AdminInventoryRequestError,
  getAdminInventory,
  type InventoryLocalizedValue,
} from "@/lib/admin-inventory";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "../inventory.module.css";

interface InventoryDetailPageProps {
  params:
    Promise<{
      inventoryId:
        string;
    }>;
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

      timeStyle:
        "short",
    },
  ).format(date);
}

function signed(
  value:
    number,
): string {
  if (value > 0) {
    return `+${value}`;
  }

  return String(value);
}

function movementLabel(type: string): string {
  const labels: Record<string, string> = {
    adjustment: "اصلاح موجودی",
    stock_adjustment: "اصلاح موجودی",
    order_reserved: "رزرو سفارش",
    order_released: "آزادسازی رزرو",
    order_fulfilled: "تکمیل سفارش",
    reservation: "رزرو",
    release: "آزادسازی",
  };

  return labels[type] ?? type;
}

export default async function InventoryDetailPage({
  params,
}: InventoryDetailPageProps) {
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

  const {
    inventoryId,
  } =
    await params;

  const id =
    Number(
      inventoryId,
    );

  if (
    !Number.isInteger(id) ||
    id <= 0
  ) {
    notFound();
  }

  let inventory;

  try {
    inventory =
      await getAdminInventory(
        id,
      );
  } catch (error) {
    if (
      error instanceof
        AdminInventoryRequestError
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

  const canManage =
    admin.permissions.includes(
      "inventory.manage",
    );

  const attributes =
    inventory.variant
      ?.attributes
      ?.map(
        (entry) => {
          const name =
            localizedText(
              entry.attribute
                ?.name ??
                null,
            );

          const value =
            localizedText(
              entry.value,
            );

          return name
            ? `${name}: ${value}`
            : value;
        },
      )
      .filter(Boolean) ??
    [];

  const movements =
    inventory.movements ??
    [];

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
            سابقه موجودی
          </p>

          <h1>
            <span dir="auto">{localizedText(
              inventory
                .product
                ?.name ??
                null,
            ) ||
              "محصول بدون نام"}</span>
          </h1>
        </div>

        <p
          className="admin-muted"
        >
          <span dir="ltr">SKU:{" "}
          {inventory.variant?.sku ?? "—"}</span>
          {attributes.length
            ? ` · ${attributes.join(
                " · ",
              )}`
            : ""}
        </p>
      </header>

      <div
        className={
          styles.stockCards
        }
      >
        <article
          className={
            styles.stockCard
          }
        >
          <span>
            فیزیکی
          </span>

          <strong dir="ltr">
          {inventory.quantity}
          </strong>
        </article>

        <article
          className={
            styles.stockCard
          }
        >
          <span>
            رزروشده
          </span>

          <strong dir="ltr">
            {inventory.reserved_quantity}
          </strong>

          <small>
            مدیریت‌شده توسط سیستم
          </small>
        </article>

        <article
          className={
            styles.stockCard
          }
        >
          <span>
            قابل‌عرضه
          </span>

          <strong dir="ltr">
            {inventory.available_quantity}
          </strong>
        </article>

        <article
          className={
            styles.stockCard
          }
        >
          <span>
            آستانه کمبود موجودی
          </span>

          <strong dir="ltr">
            {inventory.low_stock_threshold}
          </strong>
        </article>
      </div>

      <InventoryControls
        inventoryId={
          inventory.id
        }
        quantity={
          inventory.quantity
        }
        reserved={
          inventory.reserved_quantity
        }
        threshold={
          inventory.low_stock_threshold
        }
        canManage={
          canManage
        }
      />

      <section
        className={
          styles.history
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
              سوابق تغییرات
            </p>

            <h2>
              تاریخچه گردش موجودی
            </h2>
          </div>

          <span dir="ltr">
            {movements.length}
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
                  نوع
                </th>

                <th>
                  تغییر موجودی
                </th>

                <th>
                  تغییر رزرو
                </th>

                <th>
                  سفارش
                </th>

                <th>
                  توضیحات
                </th>

                <th>
                  کاربر
                </th>

                <th>
                  تاریخ
                </th>
              </tr>
            </thead>

            <tbody>
              {movements.length ? (
                movements.map(
                  (
                    movement,
                  ) => (
                    <tr
                      key={
                        movement.id
                      }
                    >
                      <td>
                        <strong>
                          {movementLabel(
                            movement.type,
                          )}
                        </strong>
                      </td>

                      <td dir="ltr">
                        {signed(
                          movement.quantity_delta,
                        )}
                      </td>

                      <td dir="ltr">
                        {signed(
                          movement.reserved_delta,
                        )}
                      </td>

                      <td dir="ltr">
                        {movement.order
                          ?.order_number ??
                          "—"}
                      </td>

                      <td>
                        {movement.note ??
                          "—"}
                      </td>

                      <td dir="ltr">
                        {movement
                          .created_by
                          ?.email ??
                          "سیستم"}
                      </td>

                      <td>
                        {formatDate(
                          movement.created_at,
                        )}
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
                    هنوز گردش موجودی ثبت نشده است.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
    </section>
  );
}