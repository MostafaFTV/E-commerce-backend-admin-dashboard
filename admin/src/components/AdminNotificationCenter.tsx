"use client";

import Link from "next/link";

import {
  useCallback,
  useEffect,
  useRef,
  useState,
} from "react";

import {
  createPortal,
} from "react-dom";

import {
  usePathname,
} from "next/navigation";

interface NotificationItem {
  id: string;
  type: string;
  title: string;
  description: string;
  href: string;
  created_at: string | null;
  is_read: boolean;
}

interface NotificationPayload {
  count: number;
  items: NotificationItem[];
}

interface AdminNotificationCenterProps {
  enabled: boolean;
  viewAllHref: string;
}

interface PanelPosition {
  top: number;
  left: number;
  width: number;
  maxHeight: number;
}

function formatDate(
  value: string | null,
): string {
  if (!value) {
    return "";
  }

  const date =
    new Date(value);

  if (
    Number.isNaN(
      date.getTime(),
    )
  ) {
    return "";
  }

  return new Intl.DateTimeFormat(
    "fa-IR",
    {
      dateStyle: "medium",
      timeStyle: "short",
    },
  ).format(date);
}

export function AdminNotificationCenter({
  enabled,
  viewAllHref,
}: AdminNotificationCenterProps) {
  const pathname =
    usePathname();

  const rootRef =
    useRef<HTMLDivElement | null>(
      null,
    );

  const triggerRef =
    useRef<HTMLButtonElement | null>(
      null,
    );

  const markingRef =
    useRef(false);

  const previousCountRef =
    useRef<number | null>(
      null,
    );

  const toastTimerRef =
    useRef<number | null>(
      null,
    );

  const [
    mobileTarget,
    setMobileTarget,
  ] = useState<HTMLElement | null>(
    null,
  );

  const [
    isMobile,
    setIsMobile,
  ] = useState(false);

  const [
    open,
    setOpen,
  ] = useState(false);

  const [
    data,
    setData,
  ] = useState<NotificationPayload>({
    count: 0,
    items: [],
  });

  const [
    loading,
    setLoading,
  ] = useState(false);

  const [
    marking,
    setMarking,
  ] = useState(false);

  const [
    error,
    setError,
  ] = useState<string | null>(
    null,
  );

  const [
    feedback,
    setFeedback,
  ] = useState<string | null>(
    null,
  );

  const [
    panelPosition,
    setPanelPosition,
  ] = useState<PanelPosition | null>(
    null,
  );

  const [
    toast,
    setToast,
  ] = useState<string | null>(
    null,
  );

  const loadNotifications =
    useCallback(
      async () => {
        if (!enabled) {
          return;
        }

        try {
          setLoading(true);

          const response =
            await fetch(
              "/api/notifications",
              {
                cache:
                  "no-store",
              },
            );

          if (!response.ok) {
            setError(
              "دریافت اعلان‌ها ناموفق بود.",
            );
            return;
          }

          const payload =
            await response.json() as
              NotificationPayload;

          if (
            typeof payload.count !==
              "number" ||
            !Array.isArray(
              payload.items,
            )
          ) {
            setError(
              "پاسخ اعلان‌ها معتبر نیست.",
            );
            return;
          }

          setError(null);
          setFeedback(null);

          const previousCount =
            previousCountRef.current;

          if (
            previousCount !== null &&
            payload.count >
              previousCount
          ) {
            setToast(
              "اعلان جدید دارید.",
            );

            if (
              toastTimerRef.current !==
              null
            ) {
              window.clearTimeout(
                toastTimerRef.current,
              );
            }

            toastTimerRef.current =
              window.setTimeout(
                () => {
                  setToast(null);

                  toastTimerRef.current =
                    null;
                },
                4000,
              );
          }

          previousCountRef.current =
            payload.count;

          setData(payload);
        } catch {
          setError(
            "ارتباط با سرویس اعلان‌ها برقرار نشد.",
          );
        } finally {
          setLoading(false);
        }
      },
      [
        enabled,
      ],
    );

  const updatePanelPosition =
    useCallback(
      () => {
        const trigger =
          triggerRef.current;

        if (!trigger) {
          return;
        }

        const bounds =
          trigger.getBoundingClientRect();
        const viewportWidth =
          document.documentElement.clientWidth;
        const viewportHeight =
          window.visualViewport?.height ??
          window.innerHeight;
        const width =
          Math.min(
            380,
            Math.max(
              0,
              viewportWidth - 24,
            ),
          );
        const left =
          Math.max(
            12,
            Math.min(
              bounds.left,
              viewportWidth - width - 12,
            ),
          );
        const top =
          Math.min(
            bounds.bottom + 10,
            Math.max(12, viewportHeight - 120),
          );

        setPanelPosition({
          top,
          left,
          width,
          maxHeight: Math.max(
            120,
            viewportHeight - top - 12,
          ),
        });
      },
      [],
    );

  const markAllRead =
    useCallback(
      async () => {
        if (
          markingRef.current ||
          data.count === 0
        ) {
          return;
        }

        markingRef.current = true;
        setMarking(true);
        setError(null);
        setFeedback(null);

        try {
          const response =
            await fetch(
              "/api/notifications",
              {
                method: "POST",
                cache: "no-store",
              },
            );

          if (!response.ok) {
            setError(
              "علامت‌گذاری اعلان‌ها ناموفق بود.",
            );
            return;
          }

          setData((current) => ({
            count: 0,
            items: current.items.map(
              (item) => ({
                ...item,
                is_read: true,
              }),
            ),
          }));
          setFeedback(
            "همه اعلان‌ها خوانده شدند.",
          );
        } catch {
          setError(
            "ارتباط با سرویس اعلان‌ها برقرار نشد.",
          );
        } finally {
          markingRef.current = false;
          setMarking(false);
        }
      },
      [
        data.count,
      ],
    );

  useEffect(() => {
    const media =
      window.matchMedia(
        "(max-width: 900px)",
      );

    function syncTarget() {
      const mobile =
        media.matches;

      setIsMobile(
        mobile,
      );

      setMobileTarget(
        mobile
          ? document.getElementById(
              "admin-mobile-notification-slot",
            )
          : null,
      );
    }

    syncTarget();

    media.addEventListener(
      "change",
      syncTarget,
    );

    return () => {
      media.removeEventListener(
        "change",
        syncTarget,
      );
    };
  }, []);

  useEffect(() => {
    if (!enabled) {
      return;
    }

    void loadNotifications();

    const timer =
      window.setInterval(
        () => {
          void loadNotifications();
        },
        15000,
      );

    return () => {
      window.clearInterval(
        timer,
      );
    };
  }, [
    enabled,
    loadNotifications,
  ]);

  useEffect(() => {
    function handleRefresh() {
      void loadNotifications();
    }

    window.addEventListener(
      "tkb:notifications-refresh",
      handleRefresh,
    );

    return () => {
      window.removeEventListener(
        "tkb:notifications-refresh",
        handleRefresh,
      );
    };
  }, [
    loadNotifications,
  ]);

  useEffect(() => {
    return () => {
      if (
        toastTimerRef.current !==
        null
      ) {
        window.clearTimeout(
          toastTimerRef.current,
        );
      }
    };
  }, []);

  useEffect(() => {
    setOpen(false);
  }, [
    pathname,
  ]);

  useEffect(() => {
    if (!open) {
      return;
    }

    updatePanelPosition();

    window.addEventListener(
      "resize",
      updatePanelPosition,
    );
    window.addEventListener(
      "scroll",
      updatePanelPosition,
      true,
    );
    window.visualViewport?.addEventListener(
      "resize",
      updatePanelPosition,
    );
    window.visualViewport?.addEventListener(
      "scroll",
      updatePanelPosition,
    );

    function handlePointerDown(
      event: PointerEvent,
    ) {
      if (
        rootRef.current &&
        !rootRef.current.contains(
          event.target as Node,
        )
      ) {
        setOpen(false);
      }
    }

    function handleKeyDown(
      event: KeyboardEvent,
    ) {
      if (
        event.key ===
        "Escape"
      ) {
        setOpen(false);
      }
    }

    document.addEventListener(
      "pointerdown",
      handlePointerDown,
    );

    window.addEventListener(
      "keydown",
      handleKeyDown,
    );

    return () => {
      document.removeEventListener(
        "pointerdown",
        handlePointerDown,
      );

      window.removeEventListener(
        "resize",
        updatePanelPosition,
      );
      window.removeEventListener(
        "scroll",
        updatePanelPosition,
        true,
      );
      window.visualViewport?.removeEventListener(
        "resize",
        updatePanelPosition,
      );
      window.visualViewport?.removeEventListener(
        "scroll",
        updatePanelPosition,
      );

      window.removeEventListener(
        "keydown",
        handleKeyDown,
      );
    };
  }, [
    open,
    updatePanelPosition,
  ]);

  if (!enabled) {
    return null;
  }

  const center = (
    <div
      ref={rootRef}
      className="admin-notification-center"
    >
      <button
        ref={triggerRef}
        type="button"
        className="admin-notification-trigger"
        aria-label="اعلان‌ها"
        aria-expanded={open}
        onClick={() => {
          const nextOpen = !open;
          setOpen(nextOpen);

          if (nextOpen) {
            updatePanelPosition();
          }

          void loadNotifications();
        }}
      >
        <svg
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path
            d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"
          />
        </svg>

        {data.count > 0 ? (
          <span
            className="admin-notification-badge"
          >
            {data.count > 99
              ? "99+"
              : data.count}
          </span>
        ) : null}
      </button>

      {open ? (
        <div
          className="admin-notification-panel"
          style={panelPosition ? {
            top: `${panelPosition.top}px`,
            left: `${panelPosition.left}px`,
            width: `${panelPosition.width}px`,
            maxHeight: `${panelPosition.maxHeight}px`,
          } : undefined}
        >
          <div
            className="admin-notification-panel-head"
          >
            <div>
              <strong>
                اعلان‌ها
              </strong>

              <span>
                اعلان‌های مهم پنل
              </span>

              <button
                type="button"
                className="admin-notification-mark-read"
                disabled={
                  marking ||
                  data.count === 0
                }
                onClick={() => {
                  void markAllRead();
                }}
              >
                {marking
                  ? "در حال ثبت…"
                  : "علامت‌گذاری همه به‌عنوان خوانده‌شده"}
              </button>
            </div>

            {data.count > 0 ? (
              <span
                className="admin-notification-count"
              >
                {data.count} جدید
              </span>
            ) : null}
          </div>

          {error || feedback ? (
            <div
              className={`admin-notification-feedback${error ? " is-error" : ""}`}
              role={error ? "alert" : "status"}
            >
              {error ?? feedback}
            </div>
          ) : null}

          <div
            className="admin-notification-list"
          >
            {loading &&
            !data.items.length ? (
              <div
                className="admin-notification-empty"
              >
                در حال دریافت اعلان‌ها…
              </div>
            ) : data.items.length ? (
              data.items.map(
                (item) => (
                  <Link
                    key={item.id}
                    href={item.href}
                    className={`admin-notification-item${item.is_read ? " is-read" : ""}`}
                  >
                    <span
                      className="admin-notification-dot"
                      aria-hidden="true"
                    />

                    <div
                      className="admin-notification-copy"
                    >
                      <strong>
                        {item.title}
                      </strong>

                      <span>
                        {item.description}
                      </span>

                      {item.created_at ? (
                        <small>
                          {formatDate(
                            item.created_at,
                          )}
                        </small>
                      ) : null}
                    </div>
                  </Link>
                ),
              )
            ) : (
              <div
                className="admin-notification-empty"
              >
                اعلان جدیدی ندارید.
              </div>
            )}
          </div>

          <Link
            href={viewAllHref}
            className="admin-notification-view-all"
          >
            مشاهده همه موارد
          </Link>
        </div>
      ) : null}
    </div>
  );

  const renderedCenter =
    isMobile &&
    mobileTarget
      ? createPortal(
          center,
          mobileTarget,
        )
      : center;

  const renderedToast =
    toast &&
    typeof document !== "undefined"
      ? createPortal(
          <div
            className="admin-notification-toast"
            role="status"
            aria-live="polite"
          >
            <span
              className="admin-notification-toast-dot"
              aria-hidden="true"
            />

            <strong>
              {toast}
            </strong>
          </div>,
          document.body,
        )
      : null;

  return (
    <>
      {renderedCenter}
      {renderedToast}
    </>
  );
}