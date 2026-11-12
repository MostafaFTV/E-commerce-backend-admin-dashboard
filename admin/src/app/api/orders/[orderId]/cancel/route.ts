import type {
  NextRequest,
} from "next/server";

import {
  proxyAdminJsonMutation,
} from "@/lib/admin-route-proxy";

export async function PATCH(
  request:
    NextRequest,
  context: {
    params:
      Promise<{
        orderId:
          string;
      }>;
  },
) {
  const {
    orderId,
  } =
    await context.params;

  const id =
    Number(orderId);

  if (
    !Number.isInteger(id) ||
    id <= 0
  ) {
    return Response.json(
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
    `/admin/orders/${id}/cancel`,
    "PATCH",
  );
}