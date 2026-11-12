import {
  notFound,
  redirect,
} from "next/navigation";

import {
  ShippingMethodForm,
} from "@/components/ShippingMethodForm";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

export default async function NewShippingMethodPage() {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "shipping_methods.create",
    )
  ) {
    notFound();
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
            افزودن روش ارسال
          </h1>
        </div>
      </header>

      <ShippingMethodForm />
    </section>
  );
}