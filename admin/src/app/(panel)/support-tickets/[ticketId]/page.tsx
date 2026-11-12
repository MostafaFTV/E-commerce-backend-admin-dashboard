import { notFound, redirect } from "next/navigation";

import { AdminSupportTicketConversation } from "@/components/AdminSupportTicketConversation";
import {
  AdminSupportTicketRequestError,
  getAdminSupportTicket,
} from "@/lib/admin-support-tickets";
import { getCurrentAdmin } from "@/lib/admin-server";

export default async function SupportTicketPage({
  params,
}: {
  params: Promise<{ ticketId: string }>;
}) {
  const admin = await getCurrentAdmin();
  if (!admin) redirect("/login");
  if (!admin.permissions.includes("contact_messages.view")) notFound();

  const { ticketId } = await params;
  const id = Number(ticketId);
  if (!Number.isInteger(id) || id <= 0) notFound();

  let conversation;
  try {
    conversation = await getAdminSupportTicket(id);
  } catch (error) {
    if (error instanceof AdminSupportTicketRequestError) {
      if (error.status === 401) redirect("/login");
      if (error.status === 403 || error.status === 404) notFound();
    }
    throw error;
  }

  return (
    <section className="admin-dashboard">
      <header className="admin-page-header">
        <div>
          <p className="admin-eyebrow">پشتیبانی · تیکت شماره {id}</p>
          <h1>{conversation.ticket.customer?.name || "مشتری"} — {conversation.ticket.subject}</h1>
        </div>
        <p className="admin-muted">
          {conversation.ticket.customer?.email}
          {conversation.ticket.customer?.phone ? ` · ${conversation.ticket.customer.phone}` : ""}
        </p>
      </header>
      <AdminSupportTicketConversation
        initialConversation={conversation}
        canReply={admin.permissions.includes("support_tickets.reply")}
        canClose={admin.roles.some((role) => role.slug === "admin" || role.slug === "super_admin")}
      />
    </section>
  );
}
