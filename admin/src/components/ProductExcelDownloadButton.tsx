"use client";

import {
  useState,
} from "react";

export function ProductExcelDownloadButton() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleDownload() {
    if (loading) {
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const response = await fetch(
        "/api/products/excel/export",
        {
          method: "GET",
          cache: "no-store",
          credentials: "same-origin",
        },
      );

      if (!response.ok) {
        let message = "دریافت فایل Excel ناموفق بود.";

        try {
          const payload: unknown =
            await response.json();

          if (
            payload &&
            typeof payload === "object" &&
            "message" in payload &&
            typeof payload.message === "string"
          ) {
            message = payload.message;
          }
        } catch {
          // Keep the default message.
        }

        throw new Error(message);
      }

      const blob = await response.blob();

      if (blob.size === 0) {
        throw new Error(
          "فایل دریافتی خالی است.",
        );
      }

      const url = URL.createObjectURL(blob);

      try {
        const link = document.createElement("a");

        link.href = url;
        link.download = "TKB-Products.xlsx";

        document.body.appendChild(link);
        link.click();
        link.remove();
      } finally {
        window.setTimeout(
          () => URL.revokeObjectURL(url),
          5000,
        );
      }
    } catch (cause) {
      setError(
        cause instanceof Error
          ? cause.message
          : "خطایی در دریافت فایل رخ داد.",
      );
    } finally {
      setLoading(false);
    }
  }

  return (
    <div
      style={{
        display: "flex",
        flexDirection: "column",
        alignItems: "flex-start",
        gap: 6,
      }}
    >
      <button
        type="button"
        className="admin-button"
        disabled={loading}
        aria-busy={loading}
        onClick={handleDownload}
      >
        {loading
          ? "در حال آماده‌سازی..."
          : "دریافت فایل Excel"}
      </button>

      {error ? (
        <span
          role="alert"
          style={{
            color: "#b42318",
            fontSize: 12,
            maxWidth: 260,
          }}
        >
          {error}
        </span>
      ) : null}
    </div>
  );
}