import {
  notFound,
  redirect,
} from "next/navigation";

import {
  StaffForm,
} from "@/components/StaffForm";

import {
  AdminStaffRequestError,
  getAdminStaffUser,
} from "@/lib/admin-staff";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

interface EditStaffPageProps {
  params:
    Promise<{
      userId:
        string;
    }>;
}

export default async function EditStaffPage({
  params,
}: EditStaffPageProps) {
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

  const {
    userId,
  } =
    await params;

  const id =
    Number(
      userId,
    );

  if (
    !Number.isInteger(
      id,
    ) ||
    id <= 0
  ) {
    notFound();
  }

  let user;

  try {
    user =
      await getAdminStaffUser(
        id,
      );
  } catch (error) {
    if (
      error instanceof
        AdminStaffRequestError
    ) {
      if (
        error.status ===
          401
      ) {
        redirect(
          "/login",
        );
      }

      if (
        error.status ===
          404
      ) {
        notFound();
      }
    }

    throw error;
  }

  const isSelf =
    user.id ===
    admin.id;

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
            ویرایش کاربر پنل
          </h1>
        </div>

        <p
          className="admin-muted"
        >
          {user.first_name}{" "}
          {user.last_name}
        </p>
      </header>

      <StaffForm
        initialUser={
          user
        }
        isSelf={
          isSelf
        }
      />
    </section>
  );
}