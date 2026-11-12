"use client";

import {
  useRef,
  useState,
  type ChangeEvent,
} from "react";

type PreviewRecord = Record<string, unknown>;

type PreviewResult = {
  valid?: boolean;
  can_apply?: boolean;
  summary?: PreviewRecord;
  changes?: unknown[];
  errors?: unknown[];
};

function record(value: unknown): PreviewRecord {
  return value !== null &&
    typeof value === "object" &&
    !Array.isArray(value)
    ? value as PreviewRecord
    : {};
}

function display(value: unknown): string {
  if (value === null || value === undefined) {
    return "—";
  }

  if (typeof value === "boolean") {
    return value ? "بله" : "خیر";
  }

  if (typeof value === "number") {
    return new Intl.NumberFormat("fa-IR", {
      maximumFractionDigits: 2,
    }).format(value);
  }

  if (typeof value === "string") {
    return value.trim() || "—";
  }

  return "—";
}

function firstValue(
  item: PreviewRecord,
  ...keys: string[]
): unknown {
  for (const key of keys) {
    if (
      Object.prototype.hasOwnProperty.call(item, key) &&
      item[key] !== undefined
    ) {
      return item[key];
    }
  }

  return null;
}

function describeError(value: unknown): string {
  if (typeof value === "string") {
    return value;
  }

  const item = record(value);
  const message = firstValue(
    item,
    "message",
    "error",
    "description",
  );

  const row = firstValue(item, "row");

  return row === null
    ? display(message)
    : `ردیف ${display(row)}: ${display(message)}`;
}

export function ProductExcelImportPreview() {
  const [file, setFile] = useState<File | null>(null);
  const [pending, setPending] = useState<"preview" | "apply" | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<PreviewResult | null>(null);
  const [reviewedFile, setReviewedFile] = useState<File | null>(null);
  const [applied, setApplied] = useState<PreviewRecord | null>(null);
  const inFlight = useRef(false);

  function onFileChange(
    event: ChangeEvent<HTMLInputElement>,
  ) {
    setFile(event.target.files?.[0] ?? null);
    setResult(null);
    setReviewedFile(null);
    setApplied(null);
    setError(null);
  }

  async function preview() {
    if (!file || inFlight.current) {
      return;
    }

    inFlight.current = true;
    setPending("preview");
    setResult(null);
    setReviewedFile(null);
    setApplied(null);
    setError(null);

    try {
      if (
        !file.name.toLowerCase().endsWith(".xlsx") ||
        file.size === 0 ||
        file.size > 5 * 1024 * 1024
      ) {
        throw new Error(
          "فایل باید XLSX و حداکثر ۵ مگابایت باشد.",
        );
      }

      const body = new FormData();
      body.append("file", file);

      const response = await fetch(
        "/api/products/excel/preview",
        {
          method: "POST",
          body,
          credentials: "same-origin",
          cache: "no-store",
        },
      );

      const payload: unknown = await response.json();
      const parsed = record(payload);

      if (!response.ok) {
        const validationErrors = record(parsed.errors);
        const fileErrors = validationErrors.file;

        const detailedMessage =
          Array.isArray(fileErrors) &&
          typeof fileErrors[0] === "string"
            ? fileErrors[0]
            : null;

        throw new Error(
          detailedMessage ??
          (typeof parsed.message === "string"
            ? parsed.message
            : "بررسی فایل ناموفق بود."),
        );
      }

      const previewResult = parsed as PreviewResult;
      setResult(previewResult);
      if (
        previewResult.valid === true &&
        Array.isArray(previewResult.errors) &&
        previewResult.errors.length === 0
      ) {
        setReviewedFile(file);
      }
    } catch (cause) {
      setError(
        cause instanceof Error
          ? cause.message
          : "خطایی هنگام بررسی فایل رخ داد.",
      );
    } finally {
      inFlight.current = false;
      setPending(null);
    }
  }

  async function apply() {
    if (
      inFlight.current ||
      !file ||
      reviewedFile !== file ||
      result?.valid !== true ||
      (result.errors?.length ?? 0) > 0 ||
      applied !== null
    ) {
      return;
    }

    inFlight.current = true;
    setPending("apply");
    setError(null);

    try {
      const body = new FormData();
      body.set("file", file);

      const response = await fetch("/api/products/excel/apply", {
        method: "POST",
        body,
        credentials: "same-origin",
        cache: "no-store",
      });
      const payload: unknown = await response.json();
      const parsed = record(payload);

      if (!response.ok) {
        if (response.status === 409) {
          setResult(null);
          setReviewedFile(null);
          throw new Error(
            "اطلاعات فایل قدیمی شده است یا با اطلاعات فعلی مطابقت ندارد. فایل جدید دانلود کنید و دوباره پیش‌نمایش بگیرید.",
          );
        }
        const fileErrors = record(parsed.errors).file;
        throw new Error(
          (Array.isArray(fileErrors) && typeof fileErrors[0] === "string"
            ? fileErrors[0]
            : null) ??
            (typeof parsed.message === "string"
              ? parsed.message
              : "اعمال تغییرات ناموفق بود."),
        );
      }
      if (parsed.success !== true) {
        throw new Error("پاسخ اعمال تغییرات نامعتبر است. وضعیت داده‌ها را بررسی کنید.");
      }

      setApplied(record(parsed.summary));
      setResult(null);
      setReviewedFile(null);
    } catch (cause) {
      setError(
        cause instanceof Error
          ? cause.message
          : "خطایی هنگام ثبت تغییرات رخ داد.",
      );
    } finally {
      inFlight.current = false;
      setPending(null);
    }
  }

  const changes = Array.isArray(result?.changes)
    ? result.changes
    : [];

  const errors = Array.isArray(result?.errors)
    ? result.errors
    : [];

  const summary = record(result?.summary);
  const hasChanges =
    Number(summary.price_changes ?? 0) > 0 ||
    Number(summary.stock_changes ?? 0) > 0;
  const canApply =
    result?.valid === true &&
    errors.length === 0 &&
    reviewedFile !== null &&
    reviewedFile === file &&
    hasChanges &&
    applied === null;

  const summaryItems = [
    {
      label: "ردیف‌های بررسی‌شده",
      value: summary.rows,
    },
    {
      label: "تغییرات قیمت",
      value: summary.price_changes,
    },
    {
      label: "تغییرات موجودی",
      value: summary.stock_changes,
    },
    {
      label: "خطاها",
      value: summary.errors ?? errors.length,
    },
  ];

  const panelStyle = {
    border: "1px solid #e2e6e0",
    background: "#fff",
    padding: 20,
    display: "grid",
    gap: 16,
  } as const;

  return (
    <section
      aria-label="پیش‌نمایش تغییرات Excel"
      style={panelStyle}
    >
      <div>
        <h2
          style={{
            margin: "0 0 6px",
            fontSize: 18,
            fontWeight: 650,
          }}
        >
          بارگذاری Excel قیمت و موجودی
        </h2>

        <p
          className="admin-muted"
          style={{ margin: 0 }}
        >
          فایل ویرایش‌شده را انتخاب کنید.
          تغییرات ابتدا بررسی می‌شوند و در
          این مرحله هیچ داده‌ای ذخیره نمی‌شود.
        </p>
      </div>

      <div
        style={{
          display: "flex",
          alignItems: "center",
          flexWrap: "wrap",
          gap: 12,
        }}
      >
        <input
          type="file"
          accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
          aria-label="انتخاب فایل Excel"
          onChange={onFileChange}
          disabled={pending !== null}
          style={{ maxWidth: "100%", minWidth: 0 }}
        />

        <button
          type="button"
          className="admin-button admin-button-primary"
          onClick={preview}
          disabled={!file || pending !== null}
          aria-busy={pending === "preview"}
        >
          {pending === "preview"
            ? "در حال بررسی فایل..."
            : "بررسی و نمایش تغییرات"}
        </button>
      </div>

      {error ? (
        <p
          role="alert"
          style={{
            margin: 0,
            color: "#b42318",
          }}
        >
          {error}
        </p>
      ) : null}

      {applied ? (
        <div role="status" style={{ display: "grid", gap: 8, color: "#256b45" }}>
          <strong>تغییرات با موفقیت در دیتابیس ثبت شدند.</strong>
          <span>
            محصولات: {display(applied.product_price_updates)} | مدل‌ها: {display(applied.variant_price_updates)} | موجودی: {display(applied.stock_updates)}
          </span>
          <span className="admin-muted">برای عملیات بعدی فایل دیگری انتخاب کنید.</span>
        </div>
      ) : null}

      {result ? (
        <div style={{ display: "grid", gap: 18 }}>
          <p
            role="status"
            style={{
              margin: 0,
              color:
                result.valid === true
                  ? "#256b45"
                  : "#b42318",
            }}
          >
            {result.valid === true
              ? "بررسی فایل انجام شد."
              : "فایل دارای خطا است و باید اصلاح شود."}
            {" "}
            هیچ تغییری در دیتابیس اعمال نشده است.
          </p>

          <div
            style={{
              display: "grid",
              gridTemplateColumns:
                "repeat(auto-fit, minmax(145px, 1fr))",
              gap: 10,
            }}
          >
            {summaryItems.map((item) => (
              <div
                key={item.label}
                style={{
                  background: "#f5f6f4",
                  padding: 14,
                  display: "grid",
                  gap: 6,
                }}
              >
                <span
                  className="admin-muted"
                  style={{ fontSize: 12 }}
                >
                  {item.label}
                </span>

                <strong style={{ fontSize: 19 }}>
                  {display(item.value)}
                </strong>
              </div>
            ))}
          </div>

          {errors.length > 0 ? (
            <div
              role="alert"
              style={{
                border: "1px solid #edc6c2",
                padding: 14,
              }}
            >
              <strong>خطاهای فایل</strong>

              <ul style={{ paddingInlineStart: 22 }}>
                {errors.slice(0, 100).map((item, index) => (
                  <li key={index}>
                    {describeError(item)}
                  </li>
                ))}
              </ul>

              {errors.length > 100 ? (
                <p>
                  فقط ۱۰۰ خطای اول نمایش داده شده‌اند.
                </p>
              ) : null}
            </div>
          ) : null}

          <div>
            <h3 style={{ fontSize: 15, margin: "0 0 10px" }}>
              پیش‌نمایش ردیف‌های تغییرکرده
            </h3>

            {changes.length === 0 ? (
              <p className="admin-muted">
                تغییری برای نمایش شناسایی نشد.
              </p>
            ) : (
              <div style={{ overflowX: "auto" }}>
                <table
                  className="admin-table"
                  style={{ width: "100%", minWidth: 750 }}
                >
                  <thead>
                    <tr>
                      <th>ردیف Excel</th>
                      <th>محصول / مدل</th>
                      <th>قیمت قبلی</th>
                      <th>قیمت جدید</th>
                      <th>موجودی قبلی</th>
                      <th>موجودی جدید</th>
                    </tr>
                  </thead>

                  <tbody>
                    {changes.slice(0, 100).map((raw, index) => {
                      const item = record(raw);

                      const oldPrice = firstValue(
                        item,
                        "old_effective_price",
                        "old_stored_price",
                        "old_price",
                      );

                      const newPrice = firstValue(
                        item,
                        "new_effective_price",
                        "new_stored_price",
                        "new_price",
                      );

                      return (
                        <tr key={index}>
                          <td>{display(item.row)}</td>

                          <td>
                            {display(
                              firstValue(
                                item,
                                "name",
                                "product_name",
                                "label",
                                "sku",
                              ),
                            )}
                          </td>

                          <td>{display(oldPrice)}</td>
                          <td>{display(newPrice)}</td>

                          <td>
                            {display(
                              firstValue(
                                item,
                                "old_quantity",
                                "old_stock",
                              ),
                            )}
                          </td>

                          <td>
                            {display(
                              firstValue(
                                item,
                                "new_quantity",
                                "new_stock",
                              ),
                            )}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>

                {changes.length > 100 ? (
                  <p className="admin-muted">
                    از {display(changes.length)} ردیف،
                    فقط ۱۰۰ ردیف اول نمایش داده شده است.
                  </p>
                ) : null}
              </div>
            )}
          </div>

          <div style={{ borderTop: "1px solid #e2e6e0", paddingTop: 12, display: "grid", gap: 10 }}>
            <p className="admin-muted" style={{ margin: 0 }}>
              پس از بررسی ردیف‌ها، با انتخاب «تأیید و اعمال تغییرات» همین فایل در سرور دوباره اعتبارسنجی و ثبت می‌شود.
            </p>
            {!hasChanges && result.valid === true ? (
              <p className="admin-muted" style={{ margin: 0 }}>هیچ تغییر جدیدی برای ثبت وجود ندارد.</p>
            ) : null}
            <div>
              <button
                type="button"
                className="admin-button admin-button-primary"
                onClick={apply}
                disabled={!canApply || pending !== null}
                aria-busy={pending === "apply"}
              >
                {pending === "apply" ? "در حال ثبت تغییرات..." : "تأیید و اعمال تغییرات"}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </section>
  );
}
