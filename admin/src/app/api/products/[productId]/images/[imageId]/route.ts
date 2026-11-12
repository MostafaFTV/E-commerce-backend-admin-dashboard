import {
  NextRequest,
  NextResponse,
} from "next/server";

import {
  proxyAdminJsonMutation,
} from "@/lib/admin-route-proxy";

interface RouteContext {
  params:
    Promise<{
      productId:
        string;

      imageId:
        string;
    }>;
}

async function resolveIds(
  context:
    RouteContext,
) {
  const {
    productId,
    imageId,
  } =
    await context.params;

  return {
    product:
      Number(productId),

    image:
      Number(imageId),
  };
}

function invalidIds(
  product:
    number,
  image:
    number,
): NextResponse | null {
  if (
    !Number.isInteger(
      product,
    ) ||
    product <= 0 ||
    !Number.isInteger(
      image,
    ) ||
    image <= 0
  ) {
    return NextResponse.json(
      {
        message:
          "شناسه نامعتبر است.",
      },
      {
        status:
          400,
      },
    );
  }

  return null;
}

export async function PATCH(
  request:
    NextRequest,
  context:
    RouteContext,
) {
  const {
    product,
    image,
  } =
    await resolveIds(
      context,
    );

  const invalid =
    invalidIds(
      product,
      image,
    );

  if (invalid) {
    return invalid;
  }

  return proxyAdminJsonMutation(
    request,
    `/admin/products/${product}/images/${image}`,
    "PATCH",
  );
}

export async function DELETE(
  request:
    NextRequest,
  context:
    RouteContext,
) {
  const {
    product,
    image,
  } =
    await resolveIds(
      context,
    );

  const invalid =
    invalidIds(
      product,
      image,
    );

  if (invalid) {
    return invalid;
  }

  return proxyAdminJsonMutation(
    request,
    `/admin/products/${product}/images/${image}`,
    "DELETE",
  );
}