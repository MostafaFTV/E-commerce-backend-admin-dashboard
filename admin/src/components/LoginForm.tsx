"use client";

import {
  useState,
  type FormEvent,
} from "react";

import { useRouter } from "next/navigation";

import type {
  ApiErrorPayload,
} from "@/lib/admin-types";

export function LoginForm() {
  const router =
    useRouter();

  const [
    identifier,
    setIdentifier,
  ] = useState("");

  const [
    password,
    setPassword,
  ] = useState("");

  const [
    error,
    setError,
  ] = useState<string | null>(
    null,
  );

  const [
    isSubmitting,
    setIsSubmitting,
  ] = useState(false);

  async function handleSubmit(
    event:
      FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    if (isSubmitting) {
      return;
    }

    setError(null);
    setIsSubmitting(true);

    try {
      const response =
        await fetch(
          "/api/auth/login",
          {
            method: "POST",

            headers: {
              Accept:
                "application/json",

              "Content-Type":
                "application/json",
            },

            body:
              JSON.stringify({
                identifier:
                  identifier.trim(),

                password,
              }),
          },
        );

      const payload =
        (await response.json()) as
          ApiErrorPayload;

      if (!response.ok) {
        const message =
          payload.errors
            ?.identifier?.[0] ??
          payload.message ??
          "ورود انجام نشد.";

        setError(message);

        return;
      }

      router.replace(
        "/dashboard",
      );

      router.refresh();
    } catch {
      setError(
        "اتصال به سرویس مدیریت برقرار نشد.",
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <form
      className="admin-login-form"
      onSubmit={handleSubmit}
    >
      <div className="admin-field">
        <label
          htmlFor="identifier"
        >
          ایمیل یا شماره تماس
        </label>

        <input
          id="identifier"
          name="identifier"
          type="text"
          autoComplete="username"
          value={identifier}
          onChange={(event) =>
            setIdentifier(
              event.target.value,
            )
          }
          required
          autoFocus
        />
      </div>

      <div className="admin-field">
        <label
          htmlFor="password"
        >
          رمز عبور
        </label>

        <input
          id="password"
          name="password"
          type="password"
          autoComplete="current-password"
          value={password}
          onChange={(event) =>
            setPassword(
              event.target.value,
            )
          }
          required
        />
      </div>

      {error ? (
        <p
          className="admin-form-error"
          role="alert"
        >
          {error}
        </p>
      ) : null}

      <button
        className="admin-primary-button"
        type="submit"
        disabled={isSubmitting}
      >
        {isSubmitting
          ? "در حال ورود…"
          : "ورود"}
      </button>
    </form>
  );
}
