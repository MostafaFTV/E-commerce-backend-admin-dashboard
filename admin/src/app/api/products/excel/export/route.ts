import {
  NextResponse,
} from "next/server";

import {
  adminBackendFetch,
  getAdminToken,
} from "@/lib/admin-server";

export const dynamic = "force-dynamic";

export async function GET() {
  const token = await getAdminToken();

  if (!token) {
    return NextResponse.json(
      {
        message: "ابتدا وارد حساب مدیریت شوید.",
      },
      { status: 401 },
    );
  }

  try {
    const response = await adminBackendFetch(
      "/admin/products/excel/export",
      { method: "GET" },
      token,
    );

    if (!response.ok) {
      let message =
        "دریافت فایل Excel انجام نشد.";

      if (response.status === 401) {
        message = "نشست مدیریت منقضی شده است.";
      } else if (response.status === 403) {
        message =
          "مجوز دریافت اطلاعات قیمت و موجودی را ندارید.";
      }

      return NextResponse.json(
        { message },
        {
          status: response.status,
          headers: {
            "Cache-Control": "no-store",
          },
        },
      );
    }

    const contentType =
      response.headers.get("content-type") ?? "";

    if (
      !contentType.includes(
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      )
    ) {
      return NextResponse.json(
        {
          message:
            "پاسخ سرور فایل Excel معتبر نیست.",
        },
        { status: 502 },
      );
    }

    if (!response.body) {
      return NextResponse.json(
        {
          message: "فایل خروجی خالی است.",
        },
        { status: 502 },
      );
    }

    return new NextResponse(
      response.body,
      {
        status: 200,
        headers: {
          "Content-Type":
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
          "Content-Disposition":
            'attachment; filename="TKB-Products.xlsx"',
          "Cache-Control":
            "private, no-store, max-age=0",
          "X-Content-Type-Options":
            "nosniff",
        },
      },
    );
  } catch {
    return NextResponse.json(
      {
        message:
          "ارتباط با سرور برقرار نشد. دوباره تلاش کنید.",
      },
      {
        status: 503,
        headers: {
          "Cache-Control": "no-store",
        },
      },
    );
  }
}