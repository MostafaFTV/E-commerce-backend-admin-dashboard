"use client";

import { AdminSelect } from "@/components/AdminSelect";

import {
  FormEvent,
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

import type {
  AdminStaffRole,
  AdminStaffStatus,
  AdminStaffUser,
} from "@/lib/admin-staff";

import styles from "./StaffForm.module.css";

interface StaffFormProps {
  initialUser?:
    AdminStaffUser | null;

  isSelf?:
    boolean;
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
      messages.length
    ) {
      return messages[0] ??
        null;
    }
  }

  return null;
}

export function StaffForm({
  initialUser =
    null,
  isSelf =
    false,
}: StaffFormProps) {
  const router =
    useRouter();

  const editing =
    Boolean(
      initialUser,
    );

  const [
    firstName,
    setFirstName,
  ] =
    useState(
      initialUser?.first_name ??
      "",
    );

  const [
    lastName,
    setLastName,
  ] =
    useState(
      initialUser?.last_name ??
      "",
    );

  const [
    email,
    setEmail,
  ] =
    useState(
      initialUser?.email ??
      "",
    );

  const [
    phone,
    setPhone,
  ] =
    useState(
      initialUser?.phone ??
      "",
    );

  const initialRole =
    (
      initialUser
        ?.roles[0] ??
      "admin"
    ) as AdminStaffRole;

  const [
    role,
    setRole,
  ] =
    useState<
      AdminStaffRole
    >(
      initialRole,
    );

  const [
    status,
    setStatus,
  ] =
    useState<
      AdminStaffStatus
    >(
      initialUser?.status ??
      "active",
    );

  const [
    password,
    setPassword,
  ] =
    useState("");

  const [
    passwordConfirmation,
    setPasswordConfirmation,
  ] =
    useState("");

  const [
    submitting,
    setSubmitting,
  ] =
    useState(false);

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

    if (
      !firstName.trim() ||
      !lastName.trim() ||
      !email.trim()
    ) {
      setError(
        "نام، نام خانوادگی و ایمیل الزامی است.",
      );

      return;
    }

    if (
      !editing &&
      password.length < 8
    ) {
      setError(
        "رمز عبور باید حداقل ۸ کاراکتر باشد.",
      );

      return;
    }

    if (
      password &&
      password !==
        passwordConfirmation
    ) {
      setError(
        "تکرار رمز عبور مطابقت ندارد.",
      );

      return;
    }

    const payload: {
      first_name:
        string;

      last_name:
        string;

      email:
        string;

      phone:
        string | null;

      password?:
        string;

      password_confirmation?:
        string;

      status?:
        AdminStaffStatus;

      role?:
        AdminStaffRole;
    } = {
      first_name:
        firstName.trim(),

      last_name:
        lastName.trim(),

      email:
        email.trim(),

      phone:
        phone.trim() ||
        null,
    };

    if (!isSelf) {
      payload.status =
        status;

      payload.role =
        role;
    }

    if (!editing) {
      payload.status =
        status;

      payload.role =
        role;
    }

    if (password) {
      payload.password =
        password;

      payload.password_confirmation =
        passwordConfirmation;
    }

    setSubmitting(
      true,
    );

    try {
      const url =
        editing &&
        initialUser
          ? `/api/staff/${initialUser.id}`
          : "/api/staff";

      const response =
        await fetch(
          url,
          {
            method:
              editing
                ? "PATCH"
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

      if (!response.ok) {
        setError(
          firstError(
            result?.errors,
          ) ??
            result?.message ??
            "ذخیره کاربر پنل انجام نشد.",
        );

        return;
      }

      router.push(
        "/staff",
      );

      router.refresh();
    } catch {
      setError(
        "ذخیره کاربر پنل انجام نشد.",
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
              مشخصات کاربر پنل
            </h2>
          </div>
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
            <label>
              نام
            </label>

            <input
              type="text"
              maxLength={100}
              required
              value={
                firstName
              }
              onChange={
                (event) =>
                  setFirstName(
                    event.target.value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label>
              نام خانوادگی
            </label>

            <input
              type="text"
              maxLength={100}
              required
              value={
                lastName
              }
              onChange={
                (event) =>
                  setLastName(
                    event.target.value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label>
              ایمیل
            </label>

            <input
              type="email"
              required
              value={
                email
              }
              onChange={
                (event) =>
                  setEmail(
                    event.target.value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label>
              شماره تماس
            </label>

            <input
              type="text"
              maxLength={30}
              value={
                phone
              }
              onChange={
                (event) =>
                  setPhone(
                    event.target.value,
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
            styles.sectionHead
          }
        >
          <div>
            <span>
              دسترسی
            </span>

            <h2>
              نقش و وضعیت
            </h2>
          </div>

          {isSelf ? (
            <p>
              نقش و وضعیت حساب خودتان محافظت می‌شود.
            </p>
          ) : null}
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
            <label>
              نقش
            </label>

            <AdminSelect
              value={
                role
              }
              disabled={
                isSelf
              }
              onChange={
                (event) =>
                  setRole(
                    event.target
                      .value as
                      AdminStaffRole,
                  )
              }
            >
              <option value="admin">
                مدیر
              </option>

              <option value="accountant">
                حسابدار
              </option>

              <option value="support">
                پشتیبانی
              </option>
            </AdminSelect>
          </div>

          <div
            className={
              styles.field
            }
          >
            <label>
              وضعیت
            </label>

            <AdminSelect
              value={
                status
              }
              disabled={
                isSelf
              }
              onChange={
                (event) =>
                  setStatus(
                    event.target
                      .value as
                      AdminStaffStatus,
                  )
              }
            >
              <option value="active">
                فعال
              </option>

              <option value="inactive">
                غیرفعال
              </option>

              <option value="suspended">
                تعلیق‌شده
              </option>
            </AdminSelect>
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
            styles.sectionHead
          }
        >
          <div>
            <span>
              امنیت
            </span>

            <h2>
              رمز عبور
            </h2>
          </div>

          <p>
            {editing
              ? "برای حفظ رمز فعلی، هر دو فیلد را خالی بگذارید."
              : "حداقل ۸ کاراکتر"}
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
            <label>
              رمز عبور
            </label>

            <input
              type="password"
              minLength={8}
              required={
                !editing
              }
              value={
                password
              }
              onChange={
                (event) =>
                  setPassword(
                    event.target.value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label>
              تکرار رمز عبور
            </label>

            <input
              type="password"
              minLength={8}
              required={
                !editing ||
                Boolean(
                  password,
                )
              }
              value={
                passwordConfirmation
              }
              onChange={
                (event) =>
                  setPasswordConfirmation(
                    event.target.value,
                  )
              }
            />
          </div>
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
                "/staff",
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
              : "ایجاد کاربر پنل"}
        </button>
      </div>
    </form>
  );
}