"use client";

import { useCallback, useEffect, useRef, useState, type FormEvent } from "react";

import type {
  AdminSupportTicketConversation as Conversation,
  SupportTicketMessage,
} from "@/lib/admin-support-tickets";
import styles from "@/app/(panel)/contact-messages/ContactMessages.module.css";
import supportStyles from "@/app/(panel)/support-tickets/SupportTickets.module.css";

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

function responseMessage(payload: unknown): string {
  if (payload && typeof payload === "object") {
    const message = (payload as { message?: unknown }).message;
    if (typeof message === "string") return message;
  }
  return "درخواست انجام نشد.";
}

export function AdminSupportTicketConversation({
  initialConversation,
  canReply,
  canClose,
}: {
  initialConversation: Conversation;
  canReply: boolean;
  canClose: boolean;
}) {
  const [conversation, setConversation] = useState(initialConversation);
  const [draft, setDraft] = useState("");
  const [busy, setBusy] = useState(false);
  const [feedback, setFeedback] = useState("");
  const [error, setError] = useState(false);
  const pollBusy = useRef(false);

  const refresh = useCallback(async (silent = false, page = 1) => {
    if (pollBusy.current) return;
    pollBusy.current = true;
    try {
      const response = await fetch(
        `/api/support-tickets/${conversation.ticket.id}?page=${page}&per_page=50`,
        { cache: "no-store" },
      );
      const payload = await response.json();
      if (!response.ok) throw new Error(responseMessage(payload));
      const next = (payload as { data: Conversation }).data;
      setConversation((current) => ({
        ...next,
        messages: current.ticket.id === next.ticket.id
          ? [...next.messages, ...current.messages].filter(
              (message, index, list) => list.findIndex((item) => item.id === message.id) === index,
            ).sort((left, right) => left.id - right.id)
          : next.messages,
      }));
      setFeedback("");
      setError(false);
      if (!silent) {
        setFeedback("");
        setError(false);
      }
    } catch (cause) {
      setError(true);
      setFeedback(cause instanceof Error ? cause.message : "دریافت گفت‌وگو انجام نشد.");
    } finally {
      pollBusy.current = false;
    }
  }, [conversation.ticket.id]);

  useEffect(() => {
    if (conversation.ticket.status !== "open") return;
    const timer = window.setInterval(() => void refresh(true), 15000);
    return () => window.clearInterval(timer);
  }, [conversation.ticket.status, refresh]);

  async function submitReply(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (busy || !draft.trim() || conversation.ticket.status !== "open") return;
    setBusy(true);
    setError(false);
    setFeedback("");
    try {
      const response = await fetch(`/api/support-tickets/${conversation.ticket.id}/messages`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          message: draft.trim(),
          client_message_id: crypto.randomUUID(),
        }),
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(responseMessage(payload));
      setDraft("");
      await refresh(true);
    } catch (cause) {
      setError(true);
      setFeedback(cause instanceof Error ? cause.message : "ارسال پاسخ انجام نشد.");
    } finally {
      setBusy(false);
    }
  }

  async function closeTicket() {
    if (busy || conversation.ticket.status === "closed") return;
    setBusy(true);
    setError(false);
    setFeedback("");
    try {
      const response = await fetch(`/api/support-tickets/${conversation.ticket.id}/close`, {
        method: "PATCH",
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(responseMessage(payload));
      await refresh(true);
      setFeedback("تیکت بسته شد.");
    } catch (cause) {
      setError(true);
      setFeedback(cause instanceof Error ? cause.message : "بستن تیکت انجام نشد.");
    } finally {
      setBusy(false);
    }
  }

  const messages = conversation.messages;
  const olderPage = conversation.meta.current_page + 1;

  return (
    <>
      <article className={styles.sectionCard}>
        <div className={supportStyles.ticketTop}>
          <div>
            <strong>وضعیت: {conversation.ticket.status === "open" ? "باز" : "بسته"}</strong>
            <span>موضوع: {conversation.ticket.subject}</span>
            <span>زبان مشتری: {conversation.ticket.locale === "fa" ? "فارسی" : "انگلیسی"}</span>
          </div>
          {conversation.ticket.status === "open" && canClose && (
            <button type="button" className="admin-button" disabled={busy} onClick={() => void closeTicket()}>
              بستن تیکت
            </button>
          )}
        </div>
        {conversation.meta.current_page < conversation.meta.last_page && (
          <button
            type="button"
            className="admin-button"
            disabled={pollBusy.current}
            onClick={() => void refresh(false, olderPage)}
          >
            نمایش پیام‌های قدیمی‌تر
          </button>
        )}
        <div className={supportStyles.messageList} aria-live="polite">
          {messages.map((message: SupportTicketMessage) => (
            <article
              className={`${supportStyles.message} ${message.sender_role === "admin" ? supportStyles.adminMessage : ""}`}
              key={message.id}
            >
              <div>
                <strong>{message.sender_name}</strong>
                <span>{message.sender_role === "admin" ? "پشتیبانی" : "مشتری"} · {dateLabel(message.created_at)}</span>
              </div>
              <p>{message.message}</p>
            </article>
          ))}
          {messages.length === 0 && <p className="admin-muted">پیامی برای نمایش وجود ندارد.</p>}
        </div>
      </article>

      {conversation.ticket.status === "open" && canReply && (
        <form className={styles.sectionCard} onSubmit={submitReply}>
          <label className={supportStyles.replyField}>
            <span>پاسخ به مشتری</span>
            <textarea
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              maxLength={5000}
              rows={5}
              required
              disabled={busy}
            />
          </label>
          <button type="submit" className="admin-button admin-button-primary" disabled={busy || !draft.trim()}>
            {busy ? "در حال ارسال…" : "ارسال پاسخ"}
          </button>
        </form>
      )}

      {feedback && (
        <p aria-live="polite" className={`${styles.feedback} ${error ? styles.error : ""}`}>
          {feedback}
        </p>
      )}
    </>
  );
}
