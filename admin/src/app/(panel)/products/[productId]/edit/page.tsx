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
  AdminProductsRequestError,
  getAdminProduct,
  getAdminProductMedia,
} from "@/lib/admin-products";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

interface EditProductPageProps {
  params:
    Promise<{
      productId:
        string;
    }>;
}

export default async function EditProductPage({
  params,
}: EditProductPageProps) {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "products.view",
    ) ||
    !admin.permissions.includes(
      "products.update",
    )
  ) {
    notFound();
  }

  const {
    productId,
  } =
    await params;

  const id =
    Number(
      productId,
    );

  if (
    !Number.isInteger(id) ||
    id <= 0
  ) {
    notFound();
  }

  let product;

  try {
    product =
      await getAdminProduct(
        id,
      );
  } catch (error) {
    if (
      error instanceof
        AdminProductsRequestError &&
      error.status === 404
    ) {
      notFound();
    }

    if (
      error instanceof
        AdminProductsRequestError &&
      error.status === 401
    ) {
      redirect(
        "/login",
      );
    }

    throw error;
  }

  const [
    categories,
    collections,
    initialBrands,
    initialAttributes,
    initialMedia,
  ] =
    await Promise.all([
      getAdminCategoryOptions(),

      admin.permissions.includes(
        "collections.view",
      )
        ? getAdminCollectionOptions()
        : Promise.resolve([]),

      getAdminBrandOptions(),

      getAdminAttributeOptions(),

      getAdminProductMedia(
        product.id,
      ),
    ]);

  return (
    <section
      dir="rtl"
      lang="fa"
      className="admin-dashboard"
    >
      <header
        className="admin-page-header"
      >
        <div>
          <p
            className="admin-eyebrow"
          >
            کاتالوگ
          </p>

          <h1>
            ویرایش محصول
          </h1>
        </div>

        <p
          className="admin-muted"
        >
          ویرایش محصول شماره {product.id}.
          تغییرات روی همین محصول ذخیره می‌شوند.
        </p>
      </header>

      <CreateProductForm
        categories={
          categories
        }
        collections={
          collections
        }
        initialBrands={
          initialBrands
        }
        initialAttributes={
          initialAttributes
        }
        initialProduct={
          product
        }
        initialMedia={
          initialMedia
        }
        canUpdateProduct={
          true
        }
      />
    </section>
  );
}