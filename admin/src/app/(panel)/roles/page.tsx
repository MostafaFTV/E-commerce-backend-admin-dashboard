import { adminPermissionGroup, adminRoleLabel } from "@/lib/admin-persian-labels";
import Link from "next/link";

import {
  notFound,
  redirect,
} from "next/navigation";

import {
  RoleDeleteButton,
} from "@/components/RoleDeleteButton";

import {
  AdminAccessControlRequestError,
  getAdminRoles,
} from "@/lib/admin-access-control";

import {
  isSystemRoleSlug,
} from "@/lib/access-control-constants";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "@/components/AccessReference.module.css";

type SearchParam =
  | string
  | string[]
  | undefined;

interface RolesPageProps {
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

function permissionGroup(
  slug:
    string,
): string { return adminPermissionGroup(slug); }

export default async function RolesPage({
  searchParams,
}: RolesPageProps) {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "roles.manage",
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
      await getAdminRoles(
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
            نقش‌ها
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
            نقش‌ها و مجوزهای اختصاص‌یافته به آن‌ها را مدیریت کنید.
          </p>

          <Link
            href="/roles/new"
            className="admin-button admin-button-primary"
          >
            ایجاد نقش
          </Link>
        </div>
      </header>

      <div
        className={
          styles.notice
        }
      >
        <strong>
          نقش‌های سیستمی محافظت‌شده
        </strong>

        <p>
          نقش‌های اصلی توسط بک‌اند محافظت می‌شوند. نقش‌های سفارشی قابل ایجاد، ویرایش و حذف هستند.
        </p>
      </div>

      <div
        className={
          styles.roleGrid
        }
      >
        {result.data.map(
          (role) => {
            const isSystem =
              isSystemRoleSlug(
                role.slug,
              );

            const isSuperAdmin =
              role.slug ===
              "super_admin";

            const grouped =
              role.permissions.reduce<
                Record<
                  string,
                  typeof role.permissions
                >
              >(
                (
                  groups,
                  permission,
                ) => {
                  const group =
                    permissionGroup(
                      permission.slug,
                    );

                  groups[
                    group
                  ] ??=
                    [];

                  groups[
                    group
                  ].push(
                    permission,
                  );

                  return groups;
                },
                {},
              );

            return (
              <article
                key={
                  role.id
                }
                className={
                  styles.roleCard
                }
              >
                <div
                  className={
                    styles.roleHeader
                  }
                >
                  <div>
                    <div
                      className={
                        styles.badges
                      }
                    >
                      {isSystem ? (
                        <span
                          className={
                            styles.systemBadge
                          }
                        >
                          نقش سیستمی
                        </span>
                      ) : (
                        <span
                          className={
                            styles.customBadge
                          }
                        >
                          نقش سفارشی
                        </span>
                      )}

                      <span
                        className={
                          styles.slugBadge
                        }
                      >
                        {role.slug}
                      </span>
                    </div>

                    <h2>
                      {adminRoleLabel(role.slug, role.name)}
                    </h2>

                    <p>
                      {role.description ||
                        "بدون توضیحات"}
                    </p>
                  </div>

                  <div
                    className={
                      styles.roleSide
                    }
                  >
                    <div
                      className={
                        styles.roleStats
                      }
                    >
                      <strong>
                        {
                          role.users_count
                        }
                      </strong>

                      <span>
                        کاربر اختصاص‌یافته
                        {""}
                      </span>
                    </div>

                    <div
                      className={
                        styles.actions
                      }
                    >
                      {!isSuperAdmin ? (
                        <Link
                          href={`/roles/${role.id}/edit`}
                          className="admin-button"
                        >
                          ویرایش
                        </Link>
                      ) : (
                        <span
                          className={
                            styles.protectedLabel
                          }
                        >
                          محافظت‌شده
                        </span>
                      )}

                      {!isSystem ? (
                        <RoleDeleteButton
                          roleId={
                            role.id
                          }
                          roleName={
                            adminRoleLabel(role.slug, role.name)
                          }
                        />
                      ) : null}
                    </div>
                  </div>
                </div>

                <div
                  className={
                    styles.permissionSummary
                  }
                >
                  <strong>
                    {
                      role.permissions
                        .length
                    }
                  </strong>

                  <span>
                    مجوز
                  </span>
                </div>

                {Object.entries(
                  grouped,
                ).length ? (
                  <div
                    className={
                      styles.permissionGroups
                    }
                  >
                    {Object.entries(
                      grouped,
                    ).map(
                      ([
                        group,
                        permissions,
                      ]) => (
                        <section
                          key={
                            group
                          }
                          className={
                            styles.permissionGroup
                          }
                        >
                          <h3>
                            {group}
                          </h3>

                          <div
                            className={
                              styles.permissionList
                            }
                          >
                            {permissions.map(
                              (
                                permission,
                              ) => (
                                <span
                                  key={
                                    permission.id
                                  }
                                  className={
                                    styles.permissionChip
                                  }
                                  title={
                                    permission.description ??
                                    permission.name
                                  }
                                >
                                  {
                                    permission.slug
                                  }
                                </span>
                              ),
                            )}
                          </div>
                        </section>
                      ),
                    )}
                  </div>
                ) : (
                  <p
                    className={
                      styles.emptyPermissions
                    }
                  >
                    مجوزی اختصاص نیافته است.
                  </p>
                )}
              </article>
            );
          },
        )}
      </div>

      {result.meta.last_page >
      1 ? (
        <nav
          className="admin-pagination"
        >
          {result.meta.current_page >
          1 ? (
            <Link
              href={`/roles?page=${
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
              href={`/roles?page=${
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