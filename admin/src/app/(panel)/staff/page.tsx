import { AdminSelect } from "@/components/AdminSelect";

import Link from "next/link";

import {
  notFound,
  redirect,
} from "next/navigation";

import {
  StaffDeleteButton,
} from "@/components/StaffDeleteButton";

import {
  AdminStaffRequestError,
  getAdminStaff,
  type AdminStaffRole,
  type AdminStaffStatus,
} from "@/lib/admin-staff";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "./staff.module.css";

type SearchParam =
  | string
  | string[]
  | undefined;

interface StaffPageProps {
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
    AdminStaffStatus,
    string
  > = {
    active:
      "فعال",

    inactive:
      "غیرفعال",

    suspended:
      "تعلیق‌شده",
  };

const roleLabels:
  Record<
    AdminStaffRole,
    string
  > = {
    admin:
      "مدیر",

    accountant:
      "حسابدار",

    support:
      "پشتیبانی",
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

function formatDate(
  value:
    string | null,
): string {
  if (!value) {
    return "—";
  }

  const parsed =
    new Date(
      value,
    );

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
  role:
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

  if (role) {
    params.set(
      "role",
      role,
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
    ? `/staff?${query}`
    : "/staff";
}

export default async function StaffPage({
  searchParams,
}: StaffPageProps) {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "users.view",
    )
  ) {
    notFound();
  }

  const canManage =
    admin.permissions.includes(
      "users.manage",
    );

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

  const role =
    firstParam(
      params.role,
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
      await getAdminStaff({
        search,
        status,
        role,
        page,
      });
  } catch (error) {
    if (
      error instanceof
        AdminStaffRequestError &&
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
            مدیریت سیستم
          </p>

          <h1>
            کاربران پنل
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
            حساب کاربران پنل، نقش‌ها و وضعیت آن‌ها را مدیریت کنید.
          </p>

          {canManage ? (
            <Link
              href="/staff/new"
              className="admin-button admin-button-primary"
            >
              افزودن کاربر پنل
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
            placeholder="جستجوی نام، ایمیل یا شماره تماس"
          />

          <AdminSelect
            name="role"
            defaultValue={
              role
            }
          >
            <option value="">
              همه نقش‌ها
            </option>

            <option value="admin">
              مدیر
            </option>

            <option value="accountant">
              حسابدار
            </option>

            <option value="support">
              پشتیبانی
            </option>
          </AdminSelect>

          <AdminSelect
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

            <option value="inactive">
              غیرفعال
            </option>

            <option value="suspended">
              تعلیق‌شده
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
          role ||
          status ? (
            <Link
              href="/staff"
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
          کاربر پنل
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
                کاربر پنل
              </th>

              <th>
                اطلاعات تماس
              </th>

              <th>
                نقش
              </th>

              <th>
                وضعیت
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
                (user) => {
                  const userRole =
                    user.roles[0] as
                      | AdminStaffRole
                      | undefined;

                  const isSelf =
                    user.id ===
                    admin.id;

                  return (
                    <tr
                      key={
                        user.id
                      }
                    >
                      <td>
                        <strong
                          className={
                            styles.staffName
                          }
                        >
                          {user.first_name}{" "}
                          {user.last_name}
                        </strong>

                        <span
                          className={
                            styles.staffId
                          }
                        >
                          کاربر پنل شماره{user.id}

                          {isSelf
                            ? " · شما"
                            : ""}
                        </span>
                      </td>

                      <td>
                        <div
                          className={
                            styles.contact
                          }
                        >
                          <span>
                            {
                              user.email
                            }
                          </span>

                          <span>
                            {user.phone ||
                              "—"}
                          </span>
                        </div>
                      </td>

                      <td>
                        <span
                          className={
                            styles.role
                          }
                        >
                          {userRole
                            ? roleLabels[
                                userRole
                              ]
                            : "—"}
                        </span>
                      </td>

                      <td>
                        <span
                          className={
                            styles.status
                          }
                          data-status={
                            user.status
                          }
                        >
                          {
                            statusLabels[
                              user.status
                            ]
                          }
                        </span>
                      </td>

                      <td>
                        {formatDate(
                          user.created_at,
                        )}
                      </td>

                      <td>
                        <div
                          className={
                            styles.actions
                          }
                        >
                          {canManage ? (
                            <Link
                              href={`/staff/${user.id}/edit`}
                              className="admin-button"
                            >
                              ویرایش
                            </Link>
                          ) : null}

                          {canManage &&
                          !isSelf ? (
                            <StaffDeleteButton
                              userId={
                                user.id
                              }
                              userName={`${user.first_name} ${user.last_name}`}
                            />
                          ) : null}
                        </div>
                      </td>
                    </tr>
                  );
                },
              )
            ) : (
              <tr>
                <td
                  colSpan={6}
                  className={
                    styles.empty
                  }
                >
                  کاربری مطابق فیلترهای انتخاب‌شده یافت نشد.
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
                role,
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
                role,
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