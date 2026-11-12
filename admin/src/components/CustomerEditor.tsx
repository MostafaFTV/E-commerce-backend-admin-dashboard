"use client";

import { AdminSelect } from "@/components/AdminSelect";

import {
  type FormEvent,
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

import type {
  AdminCustomerStatus,
} from "@/lib/admin-customers";

import styles from "./CustomerEditor.module.css";

interface CustomerEditorProps {
  customer: {
    id:
      number;

    first_name:
      string | null;

    last_name:
      string | null;

    email:
      string | null;

    phone:
      string | null;

    status:
      AdminCustomerStatus;
  };

  canManage:
    boolean;
}

const statusLabels:
  Record<
    AdminCustomerStatus,
    string
  > = {
    active:
      "فعال",

    inactive:
      "غیرفعال",

    suspended:
      "تعلیق‌شده",
  };

function backendError(
  payload:
    unknown,
): string {
  if (
    !payload ||
    typeof payload !==
      "object"
  ) {
    return "به‌روزرسانی مشتری انجام نشد.";
  }

  const record =
    payload as {
      message?:
        unknown;

      errors?:
        unknown;
    };

  if (
    record.errors &&
    typeof record.errors ===
      "object"
  ) {
    const values =
      Object.values(
        record.errors as
          Record<
            string,
            unknown
          >,
      )
        .flatMap(
          (value) =>
            Array.isArray(
              value,
            )
              ? value
              : [value],
        )
        .filter(
          (
            value,
          ): value is string =>
            typeof value ===
              "string",
        );

    if (values.length) {
      return values[0];
    }
  }

  return typeof record.message ===
    "string"
    ? record.message
    : "به‌روزرسانی مشتری انجام نشد.";
}

export function CustomerEditor({
  customer,
  canManage,
}: CustomerEditorProps) {
  const router =
    useRouter();

  const [
    firstName,
    setFirstName,
  ] =
    useState(
      customer.first_name ??
        "",
    );

  const [
    lastName,
    setLastName,
  ] =
    useState(
      customer.last_name ??
        "",
    );

  const [
    email,
    setEmail,
  ] =
    useState(
      customer.email ?? "",
    );

  const [
    phone,
    setPhone,
  ] =
    useState(
      customer.phone ?? "",
    );

  const [
    status,
    setStatus,
  ] =
    useState<
      AdminCustomerStatus
    >(
      customer.status,
    );

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

  const [
    success,
    setSuccess,
  ] =
    useState<
      string | null
    >(null);

  if (!canManage) {
    return (
      <section
        className={
          styles.card
        }
      >
        <p
          className="admin-eyebrow"
        >
          حساب کاربری
        </p>

        <h2>
          مدیریت مشتری
        </h2>

        <p
          className={
            styles.readOnly
          }
        >
          شما فقط امکان مشاهده حساب مشتریان را دارید.
        </p>
      </section>
    );
  }

  async function submit(
    event:
      FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    setError(
      null,
    );

    setSuccess(
      null,
    );

    if (
      !firstName.trim() ||
      !lastName.trim()
    ) {
      setError(
        "نام و نام خانوادگی الزامی است.",
      );

      return;
    }

    if (
      customer.status ===
        "active" &&
      status !==
        "active"
    ) {
      const confirmed =
        window.confirm(
          status ===
            "suspended"
            ? "حساب مشتری تعلیق شود؟ همه نشست‌های فعال او بلافاصله بسته می‌شوند."
            : "حساب مشتری غیرفعال شود؟ همه نشست‌های فعال او بلافاصله بسته می‌شوند.",
        );

      if (!confirmed) {
        return;
      }
    }

    setBusy(
      true,
    );

    try {
      const response =
        await fetch(
          `/api/customers/${customer.id}`,
          {
            method:
              "PATCH",

            headers: {
              "Content-Type":
                "application/json",
            },

            body:
              JSON.stringify({
                first_name:
                  firstName.trim(),

                last_name:
                  lastName.trim(),

                email:
                  email.trim() ||
                  null,

                phone:
                  phone.trim() ||
                  null,

                status,
              }),
          },
        );

      const payload =
        await response
          .json()
          .catch(
            () => null,
          );

      if (!response.ok) {
        throw new Error(
          backendError(
            payload,
          ),
        );
      }

      setSuccess(
        "اطلاعات مشتری به‌روزرسانی شد.",
      );

      router.refresh();
    } catch (
      caught
    ) {
      setError(
        caught instanceof
          Error
          ? caught.message
          : "به‌روزرسانی مشتری انجام نشد.",
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
          حساب کاربری
        </p>

        <h2>
          مدیریت مشتری
        </h2>

        <p
          className={
            styles.help
          }
        >
          با غیرفعال یا تعلیق کردن مشتری، همه نشست‌های ورود فعال او بلافاصله بسته می‌شوند.
        </p>
      </div>

      <form
        className={
          styles.form
        }
        onSubmit={
          submit
        }
      >
        <div
          className={
            styles.twoColumns
          }
        >
          <label
            className={
              styles.field
            }
          >
            <span>
              نام
            </span>

            <input
              value={
                firstName
              }
              disabled={
                busy
              }
              maxLength={
                100
              }
              onChange={(
                event,
              ) =>
                setFirstName(
                  event.target
                    .value,
                )
              }
            />
          </label>

          <label
            className={
              styles.field
            }
          >
            <span>
              نام خانوادگی
            </span>

            <input
              value={
                lastName
              }
              disabled={
                busy
              }
              maxLength={
                100
              }
              onChange={(
                event,
              ) =>
                setLastName(
                  event.target
                    .value,
                )
              }
            />
          </label>
        </div>

        <label
          className={
            styles.field
          }
        >
          <span>
            ایمیل
          </span>

          <input
            type="email"
            value={
              email
            }
            disabled={
              busy
            }
            maxLength={
              255
            }
            onChange={(
              event,
            ) =>
              setEmail(
                event.target
                  .value,
              )
            }
          />
        </label>

        <label
          className={
            styles.field
          }
        >
          <span>
            شماره تماس
          </span>

          <input
            value={
              phone
            }
            disabled={
              busy
            }
            maxLength={
              30
            }
            onChange={(
              event,
            ) =>
              setPhone(
                event.target
                  .value,
              )
            }
          />
        </label>

        <label
          className={
            styles.field
          }
        >
          <span>
            وضعیت حساب
          </span>

          <AdminSelect
            value={
              status
            }
            disabled={
              busy
            }
            onChange={(
              event,
            ) =>
              setStatus(
                event.target
                  .value as
                  AdminCustomerStatus,
              )
            }
          >
            {Object.entries(
              statusLabels,
            ).map(
              ([
                value,
                label,
              ]) => (
                <option
                  key={
                    value
                  }
                  value={
                    value
                  }
                >
                  {label}
                </option>
              ),
            )}
          </AdminSelect>
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

        {success ? (
          <p
            className={
              styles.success
            }
          >
            {success}
          </p>
        ) : null}

        <button
          type="submit"
          className={
            styles.saveButton
          }
          disabled={
            busy
          }
        >
          {busy
            ? "در حال ذخیره…"
            : "ذخیره تغییرات"}
        </button>
      </form>
    </section>
  );
}