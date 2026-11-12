import {
  notFound,
  redirect,
} from "next/navigation";

import {
  FaqContentEditor,
} from "@/components/FaqContentEditor";

import {
  AdminCmsContentRequestError,
  getAdminFaqContent,
} from "@/lib/admin-cms-content";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

export default async function FaqPage() {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect(
      "/login",
    );
  }

  if (
    !admin.permissions.includes(
      "cms.manage",
    )
  ) {
    notFound();
  }

  let content;

  try {
    content =
      await getAdminFaqContent();
  } catch (error) {
    if (
      error instanceof
        AdminCmsContentRequestError
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
            محتوا
          </p>

          <h1>
            پرسش‌های متداول
          </h1>
        </div>

        <p
          className="admin-muted"
        >
          گروه‌ها و پرسش‌های متداول فارسی و انگلیسی را مدیریت کنید.
        </p>
      </header>

      <FaqContentEditor
        initialContent={
          content
        }
      />
    </section>
  );
}