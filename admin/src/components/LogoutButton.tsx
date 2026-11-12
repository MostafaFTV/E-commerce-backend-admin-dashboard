"use client";

import { useState } from "react";

import { useRouter } from "next/navigation";

export function LogoutButton() {
  const router =
    useRouter();

  const [
    isSubmitting,
    setIsSubmitting,
  ] = useState(false);

  async function handleLogout() {
    if (isSubmitting) {
      return;
    }

    setIsSubmitting(true);

    try {
      await fetch(
        "/api/auth/logout",
        {
          method: "POST",
        },
      );
    } finally {
      router.replace(
        "/login",
      );

      router.refresh();
    }
  }

  return (
    <button
      type="button"
      className="admin-logout-button"
      onClick={handleLogout}
      disabled={isSubmitting}
    >
      {isSubmitting
        ? "در حال خروج…"
        : "خروج از حساب"}
    </button>
  );
}
