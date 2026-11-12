import {
  notFound,
  redirect,
} from "next/navigation";

import {
  ShippingMethodForm,
} from "@/components/ShippingMethodForm";

import {
  AdminShippingMethodRequestError,
  getAdminShippingMethod,
} from "@/lib/admin-shipping-methods";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

interface Props {
  params:
    Promise<{
      shippingMethodId:
        string;
    }>;
}

export default async function EditShippingMethodPage({
  params,
}: Props) {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "shipping_methods.update",
    )
  ) {
    notFound();
  }

  const {
    shippingMethodId,
  } =
    await params;

  const id =
    Number(
      shippingMethodId,
    );

  if (
    !Number.isInteger(
      id,
    ) ||
    id <= 0
  ) {
    notFound();
  }

  let method;

  try {
    method =
      await getAdminShippingMethod(
        id,
      );
  } catch (error) {
    if (
      error instanceof
        AdminShippingMethodRequestError
    ) {
      if (
        error.status === 401
      ) {
        redirect(
          "/login",
        );
      }

      if (
        error.status === 404
      ) {
        notFound();
      }
    }

    throw error;
  }

  return (
    <section
      className="admin-dashboard"
    >
      <header
        className="admin-page-header"
      >
        <div>
          <p
            className="admin-eyebrow"
          >
            عملیات / ارسال
          </p>

          <h1>
            ویرایش روش ارسال
          </h1>
        </div>

        <p
          className="admin-muted"
        >
          {method.name}
        </p>
      </header>

      <ShippingMethodForm
        initialMethod={
          method
        }
      />
    </section>
  );
}