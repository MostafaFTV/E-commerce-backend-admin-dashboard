"use client";

import { AdminSelect } from "@/components/AdminSelect";

import {
  useMemo,
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

import type {
  AdminOrderStatus,
} from "@/lib/admin-orders";

import styles from "./OrderActions.module.css";

interface OrderActionsProps {
  orderId:
    number;

  status:
    AdminOrderStatus;

  canUpdateStatus:
    boolean;

  canCancel:
    boolean;

  canReturn:
    boolean;
}

const normalTransitions:
  Partial<
    Record<
      AdminOrderStatus,
      AdminOrderStatus[]
    >
  > = {
    pending_payment: [
      "paid",
    ],

    paid: [
      "processing",
    ],

    processing: [
      "shipped",
    ],

    shipped: [
      "delivered",
    ],
  };

const labels:
  Record<
    AdminOrderStatus,
    string
  > = {
    pending_payment:
      "در انتظار پرداخت",

    paid:
      "پرداخت شده",

    processing:
      "در حال انجام",

    shipped:
      "ارسال شده",

    delivered:
      "تحویل داده‌شده",

    cancelled:
      "لغو شده",

    returned:
      "مرجوعی",
  };

async function mutation(
  path:
    string,
  body:
    Record<
      string,
      unknown
    >,
) {
  const response =
    await fetch(
      path,
      {
        method:
          "PATCH",

        headers: {
          "Content-Type":
            "application/json",
        },

        body:
          JSON.stringify(
            body,
          ),
      },
    );

  const payload =
    await response
      .json()
      .catch(
        () => null,
      );

  if (!response.ok) {
    const message =
      payload &&
      typeof payload ===
        "object" &&
      "message" in
        payload &&
      typeof payload.message ===
        "string"
        ? payload.message
        : "عملیات سفارش انجام نشد.";

    const errors =
      payload &&
      typeof payload ===
        "object" &&
      "errors" in
        payload &&
      payload.errors &&
      typeof payload.errors ===
        "object"
        ? Object.values(
            payload.errors,
          )
            .flat()
            .filter(
              (
                value,
              ): value is string =>
                typeof value ===
                  "string",
            )
        : [];

    throw new Error(
      errors[0] ??
        message,
    );
  }
}

export function OrderActions({
  orderId,
  status,
  canUpdateStatus,
  canCancel,
  canReturn,
}: OrderActionsProps) {
  const router =
    useRouter();

  const transitions =
    useMemo(
      () =>
        normalTransitions[
          status
        ] ?? [],
      [status],
    );

  const [
    selectedStatus,
    setSelectedStatus,
  ] =
    useState<
      AdminOrderStatus | ""
    >(
      transitions[0] ?? "",
    );

  const [
    note,
    setNote,
  ] =
    useState("");

  const [
    busy,
    setBusy,
  ] =
    useState(false);

  const [
    error,
    setError,
  ] =
    useState<
      string | null
    >(null);

  const canCancelCurrent =
    canCancel &&
    [
      "pending_payment",
      "paid",
      "processing",
    ].includes(
      status,
    );

  const canReturnCurrent =
    canReturn &&
    [
      "shipped",
      "delivered",
    ].includes(
      status,
    );

  const hasActions =
    (
      canUpdateStatus &&
      transitions.length > 0
    ) ||
    canCancelCurrent ||
    canReturnCurrent;

  if (!hasActions) {
    return (
      <div
        className={
          styles.readOnly
        }
      >
        برای مجوزهای شما یا وضعیت فعلی سفارش، هیچ عملیاتی در دسترس نیست.
      </div>
    );
  }

  async function run(
    action:
      "status" |
      "cancel" |
      "return",
  ) {
    setError(
      null,
    );

    if (
      action ===
        "status" &&
      !selectedStatus
    ) {
      setError(
        "وضعیتی را انتخاب کنید.",
      );

      return;
    }

    if (
      (
        action ===
          "cancel" ||
        action ===
          "return"
      ) &&
      !window.confirm(
        action ===
          "cancel"
          ? "این سفارش لغو شود؟ با این کار موجودی ممکن است بازگردانده و مبلغ پرداخت‌شده بازپرداخت شود."
          : "این سفارش مرجوع شود؟ با این کار موجودی ممکن است بازگردانده و مبلغ پرداخت‌شده بازپرداخت شود.",
      )
    ) {
      return;
    }

    setBusy(
      true,
    );

    try {
      if (
        action ===
        "status"
      ) {
        await mutation(
          `/api/orders/${orderId}/status`,
          {
            status:
              selectedStatus,

            note:
              note.trim() ||
              null,
          },
        );
      }

      if (
        action ===
        "cancel"
      ) {
        await mutation(
          `/api/orders/${orderId}/cancel`,
          {
            note:
              note.trim() ||
              null,
          },
        );
      }

      if (
        action ===
        "return"
      ) {
        await mutation(
          `/api/orders/${orderId}/return`,
          {
            note:
              note.trim() ||
              null,
          },
        );
      }

      setNote("");

      router.refresh();
    } catch (
      caught
    ) {
      setError(
        caught instanceof
          Error
          ? caught.message
          : "عملیات سفارش انجام نشد.",
      );
    } finally {
      setBusy(
        false,
      );
    }
  }

  return (
    <section
      className={
        styles.card
      }
    >
      <div>
        <p
          className="admin-eyebrow"
        >
          گردش کار
        </p>

        <h2>
          مدیریت سفارش
        </h2>

        <p
          className={
            styles.help
          }
        >
          وضعیت فعلی: {" "}
          <strong>
            {labels[
              status
            ]}
          </strong>
        </p>
      </div>

      {canUpdateStatus &&
      transitions.length >
        0 ? (
        <label
          className={
            styles.field
          }
        >
          <span>
            وضعیت بعدی
          </span>

          <AdminSelect
            value={
              selectedStatus
            }
            disabled={
              busy
            }
            onChange={(
              event,
            ) =>
              setSelectedStatus(
                event.target
                  .value as
                  AdminOrderStatus,
              )
            }
          >
            {transitions.map(
              (
                transition,
              ) => (
                <option
                  key={
                    transition
                  }
                  value={
                    transition
                  }
                >
                  {
                    labels[
                      transition
                    ]
                  }
                </option>
              ),
            )}
          </AdminSelect>
        </label>
      ) : null}

      <label
        className={
          styles.field
        }
      >
        <span>
          یادداشت
        </span>

        <textarea
          rows={4}
          maxLength={
            1000
          }
          value={
            note
          }
          disabled={
            busy
          }
          placeholder="یادداشت داخلی اختیاری"
          onChange={(
            event,
          ) =>
            setNote(
              event.target
                .value,
            )
          }
        />
      </label>

      {error ? (
        <p
          className={
            styles.error
          }
        >
          {error}
        </p>
      ) : null}

      <div
        className={
          styles.actions
        }
      >
        {canUpdateStatus &&
        transitions.length >
          0 ? (
          <button
            type="button"
            className="admin-button admin-button-primary"
            disabled={
              busy
            }
            onClick={() =>
              run(
                "status",
              )
            }
          >
            {busy
              ? "در حال ذخیره..."
              : "به‌روزرسانی وضعیت"}
          </button>
        ) : null}

        {canCancelCurrent ? (
          <button
            type="button"
            className={
              styles.dangerButton
            }
            disabled={
              busy
            }
            onClick={() =>
              run(
                "cancel",
              )
            }
          >
            لغو سفارش
          </button>
        ) : null}

        {canReturnCurrent ? (
          <button
            type="button"
            className={
              styles.warningButton
            }
            disabled={
              busy
            }
            onClick={() =>
              run(
                "return",
              )
            }
          >
            مرجوعی سفارش
          </button>
        ) : null}
      </div>
    </section>
  );
}