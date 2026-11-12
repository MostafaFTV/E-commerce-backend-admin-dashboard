import { redirect } from "next/navigation";

import { LoginForm } from "@/components/LoginForm";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

export default async function LoginPage() {
  const admin =
    await getCurrentAdmin();

  if (admin) {
    redirect(
      "/dashboard",
    );
  }

  return (
    <main className="admin-auth-page">
      <section className="admin-auth-card">
        <header className="admin-auth-header">
          <p className="admin-eyebrow">
            The Kitchen Boutique
          </p>

          <h1>
            پنل مدیریت
          </h1>

          <p className="admin-muted">
            با حساب کاربری پنل وارد شوید.
          </p>
        </header>

        <LoginForm />
      </section>
    </main>
  );
}
