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
      shippingMethodId:
        string;
    }>;
}

async function methodIdFromContext(
  context:
    RouteContext,
): Promise<number> {
  const {
    shippingMethodId,
  } =
    await context.params;

  return Number(
    shippingMethodId,
  );
}

function invalidId(
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
        status: 400,
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
    await methodIdFromContext(
      context,
    );

  const invalid =
    invalidId(
      id,
    );

  if (invalid) {
    return invalid;
  }

  return proxyAdminJsonMutation(
    request,
    `/admin/shipping-methods/${id}`,
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
    await methodIdFromContext(
      context,
    );

  const invalid =
    invalidId(
      id,
    );

  if (invalid) {
    return invalid;
  }

  return proxyAdminJsonMutation(
    request,
    `/admin/shipping-methods/${id}`,
    "DELETE",
  );
}