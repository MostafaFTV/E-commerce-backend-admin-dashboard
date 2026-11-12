import {
  notFound,
  redirect,
} from "next/navigation";

import {
  StaffForm,
} from "@/components/StaffForm";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

export default async function NewStaffPage() {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "users.manage",
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
            مدیریت سیستم / کاربران پنل
          </p>

          <h1>
            افزودن کاربر پنل
          </h1>
        </div>

        <p
          className="admin-muted"
        >
          حساب کاربر پنل را ایجاد و نقش او را مشخص کنید.
        </p>
      </header>

      <StaffForm />
    </section>
  );
}