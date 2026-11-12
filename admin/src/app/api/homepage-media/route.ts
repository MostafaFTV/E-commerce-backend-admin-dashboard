import type {
  NextRequest,
} from "next/server";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export async function POST(
  request:
    NextRequest,
) {
  const token =
    await getAdminToken();

  if (!token) {
    return Response.json(
      {
        message:
          "ابتدا وارد حساب مدیریت شوید.",
      },
      {
        status: 401,
      },
    );
  }

  const formData =
    await request.formData();

  const response =
    await adminBackendFetch(
      "/admin/homepage-media",
      {
        method:
          "POST",

        body:
          formData,
      },
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  return Response.json(
    payload ?? {},
    {
      status:
        response.status,
    },
  );
}