import { NextRequest, NextResponse } from "next/server";
import { adminBackendFetch, getAdminToken, readBackendPayload } from "@/lib/admin-server";

async function forward(request: NextRequest, method: "POST" | "GET") {
  const token = await getAdminToken();
  if (!token) return NextResponse.json({message:"ورود مجدد لازم است."}, {status:401});
  const body = method === "POST" ? await request.text() : undefined;
  const response = await adminBackendFetch("/admin/cashback/rules", {
    method, cache:"no-store", ...(body === undefined ? {} : {body, headers:{"Content-Type":"application/json"}})
  }, token);
  const payload = await readBackendPayload(response);
  return response.status === 204 ? new NextResponse(null, {status:204}) : NextResponse.json(payload ?? {}, {status:response.status});
}
export async function GET(request:NextRequest) { return forward(request,"GET"); }
export async function POST(request:NextRequest) { return forward(request,"POST"); }
