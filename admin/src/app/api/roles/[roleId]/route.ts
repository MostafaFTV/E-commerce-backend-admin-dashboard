import {
  NextRequest,
  NextResponse,
} from "next/server";

import {
  proxyAdminJsonMutation,
} from "@/lib/admin-route-proxy";

interface Context {
  params:
    Promise<{
      roleId: string;
    }>;
}

async function getId(
  context: Context,
): Promise<number> {
  const {
    roleId,
  } =
    await context.params;

  return Number(roleId);
}

function invalidId(
  id: number,
): NextResponse | null {
  if (
    !Number.isInteger(id) ||
    id <= 0
  ) {
    return NextResponse.json(
      {
        message:
          "شناسه نامعتبر است.",
      },
      {
        status: 400,
      },
    );
  }

  return null;
}

export async function PATCH(
  request: NextRequest,
  context: Context,
) {
  const id =
    await getId(context);

  const invalid =
    invalidId(id);

  if (invalid) {
    return invalid;
  }

  return proxyAdminJsonMutation(
    request,
    `/admin/roles/${id}`,
    "PATCH",
  );
}

export async function DELETE(
  request: NextRequest,
  context: Context,
) {
  const id =
    await getId(context);

  const invalid =
    invalidId(id);

  if (invalid) {
    return invalid;
  }

  return proxyAdminJsonMutation(
    request,
    `/admin/roles/${id}`,
    "DELETE",
  );
}