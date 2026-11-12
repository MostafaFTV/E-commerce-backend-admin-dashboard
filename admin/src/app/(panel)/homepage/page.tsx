import {
  notFound,
  redirect,
} from "next/navigation";

import {
  HomepageEditor,
} from "@/components/HomepageEditor";

import {
  AdminHomepageRequestError,
  getAdminHomepageSections,
} from "@/lib/admin-homepage";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

export default async function HomepagePage() {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "homepage.manage",
    )
  ) {
    notFound();
  }

  let sections;

  try {
    sections =
      await getAdminHomepageSections();
  } catch (error) {
    if (
      error instanceof
        AdminHomepageRequestError &&
      error.status ===
        401
    ) {
      redirect(
        "/login",
      );
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
            محتوا
          </p>

          <h1>
            صفحه اصلی
          </h1>
        </div>

        <p
          className="admin-muted"
        >
          محتوای صفحه اصلی را در طراحی و چیدمان فعلی سایت ویرایش کنید.
        </p>
      </header>

      <HomepageEditor
        sections={
          sections
        }
      />
    </section>
  );
}