import type {
  NextRequest,
} from "next/server";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export async function PATCH(
  request:
    NextRequest,
  context: {
    params:
      Promise<{
        sectionId:
          string;
      }>;
  },
) {
  const {
    sectionId,
  } =
    await context.params;

  const id =
    Number(
      sectionId,
    );

  if (
    !Number.isInteger(
      id,
    ) ||
    id <= 0
  ) {
    return Response.json(
      {
        message:
          "شناسه نامعتبر است.",
      },
      {
        status: 400,
      },
    );
  }

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

  const body =
    await request.json();

  const response =
    await adminBackendFetch(
      `/admin/homepage-sections/${id}`,
      {
        method:
          "PATCH",

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

  return Response.json(
    payload ?? {},
    {
      status:
        response.status,
    },
  );
}