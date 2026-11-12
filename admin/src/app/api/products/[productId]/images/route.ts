import {
  NextRequest,
  NextResponse,
} from "next/server";

import {
  proxyAdminFormDataMutation,
} from "@/lib/admin-route-proxy";

interface RouteContext {
  params: Promise<{
    productId: string;
  }>;
}

export async function POST(
  request: NextRequest,
  context: RouteContext,
) {
  const {
    productId,
  } =
    await context.params;

  const id =
    Number(productId);

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

  return proxyAdminFormDataMutation(
    request,
    `/admin/products/${id}/images`,
    "POST",
  );
}
