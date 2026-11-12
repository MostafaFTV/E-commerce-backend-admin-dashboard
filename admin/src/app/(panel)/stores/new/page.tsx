import {
  notFound,
  redirect,
} from "next/navigation";

import {
  StoreForm,
} from "@/components/StoreForm";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

export default async function NewStorePage() {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "stores.create",
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
            سایت / شعب و فروشگاه‌ها
          </p>

          <h1>
            افزودن شعبه
          </h1>
        </div>

        <p
          className="admin-muted"
        >
          شعبه‌ای برای نمایش در صفحه تماس با ما و نقشه ایجاد کنید.
        </p>
      </header>

      <StoreForm />
    </section>
  );
}