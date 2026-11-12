import Link from "next/link";
import { notFound, redirect } from "next/navigation";

import { AdminSelect } from "@/components/AdminSelect";
import {
  AdminSupportTicketRequestError,
  getAdminSupportTickets,
  type SupportTicketStatus,
} from "@/lib/admin-support-tickets";
import { getCurrentAdmin } from "@/lib/admin-server";

import styles from "../contact-messages/ContactMessages.module.css";

type SearchParam = string | string[] | undefined;

function firstParam(value: SearchParam): string {
  return Array.isArray(value) ? value[0] ?? "" : value ?? "";
}

function dateLabel(value: string | null): string {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? value
    : new Intl.DateTimeFormat("fa-IR", {
        dateStyle: "medium",
        timeStyle: "short",
      }).format(date);
}

export default async function SupportTicketsPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, SearchParam>>;
}) {
  const admin = await getCurrentAdmin();
  if (!admin) redirect("/login");
  if (!admin.permissions.includes("contact_messages.view")) notFound();

  const params = await searchParams;
  const requestedStatus = firstParam(params.status);
  const status: SupportTicketStatus | undefined =
    requestedStatus === "open" || requestedStatus === "closed"
      ? requestedStatus
      : undefined;
  const requestedPage = Number(firstParam(params.page) || "1");
  const page = Number.isInteger(requestedPage) && requestedPage > 0
    ? requestedPage
    : 1;

  let result;
  try {
    result = await getAdminSupportTickets(status, page);
  } catch (error) {
    if (error instanceof AdminSupportTicketRequestError) {
      if (error.status === 401) redirect("/login");
      if (error.status === 403) notFound();
    }
    throw error;
  }

  const paramsForPage = new URLSearchParams();
  if (status) paramsForPage.set("status", status);
  const pageHref = (target: number) => {
    const next = new URLSearchParams(paramsForPage);
    next.set("page", String(target));
    return `/support-tickets?${next.toString()}`;
  };

  return (
    <section className="admin-dashboard">
      <header className="admin-page-header">
        <div>
          <p className="admin-eyebrow">پشتیبانی</p>
          <h1>تیکت‌های پشتیبانی</h1>
        </div>
        <p className="admin-muted">گفت‌وگوهای مشتریان را بررسی و پیگیری کنید.</p>
      </header>

      <form method="get" className={styles.filters}>
        <label className={styles.field}>
          <span>وضعیت</span>
          <AdminSelect name="status" defaultValue={status ?? ""}>
            <option value="">همه وضعیت‌ها</option>
            <option value="open">باز</option>
            <option value="closed">بسته</option>
          </AdminSelect>
        </label>
        <div className={styles.filterActions}>
          <button type="submit" className="admin-button admin-button-primary">اعمال</button>
          <Link href="/support-tickets" className="admin-button">پاک کردن</Link>
        </div>
      </form>

      <div className={styles.summary}>
        <strong>{result.meta.total}</strong>
        <span>تیکت</span>
      </div>

      <div className={styles.tableFrame}>
        <table className={styles.table}>
          <thead>
            <tr>
              <th>آخرین پیام</th>
              <th>مشتری</th>
              <th>موضوع</th>
              <th>وضعیت</th>
              <th>گفت‌وگو</th>
              <th>جزئیات</th>
            </tr>
          </thead>
          <tbody>
            {result.data.length ? result.data.map((ticket) => (
              <tr key={ticket.id}>
                <td className={styles.date}>{dateLabel(ticket.last_message_at)}</td>
                <td>
                  <div className={styles.customer}>
                    <strong>{ticket.customer?.name || "—"}</strong>
                    <span>{ticket.customer?.email}</span>
                    {ticket.customer?.phone && <span>{ticket.customer.phone}</span>}
                  </div>
                </td>
                <td>{ticket.subject}</td>
                <td>
                  <span className={`${styles.status} ${ticket.status === "open" ? styles.statusNew : styles.statusResolved}`}>
                    {ticket.status === "open" ? "باز" : "بسته"}
                  </span>
                  {ticket.unread_count > 0 && <span> · {ticket.unread_count} خوانده‌نشده</span>}
                </td>
                <td className={styles.preview}>{ticket.last_message}</td>
                <td>
                  <Link className="admin-button" href={`/support-tickets/${ticket.id}`}>مشاهده</Link>
                </td>
              </tr>
            )) : (
              <tr><td className={styles.empty} colSpan={6}>تیکتی برای نمایش وجود ندارد.</td></tr>
            )}
          </tbody>
        </table>
      </div>

      {result.meta.last_page > 1 && (
        <div className={styles.filterActions} style={{ marginTop: 16 }}>
          <Link aria-disabled={page <= 1} className="admin-button" href={page > 1 ? pageHref(page - 1) : pageHref(1)}>صفحه قبل</Link>
          <span>{result.meta.current_page} از {result.meta.last_page}</span>
          <Link aria-disabled={page >= result.meta.last_page} className="admin-button" href={page < result.meta.last_page ? pageHref(page + 1) : pageHref(result.meta.last_page)}>صفحه بعد</Link>
        </div>
      )}
    </section>
  );
}
