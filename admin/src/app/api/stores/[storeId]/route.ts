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
      storeId:
        string;
    }>;
}

async function storeIdFromContext(
  context:
    RouteContext,
): Promise<number> {
  const {
    storeId,
  } =
    await context.params;

  return Number(
    storeId,
  );
}

function invalidStoreId(
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

export async function PUT(
  request:
    NextRequest,
  context:
    RouteContext,
) {
  const id =
    await storeIdFromContext(
      context,
    );

  const invalid =
    invalidStoreId(
      id,
    );

  if (invalid) {
    return invalid;
  }

  return proxyAdminJsonMutation(
    request,
    `/admin/stores/${id}`,
    "PUT",
  );
}

export async function DELETE(
  request:
    NextRequest,
  context:
    RouteContext,
) {
  const id =
    await storeIdFromContext(
      context,
    );

  const invalid =
    invalidStoreId(
      id,
    );

  if (invalid) {
    return invalid;
  }

  return proxyAdminJsonMutation(
    request,
    `/admin/stores/${id}`,
    "DELETE",
  );
}