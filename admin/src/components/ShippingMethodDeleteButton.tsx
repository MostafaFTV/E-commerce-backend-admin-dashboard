"use client";

import {
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

interface Props {
  methodId:
    number;

  methodName:
    string;
}

export function ShippingMethodDeleteButton({
  methodId,
  methodName,
}: Props) {
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
        `حذف «${methodName}"?`,
      )
    ) {
      return;
    }

    setBusy(
      true,
    );

    try {
      const response =
        await fetch(
          `/api/shipping-methods/${methodId}`,
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
          payload?.message ??
            "حذف روش ارسال انجام نشد.",
        );

        return;
      }

      if (
        payload?.deactivated
      ) {
        window.alert(
          payload?.message ??
            "این روش ارسال به‌دلیل داشتن سفارش قبلی، غیرفعال شد.",
        );
      }

      router.refresh();
    } catch {
      window.alert(
        "حذف روش ارسال انجام نشد.",
      );
    } finally {
      setBusy(
        false,
      );
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