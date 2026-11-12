import Link from "next/link";

import {
  notFound,
  redirect,
} from "next/navigation";

import {
  AdminInventoryRequestError,
  getAdminInventoryProduct,
  type InventoryLocalizedValue,
} from "@/lib/admin-inventory";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "../../inventory.module.css";

interface ProductInventoryPageProps {
  params:
    Promise<{
      productId:
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

export default async function ProductInventoryPage({
  params,
}: ProductInventoryPageProps) {
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
    productId,
  } =
    await params;

  const id =
    Number(
      productId,
    );

  if (
    !Number.isInteger(id) ||
    id <= 0
  ) {
    notFound();
  }

  let product;

  try {
    product =
      await getAdminInventoryProduct(
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

  return (
    <section
      className={`admin-dashboard ${styles.page}`}
      dir="rtl"
      lang="fa"
    >

      <header
        className={
          styles.productHeader
        }
      >
        <div>
          <p
            className="admin-eyebrow"
          >
            موجودی محصول
          </p>

          <h1>
            <span dir="auto">{localizedText(
              product.name,
            ) ||
              "محصول بدون نام"}</span>
          </h1>

          <p
            className={
              styles.productMeta
            }
          >
            {product.brand ||
              "بدون برند"}
            {" · "}
            <bdi dir="ltr">{product.variants_count}</bdi>{" "}
            تنوع
          </p>
        </div>

        <div
          className={
            styles.summaryInline
          }
        >
          <div>
            <span>
              فیزیکی
            </span>

            <strong>
              <bdi dir="ltr">{product.quantity}</bdi>
            </strong>
          </div>

          <div>
            <span>
              رزروشده
            </span>

            <strong>
              <bdi dir="ltr">{product.reserved_quantity}</bdi>
            </strong>
          </div>

          <div>
            <span>
              قابل‌عرضه
            </span>

            <strong>
              <bdi dir="ltr">{product.available_quantity}</bdi>
            </strong>
          </div>
        </div>
      </header>

      <section
        className={
          styles.variantsSection
        }
      >
        <div
          className={
            styles.sectionTitle
          }
        >
          <div>
            <p
              className="admin-eyebrow"
            >
              تنوع‌ها
            </p>

            <h2>
              موجودی بر اساس تنوع
            </h2>
          </div>

          <p>
            موجودی رزروشده به‌صورت خودکار بر اساس سفارش‌ها مدیریت می‌شود.
          </p>
        </div>

        <div
          className={
            styles.variantList
          }
        >
          {product.variants.map(
            (variant) => {
              const configured =
                variant.inventory_configured &&
                variant.inventory_id !==
                  null;

              const out =
                configured &&
                (
                  variant.available_quantity ??
                  0
                ) <= 0;

              const low =
                configured &&
                !out &&
                variant.low_stock;

              const tone =
                !configured
                  ? "neutral"
                  : out
                    ? "danger"
                    : low
                      ? "warning"
                      : "success";

              const label =
                !configured
                  ? "تنظیم‌نشده"
                  : out
                    ? "ناموجود"
                    : low
                      ? "کم‌موجود"
                      : "موجود";

              const attributes =
                variant.attributes
                  .map(
                    (
                      attribute,
                    ) =>
                      localizedText(
                        attribute.value,
                      ),
                  )
                  .filter(Boolean)
                  .join(" / ");

              return (
                <article
                  key={
                    variant.id
                  }
                  className={
                    styles.variantCard
                  }
                >
                  <div
                    className={
                      styles.variantIdentity
                    }
                  >
                    <div>
                      <strong dir="ltr">
                        {
                          variant.sku
                        }
                      </strong>

                      <span dir="auto">
                        {attributes ||
                          "تنوع پیش‌فرض"}
                      </span>
                    </div>

                    <span
                      className={
                        styles.status
                      }
                      data-tone={
                        tone
                      }
                    >
                      <span
                        className={
                          styles.statusDot
                        }
                      />

                      {label}
                    </span>
                  </div>

                  <div
                    className={
                      styles.variantStats
                    }
                  >
                    <div>
                      <span>
                        فیزیکی
                      </span>

                      <strong dir="ltr">
                        {variant.quantity ?? "—"}
                      </strong>
                    </div>

                    <div>
                      <span>
                        رزروشده
                      </span>

                      <strong dir="ltr">
                        {variant.reserved_quantity ?? "—"}
                      </strong>
                    </div>

                    <div>
                      <span>
                        قابل‌عرضه
                      </span>

                      <strong dir="ltr">
                        {variant.available_quantity ?? "—"}
                      </strong>
                    </div>

                    <div>
                      <span>
                        هشدار در
                      </span>

                      <strong dir="ltr">
                        {variant.low_stock_threshold ?? "—"}
                      </strong>
                    </div>
                  </div>

                  <div
                    className={
                      styles.variantActions
                    }
                  >
                    {configured ? (
                      <Link
                        href={`/inventory/${variant.inventory_id}`}
                        className="admin-button"
                      >
                        مدیریت موجودی

                        <span
                          aria-hidden="true"
                        >
                          ←
                        </span>
                      </Link>
                    ) : (
                      <span
                        className={
                          styles.notConfiguredNote
                        }
                      >
                        موجودی تنظیم نشده است
                      </span>
                    )}
                  </div>
                </article>
              );
            },
          )}
        </div>
      </section>
    </section>
  );
}