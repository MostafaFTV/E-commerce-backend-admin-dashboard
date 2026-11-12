import type {
  ReactNode,
} from "react";

import {
  notFound,
  redirect,
} from "next/navigation";

import {
  AdminActivityLogRequestError,
  getAdminActivityLog,
} from "@/lib/admin-activity-logs";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "../ActivityLogs.module.css";

interface ActivityLogPageProps {
  params:
    Promise<{
      activityLogId: string;
    }>;
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
      dateStyle: "long",
      timeStyle: "medium",
    },
  ).format(date);
}

function actorName(
  firstName: string,
  lastName: string,
): string {
  return (
    `${firstName} ${lastName}`
      .trim() ||
    "کاربر پنل"
  );
}

function isRecord(
  value: unknown,
): value is Record<string, unknown> {
  return (
    typeof value === "object" &&
    value !== null &&
    !Array.isArray(value)
  );
}

function formatPropertyLabel(
  key: string,
): string {
  return key
    .replaceAll("_", " ")
    .replace(
      /\b\w/g,
      (letter) =>
        letter.toUpperCase(),
    );
}

function renderPropertyValue(
  value: unknown,
): ReactNode {
  if (
    value === null ||
    value === undefined
  ) {
    return (
      <span
        className={
          styles.nullValue
        }
      >
        ثبت نشده
      </span>
    );
  }

  if (
    typeof value ===
    "boolean"
  ) {
    return (
      <span
        className={
          styles.booleanValue
        }
      >
        {value
          ? "بله"
          : "خیر"}
      </span>
    );
  }

  if (
    typeof value ===
      "string" ||
    typeof value ===
      "number"
  ) {
    return (
      <span
        className={
          styles.primitiveValue
        }
      >
        {String(value)}
      </span>
    );
  }

  if (
    Array.isArray(value)
  ) {
    if (!value.length) {
      return (
        <span
          className={
            styles.nullValue
          }
        >
          خالی
        </span>
      );
    }

    return (
      <div
        className={
          styles.arrayValue
        }
      >
        {value.map(
          (
            item,
            index,
          ) => (
            <div
              key={index}
              className={
                styles.arrayItem
              }
            >
              {renderPropertyValue(
                item,
              )}
            </div>
          ),
        )}
      </div>
    );
  }

  if (isRecord(value)) {
    return (
      <div
        className={
          styles.nestedProperties
        }
      >
        {Object.entries(
          value,
        ).map(
          ([
            key,
            nestedValue,
          ]) => (
            <div
              key={key}
              className={
                styles.nestedProperty
              }
            >
              <span
                className={
                  styles.nestedLabel
                }
              >
                {formatPropertyLabel(
                  key,
                )}
              </span>

              <div
                className={
                  styles.nestedValue
                }
              >
                {renderPropertyValue(
                  nestedValue,
                )}
              </div>
            </div>
          ),
        )}
      </div>
    );
  }

  return (
    <span
      className={
        styles.primitiveValue
      }
    >
      {String(value)}
    </span>
  );
}

function PropertiesView({
  value,
}: {
  value: unknown;
}) {
  if (!isRecord(value)) {
    return (
      <div
        className={
          styles.emptyProperties
        }
      >
        اطلاعات تکمیلی ثبت نشده است.
      </div>
    );
  }

  const entries =
    Object.entries(value);

  if (!entries.length) {
    return (
      <div
        className={
          styles.emptyProperties
        }
      >
        اطلاعات تکمیلی ثبت نشده است.
      </div>
    );
  }

  return (
    <div
      className={
        styles.propertiesGrid
      }
    >
      {entries.map(
        ([
          key,
          propertyValue,
        ]) => (
          <div
            key={key}
            className={
              styles.propertyCard
            }
          >
            <span
              className={
                styles.propertyLabel
              }
            >
              {formatPropertyLabel(
                key,
              )}
            </span>

            <div
              className={
                styles.propertyValue
              }
            >
              {renderPropertyValue(
                propertyValue,
              )}
            </div>
          </div>
        ),
      )}
    </div>
  );
}

export default async function ActivityLogPage({
  params,
}: ActivityLogPageProps) {
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

  const {
    activityLogId,
  } = await params;

  const id =
    Number(activityLogId);

  if (
    !Number.isInteger(id) ||
    id <= 0
  ) {
    notFound();
  }

  let log;

  try {
    log =
      await getAdminActivityLog(
        id,
      );
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
        error.status === 403 ||
        error.status === 404
      ) {
        notFound();
      }
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
            فعالیت شماره
            {log.id}
          </p>

          <h1>
            {log.action}
          </h1>
        </div>

        <p
          className="admin-muted"
        >
          این گزارش فقط قابل مشاهده است.
        </p>
      </header>

      <div
        className={
          styles.detailGrid
        }
      >
        <article
          className={
            styles.detailCard
          }
        >
          <span
            className={
              styles.detailLabel
            }
          >
            عملیات
          </span>

          <code
            className={
              styles.actionCode
            }
          >
            {log.action}
          </code>
        </article>

        <article
          className={
            styles.detailCard
          }
        >
          <span
            className={
              styles.detailLabel
            }
          >
            تاریخ و ساعت
          </span>

          <strong>
            {
              formatDate(
                log.created_at,
              )
            }
          </strong>
        </article>

        <article
          className={
            styles.detailCard
          }
        >
          <span
            className={
              styles.detailLabel
            }
          >
            نشانی IP
          </span>

          <strong>
            {log.ip_address ||
              "—"}
          </strong>
        </article>

        <article
          className={
            styles.detailCard
          }
        >
          <span
            className={
              styles.detailLabel
            }
          >
            انجام‌دهنده
          </span>

          {log.user ? (
            <div
              className={
                styles.detailUser
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

              <small>
                کاربر شماره
                {
                  log.user.id
                }
                {log.user.roles
                  .length
                  ? ` · ${log.user.roles.join(
                      ", ",
                    )}`
                  : ""}
              </small>
            </div>
          ) : (
            <strong>
              سیستم
            </strong>
          )}
        </article>
      </div>

      <article
        className={
          styles.sectionCard
        }
      >
        <h2>
          توضیحات
        </h2>

        <p>
          {log.description ||
            "توضیحی ثبت نشده است."}
        </p>
      </article>

      <article
        className={
          styles.sectionCard
        }
      >
        <h2>
          اطلاعات تکمیلی
        </h2>

        <PropertiesView
          value={
            log.properties
          }
        />
      </article>

      <article
        className={
          styles.sectionCard
        }
      >
        <h2>
          مشخصات مرورگر
        </h2>

        <p
          className={
            styles.userAgent
          }
        >
          {log.user_agent ||
            "—"}
        </p>
      </article>
    </section>
  );
}