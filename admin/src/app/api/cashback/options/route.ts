import { NextRequest, NextResponse } from "next/server";
import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

type Row = Record<string, unknown>;

function object(value: unknown): Row | null {
  return value !== null && typeof value === "object" && !Array.isArray(value)
    ? value as Row
    : null;
}

function dataArray(payload: unknown): unknown[] {
  const body = object(payload);
  const first = body?.data;
  if (Array.isArray(first)) return first;
  const nested = object(first);
  return Array.isArray(nested?.data) ? nested.data : [];
}

function label(value: unknown): string {
  if (typeof value === "string") return value;
  const translated = object(value);
  return String(translated?.fa || translated?.en || "");
}

function normalize(payload: unknown, tree = false) {
  const items: Array<{ id: number; name: string }> = [];

  function visit(value: unknown) {
    const row = object(value);
    if (!row) return;

    const id = Number(row.id);
    if (Number.isSafeInteger(id) && id > 0) {
      items.push({
        id,
        name: label(row.name) || String(row.slug ?? `#${id}`),
      });
    }

    if (tree) {
      const children =
        row.children ??
        row.children_recursive ??
        row.childrenRecursive;

      if (Array.isArray(children)) children.forEach(visit);
    }
  }

  dataArray(payload).forEach(visit);
  return [...new Map(items.map(item => [item.id, item])).values()];
}

export async function GET(request: NextRequest) {
  const token = await getAdminToken();

  if (!token) {
    return NextResponse.json(
      { message: "ابتدا وارد پنل شوید." },
      { status: 401 }
    );
  }

  const params = request.nextUrl.searchParams;
  const kind = params.get("kind");

  if (kind !== "products" && kind !== "categories") {
    return NextResponse.json(
      { message: "نوع جست‌وجو معتبر نیست." },
      { status: 400 }
    );
  }

  const search = (params.get("q") ?? "").trim().slice(0, 100);

  try {
    if (kind === "categories") {
      const response = await adminBackendFetch(
        "/admin/categories/tree",
        { cache: "no-store" },
        token
      );

      const payload = await readBackendPayload(response);

      if (!response.ok) {
        return NextResponse.json(
          payload ?? { message: "دریافت دسته‌بندی‌ها انجام نشد." },
          { status: response.status }
        );
      }

      const normalized = normalize(payload, true);
      const items = search
        ? normalized.filter(item =>
            item.name.toLocaleLowerCase().includes(
              search.toLocaleLowerCase()
            )
          )
        : normalized;

      return NextResponse.json({ items });
    }

    const ids = (params.get("ids") ?? "")
      .split(",")
      .filter(Boolean)
      .map(Number)
      .filter(id => Number.isSafeInteger(id) && id > 0)
      .slice(0, 100);

    if (ids.length > 0) {
      const results = await Promise.all(
        [...new Set(ids)].map(async id => {
          const response = await adminBackendFetch(
            `/admin/products/${id}`,
            { cache: "no-store" },
            token
          );
          const payload = await readBackendPayload(response);
          return {
            status: response.status,
            ok: response.ok,
            payload,
          };
        })
      );

      const denied = results.find(result =>
        result.status === 401 || result.status === 403
      );

      if (denied) {
        return NextResponse.json(
          { message: "مجوز مشاهده محصولات موجود نیست." },
          { status: denied.status }
        );
      }

      const items = results.flatMap(result => {
        if (!result.ok) return [];

        const body = object(result.payload);
        const product = object(body?.data ?? result.payload);
        const id = Number(product?.id);

        if (!product || !Number.isSafeInteger(id)) return [];

        return [{
          id,
          name: label(product.name) || `#${id}`,
        }];
      });

      return NextResponse.json({ items });
    }

    const query = new URLSearchParams({
      page: "1",
      per_page: "30",
    });

    if (search) query.set("search", search);

    const response = await adminBackendFetch(
      `/admin/products?${query.toString()}`,
      { cache: "no-store" },
      token
    );

    const payload = await readBackendPayload(response);

    if (!response.ok) {
      return NextResponse.json(
        payload ?? { message: "دریافت محصولات انجام نشد." },
        { status: response.status }
      );
    }

    return NextResponse.json({ items: normalize(payload) });

  } catch {
    return NextResponse.json(
      { message: "ارتباط با Backend برقرار نشد." },
      { status: 503 }
    );
  }
}