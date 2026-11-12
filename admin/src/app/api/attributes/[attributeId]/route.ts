import {
  NextRequest,
  NextResponse,
} from "next/server";

import {
  proxyAdminJsonMutation,
} from "@/lib/admin-route-proxy";

interface RouteContext {
  params: Promise<{
    attributeId: string;
  }>;
}

export async function PATCH(
  request: NextRequest,
  context: RouteContext,
) {
  const {
    attributeId,
  } =
    await context.params;

  const id =
    Number(attributeId);

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
    `/admin/attributes/${id}`,
    "PATCH",
  );
}