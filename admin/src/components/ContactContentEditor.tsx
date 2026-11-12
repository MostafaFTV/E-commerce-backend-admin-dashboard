"use client";

import {
  FormEvent,
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

import type {
  AdminContactPageContent,
} from "@/lib/admin-cms-content";

import type {
  ApiErrorPayload,
  ApiValidationErrors,
} from "@/lib/admin-types";

import styles from "./ContactContentEditor.module.css";

interface Props {
  initialContent:
    AdminContactPageContent;
}

interface ContactFormState {
  eyebrow: string;
  eyebrow_en: string;

  title: string;
  title_en: string;

  instagram_label: string;
  instagram_label_en: string;
  instagram_value: string;
  instagram_url: string;

  telegram_label: string;
  telegram_label_en: string;
  telegram_value: string;
  telegram_url: string;

  whatsapp_label: string;
  whatsapp_label_en: string;
  whatsapp_value: string;
  whatsapp_url: string;

  phone_label: string;
  phone_label_en: string;
  phone_value: string;
  phone_url: string;

  is_active: boolean;
}

function firstError(
  errors: ApiValidationErrors,
  key: string,
): string | null {
  const values =
    errors[key];

  if (
    !values ||
    values.length === 0
  ) {
    return null;
  }

  return values[0] ?? null;
}

function initialState(
  content: AdminContactPageContent,
): ContactFormState {
  return {
    eyebrow:
      content.eyebrow ?? "",

    eyebrow_en:
      content.eyebrow_en ?? "",

    title:
      content.title ?? "",

    title_en:
      content.title_en ?? "",

    instagram_label:
      content.instagram_label ?? "",

    instagram_label_en:
      content.instagram_label_en ?? "",

    instagram_value:
      content.instagram_value ?? "",

    instagram_url:
      content.instagram_url ?? "",

    telegram_label:
      content.telegram_label ?? "",

    telegram_label_en:
      content.telegram_label_en ?? "",

    telegram_value:
      content.telegram_value ?? "",

    telegram_url:
      content.telegram_url ?? "",

    whatsapp_label:
      content.whatsapp_label ?? "",

    whatsapp_label_en:
      content.whatsapp_label_en ?? "",

    whatsapp_value:
      content.whatsapp_value ?? "",

    whatsapp_url:
      content.whatsapp_url ?? "",

    phone_label:
      content.phone_label ?? "",

    phone_label_en:
      content.phone_label_en ?? "",

    phone_value:
      content.phone_value ?? "",

    phone_url:
      content.phone_url ?? "",

    is_active:
      Boolean(
        content.is_active,
      ),
  };
}

export function ContactContentEditor({
  initialContent,
}: Props) {
  const router =
    useRouter();

  const [
    form,
    setForm,
  ] =
    useState<ContactFormState>(
      () =>
        initialState(
          initialContent,
        ),
    );

  const [
    errors,
    setErrors,
  ] =
    useState<ApiValidationErrors>(
      {},
    );

  const [
    message,
    setMessage,
  ] =
    useState("");

  const [
    submitting,
    setSubmitting,
  ] =
    useState(false);

  function setField<
    K extends keyof ContactFormState,
  >(
    key: K,
    value: ContactFormState[K],
  ) {
    setForm(
      (current) => ({
        ...current,
        [key]: value,
      }),
    );

    setErrors(
      (current) => {
        if (!current[key]) {
          return current;
        }

        const next = {
          ...current,
        };

        delete next[key];

        return next;
      },
    );

    setMessage("");
  }

  async function handleSubmit(
    event: FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    if (submitting) {
      return;
    }

    setSubmitting(true);
    setErrors({});
    setMessage("");

    try {
      const response =
        await fetch(
          "/api/contact-content",
          {
            method: "PUT",

            headers: {
              "Content-Type":
                "application/json",

              Accept:
                "application/json",
            },

            body:
              JSON.stringify(
                form,
              ),
          },
        );

      const payload =
        await response
          .json()
          .catch(
            () => null,
          ) as
            ApiErrorPayload |
            null;

      if (!response.ok) {
        if (
          payload?.errors &&
          typeof payload.errors ===
            "object"
        ) {
          setErrors(
            payload.errors,
          );
        }

        setMessage(
          payload?.message ??
            "ذخیره محتوای تماس انجام نشد.",
        );

        return;
      }

      setMessage(
        payload?.message ??
          "محتوای تماس ذخیره شد.",
      );

      router.refresh();
    } catch {
      setMessage(
        "اتصال به سرور برقرار نشد.",
      );
    } finally {
      setSubmitting(false);
    }
  }

  function textField(
    key: keyof ContactFormState,
    label: string,
    options?: {
      dir?: "ltr" | "rtl";
      type?: string;
      placeholder?: string;
    },
  ) {
    const value =
      form[key];

    if (
      typeof value !==
      "string"
    ) {
      return null;
    }

    const error =
      firstError(
        errors,
        key,
      );

    return (
      <label
        className={
          styles.field
        }
      >
        <span>
          {label}
        </span>

        <input
          type={
            options?.type ??
            "text"
          }
          dir={
            options?.dir ??
            "ltr"
          }
          value={value}
          placeholder={
            options
              ?.placeholder
          }
          onChange={(
            event,
          ) =>
            setField(
              key,
              event.target
                .value,
            )
          }
        />

        {error ? (
          <small
            className={
              styles.fieldError
            }
          >
            {error}
          </small>
        ) : null}
      </label>
    );
  }

  return (
    <form
      className={styles.form}
      onSubmit={
        handleSubmit
      }
    >
      {message ? (
        <div
          className={
            styles.notice
          }
          data-error={
            Object.keys(
              errors,
            ).length > 0
          }
        >
          {message}
        </div>
      ) : null}

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.sectionHeader
          }
        >
          <div>
            <p>
              بخش تماس
            </p>

            <h2>
              عنوان‌ها
            </h2>
          </div>

          <span>
            چیدمان فعلی سایت حفظ می‌شود.
          </span>
        </div>

        <div
          className={
            styles.grid
          }
        >
          {textField(
            "eyebrow",
            "عنوان کوچک — فارسی",
            {
              dir: "rtl",
            },
          )}

          {textField(
            "eyebrow_en",
            "عنوان کوچک — انگلیسی",
          )}

          {textField(
            "title",
            "عنوان — فارسی",
            {
              dir: "rtl",
            },
          )}

          {textField(
            "title_en",
            "عنوان — انگلیسی",
          )}
        </div>
      </section>

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.sectionHeader
          }
        >
          <div>
            <p>
              شبکه اجتماعی
            </p>

            <h2>
              اینستاگرام
            </h2>
          </div>
        </div>

        <div
          className={
            styles.grid
          }
        >
          {textField(
            "instagram_label",
            "برچسب — فارسی",
            {
              dir: "rtl",
            },
          )}

          {textField(
            "instagram_label_en",
            "برچسب — انگلیسی",
          )}

          {textField(
            "instagram_value",
            "متن نمایشی",
          )}

          {textField(
            "instagram_url",
            "آدرس مقصد",
            {
              type: "url",
              placeholder:
                "https://instagram.com/...",
            },
          )}
        </div>
      </section>

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.sectionHeader
          }
        >
          <div>
            <p>
              پیام‌رسان
            </p>

            <h2>
              تلگرام
            </h2>
          </div>
        </div>

        <div
          className={
            styles.grid
          }
        >
          {textField(
            "telegram_label",
            "برچسب — فارسی",
            {
              dir: "rtl",
            },
          )}

          {textField(
            "telegram_label_en",
            "برچسب — انگلیسی",
          )}

          {textField(
            "telegram_value",
            "متن نمایشی",
          )}

          {textField(
            "telegram_url",
            "آدرس مقصد",
            {
              placeholder:
                "tg://resolve?...",
            },
          )}
        </div>
      </section>

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.sectionHeader
          }
        >
          <div>
            <p>
              پیام‌رسان
            </p>

            <h2>
              واتس‌اپ
            </h2>
          </div>
        </div>

        <div
          className={
            styles.grid
          }
        >
          {textField(
            "whatsapp_label",
            "برچسب — فارسی",
            {
              dir: "rtl",
            },
          )}

          {textField(
            "whatsapp_label_en",
            "برچسب — انگلیسی",
          )}

          {textField(
            "whatsapp_value",
            "متن نمایشی",
          )}

          {textField(
            "whatsapp_url",
            "آدرس مقصد",
            {
              type: "url",
              placeholder:
                "https://wa.me/...",
            },
          )}
        </div>
      </section>

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.sectionHeader
          }
        >
          <div>
            <p>
              تماس مستقیم
            </p>

            <h2>
              شماره تماس
            </h2>
          </div>
        </div>

        <div
          className={
            styles.grid
          }
        >
          {textField(
            "phone_label",
            "برچسب — فارسی",
            {
              dir: "rtl",
            },
          )}

          {textField(
            "phone_label_en",
            "برچسب — انگلیسی",
          )}

          {textField(
            "phone_value",
            "شماره نمایشی",
          )}

          {textField(
            "phone_url",
            "لینک تماس",
            {
              placeholder:
                "tel:0912...",
            },
          )}
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
              نمایش محتوای تماس فعال باشد
            </strong>

            <p>
              با غیرفعال کردن این گزینه، این بخش تماس در سایت منتشر نمی‌شود.
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
                form.is_active
              }
              onChange={(
                event,
              ) =>
                setField(
                  "is_active",
                  event.target
                    .checked,
                )
              }
            />

            <span>
              {form.is_active
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
          type="submit"
          className="admin-button admin-button-primary"
          disabled={
            submitting
          }
        >
          {submitting
            ? "در حال ذخیره…"
            : "ذخیره محتوای تماس"}
        </button>
      </div>
    </form>
  );
}