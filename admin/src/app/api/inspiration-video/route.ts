import type { NextRequest } from "next/server";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

const backendEndpoint = "/admin/inspiration-video";

async function forwardResponse(
  response: Response,
): Promise<Response> {
  const payload = await readBackendPayload(response);

  return Response.json(payload ?? {}, {
    status: response.status,
    headers: {
      "Cache-Control": "no-store",
    },
  });
}

export async function GET() {
  const token = await getAdminToken();

  if (!token) {
    return Response.json(
      { message: "ابتدا وارد حساب مدیریت شوید." },
      { status: 401 },
    );
  }

  const response = await adminBackendFetch(
    backendEndpoint,
    {
      method: "GET",
      cache: "no-store",
    },
    token,
  );

  return forwardResponse(response);
}

export async function POST(request: NextRequest) {
  const token = await getAdminToken();

  if (!token) {
    return Response.json(
      { message: "ابتدا وارد حساب مدیریت شوید." },
      { status: 401 },
    );
  }

  const formData = await request.formData();
  const video = formData.get("video");

  if (!(video instanceof File)) {
    return Response.json(
      { message: "فایل ویدیو انتخاب نشده است." },
      { status: 422 },
    );
  }

  if (
    !video.name.toLowerCase().endsWith(".mp4") ||
    video.size > 50 * 1024 * 1024 ||
    video.size === 0
  ) {
    return Response.json(
      {
        message:
          "فقط فایل MP4 با حجم حداکثر ۵۰ مگابایت مجاز است.",
      },
      { status: 422 },
    );
  }

  const upstream = new FormData();
  upstream.append("video", video);

  const response = await adminBackendFetch(
    backendEndpoint,
    {
      method: "POST",
      body: upstream,
      cache: "no-store",
    },
    token,
  );

  return forwardResponse(response);
}