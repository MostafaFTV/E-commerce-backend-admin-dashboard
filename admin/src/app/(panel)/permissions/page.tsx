import { adminPermissionLabel } from "@/lib/admin-persian-labels";
import Link from "next/link";

import {
  notFound,
  redirect,
} from "next/navigation";

import {
  AdminAccessControlRequestError,
  getAdminPermissions,
} from "@/lib/admin-access-control";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "@/components/AccessReference.module.css";

type SearchParam =
  | string
  | string[]
  | undefined;

interface PermissionsPageProps {
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

export default async function PermissionsPage({
  searchParams,
}: PermissionsPageProps) {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "permissions.manage",
    )
  ) {
    notFound();
  }

  const params =
    await searchParams;

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
      await getAdminPermissions(
        page,
      );
  } catch (error) {
    if (
      error instanceof
        AdminAccessControlRequestError &&
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
            مجوزهای دسترسی
          </h1>
        </div>

        <p
          className="admin-muted"
        >
          فهرست مجوزهایی که می‌توان به نقش‌ها اختصاص داد.
        </p>
      </header>

      <div
        className={
          styles.notice
        }
      >
        <strong>
          مجوزهای فقط قابل مشاهده
        </strong>

        <p>
          شناسه مجوزها در منطق و مسیرهای سیستم استفاده می‌شود؛ مجوزهای سیستمی در این بخش قابل ویرایش یا حذف نیستند.
        </p>
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
                مجوز
              </th>

              <th>
                شناسه متنی
              </th>

              <th>
                توضیحات
              </th>

              <th>
                نقش‌ها
              </th>
            </tr>
          </thead>

          <tbody>
            {result.data.length ? (
              result.data.map(
                (permission) => (
                  <tr
                    key={
                      permission.id
                    }
                  >
                    <td>
                      <strong
                        className={
                          styles.permissionName
                        }
                      >
                        {
                          adminPermissionLabel(permission.slug, permission.name)
                        }
                      </strong>

                      <span
                        className={
                          styles.permissionId
                        }
                      >
                        مجوز شماره
                        {
                          permission.id
                        }
                      </span>
                    </td>

                    <td>
                      <code
                        className={
                          styles.permissionSlug
                        }
                      >
                        {
                          permission.slug
                        }
                      </code>
                    </td>

                    <td>
                      <span
                        className={
                          styles.description
                        }
                      >
                        {permission.description ||
                          "—"}
                      </span>
                    </td>

                    <td>
                      <span
                        className={
                          styles.roleCount
                        }
                      >
                        {
                          permission.roles_count
                        }
                      </span>
                    </td>
                  </tr>
                ),
              )
            ) : (
              <tr>
                <td
                  colSpan={4}
                  className={
                    styles.empty
                  }
                >
                  مجوزی یافت نشد.
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
              href={`/permissions?page=${
                result.meta
                  .current_page -
                1
              }`}
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
              href={`/permissions?page=${
                result.meta
                  .current_page +
                1
              }`}
            >
              بعدی ←
            </Link>
          ) : null}
        </nav>
      ) : null}
    </section>
  );
}