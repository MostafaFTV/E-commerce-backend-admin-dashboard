
import {
  NextRequest,
  NextResponse,
} from "next/server";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export async function POST(
  request: NextRequest,
) {
  const origin =
    request.headers.get(
      "origin",
    );

  if (origin) {
    const host =
      request.headers.get(
        "x-forwarded-host",
      ) ??
      request.headers.get(
        "host",
      );

    const protocol =
      request.headers.get(
        "x-forwarded-proto",
      ) ??
      request.nextUrl.protocol.replace(
        /:$/,
        "",
      );

    const expectedOrigin = host
      ? `${protocol}://${host}`
      : null;

    if (
      !expectedOrigin ||
      origin !== expectedOrigin
    ) {
      return NextResponse.json(
        {
          message:
            "مبدأ درخواست نامعتبر است.",
        },
        {
          status: 403,
        },
      );
    }
  }

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

  let body: unknown;

  try {
    body =
      await request.json();
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
    const response =
      await adminBackendFetch(
        "/admin/products",
        {
          method: "POST",

          body:
            JSON.stringify(
              body,
            ),
        },
        token,
      );

    const payload =
      await readBackendPayload(
        response,
      );

    if (response.status === 204) {
      return new NextResponse(
        null,
        {
          status: 204,
        },
      );
    }

    return NextResponse.json(
      payload ?? {
        message:
          response.ok
            ? "محصول ایجاد شد."
            : "ایجاد محصول انجام نشد.",
      },
      {
        status:
          response.status,
      },
    );
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
