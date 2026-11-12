import "server-only";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export interface AdminBrandOption {
  id: number;
  name: string;
  slug: string;
  is_active: boolean;
  sort_order: number;
}

interface AdminBrandsResponse {
  data?: AdminBrandOption[];
}

export async function getAdminBrandOptions():
Promise<AdminBrandOption[]> {
  const token =
    await getAdminToken();

  if (!token) {
    return [];
  }

  try {
    const response =
      await adminBackendFetch(
        "/admin/brands",
        {},
        token,
      );

    if (!response.ok) {
      return [];
    }

    const payload =
      await readBackendPayload(
        response,
      ) as AdminBrandsResponse | null;

    if (
      !payload ||
      !Array.isArray(
        payload.data,
      )
    ) {
      return [];
    }

    return payload.data;
  } catch {
    return [];
  }
}
