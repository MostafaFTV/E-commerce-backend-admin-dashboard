import { NextResponse } from "next/server";

import type {
  AdminLoginResponse,
  ApiErrorPayload,
} from "@/lib/admin-types";

import {
  ADMIN_TOKEN_COOKIE,
  adminBackendFetch,
  readBackendPayload,
} from "@/lib/admin-server";

export async function POST(
  request: Request,
) {
  let body: unknown;

  try {
    body = await request.json();
  } catch {
    return NextResponse.json(
      {
        message:
          "اطلاعات درخواست نامعتبر است.",
      },
      {
        status: 400,
      },
    );
  }

  try {
    const backendResponse =
      await adminBackendFetch(
        "/admin/auth/login",
        {
          method: "POST",
          body:
            JSON.stringify(body),
        },
      );

    const payload =
      await readBackendPayload(
        backendResponse,
      );

    if (!backendResponse.ok) {
      return NextResponse.json(
        (
          payload ?? {
            message:
              "ورود انجام نشد.",
          }
        ) as ApiErrorPayload,
        {
          status:
            backendResponse.status,
        },
      );
    }

    const auth =
      payload as AdminLoginResponse;

    const response =
      NextResponse.json({
        message: auth.message,
        user: auth.user,
      });

    response.cookies.set({
      name:
        ADMIN_TOKEN_COOKIE,
      value:
        auth.token,
      httpOnly: true,
      sameSite: "lax",
      secure:
        process.env.NODE_ENV ===
        "production",
      path: "/",
    });

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
