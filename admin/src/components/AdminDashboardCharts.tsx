import type {
  AdminDashboardInventory,
  AdminDashboardMonthlyOrder,
} from "@/lib/admin-dashboard";

import styles from "./AdminDashboardCharts.module.css";

const dashboardNumber = new Intl.NumberFormat("fa-IR");

interface OrdersTrendProps {
  points:
    AdminDashboardMonthlyOrder[];
}

interface StatusChartProps {
  statuses:
    Record<string, number>;
}

interface InventoryChartProps {
  inventory:
    AdminDashboardInventory;
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

export function OrdersTrendChart({
  points,
}: OrdersTrendProps) {
  const width = 620;
  const height = 220;

  const left = 30;
  const right = 18;
  const top = 18;
  const bottom = 36;

  const drawableWidth =
    width - left - right;

  const drawableHeight =
    height - top - bottom;

  const maxValue =
    Math.max(
      1,
      ...points.map(
        (point) =>
          point.orders,
      ),
    );

  const coordinates =
    points.map(
      (point, index) => {
        const x =
          left +
          (
            points.length <= 1
              ? drawableWidth / 2
              : (
                  index /
                  (
                    points.length -
                    1
                  )
                ) *
                drawableWidth
          );

        const y =
          top +
          drawableHeight -
          (
            point.orders /
            maxValue
          ) *
            drawableHeight;

        return {
          ...point,
          x,
          y,
        };
      },
    );

  const polyline =
    coordinates
      .map(
        (point) =>
          `${point.x},${point.y}`,
      )
      .join(" ");

  return (
    <div
      className={
        styles.chart
      }
    >
      <svg
        viewBox={`0 0 ${width} ${height}`}
        role="img"
        aria-label="روند سفارش‌ها در شش ماه گذشته"
      >
        {[0, 1, 2, 3, 4].map(
          (step) => {
            const y =
              top +
              (
                drawableHeight /
                4
              ) *
                step;

            return (
              <line
                key={step}
                x1={left}
                x2={
                  width - right
                }
                y1={y}
                y2={y}
                className={
                  styles.gridLine
                }
              />
            );
          },
        )}

        {polyline ? (
          <polyline
            points={polyline}
            className={
              styles.line
            }
          />
        ) : null}

        {coordinates.map(
          (point) => (
            <g
              key={point.key}
            >
              <circle
                cx={point.x}
                cy={point.y}
                r="5"
                className={
                  styles.point
                }
              />

              <text
                x={point.x}
                y={
                  height - 12
                }
                textAnchor="middle"
                className={
                  styles.axisLabel
                }
              >
                {point.label}
              </text>

              <text
                x={point.x}
                y={
                  Math.max(
                    13,
                    point.y - 11,
                  )
                }
                textAnchor="middle"
                className={
                  styles.valueLabel
                }
              >
                {dashboardNumber.format(point.orders)}
              </text>
            </g>
          ),
        )}
      </svg>
    </div>
  );
}

export function StatusDistribution({
  statuses,
}: StatusChartProps) {
  const entries =
    Object.entries(
      statuses,
    ).sort(
      (first, second) =>
        second[1] -
        first[1],
    );

  const max =
    Math.max(
      1,
      ...entries.map(
        ([, count]) =>
          count,
      ),
    );

  if (!entries.length) {
    return (
      <p
        className={
          styles.empty
        }
      >
        هنوز اطلاعاتی از سفارش‌ها ثبت نشده است.
      </p>
    );
  }

  return (
    <div
      className={
        styles.barList
      }
    >
      {entries.map(
        ([status, count]) => (
          <div
            key={status}
            className={
              styles.barRow
            }
          >
            <div
              className={
                styles.barMeta
              }
            >
              <span>
                {humanize(
                  status,
                )}
              </span>

              <strong>
                {dashboardNumber.format(count)}
              </strong>
            </div>

            <div
              className={
                styles.barTrack
              }
            >
              <span
                style={{
                  width:
                    `${
                      (
                        count /
                        max
                      ) * 100
                    }%`,
                }}
              />
            </div>
          </div>
        ),
      )}
    </div>
  );
}

export function InventoryHealth({
  inventory,
}: InventoryChartProps) {
  const total =
    Math.max(
      1,
      inventory.healthy +
        inventory.low_stock +
        inventory.out_of_stock,
    );

  const items = [
    {
      label:
        "موجودی مناسب",
      value:
        inventory.healthy,
      className:
        styles.healthy,
    },
    {
      label:
        "موجودی کم",
      value:
        inventory.low_stock,
      className:
        styles.low,
    },
    {
      label:
        "ناموجود",
      value:
        inventory.out_of_stock,
      className:
        styles.out,
    },
  ];

  return (
    <div>
      <div
        className={
          styles.inventoryTrack
        }
      >
        {items.map(
          (item) =>
            item.value > 0 ? (
              <span
                key={
                  item.label
                }
                className={
                  item.className
                }
                style={{
                  width:
                    `${
                      (
                        item.value /
                        total
                      ) * 100
                    }%`,
                }}
              />
            ) : null,
        )}
      </div>

      <div
        className={
          styles.inventoryLegend
        }
      >
        {items.map(
          (item) => (
            <div
              key={
                item.label
              }
            >
              <span
                className={
                  item.className
                }
              />

              <div>
                <small>
                  {item.label}
                </small>

                <strong>
                  {dashboardNumber.format(item.value)}
                </strong>
              </div>
            </div>
          ),
        )}
      </div>
    </div>
  );
}