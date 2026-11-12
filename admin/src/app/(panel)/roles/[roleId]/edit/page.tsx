import { adminRoleLabel } from "@/lib/admin-persian-labels";
import {
  notFound,
  redirect,
} from "next/navigation";

import {
  RoleForm,
} from "@/components/RoleForm";

import {
  AdminAccessControlRequestError,
  getAdminRole,
  getAllAdminPermissions,
} from "@/lib/admin-access-control";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

interface Props {
  params:
    Promise<{
      roleId: string;
    }>;
}

export default async function EditRolePage({
  params,
}: Props) {
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

  const {
    roleId,
  } =
    await params;

  const id =
    Number(roleId);

  if (
    !Number.isInteger(id) ||
    id <= 0
  ) {
    notFound();
  }

  let role;

  try {
    role =
      await getAdminRole(id);
  } catch (error) {
    if (
      error instanceof
        AdminAccessControlRequestError &&
      error.status === 404
    ) {
      notFound();
    }

    throw error;
  }

  if (
    role.slug ===
      "super_admin"
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
            ویرایش نقش
          </h1>
        </div>

        <p className="admin-muted">
          {adminRoleLabel(role.slug, role.name)}
        </p>
      </header>

      <RoleForm
        initialRole={
          role
        }
        permissions={
          permissions
        }
      />
    </section>
  );
}