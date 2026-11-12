import {
  AdminSelect,
} from "@/components/AdminSelect";

import Link from "next/link";

import {
  notFound,
  redirect,
} from "next/navigation";

import {
  StoreDeleteButton,
} from "@/components/StoreDeleteButton";

import {
  AdminStoreRequestError,
  getAdminStores,
} from "@/lib/admin-stores";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "./stores.module.css";

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
    ? `/stores?${query}`
    : "/stores";
}

export default async function StoresPage({
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
      "stores.view",
    )
  ) {
    notFound();
  }

  const canCreate =
    admin.permissions.includes(
      "stores.create",
    );

  const canUpdate =
    admin.permissions.includes(
      "stores.update",
    );

  const canDelete =
    admin.permissions.includes(
      "stores.delete",
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
      await getAdminStores({
        search,
        active,
        page,
      });
  } catch (error) {
    if (
      error instanceof
        AdminStoreRequestError &&
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
            سایت
          </p>

          <h1>
            شعب و فروشگاه‌ها
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
            شعب نمایش‌داده‌شده در صفحه تماس با ما را مدیریت کنید.
          </p>

          {canCreate ? (
            <Link
              href="/stores/new"
              className="admin-button admin-button-primary"
            >
              افزودن شعبه
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
            placeholder="جستجوی شعب"
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
              href="/stores"
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
          شعبه
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
                شعبه
              </th>

              <th>
                موقعیت
              </th>

              <th>
                طبقه و واحد
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
                (store) => (
                  <tr
                    key={
                      store.id
                    }
                  >
                    <td>
                      <strong
                        className={
                          styles.storeName
                        }
                        dir="auto"
                      >
                        {
                          store.name
                        }
                      </strong>

                      <span
                        className={
                          styles.secondary
                        }
                        dir="auto"
                      >
                        {store.name_en ||
                          store.slug}
                      </span>
                    </td>

                    <td>
                      <strong
                        className={
                          styles.location
                        }
                        dir="auto"
                      >
                        {
                          store.city
                        }
                      </strong>

                      <span
                        className={
                          styles.secondary
                        }
                        dir="auto"
                      >
                        {store.address}
                      </span>
                    </td>

                    <td>
                      <span
                        dir="auto"
                      >
                        {store.floor_unit ||
                          "—"}
                      </span>

                      {store.floor_unit_en ? (
                        <span
                          className={
                            styles.secondary
                          }
                          dir="auto"
                        >
                          {
                            store.floor_unit_en
                          }
                        </span>
                      ) : null}
                    </td>

                    <td>
                      <span
                        className={
                          styles.status
                        }
                        data-active={
                          store.is_active
                            ? "true"
                            : "false"
                        }
                      >
                        {store.is_active
                          ? "فعال"
                          : "غیرفعال"}
                      </span>
                    </td>

                    <td>
                      {
                        store.sort_order
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
                            href={`/stores/${store.id}/edit`}
                            className="admin-button"
                          >
                            ویرایش
                          </Link>
                        ) : null}

                        {canDelete ? (
                          <StoreDeleteButton
                            storeId={
                              store.id
                            }
                            storeName={
                              store.name_en ||
                              store.name
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
                  شعبه‌ای مطابق فیلترهای انتخاب‌شده یافت نشد.
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