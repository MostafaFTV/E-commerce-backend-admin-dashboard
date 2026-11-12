import { NextResponse } from "next/server";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

interface RouteContext {
  params: Promise<{ ticketId: string }>;
}

export async function PATCH(_request: Request, context: RouteContext) {
  const token = await getAdminToken();
  if (!token) {
    return NextResponse.json({ message: "ابتدا وارد حساب مدیریت شوید." }, { status: 401 });
  }

  const { ticketId } = await context.params;
  try {
    const response = await adminBackendFetch(
      `/admin/support-tickets/${encodeURIComponent(ticketId)}/close`,
      { method: "PATCH" },
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
