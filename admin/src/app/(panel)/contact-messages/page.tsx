import Link from "next/link";

import {
  notFound,
  redirect,
} from "next/navigation";

import {
  AdminSelect,
} from "@/components/AdminSelect";

import {
  AdminContactMessageRequestError,
  contactMessageStatuses,
  getAdminContactMessages,
  type ContactMessageStatus,
} from "@/lib/admin-contact-messages";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "./ContactMessages.module.css";

type SearchParam =
  | string
  | string[]
  | undefined;

interface ContactMessagesPageProps {
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

function subjectLabel(
  subject: string,
): string {
  const labels:
    Record<string, string> = {
      product: "محصول",
      order: "سفارش",
      returns: "مرجوعی",
      press: "رسانه‌ها",
      other: "سایر",
    };

  return labels[subject] ?? subject;
}

function statusLabel(
  status: string,
): string {
  return status
    .replaceAll("_", " ")
    .replace(
      /\b\w/g,
      (letter) =>
        letter.toUpperCase(),
    );
}

function statusClass(
  status: ContactMessageStatus,
): string {
  switch (status) {
    case "new":
      return styles.statusNew;

    case "read":
      return styles.statusRead;

    case "resolved":
      return styles.statusResolved;
  }
}

export default async function ContactMessagesPage({
  searchParams,
}: ContactMessagesPageProps) {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect("/login");
  }

  if (
    !admin.permissions.includes(
      "contact_messages.view",
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

  const statusRaw =
    firstParam(
      params.status,
    ).trim();

  const status =
    contactMessageStatuses.includes(
      statusRaw as ContactMessageStatus,
    )
      ? statusRaw as ContactMessageStatus
      : undefined;

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
      await getAdminContactMessages({
        page,
        search:
          search || undefined,
        status,
      });
  } catch (error) {
    if (
      error instanceof
        AdminContactMessageRequestError
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

  if (status) {
    filterParams.set(
      "status",
      status,
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

    return `/contact-messages?${next.toString()}`;
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
      ? `/contact-messages?${currentListParams.toString()}`
      : "/contact-messages";

  return (
    <section className="admin-dashboard">
      <header className="admin-page-header">
        <div>
          <p className="admin-eyebrow">
            پشتیبانی
          </p>

          <h1>
            پیام‌های تماس
          </h1>
        </div>

        <p className="admin-muted">
          پیام‌های فرم تماس با ما را بررسی و از طریق اطلاعات تماس مشتری پیگیری کنید.
        </p>
      </header>

      <form
        method="get"
        className={styles.filters}
      >
        <label className={styles.field}>
          <span>
            جستجو
          </span>

          <input
            type="search"
            name="search"
            defaultValue={search}
            placeholder="نام، اطلاعات تماس، موضوع یا متن پیام"
          />
        </label>

        <label className={styles.field}>
          <span>
            وضعیت
          </span>

          <AdminSelect
            name="status"
            defaultValue={
              status ?? ""
            }
          >
            <option value="">
              همه وضعیت‌ها
            </option>

            {contactMessageStatuses.map(
              (item) => (
                <option
                  key={item}
                  value={item}
                >
                  {statusLabel(item)}
                </option>
              ),
            )}
          </AdminSelect>
        </label>

        <div
          className={styles.filterActions}
        >
          <button
            type="submit"
            className="admin-button admin-button-primary"
          >
            اعمال
          </button>

          <Link
            href="/contact-messages"
            className="admin-button"
          >
            پاک کردن
          </Link>
        </div>
      </form>

      <div className={styles.summary}>
        <strong>
          {result.meta.total}
        </strong>

        <span>
          پیام
        </span>
      </div>

      <div className={styles.tableFrame}>
        <table className={styles.table}>
          <thead>
            <tr>
              <th>
                زمان دریافت
              </th>

              <th>
                مشتری
              </th>

              <th>
                موضوع
              </th>

              <th>
                وضعیت
              </th>

              <th>
                پیام
              </th>

              <th>
                جزئیات
              </th>
            </tr>
          </thead>

          <tbody>
            {result.data.length ? (
              result.data.map(
                (message) => {
                  const preview =
                    message.message.length >
                    90
                      ? `${message.message.slice(
                          0,
                          90,
                        )}…`
                      : message.message;

                  return (
                    <tr
                      key={message.id}
                    >
                      <td
                        className={
                          styles.date
                        }
                      >
                        {formatDate(
                          message.created_at,
                        )}
                      </td>

                      <td>
                        <div
                          className={
                            styles.customer
                          }
                        >
                          <strong>
                            {
                              message.full_name
                            }
                          </strong>

                          <span>
                            {
                              message.contact
                            }
                          </span>

                          <span>
                            زبان:{" "}
                            {
                              message.locale
                            }
                          </span>
                        </div>
                      </td>

                      <td>
                        {subjectLabel(
                          message.subject,
                        )}
                      </td>

                      <td>
                        <span
                          className={`${styles.status} ${statusClass(
                            message.status,
                          )}`}
                        >
                          {statusLabel(
                            message.status,
                          )}
                        </span>
                      </td>

                      <td
                        className={
                          styles.preview
                        }
                      >
                        {preview}
                      </td>

                      <td>
                        <Link
                          href={`/contact-messages/${message.id}?returnTo=${encodeURIComponent(
                            currentListUrl,
                          )}`}
                          className="admin-button"
                        >
                          مشاهده
                        </Link>
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
                  پیامی یافت نشد.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {result.meta.last_page >
      1 ? (
        <nav className="admin-pagination">
          {result.meta.current_page >
          1 ? (
            <Link
              href={pageHref(
                result.meta
                  .current_page - 1,
              )}
            >
              → قبلی
            </Link>
          ) : null}

          <span>
            صفحه{" "}
            {
              result.meta.current_page
            }{" "}
            از{" "}
            {
              result.meta.last_page
            }
          </span>

          {result.meta.current_page <
          result.meta.last_page ? (
            <Link
              href={pageHref(
                result.meta
                  .current_page + 1,
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