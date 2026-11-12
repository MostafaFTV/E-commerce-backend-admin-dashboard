import { NextResponse } from "next/server";

import {
  ADMIN_TOKEN_COOKIE,
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export async function GET() {
  const token =
    await getAdminToken();

  if (!token) {
    return NextResponse.json(
      {
        message:
          "ابتدا وارد حساب مدیریت شوید.",
      },
      {
        status: 401,
      },
    );
  }

  try {
    const backendResponse =
      await adminBackendFetch(
        "/admin/auth/me",
        {},
        token,
      );

    const payload =
      await readBackendPayload(
        backendResponse,
      );

    const response =
      NextResponse.json(
        payload ?? {
          message:
            "دریافت نشست مدیریت انجام نشد.",
        },
        {
          status:
            backendResponse.status,
        },
      );

    if (
      backendResponse.status === 401 ||
      backendResponse.status === 403
    ) {
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
    }

    return response;
  } catch {
    return NextResponse.json(
      {
        message:
          "اتصال به بک‌اند برقرار نشد.",
      },
      {
        status: 503,
      },
    );
  }
}
