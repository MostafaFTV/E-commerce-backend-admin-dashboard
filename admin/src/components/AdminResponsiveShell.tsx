"use client";

import {
  type ReactNode,
  useEffect,
  useState,
} from "react";
import {
  usePathname,
} from "next/navigation";

interface AdminResponsiveShellProps {
  sidebar: ReactNode;
  children: ReactNode;
}

export function AdminResponsiveShell({
  sidebar,
  children,
}: AdminResponsiveShellProps) {
  const pathname =
    usePathname();

  const [
    navigationOpen,
    setNavigationOpen,
  ] =
    useState(false);

  useEffect(() => {
    setNavigationOpen(false);
  }, [pathname]);

  useEffect(() => {
    if (!navigationOpen) {
      return;
    }

    const previousOverflow =
      document.documentElement
        .style
        .overflow;

    document.documentElement
      .style
      .overflow =
      "hidden";

    const handleKeyDown = (
      event: KeyboardEvent,
    ) => {
      if (
        event.key ===
        "Escape"
      ) {
        setNavigationOpen(
          false,
        );
      }
    };

    window.addEventListener(
      "keydown",
      handleKeyDown,
    );

    return () => {
      document.documentElement
        .style
        .overflow =
        previousOverflow;

      window.removeEventListener(
        "keydown",
        handleKeyDown,
      );
    };
  }, [
    navigationOpen,
  ]);

  return (
    <div
      dir="rtl"
      className={[
        "admin-layout",
        navigationOpen
          ? "admin-nav-open"
          : "",
      ]
        .filter(Boolean)
        .join(" ")}
    >
      <header
        className="admin-mobile-bar"
      >
        <button
          type="button"
          className="admin-mobile-menu-button"
          aria-label="باز کردن منوی مدیریت"
          aria-expanded={
            navigationOpen
          }
          onClick={() =>
            setNavigationOpen(
              true,
            )
          }
        >
          <span />
          <span />
          <span />
        </button>

        <div
          className="admin-mobile-brand"
        >
          <span>
            TKB
          </span>

          <strong>
            پنل مدیریت
          </strong>
        </div>

        <div
          id="admin-mobile-notification-slot"
          className="admin-mobile-notification-slot"
        />
      </header>

      <button
        type="button"
        className="admin-mobile-backdrop"
        aria-label="بستن منوی مدیریت"
        tabIndex={
          navigationOpen
            ? 0
            : -1
        }
        onClick={() =>
          setNavigationOpen(
            false,
          )
        }
      />

      <aside
        className="admin-sidebar"
        aria-label="منوی مدیریت"
      >
        <div
          className="admin-mobile-sidebar-tools"
        >
          <button
            type="button"
            className="admin-mobile-close-button"
            aria-label="بستن منوی مدیریت"
            onClick={() =>
              setNavigationOpen(
                false,
              )
            }
          >
            ×
          </button>
        </div>

        {sidebar}
      </aside>

      <main
        className="admin-main"
      >
        {children}
      </main>
    </div>
  );
}