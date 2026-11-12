import {
  NextRequest,
  NextResponse,
} from "next/server";

import {
  proxyAdminJsonMutation,
} from "@/lib/admin-route-proxy";

interface RouteContext {
  params: Promise<{
    attributeValueId: string;
  }>;
}

export async function PATCH(
  request: NextRequest,
  context: RouteContext,
) {
  const {
    attributeValueId,
  } =
    await context.params;

  const id =
    Number(
      attributeValueId,
    );

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

  return proxyAdminJsonMutation(
    request,
    `/admin/attribute-values/${id}`,
    "PATCH",
  );
}