"use client";

import {
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

interface CollectionDeleteButtonProps {
  collectionId:
    number;

  collectionName:
    string;

  collectionNameDirection:
    "ltr" | "rtl";
}

interface ErrorPayload {
  message?:
    string;
}

export function CollectionDeleteButton({
  collectionId,
  collectionName,
  collectionNameDirection,
}: CollectionDeleteButtonProps) {
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
          `/api/collections/${collectionId}`,
          {
            method:
              "DELETE",
          },
        );

      if (!response.ok) {
        let message =
          "حذف مجموعه انجام نشد.";

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
          // Keep fallback message.
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
        "حذف مجموعه انجام نشد.",
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
        dir="rtl"
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
          dir="rtl"
          lang="fa"
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
            role="dialog"
            aria-modal="true"
            aria-labelledby={`delete-collection-${collectionId}`}
            onMouseDown={
              (event) =>
                event.stopPropagation()
            }
          >
            <span className="admin-delete-modal-eyebrow">
              حذف مجموعه
            </span>

            <h3
              id={`delete-collection-${collectionId}`}
            >
              از حذف این مجموعه مطمئن هستید؟
            </h3>

            <p>
              مجموعه{" "}
              <strong>
                <span
                  dir={
                    collectionNameDirection
                  }
                >
                  {collectionName}
                </span>
              </strong>
              حذف خواهد شد.
            </p>

            <p className="admin-delete-modal-warning">
              محصولات حذف نمی‌شوند و فقط ارتباط آن‌ها با این مجموعه برداشته می‌شود.
            </p>

            <div className="admin-delete-modal-actions">
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