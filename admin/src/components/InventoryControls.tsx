"use client";

import {
  FormEvent,
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

import styles from "./InventoryControls.module.css";

interface InventoryControlsProps {
  inventoryId:
    number;

  quantity:
    number;

  reserved:
    number;

  threshold:
    number;

  canManage:
    boolean;
}

interface ErrorPayload {
  message?:
    string;

  errors?: {
    [key: string]:
      string[] | undefined;
  };
}

function firstError(
  payload:
    ErrorPayload,
): string {
  if (payload.errors) {
    for (
      const values
      of Object.values(
        payload.errors,
      )
    ) {
      if (
        Array.isArray(
          values,
        ) &&
        values[0]
      ) {
        return values[0];
      }
    }
  }

  return (
    payload.message ??
    "انجام درخواست ممکن نشد."
  );
}

export function InventoryControls({
  inventoryId,
  quantity,
  reserved,
  threshold,
  canManage,
}: InventoryControlsProps) {
  const router =
    useRouter();

  const [
    quantityDelta,
    setQuantityDelta,
  ] =
    useState("");

  const [
    note,
    setNote,
  ] =
    useState("");

  const [
    nextThreshold,
    setNextThreshold,
  ] =
    useState(
      String(
        threshold,
      ),
    );

  const [
    adjustmentError,
    setAdjustmentError,
  ] =
    useState<string | null>(
      null,
    );

  const [
    thresholdError,
    setThresholdError,
  ] =
    useState<string | null>(
      null,
    );

  const [
    adjustmentPending,
    setAdjustmentPending,
  ] =
    useState(false);

  const [
    thresholdPending,
    setThresholdPending,
  ] =
    useState(false);

  async function submitAdjustment(
    event:
      FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    setAdjustmentError(
      null,
    );

    const delta =
      Number(
        quantityDelta,
      );

    if (
      !Number.isInteger(
        delta,
      ) ||
      delta === 0
    ) {
      setAdjustmentError(
        "یک عدد صحیح غیرصفر وارد کنید.",
      );

      return;
    }

    if (!note.trim()) {
      setAdjustmentError(
        "واردکردن دلیل اصلاح موجودی الزامی است.",
      );

      return;
    }

    setAdjustmentPending(
      true,
    );

    try {
      const response =
        await fetch(
          `/api/inventory/${inventoryId}/adjust`,
          {
            method:
              "POST",

            headers: {
              "Content-Type":
                "application/json",
            },

            body:
              JSON.stringify({
                quantity_delta:
                  delta,

                note:
                  note.trim(),
              }),
          },
        );

      const payload =
        await response
          .json()
          .catch(
            () => ({}),
          ) as ErrorPayload;

      if (!response.ok) {
        setAdjustmentError(
          firstError(
            payload,
          ),
        );

        return;
      }

      setQuantityDelta(
        "",
      );

      setNote(
        "",
      );

      router.refresh();
    } catch {
      setAdjustmentError(
        "ارتباط با سرور برقرار نشد.",
      );
    } finally {
      setAdjustmentPending(
        false,
      );
    }
  }

  async function submitThreshold(
    event:
      FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    setThresholdError(
      null,
    );

    const value =
      Number(
        nextThreshold,
      );

    if (
      !Number.isInteger(
        value,
      ) ||
      value < 0
    ) {
      setThresholdError(
        "آستانه باید عدد صحیحی بزرگ‌تر یا مساوی صفر باشد.",
      );

      return;
    }

    setThresholdPending(
      true,
    );

    try {
      const response =
        await fetch(
          `/api/inventory/${inventoryId}/threshold`,
          {
            method:
              "PATCH",

            headers: {
              "Content-Type":
                "application/json",
            },

            body:
              JSON.stringify({
                low_stock_threshold:
                  value,
              }),
          },
        );

      const payload =
        await response
          .json()
          .catch(
            () => ({}),
          ) as ErrorPayload;

      if (!response.ok) {
        setThresholdError(
          firstError(
            payload,
          ),
        );

        return;
      }

      router.refresh();
    } catch {
      setThresholdError(
        "ارتباط با سرور برقرار نشد.",
      );
    } finally {
      setThresholdPending(
        false,
      );
    }
  }

  if (!canManage) {
    return (
      <div
        className={
          styles.readOnly
        }
        dir="rtl"
        lang="fa"
      >
        دسترسی شما به موجودی فقط‌خواندنی است. با مجوزهای فعلی
        امکان تغییر موجودی فیزیکی و تنظیمات کمبود موجودی را ندارید.
      </div>
    );
  }

  return (
    <div
      className={
        styles.grid
      }
      dir="rtl"
      lang="fa"
    >
      <form
        className={
          styles.card
        }
        onSubmit={
          submitAdjustment
        }
      >
        <div>
          <span
            className={
              styles.eyebrow
            }
          >
            موجودی فیزیکی
          </span>

          <h2>
            اصلاح موجودی
          </h2>
        </div>

        <p
          className={
            styles.help
          }
        >
          موجودی فیزیکی فعلی:{" "}
          <strong>
            <bdi dir="ltr">{quantity}</bdi>
          </strong>
          . مقدار رزروشده:{" "}
          <strong>
            <bdi dir="ltr">{reserved}</bdi>
          </strong>
          . موجودی رزروشده به‌صورت سیستمی مدیریت می‌شود.
        </p>

        <label
          className={
            styles.field
          }
        >
          <span>
            تغییر تعداد
          </span>

          <input
            type="number"
            dir="ltr"
            step="1"
            value={
              quantityDelta
            }
            onChange={(
              event,
            ) =>
              setQuantityDelta(
                event.target
                  .value,
              )
            }
            placeholder="+5 یا ‎-2"
          />
        </label>

        <label
          className={
            styles.field
          }
        >
          <span>
            دلیل
          </span>

          <textarea
            rows={4}
            value={note}
            onChange={(
              event,
            ) =>
              setNote(
                event.target
                  .value,
              )
            }
            placeholder="برای نمونه: اصلاح شمارش یا دریافت کالا در انبار"
          />
        </label>

        {adjustmentError ? (
          <p
            className={
              styles.error
            }
          >
            {
              adjustmentError
            }
          </p>
        ) : null}

        <button
          type="submit"
          className="admin-button admin-button-primary"
          disabled={
            adjustmentPending
          }
        >
          {adjustmentPending
            ? "در حال ذخیره..."
            : "ثبت اصلاح موجودی"}
        </button>
      </form>

      <form
        className={
          styles.card
        }
        onSubmit={
          submitThreshold
        }
      >
        <div>
          <span
            className={
              styles.eyebrow
            }
          >
            هشدار موجودی
          </span>

          <h2>
            آستانه کمبود موجودی
          </h2>
        </div>

        <p
          className={
            styles.help
          }
        >
          اگر موجودی قابل‌عرضه به این تعداد یا کمتر برسد،
          کالا کم‌موجود در نظر گرفته می‌شود.
        </p>

        <label
          className={
            styles.field
          }
        >
          <span>
            آستانه
          </span>

          <input
            type="number"
            dir="ltr"
            min="0"
            step="1"
            value={
              nextThreshold
            }
            onChange={(
              event,
            ) =>
              setNextThreshold(
                event.target
                  .value,
              )
            }
          />
        </label>

        {thresholdError ? (
          <p
            className={
              styles.error
            }
          >
            {
              thresholdError
            }
          </p>
        ) : null}

        <button
          type="submit"
          className="admin-button"
          disabled={
            thresholdPending
          }
        >
          {thresholdPending
            ? "در حال ذخیره..."
            : "به‌روزرسانی آستانه"}
        </button>
      </form>
    </div>
  );
}