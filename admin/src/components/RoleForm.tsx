"use client";

import { adminPermissionLabel } from "@/lib/admin-persian-labels";


import {
  FormEvent,
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

import type {
  AdminPermission,
  AdminRole,
} from "@/lib/admin-access-control";

import {
  isSystemRoleSlug,
} from "@/lib/access-control-constants";

import styles from "./AccessControlForm.module.css";

interface Props {
  initialRole?: AdminRole | null;
  permissions: AdminPermission[];
}

interface ErrorPayload {
  message?: string;

  errors?: Record<
    string,
    string[] | undefined
  >;
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
    of Object.values(errors)
  ) {
    if (
      messages &&
      messages.length
    ) {
      return messages[0] ?? null;
    }
  }

  return null;
}

export function RoleForm({
  initialRole = null,
  permissions,
}: Props) {
  const router =
    useRouter();

  const editing =
    Boolean(initialRole);

  const systemRole =
    initialRole
      ? isSystemRoleSlug(
          initialRole.slug,
        )
      : false;

  const permissionsLocked =
    initialRole?.slug ===
      "super_admin" ||
    initialRole?.slug ===
      "customer";

  const [
    name,
    setName,
  ] =
    useState(
      initialRole?.name ??
      "",
    );

  const [
    slug,
    setSlug,
  ] =
    useState(
      initialRole?.slug ??
      "",
    );

  const [
    description,
    setDescription,
  ] =
    useState(
      initialRole?.description ??
      "",
    );

  const [
    selectedPermissions,
    setSelectedPermissions,
  ] =
    useState<number[]>(
      initialRole?.permissions.map(
        (permission) =>
          permission.id,
      ) ?? [],
    );

  const [
    submitting,
    setSubmitting,
  ] =
    useState(false);

  const [
    error,
    setError,
  ] =
    useState<string | null>(
      null,
    );

  function togglePermission(
    id: number,
  ) {
    if (permissionsLocked) {
      return;
    }

    setSelectedPermissions(
      (current) =>
        current.includes(id)
          ? current.filter(
              (value) =>
                value !== id,
            )
          : [
              ...current,
              id,
            ],
    );
  }

  async function handleSubmit(
    event:
      FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    if (submitting) {
      return;
    }

    setError(null);

    if (
      !name.trim() ||
      !slug.trim()
    ) {
      setError(
        "نام و شناسه متنی الزامی است.",
      );

      return;
    }

    const payload: {
      name: string;
      slug?: string;
      description: string | null;
      permission_ids?: number[];
    } = {
      name:
        name.trim(),

      description:
        description.trim() ||
        null,
    };

    if (!systemRole) {
      payload.slug =
        slug.trim();
    }

    if (!permissionsLocked) {
      payload.permission_ids =
        selectedPermissions;
    }

    setSubmitting(true);

    try {
      const response =
        await fetch(
          editing &&
          initialRole
            ? `/api/roles/${initialRole.id}`
            : "/api/roles",
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
            "ذخیره نقش انجام نشد.",
        );

        return;
      }

      router.push(
        "/roles",
      );

      router.refresh();
    } catch {
      setError(
        "ذخیره نقش انجام نشد.",
      );
    } finally {
      setSubmitting(false);
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
              نقش
            </span>

            <h2>
              اطلاعات نقش
            </h2>
          </div>

          {systemRole ? (
            <p>
              شناسه و مجوزهای نقش سیستمی محافظت می‌شوند.
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
              نام
            </label>

            <input
              type="text"
              maxLength={100}
              required
              value={
                name
              }
              onChange={
                (event) =>
                  setName(
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
              شناسه متنی
            </label>

            <input
              type="text"
              maxLength={100}
              required
              pattern="[a-z0-9_]+"
              disabled={
                systemRole
              }
              value={
                slug
              }
              onChange={
                (event) =>
                  setSlug(
                    event.target.value,
                  )
              }
            />
          </div>
        </div>

        <div
          className={
            styles.field
          }
        >
          <label>
            توضیحات
          </label>

          <textarea
            value={
              description
            }
            onChange={
              (event) =>
                setDescription(
                  event.target.value,
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
              دسترسی
            </span>

            <h2>
              مجوزهای دسترسی
            </h2>
          </div>

          <p>
            {permissionsLocked
              ? "مجوزهای این نقش محافظت‌شده قابل تغییر نیستند."
              : `${selectedPermissions.length} مجوز انتخاب شده است.`}
          </p>
        </div>

        <div
          className={
            styles.permissionGrid
          }
        >
          {permissions.map(
            (permission) => {
              const checked =
                selectedPermissions.includes(
                  permission.id,
                );

              return (
                <label
                  key={
                    permission.id
                  }
                  className={
                    styles.permissionOption
                  }
                  data-selected={
                    checked
                      ? "true"
                      : "false"
                  }
                >
                  <input
                    type="checkbox"
                    checked={
                      checked
                    }
                    disabled={
                      permissionsLocked
                    }
                    onChange={
                      () =>
                        togglePermission(
                          permission.id,
                        )
                    }
                  />

                  <span>
                    <strong>
                      {
                        adminPermissionLabel(permission.slug, permission.name)
                      }
                    </strong>

                    <code>
                      {
                        permission.slug
                      }
                    </code>
                  </span>
                </label>
              );
            },
          )}
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
          onClick={
            () =>
              router.push(
                "/roles",
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
              : "ایجاد نقش"}
        </button>
      </div>
    </form>
  );
}