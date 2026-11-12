import {
  notFound,
  redirect,
} from "next/navigation";

import {
  ContactMessageActions,
} from "@/components/ContactMessageActions";

import {
  AdminContactMessageRequestError,
  getAdminContactMessage,
  type ContactMessageStatus,
} from "@/lib/admin-contact-messages";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

import styles from "../ContactMessages.module.css";

interface ContactMessagePageProps {
  params:
    Promise<{
      contactMessageId: string;
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
      timeStyle: "short",
    },
  ).format(date);
}

function subjectLabel(
  value: string,
): string {
  const labels:
    Record<string, string> = {
      product: "محصول",
      order: "سفارش",
      returns: "مرجوعی",
      press: "رسانه‌ها",
      other: "سایر",
    };

  return labels[value] ?? value;
}

function statusLabel(
  value: string,
): string {
  return value
    .replaceAll("_", " ")
    .replace(
      /\b\w/g,
      (letter) =>
        letter.toUpperCase(),
    );
}

export default async function ContactMessagePage({
  params,
}: ContactMessagePageProps) {
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

  const {
    contactMessageId,
  } = await params;

  const id =
    Number(contactMessageId);

  if (
    !Number.isInteger(id) ||
    id <= 0
  ) {
    notFound();
  }

  let message;

  try {
    message =
      await getAdminContactMessage(
        id,
      );
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
        error.status === 403 ||
        error.status === 404
      ) {
        notFound();
      }
    }

    throw error;
  }


  return (
    <section className="admin-dashboard">
      <div className={styles.detailTop}>

        <span>
          پیام شماره{message.id}
        </span>
      </div>

      <header className="admin-page-header">
        <div>
          <p className="admin-eyebrow">
            پشتیبانی
          </p>

          <h1>
            {message.full_name}
          </h1>
        </div>

        <p className="admin-muted">
          پیام تماس دریافت شد{" "}
          {formatDate(
            message.created_at,
          )}
        </p>
      </header>

      <div className={styles.detailGrid}>
        <article className={styles.detailCard}>
          <span className={styles.detailLabel}>
            اطلاعات تماس
          </span>

          <strong className={styles.detailValue}>
            {message.contact}
          </strong>
        </article>

        <article className={styles.detailCard}>
          <span className={styles.detailLabel}>
            موضوع
          </span>

          <strong className={styles.detailValue}>
            {subjectLabel(
              message.subject,
            )}
          </strong>
        </article>

        <article className={styles.detailCard}>
          <span className={styles.detailLabel}>
            وضعیت
          </span>

          <strong className={styles.detailValue}>
            {statusLabel(
              message.status,
            )}
          </strong>
        </article>

        <article className={styles.detailCard}>
          <span className={styles.detailLabel}>
            زبان
          </span>

          <strong className={styles.detailValue}>
            {message.locale === "fa"
              ? "فارسی"
              : "انگلیسی"}
          </strong>
        </article>
      </div>

      <article className={styles.sectionCard}>
        <h2>
          پیام مشتری
        </h2>

        <p className={styles.originalMessage}>
          {message.message}
        </p>
      </article>

      <article className={styles.sectionCard}>
        <h2>
          پیگیری
        </h2>

        <p className="admin-muted">
          از طریق ایمیل یا شماره تماس بالا با مشتری ارتباط بگیرید و پس از رسیدگی، وضعیت پیام را به‌روزرسانی کنید.
        </p>

        <ContactMessageActions
          contactMessageId={
            message.id
          }
          currentStatus={
            message.status as
              ContactMessageStatus
          }
        />
      </article>
    </section>
  );
}