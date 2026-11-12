import {
  notFound,
  redirect,
} from "next/navigation";

import {
  CategoryForm,
} from "@/components/CategoryForm";

import {
  getAdminCategories,
} from "@/lib/admin-categories";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

export default async function NewCategoryPage() {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "categories.create",
    )
  ) {
    notFound();
  }

  const categories =
    admin.permissions.includes(
      "categories.view",
    )
      ? await getAdminCategories()
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
            ایجاد دسته‌بندی
          </h1>
        </div>

        <p className="admin-muted">
          دسته‌ای مستقل بسازید یا آن را
          زیرمجموعه دسته‌ای دیگر قرار دهید.
        </p>
      </header>

      <CategoryForm
        categories={
          categories
        }
      />
    </section>
  );
}