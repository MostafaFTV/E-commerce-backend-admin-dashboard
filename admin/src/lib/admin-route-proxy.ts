import "server-only";

import {
  NextRequest,
  NextResponse,
} from "next/server";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

type MutationMethod =
  | "POST"
  | "PUT"
  | "PATCH"
  | "DELETE";

function invalidOriginResponse(
  request: NextRequest,
): NextResponse | null {
  const origin =
    request.headers.get(
      "origin",
    );

  if (!origin) {
    return null;
  }

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
    request.nextUrl.protocol
      .replace(
        /:$/,
        "",
      );

  if (!host) {
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

  const expectedOrigin =
    `${protocol}://${host}`;

  if (
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

  return null;
}

function unauthenticatedResponse():
NextResponse {
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

async function toNextResponse(
  response: Response,
): Promise<NextResponse> {
  if (response.status === 204) {
    return new NextResponse(
      null,
      {
        status: 204,
      },
    );
  }

  const payload =
    await readBackendPayload(
      response,
    );

  return NextResponse.json(
    payload ?? {
      message: response.ok
        ? "درخواست انجام شد."
        : "درخواست بک‌اند انجام نشد.",
    },
    {
      status:
        response.status,
    },
  );
}

export async function proxyAdminJsonMutation(
  request: NextRequest,
  path: string,
  method: MutationMethod,
): Promise<NextResponse> {
  const originError =
    invalidOriginResponse(
      request,
    );

  if (originError) {
    return originError;
  }

  const token =
    await getAdminToken();

  if (!token) {
    return unauthenticatedResponse();
  }

  let body: unknown = undefined;

  if (method !== "DELETE") {
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
  }

  try {
    const response =
      await adminBackendFetch(
        path,
        {
          method,

          ...(method !== "DELETE"
            ? {
                body:
                  JSON.stringify(
                    body,
                  ),
              }
            : {}),
        },
        token,
      );

    return await toNextResponse(
      response,
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

export async function proxyAdminFormDataMutation(
  request: NextRequest,
  path: string,
  method: "POST" | "PUT" | "PATCH",
): Promise<NextResponse> {
  const originError =
    invalidOriginResponse(
      request,
    );

  if (originError) {
    return originError;
  }

  const token =
    await getAdminToken();

  if (!token) {
    return unauthenticatedResponse();
  }

  let body: FormData;

  try {
    body =
      await request.formData();
  } catch {
    return NextResponse.json(
      {
        message:
          "اطلاعات بارگذاری نامعتبر است.",
      },
      {
        status: 400,
      },
    );
  }

  try {
    const response =
      await adminBackendFetch(
        path,
        {
          method,
          body,
        },
        token,
      );

    return await toNextResponse(
      response,
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
