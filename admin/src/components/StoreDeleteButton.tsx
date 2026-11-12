"use client";

import {
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

interface Props {
  storeId:
    number;

  storeName:
    string;
}

export function StoreDeleteButton({
  storeId,
  storeName,
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
        `حذف «${storeName}"?`,
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
          `/api/stores/${storeId}`,
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
            "حذف شعبه انجام نشد.",
        );

        return;
      }

      router.refresh();
    } catch {
      window.alert(
        "حذف شعبه انجام نشد.",
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