"use client";

import {
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

interface StaffDeleteButtonProps {
  userId:
    number;

  userName:
    string;
}

export function StaffDeleteButton({
  userId,
  userName,
}: StaffDeleteButtonProps) {
  const router =
    useRouter();

  const [
    deleting,
    setDeleting,
  ] =
    useState(false);

  async function handleDelete() {
    if (
      deleting ||
      !window.confirm(
        `کاربر پنل «${userName}» حذف شود؟ این عملیات قابل بازگشت نیست.`,
      )
    ) {
      return;
    }

    setDeleting(
      true,
    );

    try {
      const response =
        await fetch(
          `/api/staff/${userId}`,
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
        window.alert(
          payload?.errors?.user?.[0] ??
            payload?.message ??
            "حذف کاربر پنل انجام نشد.",
        );

        return;
      }

      router.refresh();
    } catch {
      window.alert(
        "حذف کاربر پنل انجام نشد.",
      );
    } finally {
      setDeleting(
        false,
      );
    }
  }

  return (
    <button
      type="button"
      className="admin-button"
      disabled={
        deleting
      }
      onClick={
        () =>
          void handleDelete()
      }
    >
      {deleting
        ? "در حال حذف…"
        : "حذف"}
    </button>
  );
}