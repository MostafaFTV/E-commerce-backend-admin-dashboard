import {
  notFound,
  redirect,
} from "next/navigation";

import {
  RoleForm,
} from "@/components/RoleForm";

import {
  getAllAdminPermissions,
} from "@/lib/admin-access-control";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

export default async function NewRolePage() {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect("/login");
  }

  if (
    !admin.permissions.includes(
      "roles.manage",
    )
  ) {
    notFound();
  }

  const permissions =
    await getAllAdminPermissions();

  return (
    <section className="admin-dashboard">
      <header className="admin-page-header">
        <div>
          <p className="admin-eyebrow">
            مدیریت سیستم / نقش‌ها
          </p>

          <h1>
            ایجاد نقش
          </h1>
        </div>
      </header>

      <RoleForm
        permissions={
          permissions
        }
      />
    </section>
  );
}