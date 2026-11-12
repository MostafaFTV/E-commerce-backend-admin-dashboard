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
      collectionId:
        string;
    }>;
}

async function collectionIdFromContext(
  context:
    RouteContext,
): Promise<number> {
  const {
    collectionId,
  } =
    await context.params;

  return Number(
    collectionId,
  );
}

function invalidCollectionId(
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

export async function PUT(
  request:
    NextRequest,
  context:
    RouteContext,
) {
  const id =
    await collectionIdFromContext(
      context,
    );

  const invalid =
    invalidCollectionId(
      id,
    );

  if (invalid) {
    return invalid;
  }

  return proxyAdminJsonMutation(
    request,
    `/admin/collections/${id}`,
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
    await collectionIdFromContext(
      context,
    );

  const invalid =
    invalidCollectionId(
      id,
    );

  if (invalid) {
    return invalid;
  }

  return proxyAdminJsonMutation(
    request,
    `/admin/collections/${id}`,
    "DELETE",
  );
}