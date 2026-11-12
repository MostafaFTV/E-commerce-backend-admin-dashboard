import { AdminDatePicker } from "@/components/AdminDatePicker";

import { AdminSelect } from "@/components/AdminSelect";

import Link from "next/link";

import {
  notFound,
  redirect,
} from "next/navigation";

import {
  AdminActivityLogRequestError,
  getAdminActivityLogActions,
  getAdminActivityLogs,
} from "@/lib/admin-activity-logs";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "./ActivityLogs.module.css";

type SearchParam =
  | string
  | string[]
  | undefined;

interface ActivityLogsPageProps {
  searchParams:
    Promise<
      Record<
        string,
        SearchParam
      >
    >;
}

function firstParam(
  value: SearchParam,
): string {
  return Array.isArray(value)
    ? value[0] ?? ""
    : value ?? "";
}

function formatDate(
  value: string | null,
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
    "fa-IR",
    {
      dateStyle: "medium",
      timeStyle: "short",
    },
  ).format(date);
}

function actorName(
  firstName: string,
  lastName: string,
): string {
  const name =
    `${firstName} ${lastName}`
      .trim();

  return name || "کاربر پنل";
}

export default async function ActivityLogsPage({
  searchParams,
}: ActivityLogsPageProps) {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect("/login");
  }

  if (
    !admin.permissions.includes(
      "activity_logs.view",
    )
  ) {
    notFound();
  }

  const params =
    await searchParams;

  const search =
    firstParam(
      params.search,
    ).trim();

  const action =
    firstParam(
      params.action,
    ).trim();

  const userIdRaw =
    firstParam(
      params.user_id,
    ).trim();

  const from =
    firstParam(
      params.from,
    ).trim();

  const to =
    firstParam(
      params.to,
    ).trim();

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

  const parsedUserId =
    Number(userIdRaw);

  const userId =
    userIdRaw &&
    Number.isInteger(
      parsedUserId,
    ) &&
    parsedUserId > 0
      ? parsedUserId
      : undefined;

  let result;
  let actions: string[] = [];

  try {
    [
      result,
      actions,
    ] = await Promise.all([
      getAdminActivityLogs({
        page,
        search:
          search || undefined,
        action:
          action || undefined,
        userId,
        from:
          from || undefined,
        to:
          to || undefined,
      }),
      getAdminActivityLogActions(),
    ]);
  } catch (error) {
    if (
      error instanceof
        AdminActivityLogRequestError
    ) {
      if (
        error.status === 401
      ) {
        redirect("/login");
      }

      if (
        error.status === 403
      ) {
        notFound();
      }
    }

    throw error;
  }

  const filterParams =
    new URLSearchParams();

  if (search) {
    filterParams.set(
      "search",
      search,
    );
  }

  if (action) {
    filterParams.set(
      "action",
      action,
    );
  }

  if (userIdRaw) {
    filterParams.set(
      "user_id",
      userIdRaw,
    );
  }

  if (from) {
    filterParams.set(
      "from",
      from,
    );
  }

  if (to) {
    filterParams.set(
      "to",
      to,
    );
  }

  function pageHref(
    targetPage: number,
  ): string {
    const next =
      new URLSearchParams(
        filterParams,
      );

    next.set(
      "page",
      String(targetPage),
    );

    return `/activity-logs?${next.toString()}`;
  }

  const currentListParams =
    new URLSearchParams(
      filterParams,
    );

  if (page > 1) {
    currentListParams.set(
      "page",
      String(page),
    );
  }

  const currentListUrl =
    currentListParams.toString()
      ? `/activity-logs?${currentListParams.toString()}`
      : "/activity-logs";

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
            تاریخچه فعالیت‌ها
          </h1>
        </div>

        <p
          className="admin-muted"
        >
          فعالیت‌های ثبت‌شده کاربران پنل و جزئیات آن‌ها را بررسی کنید.
        </p>
      </header>

      <form
        method="get"
        className={
          styles.filters
        }
      >
        <label
          className={
            styles.field
          }
        >
          <span>
            جستجو
          </span>

          <input
            type="search"
            name="search"
            defaultValue={
              search
            }
            placeholder="عملیات یا توضیحات"
          />
        </label>

        <label
          className={
            styles.field
          }
        >
          <span>
            عملیات
          </span>

          <AdminSelect
            name="action"
            defaultValue={
              action
            }
          >
            <option value="">
              همه عملیات‌ها
            </option>

            {actions.map(
              (item) => (
                <option
                  key={item}
                  value={item}
                >
                  {item}
                </option>
              ),
            )}
          </AdminSelect>
        </label>

        <label
          className={
            styles.field
          }
        >
          <span>
            شناسه کاربر
          </span>

          <input
            type="number"
            min="1"
            name="user_id"
            defaultValue={
              userIdRaw
            }
            placeholder="شناسه کاربر"
          />
        </label>

        <label
          className={
            styles.field
          }
        >
          <span>
            از تاریخ
          </span>

          <AdminDatePicker
            id="activity-log-from"
            name="from"
            defaultValue={from}
          />
        </label>

        <label
          className={
            styles.field
          }
        >
          <span>
            تا تاریخ
          </span>

          <AdminDatePicker
            id="activity-log-to"
            name="to"
            defaultValue={to}
          />
        </label>

        <div
          className={
            styles.filterActions
          }
        >
          <button
            type="submit"
            className="admin-button admin-button-primary"
          >
            اعمال فیلترها
          </button>

          <Link
            href="/activity-logs"
            className="admin-button"
          >
            پاک کردن
          </Link>
        </div>
      </form>

      <div
        className={
          styles.summary
        }
      >
        <strong>
          {
            result.meta.total
          }
        </strong>

        <span>
          فعالیت ثبت‌شده
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
                زمان
              </th>

              <th>
                عملیات
              </th>

              <th>
                توضیحات
              </th>

              <th>
                کاربر
              </th>

              <th>
                نشانی IP
              </th>

              <th>
                جزئیات
              </th>
            </tr>
          </thead>

          <tbody>
            {result.data.length ? (
              result.data.map(
                (log) => (
                  <tr
                    key={
                      log.id
                    }
                  >
                    <td
                      className={
                        styles.dateCell
                      }
                    >
                      {
                        formatDate(
                          log.created_at,
                        )
                      }
                    </td>

                    <td>
                      <code
                        className={
                          styles.actionCode
                        }
                      >
                        {
                          log.action
                        }
                      </code>
                    </td>

                    <td
                      className={
                        styles.description
                      }
                    >
                      {log.description ||
                        "—"}
                    </td>

                    <td>
                      {log.user ? (
                        <div
                          className={
                            styles.user
                          }
                        >
                          <strong>
                            {actorName(
                              log.user
                                .first_name,
                              log.user
                                .last_name,
                            )}
                          </strong>

                          <span>
                            {
                              log.user
                                .email
                            }
                          </span>

                          {log.user.roles
                            .length ? (
                            <small>
                              {log.user.roles.join(
                                ", ",
                              )}
                            </small>
                          ) : null}
                        </div>
                      ) : (
                        <span
                          className={
                            styles.systemActor
                          }
                        >
                          سیستم
                        </span>
                      )}
                    </td>

                    <td
                      className={
                        styles.ip
                      }
                    >
                      {log.ip_address ||
                        "—"}
                    </td>

                    <td>
                      <Link
                        href={`/activity-logs/${log.id}?returnTo=${encodeURIComponent(
                          currentListUrl,
                        )}`}
                        className="admin-button"
                      >
                        مشاهده
                      </Link>
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
                  فعالیتی یافت نشد.
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