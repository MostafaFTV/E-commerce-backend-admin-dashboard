import {
  NextRequest,
} from "next/server";

import {
  proxyAdminJsonMutation,
} from "@/lib/admin-route-proxy";

export async function PUT(
  request: NextRequest,
) {
  return proxyAdminJsonMutation(
    request,
    "/admin/contact-content",
    "PUT",
  );
}