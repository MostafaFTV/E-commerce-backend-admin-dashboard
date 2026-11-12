import "server-only";

import { cookies } from "next/headers";

import type {
  AdminMeResponse,
  AdminUser,
} from "@/lib/admin-types";

export const ADMIN_TOKEN_COOKIE =
  "tkb_admin_token";

const DEFAULT_API_BASE_URL =
  "http://localhost:8000/api";

const API_BASE_URL = (
  process.env.TKB_API_BASE_URL ??
  DEFAULT_API_BASE_URL
).replace(/\/+$/, "");

export function adminBackendUrl(
  path: string,
): string {
  const normalizedPath =
    path.replace(/^\/+/, "");

  return `${API_BASE_URL}/${normalizedPath}`;
}

export function adminBackendAssetUrl(
  path:
    string | null | undefined,
): string {
  if (!path) {
    return "";
  }

  if (
    /^https?:\/\//i.test(
      path,
    )
  ) {
    return path;
  }

  const publicOrigin =
    process.env.TKB_PUBLIC_API_ORIGIN?.trim();

  const origin =
    publicOrigin
      ? new URL(publicOrigin).origin
      : new URL(API_BASE_URL).origin;

  return `${origin}/${path.replace(/^\/+/, "")}`;
}
export async function adminBackendFetch(
  path: string,
  init: RequestInit = {},
  token?: string | null,
): Promise<Response> {
  const headers =
    new Headers(init.headers);

  headers.set(
    "Accept",
    "application/json",
  );

  const isFormData =
    typeof FormData !== "undefined" &&
    init.body instanceof FormData;

  /*
   * JSON remains the default for the existing
   * admin BFF calls.
   *
   * FormData MUST NOT receive a manual
   * Content-Type header because fetch needs
   * to generate its multipart boundary.
   */
  if (
    init.body &&
    !headers.has("Content-Type") &&
    !isFormData
  ) {
    headers.set(
      "Content-Type",
      "application/json",
    );
  }

  if (token) {
    headers.set(
      "Authorization",
      `Bearer ${token}`,
    );
  }

  return fetch(
    adminBackendUrl(path),
    {
      ...init,
      headers,
      cache: "no-store",
    },
  );
}

export async function readBackendPayload(
  response: Response,
): Promise<unknown> {
  const contentType =
    response.headers.get(
      "content-type",
    ) ?? "";

  if (
    contentType.includes(
      "application/json",
    )
  ) {
    try {
      return await response.json();
    } catch {
      return null;
    }
  }

  try {
    const text =
      await response.text();

    return text
      ? {
          message: text,
        }
      : null;
  } catch {
    return null;
  }
}

export async function getAdminToken():
Promise<string | null> {
  const cookieStore =
    await cookies();

  return (
    cookieStore.get(
      ADMIN_TOKEN_COOKIE,
    )?.value ?? null
  );
}

export async function getCurrentAdmin():
Promise<AdminUser | null> {
  const token =
    await getAdminToken();

  if (!token) {
    return null;
  }

  try {
    const response =
      await adminBackendFetch(
        "/admin/auth/me",
        {},
        token,
      );

    if (!response.ok) {
      return null;
    }

    const payload =
      (await response.json()) as
        AdminMeResponse;

    return payload.user ?? null;
  } catch {
    return null;
  }
}
