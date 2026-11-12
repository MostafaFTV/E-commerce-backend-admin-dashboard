"use client";

import {
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

export function RoleDeleteButton({
  roleId,
  roleName,
}: {
  roleId: number;
  roleName: string;
}) {
  const router =
    useRouter();

  const [
    busy,
    setBusy,
  ] =
    useState(false);

  async function handleDelete() {
    if (
      busy ||
      !window.confirm(
        `نقش «${roleName}"?`,
      )
    ) {
      return;
    }

    setBusy(true);

    try {
      const response =
        await fetch(
          `/api/roles/${roleId}`,
          {
            method:
              "DELETE",
          },
        );

      const payload =
        await response
          .json()
          .catch(
            () => null,
          );

      if (!response.ok) {
        const firstError =
          payload?.errors?.role?.[0];

        window.alert(
          firstError ??
            payload?.message ??
            "حذف نقش انجام نشد.",
        );

        return;
      }

      router.refresh();
    } catch {
      window.alert(
        "حذف نقش انجام نشد.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <button
      type="button"
      className="admin-button"
      disabled={
        busy
      }
      onClick={
        () =>
          void handleDelete()
      }
    >
      {busy
        ? "در حال حذف…"
        : "حذف"}
    </button>
  );
}