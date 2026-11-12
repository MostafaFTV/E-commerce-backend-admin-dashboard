"use client";

import { useEffect, useState } from "react";

const ENDPOINT = "/api/inspiration-video";
const MAX_SIZE = 50 * 1024 * 1024;

type VideoInfo = {
  path: string;
  url: string;
  size: number;
};

type ApiPayload = {
  data?: VideoInfo;
  message?: string;
  errors?: Record<string, string[]>;
};

export function InspirationVideoField() {
  const [video, setVideo] = useState<VideoInfo | null>(null);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [revision, setRevision] = useState(0);

  useEffect(() => {
    const controller = new AbortController();

    async function loadVideo() {
      try {
        const response = await fetch(ENDPOINT, {
          cache: "no-store",
          signal: controller.signal,
        });

        const payload = (await response.json()) as ApiPayload;

        if (!response.ok || !payload.data) {
          throw new Error(
            payload.message ?? "دریافت اطلاعات ویدیو انجام نشد.",
          );
        }

        if (!controller.signal.aborted) {
          setVideo(payload.data);
          setError("");
        }
      } catch (caught) {
        if (!controller.signal.aborted) {
          setError(
            caught instanceof Error
              ? caught.message
              : "خطا در دریافت ویدیو.",
          );
        }
      } finally {
        if (!controller.signal.aborted) {
          setLoading(false);
        }
      }
    }

    void loadVideo();

    return () => controller.abort();
  }, []);

  async function uploadVideo(file: File) {
    setError("");
    setSuccess("");

    if (
      !file.name.toLowerCase().endsWith(".mp4") ||
      file.size === 0 ||
      file.size > MAX_SIZE
    ) {
      setError("فقط فایل MP4 با حجم حداکثر ۵۰ مگابایت مجاز است.");
      return;
    }

    const formData = new FormData();
    formData.append("video", file);

    setUploading(true);

    try {
      const response = await fetch(ENDPOINT, {
        method: "POST",
        body: formData,
        cache: "no-store",
      });

      const payload = (await response.json()) as ApiPayload;

      if (!response.ok || !payload.data) {
        const validationMessage = payload.errors
          ? Object.values(payload.errors).flat()[0]
          : undefined;

        throw new Error(
          validationMessage ??
            payload.message ??
            "تعویض ویدیو انجام نشد.",
        );
      }

      setVideo(payload.data);
      setRevision(Date.now());
      setSuccess("ویدیوی Inspiration با موفقیت جایگزین شد.");
    } catch (caught) {
      setError(
        caught instanceof Error
          ? caught.message
          : "خطا در آپلود ویدیو.",
      );
    } finally {
      setUploading(false);
    }
  }

  const videoUrl = video
    ? `${video.url}${video.url.includes("?") ? "&" : "?"}admin_revision=${revision}`
    : null;

  return (
    <div
      style={{
        display: "grid",
        gap: 16,
        padding: 16,
        border: "1px solid rgba(45, 63, 33, 0.1)",
        borderRadius: 14,
      }}
    >
      <strong>ویدیوی Inspiration / داستان ما</strong>

      {loading ? (
        <p>در حال دریافت ویدیو...</p>
      ) : videoUrl ? (
        <video
          key={videoUrl}
          src={videoUrl}
          controls
          playsInline
          preload="metadata"
          style={{
            display: "block",
            width: "100%",
            maxWidth: 640,
            aspectRatio: "16 / 9",
            objectFit: "cover",
            background: "#111",
            borderRadius: 10,
          }}
        />
      ) : (
        <p>ویدیویی برای نمایش دریافت نشد.</p>
      )}

      <div style={{ display: "grid", gap: 8 }}>
        <label
          style={{
            display: "inline-flex",
            alignItems: "center",
            justifyContent: "center",
            width: "fit-content",
            minHeight: 38,
            padding: "8px 16px",
            border: "1px solid rgba(45, 63, 33, 0.2)",
            borderRadius: 8,
            cursor: uploading ? "wait" : "pointer",
          }}
        >
          {uploading ? "در حال آپلود و جایگزینی..." : "تعویض ویدیو"}

          <input
            type="file"
            accept=".mp4,video/mp4"
            disabled={uploading || loading || !video}
            style={{ display: "none" }}
            onChange={(event) => {
              const file = event.target.files?.[0];

              if (file) {
                void uploadVideo(file);
              }

              event.target.value = "";
            }}
          />
        </label>

        <small>
          فرمت MP4 — حداکثر ۵۰ مگابایت. انتخاب فایل، عملیات
          جایگزینی را مستقیماً آغاز می‌کند و نیازی به ذخیره
          مجدد محتوای بخش ندارد.
        </small>
      </div>

      {error ? (
        <p role="alert" style={{ color: "#b42318" }}>
          {error}
        </p>
      ) : null}

      {success ? (
        <p role="status" style={{ color: "#287043" }}>
          {success}
        </p>
      ) : null}
    </div>
  );
}