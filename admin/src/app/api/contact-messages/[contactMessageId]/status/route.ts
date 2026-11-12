import {
  NextResponse,
} from "next/server";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

interface RouteContext {
  params:
    Promise<{
      contactMessageId: string;
    }>;
}

export async function PATCH(
  request: Request,
  context: RouteContext,
) {
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

  const {
    contactMessageId,
  } = await context.params;

  const body =
    await request.json()
      .catch(
        () => ({}),
      );

  try {
    const response =
      await adminBackendFetch(
        `/admin/contact-messages/${encodeURIComponent(
          contactMessageId,
        )}/status`,
        {
          method: "PATCH",
          body:
            JSON.stringify(body),
        },
        token,
      );

    const payload =
      await readBackendPayload(
        response,
      );

    return NextResponse.json(
      payload ?? {
        message:
          "پاسخ بک‌اند خالی است.",
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
          "بک‌اند در دسترس نیست.",
      },
      {
        status: 502,
      },
    );
  }
}