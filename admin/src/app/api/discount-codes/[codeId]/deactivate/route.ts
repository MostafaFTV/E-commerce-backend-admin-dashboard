import { NextRequest, NextResponse } from "next/server";

import { proxyAdminJsonMutation } from "@/lib/admin-route-proxy";

type RouteContext = {
  params: Promise<{ codeId: string }>;
};

export async function PATCH(request: NextRequest, context: RouteContext) {
  const { codeId } = await context.params;
  if (!/^[1-9]\d*$/.test(codeId)) {
    return NextResponse.json(
      { message: "شناسه نامعتبر است." },
      { status: 400 },
    );
  }

  return proxyAdminJsonMutation(
    request,
    `/admin/discount-codes/${codeId}/deactivate?locale=fa`,
    "PATCH",
  );
}
