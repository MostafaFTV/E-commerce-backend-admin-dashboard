import {
  notFound,
  redirect,
} from "next/navigation";

import {
  ContactContentEditor,
} from "@/components/ContactContentEditor";

import {
  AdminCmsContentRequestError,
  getAdminContactPageContent,
} from "@/lib/admin-cms-content";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

export default async function ContactContentPage() {
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
      await getAdminContactPageContent();
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
            محتوای تماس با ما
          </h1>
        </div>

        <p
          className="admin-muted"
        >
          محتوای بخش تماس با ما را در چیدمان فعلی سایت ویرایش کنید.
        </p>
      </header>

      <ContactContentEditor
        initialContent={
          content
        }
      />
    </section>
  );
}