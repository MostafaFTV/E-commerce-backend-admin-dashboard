"use client";

import {
  usePathname,
  useRouter,
} from "next/navigation";

function fallbackPath(
  pathname:
    string,
): string {
  const segments =
    pathname
      .split("/")
      .filter(Boolean);

  if (
    segments.length <= 1
  ) {
    return "/dashboard";
  }

  return `/${segments[0]}`;
}

function getSafeReturnPath():
string | null {
  if (
    typeof window ===
      "undefined"
  ) {
    return null;
  }

  const params =
    new URLSearchParams(
      window.location.search,
    );

  const returnTo =
    params.get(
      "returnTo",
    );

  if (
    !returnTo ||
    !returnTo.startsWith(
      "/",
    ) ||
    returnTo.startsWith(
      "//",
    )
  ) {
    return null;
  }

  return returnTo;
}

export function AdminBackButton() {
  const pathname =
    usePathname();

  const router =
    useRouter();

  const segments =
    pathname
      .split("/")
      .filter(Boolean);

  if (
    segments.length <= 1
  ) {
    return null;
  }

  function handleBack() {
    if (
      typeof window ===
        "undefined"
    ) {
      return;
    }

    const returnTo =
      getSafeReturnPath();

    if (returnTo) {
      router.push(
        returnTo,
      );

      return;
    }

    let sameAdminOrigin =
      false;

    if (
      document.referrer
    ) {
      try {
        sameAdminOrigin =
          new URL(
            document.referrer,
          ).origin ===
          window.location.origin;
      } catch {
        sameAdminOrigin =
          false;
      }
    }

    if (
      sameAdminOrigin
    ) {
      router.back();

      return;
    }

    router.push(
      fallbackPath(
        pathname,
      ),
    );
  }

  return (
    <button
      type="button"
      className="admin-back-button"
      onClick={
        handleBack
      }
    >
      <span
        aria-hidden="true"
      >
        ←
      </span>

      <span>
        بازگشت
      </span>
    </button>
  );
}