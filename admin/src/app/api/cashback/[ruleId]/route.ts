import { NextRequest, NextResponse } from "next/server";
import { adminBackendFetch, getAdminToken, readBackendPayload } from "@/lib/admin-server";

type Context = {params:Promise<{ruleId:string}>};
async function forward(request:NextRequest, context:Context, method:"PUT"|"DELETE") {
  const {ruleId} = await context.params;
  if (!/^[1-9]\d*$/.test(ruleId)) return NextResponse.json({message:"شناسه نامعتبر است."},{status:400});
  const token = await getAdminToken();
  if (!token) return NextResponse.json({message:"ورود مجدد لازم است."},{status:401});
  const body = method === "PUT" ? await request.text() : undefined;
  const response = await adminBackendFetch(`/admin/cashback/rules/${ruleId}`, {
    method, cache:"no-store", ...(body === undefined ? {} : {body, headers:{"Content-Type":"application/json"}})
  }, token);
  const payload = await readBackendPayload(response);
  return response.status === 204 ? new NextResponse(null, {status:204}) : NextResponse.json(payload ?? {}, {status:response.status});
}
export async function PUT(request:NextRequest, context:Context) {return forward(request,context,"PUT");}
export async function DELETE(request:NextRequest, context:Context) {return forward(request,context,"DELETE");}
