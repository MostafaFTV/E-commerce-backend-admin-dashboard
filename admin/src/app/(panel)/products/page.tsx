import { AdminSelect } from "@/components/AdminSelect";

import Link from "next/link";

import {
  notFound,
  redirect,
} from "next/navigation";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import {
  getAdminCategoryOptions,
} from "@/lib/admin-categories";

import {
  AdminProduct,
  AdminProductCollectionResponse,
  AdminProductsRequestError,
  AdminProductStatus,
  LocalizedAdminText,
  getAdminProducts,
} from "@/lib/admin-products";

import {
  ProductDeleteButton,
} from "@/components/ProductDeleteButton";
import {
  ProductExcelDownloadButton,
} from "@/components/ProductExcelDownloadButton";
import {
  ProductExcelImportPreview,
} from "@/components/ProductExcelImportPreview";
import styles from "./products.module.css";

type SearchParam =
  | string
  | string[]
  | undefined;

interface ProductsPageProps {
  searchParams: Promise<
    Record<
      string,
      SearchParam
    >
  >;
}

function firstParam(
  value: SearchParam,
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
  value: LocalizedAdminText,
): string {
  if (
    typeof value ===
    "string"
  ) {
    return value;
  }

  if (!value) {
    return "—";
  }

  const preferred =
    value.fa ??
    value.en;

  if (
    typeof preferred ===
      "string" &&
    preferred.trim() !== ""
  ) {
    return preferred;
  }

  const fallback =
    Object.values(
      value,
    ).find(
      (item) =>
        typeof item ===
          "string" &&
        item.trim() !== "",
    );

  return typeof fallback ===
    "string"
    ? fallback
    : "—";
}

function formatNumber(
  value:
    | number
    | null
    | undefined,
): string {
  if (
    typeof value !==
      "number" ||
    !Number.isFinite(
      value,
    )
  ) {
    return "—";
  }

  return new Intl.NumberFormat(
    "fa-IR",
    {
      maximumFractionDigits:
        2,
    },
  ).format(value);
}

function formatProductPrice(
  product: AdminProduct,
): string {
  if (
    product.has_price_range &&
    product.min_price !==
      product.max_price
  ) {
    return `${formatNumber(
      product.min_price,
    )} – ${formatNumber(
      product.max_price,
    )}`;
  }

  return formatNumber(
    product.price,
  );
}

function formatStatus(
  status:
    | string
    | null,
): string {
  if (!status) {
    return "—";
  }

  const labels: Record<string, string> = {
    active: "فعال",
    draft: "پیش‌نویس",
    inactive: "غیرفعال",
  };

  if (Object.prototype.hasOwnProperty.call(labels, status)) {
    return labels[status];
  }

  return status
    .replaceAll(
      "_",
      " ",
    )
    .replace(
      /\b\w/g,
      (letter) =>
        letter.toUpperCase(),
    );
}

function formatDate(
  value:
    | string
    | null,
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
      year: "numeric",
    },
  ).format(date);
}

function isProductStatus(
  value: string,
): value is AdminProductStatus {
  return (
    value === "draft" ||
    value === "active" ||
    value === "inactive"
  );
}

interface ProductUrlState {
  search: string;

  categoryId:
    | number
    | null;

  status:
    | AdminProductStatus
    | "";
}

function pageHref(
  targetPage: number,
  state: ProductUrlState,
): string {
  const params =
    new URLSearchParams();

  if (state.search) {
    params.set(
      "search",
      state.search,
    );
  }

  if (state.categoryId) {
    params.set(
      "category_id",
      String(
        state.categoryId,
      ),
    );
  }

  if (state.status) {
    params.set(
      "status",
      state.status,
    );
  }

  if (targetPage > 1) {
    params.set(
      "page",
      String(
        targetPage,
      ),
    );
  }

  const query =
    params.toString();

  return query
    ? `/products?${query}`
    : "/products";
}

export default async function ProductsPage({
  searchParams,
}: ProductsPageProps) {
  const params =
    await searchParams;

  const search =
    firstParam(
      params.search,
    ).trim();

  const categoryValue =
    Number.parseInt(
      firstParam(
        params.category_id,
      ),
      10,
    );

  const categoryId =
    Number.isFinite(
      categoryValue,
    ) &&
    categoryValue > 0
      ? categoryValue
      : null;

  const rawStatus =
    firstParam(
      params.status,
    );

  const status:
    | AdminProductStatus
    | "" =
    isProductStatus(
      rawStatus,
    )
      ? rawStatus
      : "";

  const pageValue =
    Number.parseInt(
      firstParam(
        params.page,
      ) || "1",
      10,
    );

  const page =
    Number.isFinite(
      pageValue,
    ) &&
    pageValue > 0
      ? pageValue
      : 1;

  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect("/login");
  }

  if (
    !admin.permissions.includes(
      "products.view",
    )
  ) {
    notFound();
  }

  const canCreate =
    admin.permissions.includes(
      "products.create",
    );

  const canUpdate =
    admin.permissions.includes(
      "products.update",
    );

  const canDelete =
    admin.permissions.includes(
      "products.delete",
    );

  const [
    categories,
    productResult,
  ] =
    await Promise.all([
      getAdminCategoryOptions(),

      getAdminProducts({
        search,
        categoryId,
        status,
        page,
        perPage: 15,
      })
        .then(
          (response) => ({
            response,
            error: null,
          }),
        )
        .catch(
          (error: unknown) => ({
            response: null,
            error,
          }),
        ),
    ]);

  let response:
    | AdminProductCollectionResponse
    | null =
    productResult.response;

  let loadError:
    | string
    | null = null;

  if (
    productResult.error
  ) {
    const error =
      productResult.error;

    if (
      error instanceof
      AdminProductsRequestError
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
        403
      ) {
        notFound();
      }

      loadError =
        error.message;
    } else {
      loadError =
        "ارتباط با سرور برقرار نشد.";
    }
  }

  const products =
    response?.data ?? [];

  const meta =
    response?.meta;

  const hasFilters =
    Boolean(
      search ||
      categoryId ||
      status,
    );

  const urlState:
    ProductUrlState = {
      search,
      categoryId,
      status,
    };

  return (
    <section dir="rtl" lang="fa" className={`admin-dashboard ${styles.productsPage}`}>
      <header className="admin-page-header">
        <div>
          <p className="admin-eyebrow">
            کاتالوگ
          </p>

          <h1>
            محصولات
          </h1>
        </div>

        <div
          className={
            styles.headerActions
          }
        >
          <p className="admin-muted">
            محصولات موجود در کاتالوگ را مشاهده و مدیریت کنید.
          </p>

          {admin.permissions.includes("inventory.view") ? (
            <ProductExcelDownloadButton />
          ) : null}

          {canCreate ? (
            <Link
              href="/products/new"
              className="admin-button admin-button-primary"
            >
              ایجاد محصول
            </Link>
          ) : null}
        </div>
      </header>

      {admin.permissions.includes("products.update") &&
      admin.permissions.includes("inventory.manage") ? (
        <ProductExcelImportPreview />
      ) : null}

      <div
        className={
          styles.toolbar
        }
      >
        <form
          action="/products"
          method="get"
          className={
            styles.filterForm
          }
        >
          <div
            className={
              styles.searchField
            }
          >
            <label
              htmlFor="product-search"
            >
              جست‌وجو
            </label>

            <input
              id="product-search"
              className={
                styles.control
              }
              type="search"
              name="search"
              defaultValue={
                search
              }
              placeholder="نام محصول یا اسلاگ"
            />
          </div>

          <div
            className={
              styles.filterField
            }
          >
            <label
              htmlFor="product-category"
            >
              دسته‌بندی
            </label>

            <AdminSelect
              id="product-category"
              className={
                styles.control
              }
              name="category_id"
              defaultValue={
                categoryId
                  ? String(
                      categoryId,
                    )
                  : ""
              }
            >
              <option value="">
                همه دسته‌بندی‌ها
              </option>

              {categories.map(
                (category) => (
                  <option
                    key={
                      category.id
                    }
                    value={
                      category.id
                    }
                  >
                    {localizedText(
                      category.name,
                    )}
                  </option>
                ),
              )}
            </AdminSelect>
          </div>

          <div
            className={
              styles.filterField
            }
          >
            <label
              htmlFor="product-status"
            >
              وضعیت
            </label>

            <AdminSelect
              id="product-status"
              className={
                styles.control
              }
              name="status"
              defaultValue={
                status
              }
            >
              <option value="">
                همه وضعیت‌ها
              </option>

              <option value="active">
                فعال
              </option>

              <option value="draft">
                پیش‌نویس
              </option>

              <option value="inactive">
                غیرفعال
              </option>
            </AdminSelect>
          </div>

          <div
            className={
              styles.filterActions
            }
          >
            <button
              className="admin-button admin-button-primary"
              type="submit"
            >
              اعمال فیلتر
            </button>

            {hasFilters ? (
              <Link
                href="/products"
                className="admin-button"
              >
                پاک کردن
              </Link>
            ) : null}
          </div>
        </form>

        <div
          className={
            styles.resultMeta
          }
        >
          {meta ? (
            <>
              <strong>
                {formatNumber(
                  meta.total,
                )}
              </strong>

              <span>
                محصول
              </span>
            </>
          ) : null}
        </div>
      </div>

      {loadError ? (
        <div
          className={
            styles.errorState
          }
        >
          <strong>
            بارگذاری محصولات ناموفق بود.
          </strong>

          <p>
            {loadError}
          </p>
        </div>
      ) : products.length ===
        0 ? (
        <div
          className={
            styles.emptyState
          }
        >
          <strong>
            محصولی پیدا نشد.
          </strong>

          <p>
            {hasFilters
              ? "محصولی با فیلترهای انتخاب‌شده مطابقت ندارد."
              : "در حال حاضر محصولی در کاتالوگ ثبت نشده است."}
          </p>
        </div>
      ) : (
        <>
          <div className="admin-table-wrap">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>
                    محصول
                  </th>

                  <th>
                    دسته‌بندی
                  </th>

                  <th>
                    وضعیت
                  </th>

                  <th>
                    قیمت
                  </th>

                  <th>
                    تنوع‌ها
                  </th>

                  <th>
                    موجودی
                  </th>

                  <th>
                    آخرین ویرایش
                  </th>

                  {canUpdate ||
                  canDelete ? (
                    <th
                      className={
                        styles.actionsHead
                      }
                    >
                      عملیات
                    </th>
                  ) : null}
                </tr>
              </thead>

              <tbody>
                {products.map(
                  (product) => (
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
                            className={
                              styles.productName
                            }
                          >
                            {localizedText(
                              product.name,
                            )}
                          </strong>

                          <span
                            className={
                              styles.productSlug
                            }
                          >
                            {product.slug}
                          </span>

                          {product.brand ? (
                            <span
                              className={
                                styles.productBrand
                              }
                            >
                              {product.brand}
                            </span>
                          ) : null}
                        </div>
                      </td>

                      <td>
                        {product.category
                          ? localizedText(
                              product
                                .category
                                .name,
                            )
                          : "—"}
                      </td>

                      <td>
                        <span
                          className={
                            styles.status
                          }
                        >
                          {formatStatus(
                            product.status,
                          )}
                        </span>
                      </td>

                      <td
                        className={
                          styles.numeric
                        }
                      >
                        {formatProductPrice(
                          product,
                        )}
                      </td>

                      <td
                        className={
                          styles.numeric
                        }
                      >
                        {formatNumber(
                          product
                            .variants
                            ?.length ??
                            0,
                        )}
                      </td>

                      <td>
                        <div
                          className={
                            styles.inventory
                          }
                        >
                          <strong>
                            {formatNumber(
                              product
                                .available_inventory,
                            )}
                          </strong>

                          <span>
                            قابل‌فروش /{" "}
                            {formatNumber(
                              product
                                .total_inventory,
                            )}{" "}
                            کل
                          </span>
                        </div>
                      </td>

                      <td>
                        {formatDate(
                          product.updated_at,
                        )}
                      </td>

                      {canUpdate ||
                      canDelete ? (
                        <td
                          className={
                            styles.actionsCell
                          }
                        >
                          <div
                            className={
                              styles.rowActions
                            }
                          >
                            {canUpdate ? (
                              <Link
                                href={`/products/${product.id}/edit`}
                                className="admin-button"
                                aria-label={`ویرایش ${localizedText(
                                  product.name,
                                )}`}
                              >
                                ویرایش
                              </Link>
                            ) : null}

                            {canDelete ? (
                              <ProductDeleteButton
                                productId={
                                  product.id
                                }
                                productName={
                                  localizedText(
                                    product.name,
                                  )
                                }
                              />
                            ) : null}
                          </div>
                        </td>
                      ) : null}
                    </tr>
                  ),
                )}
              </tbody>
            </table>
          </div>

          {meta &&
          meta.last_page > 1 ? (
            <nav
              className="admin-pagination"
              aria-label="صفحه‌بندی محصولات"
            >
              {meta.current_page >
              1 ? (
                <Link

                  href={pageHref(
                    meta.current_page -
                      1,
                    urlState,
                  )}
                >
                  قبلی
                </Link>
              ) : (
                <span

                >
                  قبلی
                </span>
              )}

              <span
                className={
                  styles.pageCount
                }
              >
                صفحه{" "}
                {formatNumber(meta.current_page)}{" "}
                از{" "}
                {formatNumber(meta.last_page)}
              </span>

              {meta.current_page <
              meta.last_page ? (
                <Link

                  href={pageHref(
                    meta.current_page +
                      1,
                    urlState,
                  )}
                >
                  بعدی
                </Link>
              ) : (
                <span

                >
                  بعدی
                </span>
              )}
            </nav>
          ) : null}
        </>
      )}
    </section>
  );
}
