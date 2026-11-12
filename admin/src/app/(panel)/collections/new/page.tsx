import {
  notFound,
  redirect,
} from "next/navigation";

import {
  CollectionForm,
} from "@/components/CollectionForm";

import {
  getAdminCollectionProductOptions,
} from "@/lib/admin-collections";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

export default async function NewCollectionPage() {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "collections.create",
    )
  ) {
    notFound();
  }

  const canManageProducts =
    admin.permissions.includes(
      "products.view",
    );

  const products =
    canManageProducts
      ? await getAdminCollectionProductOptions()
      : [];

  return (
    <section
      className="admin-dashboard"
      dir="rtl"
      lang="fa"
    >
      <header className="admin-page-header">
        <div>
          <p className="admin-eyebrow">
            کاتالوگ
          </p>

          <h1>
            ایجاد مجموعه
          </h1>
        </div>

        <p className="admin-muted">
          یک مجموعه منتخب از محصولات فروشگاه ایجاد کنید.
        </p>
      </header>

      <CollectionForm
        products={
          products
        }
        canManageProducts={
          canManageProducts
        }
      />
    </section>
  );
}