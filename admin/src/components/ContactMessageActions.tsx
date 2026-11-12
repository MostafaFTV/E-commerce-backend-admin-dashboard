"use client";

import {
  FormEvent,
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

import styles from "@/app/(panel)/contact-messages/ContactMessages.module.css";

import type {
  ContactMessageStatus,
} from "@/lib/admin-contact-messages";

interface ContactMessageActionsProps {
  contactMessageId: number;
  currentStatus: ContactMessageStatus;
}

const statuses:
  ContactMessageStatus[] = [
    "new",
    "read",
    "resolved",
  ];

function label(
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

async function responseMessage(
  response: Response,
): Promise<string> {
  try {
    const payload =
      await response.json() as {
        message?: unknown;
      };

    if (
      typeof payload.message ===
      "string"
    ) {
      return payload.message;
    }
  } catch {
    // Ignore malformed payloads.
  }

  return "درخواست انجام نشد.";
}

export function ContactMessageActions({
  contactMessageId,
  currentStatus,
}: ContactMessageActionsProps) {
  const router =
    useRouter();

  const [
    status,
    setStatus,
  ] = useState<
    ContactMessageStatus
  >(currentStatus);

  const [
    busy,
    setBusy,
  ] = useState(false);

  const [
    feedback,
    setFeedback,
  ] = useState<string | null>(
    null,
  );

  const [
    isError,
    setIsError,
  ] = useState(false);

  async function updateStatus(
    event: FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    if (busy) {
      return;
    }

    setBusy(true);
    setFeedback(null);
    setIsError(false);

    try {
      const response =
        await fetch(
          `/api/contact-messages/${contactMessageId}/status`,
          {
            method: "PATCH",

            headers: {
              "Content-Type":
                "application/json",
            },

            body: JSON.stringify({
              status,
            }),
          },
        );

      if (!response.ok) {
        throw new Error(
          await responseMessage(
            response,
          ),
        );
      }

      setFeedback(
        "وضعیت به‌روزرسانی شد.",
      );

      window.dispatchEvent(
        new Event(
          "tkb:notifications-refresh",
        ),
      );

      router.refresh();
    } catch (error) {
      setIsError(true);

      setFeedback(
        error instanceof Error
          ? error.message
          : "به‌روزرسانی وضعیت انجام نشد.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <form
      onSubmit={updateStatus}
      className={
        styles.statusPanel
      }
    >
      <label className={styles.field}>
        <span>
          وضعیت پیام
        </span>

        <select
          value={status}
          disabled={busy}
          onChange={(event) =>
            setStatus(
              event.target
                .value as
                ContactMessageStatus,
            )
          }
        >
          {statuses.map(
            (item) => (
              <option
                key={item}
                value={item}
              >
                {label(item)}
              </option>
            ),
          )}
        </select>
      </label>

      <button
        type="submit"
        disabled={busy}
        className="admin-button admin-button-primary"
      >
        {busy
          ? "در حال ذخیره…"
          : "به‌روزرسانی وضعیت"}
      </button>

      <p
        aria-live="polite"
        className={`${styles.feedback} ${
          isError
            ? styles.error
            : ""
        }`}
      >
        {feedback ?? ""}
      </p>
    </form>
  );
}