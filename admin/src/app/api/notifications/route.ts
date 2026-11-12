import { NextResponse } from "next/server";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

async function forwardNotificationRequest(
  method: "GET" | "POST",
) {
  const token = await getAdminToken();

  if (!token) {
    return NextResponse.json(
      { message: "ابتدا وارد حساب مدیریت شوید." },
      { status: 401 },
    );
  }

  try {
    const response = await adminBackendFetch(
      method === "POST"
        ? "/admin/notifications/read-all"
        : "/admin/notifications",
      { method },
      token,
    );
    const payload = await readBackendPayload(response);

    return NextResponse.json(
      payload ?? { message: "پاسخ بک‌اند خالی است." },
      { status: response.status },
    );
  } catch {
    return NextResponse.json(
      { message: "بک‌اند در دسترس نیست." },
      { status: 502 },
    );
  }
}

export async function GET() {
  return forwardNotificationRequest("GET");
}

export async function POST() {
  return forwardNotificationRequest("POST");
}
