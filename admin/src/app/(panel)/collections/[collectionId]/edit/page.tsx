import {
  notFound,
  redirect,
} from "next/navigation";

import {
  CollectionForm,
} from "@/components/CollectionForm";

import {
  AdminCollectionsRequestError,
  getAdminCollection,
  getAdminCollectionProductOptions,
} from "@/lib/admin-collections";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

interface EditCollectionPageProps {
  params:
    Promise<{
      collectionId:
        string;
    }>;
}

export default async function EditCollectionPage({
  params,
}: EditCollectionPageProps) {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "collections.update",
    ) ||
    !admin.permissions.includes(
      "collections.view",
    )
  ) {
    notFound();
  }

  const {
    collectionId,
  } =
    await params;

  const id =
    Number(
      collectionId,
    );

  if (
    !Number.isInteger(
      id,
    ) ||
    id <= 0
  ) {
    notFound();
  }

  const canManageProducts =
    admin.permissions.includes(
      "products.view",
    );

  try {
    const [
      collection,
      products,
    ] =
      await Promise.all([
        getAdminCollection(
          id,
        ),

        canManageProducts
          ? getAdminCollectionProductOptions()
          : Promise.resolve([]),
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
              ویرایش مجموعه
            </h1>
          </div>

          <p className="admin-muted">
            ترجمه‌ها، محصولات مجموعه، ترتیب نمایش و وضعیت آن را ویرایش کنید.
          </p>
        </header>

        <CollectionForm
          products={
            products
          }
          initialCollection={
            collection
          }
          canManageProducts={
            canManageProducts
          }
        />
      </section>
    );
  } catch (
    error
  ) {
    if (
      error instanceof
        AdminCollectionsRequestError &&
      error.status ===
        404
    ) {
      notFound();
    }

    throw error;
  }
}