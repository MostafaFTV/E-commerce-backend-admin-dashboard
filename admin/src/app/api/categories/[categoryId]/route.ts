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
      categoryId:
        string;
    }>;
}

async function categoryIdFromContext(
  context:
    RouteContext,
): Promise<number> {
  const {
    categoryId,
  } =
    await context.params;

  return Number(
    categoryId,
  );
}

function invalidCategoryId(
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
    await categoryIdFromContext(
      context,
    );

  const invalid =
    invalidCategoryId(
      id,
    );

  if (invalid) {
    return invalid;
  }

  return proxyAdminJsonMutation(
    request,
    `/admin/categories/${id}`,
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
    await categoryIdFromContext(
      context,
    );

  const invalid =
    invalidCategoryId(
      id,
    );

  if (invalid) {
    return invalid;
  }

  return proxyAdminJsonMutation(
    request,
    `/admin/categories/${id}`,
    "DELETE",
  );
}