import {
  NextRequest,
  NextResponse,
} from "next/server";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

const MAX_FILE_BYTES = 5 * 1024 * 1024;

function errorResponse(message: string, status: number) {
  return NextResponse.json(
    { message },
    {
      status,
      headers: {
        "Cache-Control": "no-store",
      },
    },
  );
}

export async function POST(request: NextRequest) {
  const origin = request.headers.get("origin");

  const allowedOrigins = new Set([
    request.nextUrl.origin,

  ]);

  if (origin && !allowedOrigins.has(origin)) {
    return errorResponse("مبدأ درخواست نامعتبر است.", 403);
  }

  const token = await getAdminToken();

  if (!token) {
    return errorResponse(
      "ابتدا وارد حساب مدیریت شوید.",
      401,
    );
  }

  const contentLength = Number(
    request.headers.get("content-length") ?? "0",
  );

  if (
    Number.isFinite(contentLength) &&
    contentLength > MAX_FILE_BYTES + 1024 * 1024
  ) {
    return errorResponse(
      "حجم فایل بیشتر از حد مجاز است.",
      413,
    );
  }

  let formData: FormData;

  try {
    formData = await request.formData();
  } catch {
    return errorResponse(
      "اطلاعات فایل قابل خواندن نیست.",
      400,
    );
  }

  const file = formData.get("file");

  if (!(file instanceof File)) {
    return errorResponse(
      "فایل Excel انتخاب نشده است.",
      422,
    );
  }

  if (
    !file.name.toLowerCase().endsWith(".xlsx") ||
    file.size === 0 ||
    file.size > MAX_FILE_BYTES
  ) {
    return errorResponse(
      "فایل باید XLSX و حداکثر ۵ مگابایت باشد.",
      422,
    );
  }

  const outgoing = new FormData();
  outgoing.set("file", file, file.name);

  try {
    const response = await adminBackendFetch(
      "/admin/products/excel/preview",
      {
        method: "POST",
        body: outgoing,
      },
      token,
    );

    const payload = await readBackendPayload(response);

    return NextResponse.json(
      payload ?? {
        message: response.ok
          ? "پیش‌نمایش آماده شد."
          : "بررسی فایل Excel ناموفق بود.",
      },
      {
        status: response.status,
        headers: {
          "Cache-Control": "private, no-store",
        },
      },
    );
  } catch {
    return errorResponse(
      "اتصال به سرور برقرار نشد.",
      503,
    );
  }
}