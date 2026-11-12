import {
  NextRequest,
  NextResponse,
} from "next/server";

import {
  proxyAdminJsonMutation,
} from "@/lib/admin-route-proxy";

interface RouteContext {
  params:
    Promise<{
      userId:
        string;
    }>;
}

async function userIdFromContext(
  context:
    RouteContext,
): Promise<number> {
  const {
    userId,
  } =
    await context.params;

  return Number(
    userId,
  );
}

function invalidUserId(
  id:
    number,
): NextResponse | null {
  if (
    !Number.isInteger(
      id,
    ) ||
    id <= 0
  ) {
    return NextResponse.json(
      {
        message:
          "شناسه نامعتبر است.",
      },
      {
        status:
          400,
      },
    );
  }

  return null;
}

export async function PATCH(
  request:
    NextRequest,
  context:
    RouteContext,
) {
  const id =
    await userIdFromContext(
      context,
    );

  const invalid =
    invalidUserId(
      id,
    );

  if (invalid) {
    return invalid;
  }

  return proxyAdminJsonMutation(
    request,
    `/admin/users/${id}`,
    "PATCH",
  );
}

export async function DELETE(
  request:
    NextRequest,
  context:
    RouteContext,
) {
  const id =
    await userIdFromContext(
      context,
    );

  const invalid =
    invalidUserId(
      id,
    );

  if (invalid) {
    return invalid;
  }

  return proxyAdminJsonMutation(
    request,
    `/admin/users/${id}`,
    "DELETE",
  );
}