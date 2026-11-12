import { NextRequest, NextResponse } from "next/server";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export async function GET(request: NextRequest) {
  const token = await getAdminToken();
  if (!token) {
    return NextResponse.json(
      { message: "ابتدا وارد حساب مدیریت شوید." },
      { status: 401 },
    );
  }

  const params = new URLSearchParams(request.nextUrl.searchParams);
  params.set("locale", "fa");
  const query = params.toString();
  try {
    const response = await adminBackendFetch(
      `/admin/discount-codes/options${query ? `?${query}` : ""}`,
      {},
      token,
    );
    const payload = await readBackendPayload(response);
    return NextResponse.json(payload ?? {}, { status: response.status });
  } catch {
    return NextResponse.json(
      { message: "اتصال به بک‌اند برقرار نشد." },
      { status: 503 },
    );
  }
}
