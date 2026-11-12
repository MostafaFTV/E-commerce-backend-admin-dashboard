import {
  notFound,
  redirect,
} from "next/navigation";

import {
  StoreForm,
} from "@/components/StoreForm";

import {
  AdminStoreRequestError,
  getAdminStore,
} from "@/lib/admin-stores";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

interface Props {
  params:
    Promise<{
      storeId:
        string;
    }>;
}

export default async function EditStorePage({
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
      "stores.update",
    )
  ) {
    notFound();
  }

  const {
    storeId,
  } =
    await params;

  const id =
    Number(
      storeId,
    );

  if (
    !Number.isInteger(
      id,
    ) ||
    id <= 0
  ) {
    notFound();
  }

  let store;

  try {
    store =
      await getAdminStore(
        id,
      );
  } catch (error) {
    if (
      error instanceof
        AdminStoreRequestError
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
            سایت / شعب و فروشگاه‌ها
          </p>

          <h1>
            ویرایش شعبه
          </h1>
        </div>

        <p
          className="admin-muted"
          dir="auto"
        >
          {store.name_en ||
            store.name}
        </p>
      </header>

      <StoreForm
        initialStore={
          store
        }
      />
    </section>
  );
}