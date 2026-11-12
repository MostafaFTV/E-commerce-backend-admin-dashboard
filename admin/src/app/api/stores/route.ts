import {
  NextRequest,
} from "next/server";

import {
  proxyAdminJsonMutation,
} from "@/lib/admin-route-proxy";

export async function POST(
  request:
    NextRequest,
) {
  return proxyAdminJsonMutation(
    request,
    "/admin/stores",
    "POST",
  );
}