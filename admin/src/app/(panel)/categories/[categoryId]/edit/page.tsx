import {
  notFound,
  redirect,
} from "next/navigation";

import {
  CategoryForm,
} from "@/components/CategoryForm";

import {
  AdminCategoriesRequestError,
  getAdminCategories,
  getAdminCategory,
} from "@/lib/admin-categories";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

interface EditCategoryPageProps {
  params:
    Promise<{
      categoryId:
        string;
    }>;
}

export default async function EditCategoryPage({
  params,
}: EditCategoryPageProps) {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "categories.update",
    ) ||
    !admin.permissions.includes(
      "categories.view",
    )
  ) {
    notFound();
  }

  const {
    categoryId,
  } =
    await params;

  const id =
    Number(
      categoryId,
    );

  if (
    !Number.isInteger(
      id,
    ) ||
    id <= 0
  ) {
    notFound();
  }

  try {
    const [
      category,
      categories,
    ] =
      await Promise.all([
        getAdminCategory(
          id,
        ),

        getAdminCategories(),
      ]);

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
              ویرایش دسته‌بندی
            </h1>
          </div>

          <p className="admin-muted">
            ترجمه‌ها، ساختار والد و فرزند،
            و وضعیت نمایش دسته‌بندی را ویرایش کنید.
          </p>
        </header>

        <CategoryForm
          categories={
            categories
          }
          initialCategory={
            category
          }
        />
      </section>
    );
  } catch (
    error
  ) {
    if (
      error instanceof
        AdminCategoriesRequestError &&
      error.status ===
        404
    ) {
      notFound();
    }

    throw error;
  }
}