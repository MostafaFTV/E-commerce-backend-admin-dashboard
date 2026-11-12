import { NextResponse } from "next/server";

import {
  ADMIN_TOKEN_COOKIE,
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export async function POST() {
  const token =
    await getAdminToken();

  let status = 200;

  let payload: unknown = {
    message:
      "خروج انجام شد.",
  };

  if (token) {
    try {
      const backendResponse =
        await adminBackendFetch(
          "/admin/auth/logout",
          {
            method: "POST",
          },
          token,
        );

      status =
        backendResponse.status;

      payload =
        await readBackendPayload(
          backendResponse,
        );

      if (
        payload === null
      ) {
        payload = {
          message:
            "خروج انجام شد.",
        };
      }
    } catch {
      status = 503;

      payload = {
        message:
          "تأیید خروج از بک‌اند انجام نشد.",
      };
    }
  }

  const response =
    NextResponse.json(
      payload,
      {
        status,
      },
    );

  response.cookies.set({
    name:
      ADMIN_TOKEN_COOKIE,
    value: "",
    expires:
      new Date(0),
    httpOnly: true,
    sameSite: "lax",
    secure:
      process.env.NODE_ENV ===
      "production",
    path: "/",
  });

  return response;
}
