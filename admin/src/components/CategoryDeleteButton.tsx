"use client";

import {
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

interface CategoryDeleteButtonProps {
  categoryId:
    number;

  categoryName:
    string;

  categoryNameDirection:
    "ltr" | "rtl";
}

interface ErrorPayload {
  message?:
    string;
}

export function CategoryDeleteButton({
  categoryId,
  categoryName,
  categoryNameDirection,
}: CategoryDeleteButtonProps) {
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

  const [error, setError] = useState<string | null>(null);

  async function handleDelete() {
    if (deleting) {
      return;
    }

    setError(null);
    setDeleting(
      true,
    );

    try {
      const response =
        await fetch(
          `/api/categories/${categoryId}`,
          {
            method:
              "DELETE",
          },
        );

      if (!response.ok) {
        let message =
          "حذف دسته‌بندی انجام نشد.";

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

        if (message === "Cannot delete category with products") {
          message = "این دسته‌بندی دارای محصول (حتی محصول حذف‌شده) است. ابتدا محصولات را به دسته‌بندی دیگری منتقل کنید.";
        } else if (message === "Cannot delete category with children") {
          message = "این دسته‌بندی دارای زیردسته است. ابتدا زیردسته‌ها را منتقل یا حذف کنید.";
        } else if (response.status === 409) {
          message = "این دسته‌بندی به اطلاعات دیگری وابسته است و امکان حذف آن وجود ندارد.";
        } else if (response.status >= 500) {
          message = "خطای سرور هنگام حذف دسته‌بندی. لطفاً دوباره تلاش کنید.";
        }

        setError(message);

        return;
      }

      setConfirmOpen(
        false,
      );

      router.refresh();
    } catch {
      setError("ارتباط با سرور برقرار نشد. لطفاً دوباره تلاش کنید.");
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
          () => {
            setError(null);
            setConfirmOpen(true);
          }
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
            aria-labelledby={`delete-category-${categoryId}`}
            onMouseDown={
              (event) =>
                event.stopPropagation()
            }
          >
            <span className="admin-delete-modal-eyebrow">
              حذف دسته‌بندی
            </span>

            <h3
              id={`delete-category-${categoryId}`}
            >
              از حذف دسته‌بندی مطمئن هستید؟
            </h3>

            <p>
              دسته‌بندی{" "}
              <strong>
                <span
                  dir={
                    categoryNameDirection
                  }
                >
                  {categoryName}
                </span>
              </strong>
              حذف خواهد شد.
            </p>

            <p className="admin-delete-modal-warning">
              این عملیات قابل بازگشت نیست.
            </p>

            {error ? (
              <p role="alert" className="admin-delete-modal-warning">
                {error}
              </p>
            ) : null}

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
