"use client";

import {
  FormEvent,
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

import type {
  AdminShippingMethod,
} from "@/lib/admin-shipping-methods";

import styles from "./ShippingMethodForm.module.css";

interface ShippingMethodFormProps {
  initialMethod?:
    AdminShippingMethod | null;
}

interface ErrorPayload {
  message?:
    string;

  errors?: {
    [key: string]:
      | string[]
      | undefined;
  };
}

function firstError(
  errors:
    ErrorPayload["errors"],
): string | null {
  if (!errors) {
    return null;
  }

  for (
    const messages
    of Object.values(
      errors,
    )
  ) {
    if (
      Array.isArray(
        messages,
      ) &&
      messages.length >
        0
    ) {
      return (
        messages[0] ??
        null
      );
    }
  }

  return null;
}

export function ShippingMethodForm({
  initialMethod =
    null,
}: ShippingMethodFormProps) {
  const router =
    useRouter();

  const editing =
    Boolean(
      initialMethod,
    );

  const [
    name,
    setName,
  ] =
    useState(
      initialMethod?.name ??
      "",
    );

  const [
    description,
    setDescription,
  ] =
    useState(
      initialMethod?.description ??
      "",
    );

  const [
    price,
    setPrice,
  ] =
    useState(
      initialMethod?.price
        ?.toString() ??
      "0",
    );

  const [
    estimatedDays,
    setEstimatedDays,
  ] =
    useState(
      initialMethod?.estimated_days ??
      "",
    );

  const [
    sortOrder,
    setSortOrder,
  ] =
    useState(
      (
        initialMethod?.sort_order ??
        0
      ).toString(),
    );

  const [
    isActive,
    setIsActive,
  ] =
    useState(
      initialMethod?.is_active ??
      true,
    );

  const [
    submitting,
    setSubmitting,
  ] =
    useState(
      false,
    );

  const [
    error,
    setError,
  ] =
    useState<
      string | null
    >(null);

  async function handleSubmit(
    event:
      FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    if (submitting) {
      return;
    }

    setError(
      null,
    );

    const cleanName =
      name.trim();

    if (!cleanName) {
      setError(
        "نام روش ارسال الزامی است.",
      );

      return;
    }

    const numericPrice =
      Number(
        price,
      );

    if (
      !Number.isFinite(
        numericPrice,
      ) ||
      numericPrice < 0
    ) {
      setError(
        "قیمت باید صفر یا بیشتر باشد.",
      );

      return;
    }

    const order =
      Number(
        sortOrder,
      );

    if (
      !Number.isInteger(
        order,
      ) ||
      order < 0
    ) {
      setError(
        "ترتیب نمایش باید صفر یا عدد صحیح مثبت باشد.",
      );

      return;
    }

    const payload = {
      name:
        cleanName,

      description:
        description.trim() ||
        null,

      price:
        numericPrice,

      estimated_days:
        estimatedDays.trim() ||
        null,

      is_active:
        isActive,

      sort_order:
        order,
    };

    setSubmitting(
      true,
    );

    try {
      const url =
        editing &&
        initialMethod
          ? `/api/shipping-methods/${initialMethod.id}`
          : "/api/shipping-methods";

      const response =
        await fetch(
          url,
          {
            method:
              editing
                ? "PUT"
                : "POST",

            headers: {
              "Content-Type":
                "application/json",
            },

            body:
              JSON.stringify(
                payload,
              ),
          },
        );

      const result =
        (
          await response
            .json()
            .catch(
              () => null,
            )
        ) as
          | ErrorPayload
          | null;

      if (
        !response.ok
      ) {
        setError(
          firstError(
            result?.errors,
          ) ??
            result?.message ??
            "ذخیره روش ارسال انجام نشد.",
        );

        return;
      }

      router.push(
        "/shipping-methods",
      );

      router.refresh();
    } catch {
      setError(
        "ذخیره روش ارسال انجام نشد.",
      );
    } finally {
      setSubmitting(
        false,
      );
    }
  }

  return (
    <form
      className={
        styles.form
      }
      onSubmit={
        handleSubmit
      }
    >
      {error ? (
        <div
          className={
            styles.errorBox
          }
        >
          {error}
        </div>
      ) : null}

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.sectionHead
          }
        >
          <div>
            <span>
              مشخصات
            </span>

            <h2>
              روش ارسال
            </h2>
          </div>

          <p>
            نام و توضیحات نمایشی این روش ارسال
          </p>
        </div>

        <div
          className={
            styles.field
          }
        >
          <label
            htmlFor="shipping-name"
          >
            نام
          </label>

          <input
            id="shipping-name"
            type="text"
            maxLength={150}
            required
            value={
              name
            }
            onChange={
              (event) =>
                setName(
                  event.target
                    .value,
                )
            }
          />
        </div>

        <div
          className={
            styles.field
          }
        >
          <label
            htmlFor="shipping-description"
          >
            توضیحات
          </label>

          <textarea
            id="shipping-description"
            value={
              description
            }
            onChange={
              (event) =>
                setDescription(
                  event.target
                    .value,
                )
            }
          />
        </div>
      </section>

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.sectionHead
          }
        >
          <div>
            <span>
              ارسال
            </span>

            <h2>
              هزینه و زمان
            </h2>
          </div>

          <p>
            هزینه و پیام زمان تقریبی تحویل را تنظیم کنید.
          </p>
        </div>

        <div
          className={
            styles.grid
          }
        >
          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="shipping-price"
            >
              قیمت
            </label>

            <input
              id="shipping-price"
              type="number"
              min="0"
              step="0.01"
              value={
                price
              }
              onChange={
                (event) =>
                  setPrice(
                    event.target
                      .value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="shipping-estimated"
            >
              زمان تقریبی تحویل
            </label>

            <input
              id="shipping-estimated"
              type="text"
              maxLength={100}
              value={
                estimatedDays
              }
              placeholder="۳ تا ۵ روز"
              onChange={
                (event) =>
                  setEstimatedDays(
                    event.target
                      .value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="shipping-order"
            >
              ترتیب نمایش
            </label>

            <input
              id="shipping-order"
              type="number"
              min="0"
              step="1"
              value={
                sortOrder
              }
              onChange={
                (event) =>
                  setSortOrder(
                    event.target
                      .value,
                  )
              }
            />
          </div>
        </div>
      </section>

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.statusRow
          }
        >
          <div>
            <strong>
              روش ارسال فعال باشد
            </strong>

            <p>
              روش‌های غیرفعال در پنل باقی می‌مانند و به مشتری نمایش داده نمی‌شوند.
            </p>
          </div>

          <label
            className={
              styles.switch
            }
          >
            <input
              type="checkbox"
              checked={
                isActive
              }
              onChange={
                (event) =>
                  setIsActive(
                    event.target
                      .checked,
                  )
              }
            />

            <span>
              {isActive
                ? "فعال"
                : "غیرفعال"}
            </span>
          </label>
        </div>
      </section>

      <div
        className={
          styles.footer
        }
      >
        <button
          type="button"
          className="admin-button"
          disabled={
            submitting
          }
          onClick={
            () =>
              router.push(
                "/shipping-methods",
              )
          }
        >
          انصراف
        </button>

        <button
          type="submit"
          className="admin-button admin-button-primary"
          disabled={
            submitting
          }
        >
          {submitting
            ? "در حال ذخیره…"
            : editing
              ? "ذخیره تغییرات"
              : "ایجاد روش ارسال"}
        </button>
      </div>
    </form>
  );
}