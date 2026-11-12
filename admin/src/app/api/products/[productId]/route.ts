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
      productId:
        string;
    }>;
}

async function productIdFromContext(
  context:
    RouteContext,
): Promise<number> {
  const {
    productId,
  } =
    await context.params;

  return Number(
    productId,
  );
}

function invalidProductId(
  id:
    number,
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
    await productIdFromContext(
      context,
    );

  const invalid =
    invalidProductId(
      id,
    );

  if (invalid) {
    return invalid;
  }

  return proxyAdminJsonMutation(
    request,
    `/admin/products/${id}`,
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
    await productIdFromContext(
      context,
    );

  const invalid =
    invalidProductId(
      id,
    );

  if (invalid) {
    return invalid;
  }

  return proxyAdminJsonMutation(
    request,
    `/admin/products/${id}`,
    "DELETE",
  );
}