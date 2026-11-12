"use client";

import {
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

interface ProductDeleteButtonProps {
  productId:
    number;

  productName:
    string;
}

interface ErrorPayload {
  message?:
    string;
}

export function ProductDeleteButton({
  productId,
  productName,
}: ProductDeleteButtonProps) {
  const router =
    useRouter();

  const [
    deleting,
    setDeleting,
  ] =
    useState(false);

  const [
    confirmOpen,
    setConfirmOpen,
  ] =
    useState(false);

  async function handleDelete() {
    if (deleting) {
      return;
    }

    setDeleting(
      true,
    );

    try {
      const response =
        await fetch(
          `/api/products/${productId}`,
          {
            method:
              "DELETE",
          },
        );

      if (!response.ok) {
        let message =
          "حذف محصول انجام نشد.";

        try {
          const payload =
            (await response.json()) as
              ErrorPayload;

          if (
            payload.message
          ) {
            message =
              payload.message;
          }
        } catch {
          // Keep fallback.
        }

        window.alert(
          message,
        );

        return;
      }

      setConfirmOpen(
        false,
      );

      router.refresh();
    } catch {
      window.alert(
        "حذف محصول انجام نشد.",
      );
    } finally {
      setDeleting(
        false,
      );
    }
  }

  return (
    <>
      <button
        type="button"
        className="admin-button admin-button-danger"
        disabled={deleting}
        aria-label={`حذف ${productName}`}
        onClick={
          () =>
            setConfirmOpen(
              true,
            )
        }
      >
        حذف
      </button>

      {confirmOpen ? (
        <div
          className="admin-delete-modal-backdrop"
          role="presentation"
          onMouseDown={
            () => {
              if (!deleting) {
                setConfirmOpen(
                  false,
                );
              }
            }
          }
        >
          <div
            className="admin-delete-modal"
            dir="rtl"
            lang="fa"
            role="dialog"
            aria-modal="true"
            aria-labelledby={`delete-product-${productId}`}
            onMouseDown={
              (event) =>
                event.stopPropagation()
            }
          >
            <span
              className="admin-delete-modal-eyebrow"
            >
              حذف محصول
            </span>

            <h3
              id={`delete-product-${productId}`}
            >
              از حذف این محصول مطمئن هستید؟
            </h3>

            <p>
              محصول{" "}
              <strong>
                {productName}
              </strong>
              {" "}حذف خواهد شد.
            </p>

            <p
              className="admin-delete-modal-warning"
            >
              این عملیات قابل بازگشت نیست.
            </p>

            <div
              className="admin-delete-modal-actions"
            >
              <button
                type="button"
                className="admin-button"
                disabled={deleting}
                onClick={
                  () =>
                    setConfirmOpen(
                      false,
                    )
                }
              >
                انصراف
              </button>

              <button
                type="button"
                className="admin-button admin-button-danger"
                disabled={deleting}
                onClick={
                  handleDelete
                }
              >
                {deleting
                  ? "در حال حذف..."
                  : "بله، حذف شود"}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}