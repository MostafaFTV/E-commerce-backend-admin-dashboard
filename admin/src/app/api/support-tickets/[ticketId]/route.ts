import { NextResponse } from "next/server";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

interface RouteContext {
  params: Promise<{ ticketId: string }>;
}

export async function GET(request: Request, context: RouteContext) {
  const token = await getAdminToken();
  if (!token) {
    return NextResponse.json({ message: "ابتدا وارد حساب مدیریت شوید." }, { status: 401 });
  }

  const { ticketId } = await context.params;
  const url = new URL(request.url);
  const query = new URLSearchParams();
  for (const key of ["page", "per_page"]) {
    const value = url.searchParams.get(key);
    if (value) query.set(key, value);
  }
  const queryString = query.size ? `?${query.toString()}` : "";
  try {
    const response = await adminBackendFetch(
      `/admin/support-tickets/${encodeURIComponent(ticketId)}${queryString}`,
      {},
      token,
    );
    return NextResponse.json(
      (await readBackendPayload(response)) ?? { message: "پاسخ بک‌اند خالی است." },
      { status: response.status },
    );
  } catch {
    return NextResponse.json({ message: "بک‌اند در دسترس نیست." }, { status: 502 });
  }
}
