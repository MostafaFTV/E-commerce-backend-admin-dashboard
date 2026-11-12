import {
  notFound,
  redirect,
} from "next/navigation";

import {
  CreateProductForm,
} from "@/components/CreateProductForm";

import {
  getAdminAttributeOptions,
} from "@/lib/admin-attributes";

import {
  getAdminBrandOptions,
} from "@/lib/admin-brands";

import {
  getAdminCategoryOptions,
} from "@/lib/admin-categories";

import {
  getAdminCollectionOptions,
} from "@/lib/admin-collections";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

export default async function NewProductPage() {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect("/login");
  }

  if (
    !admin.permissions.includes(
      "products.create",
    )
  ) {
    notFound();
  }

  const canUpdateProduct =
    admin.permissions.includes(
      "products.update",
    );

  const [
    categories,
    collections,
    brands,
    attributes,
  ] =
    await Promise.all([
      getAdminCategoryOptions(),

      admin.permissions.includes(
        "collections.view",
      )
        ? getAdminCollectionOptions()
        : Promise.resolve([]),

      admin.permissions.includes(
        "products.view",
      )
        ? getAdminBrandOptions()
        : Promise.resolve([]),

      admin.permissions.includes(
        "products.view",
      )
        ? getAdminAttributeOptions()
        : Promise.resolve([]),
    ]);

  return (
    <section className="admin-dashboard">
      <header dir="rtl" lang="fa" className="admin-page-header">
        <div>
          <p className="admin-eyebrow">
            کاتالوگ
          </p>

          <h1>
            ایجاد محصول
          </h1>
        </div>

        <p className="admin-muted">
          محصول، تنوع‌ها، موجودی و تصاویر آن را در یک فرآیند مدیریت کنید.
        </p>
      </header>

      <CreateProductForm
        categories={categories}
        collections={collections}
        initialBrands={brands}
        initialAttributes={attributes}
        canUpdateProduct={
          canUpdateProduct
        }
      />
    </section>
  );
}
