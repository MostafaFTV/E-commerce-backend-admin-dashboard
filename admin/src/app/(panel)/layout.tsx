import {
  redirect,
} from "next/navigation";

import {
  AdminBackButton,
} from "@/components/AdminBackButton";
import {
  AdminNavigation,
} from "@/components/AdminNavigation";
import {
  AdminNotificationCenter,
} from "@/components/AdminNotificationCenter";
import {
  AdminResponsiveShell,
} from "@/components/AdminResponsiveShell";
import {
  LogoutButton,
} from "@/components/LogoutButton";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

export default async function PanelLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect("/login");
  }

  // Presentation labels only; backend role identities and permissions stay unchanged.
  const localizedRoleNames: Record<string, string> = {
    "Super Admin": "مدیر ارشد",
    Admin: "مدیر",
    Accountant: "حسابدار",
    Support: "پشتیبانی",
  };

  const roleNames =
    admin.roles
      .map(
        (role) =>
          localizedRoleNames[role.name] ?? role.name,
      )
      .join(", ");

  const sidebar = (
    <>
      <div className="admin-sidebar-brand">
        <p className="admin-eyebrow">
          TKB
        </p>

        <h2 className="admin-sidebar-title">
          پنل مدیریت
        </h2>
      </div>

      <AdminNavigation
        permissions={
          admin.permissions
        }
      />

      <div className="admin-sidebar-footer">
        <div>
          <strong className="admin-user-name">
            {admin.first_name}{" "}
            {admin.last_name}
          </strong>

          <span className="admin-user-role">
            {roleNames}
          </span>
        </div>

        <LogoutButton />
      </div>
    </>
  );

  return (
    <AdminResponsiveShell
      sidebar={sidebar}
    >
      <div className="admin-panel-toolbar">
        <AdminBackButton />

        <AdminNotificationCenter
          enabled={
            admin.permissions.some((permission) =>
              [
                "contact_messages.view",
                "orders.view",
                "inventory.view",
              ].includes(permission),
            )
          }
          viewAllHref={
            admin.permissions.includes("contact_messages.view")
              ? "/contact-messages?status=new"
              : admin.permissions.includes("orders.view")
                ? "/orders?status=pending_payment"
                : "/inventory"
          }
        />
      </div>

      {children}
    </AdminResponsiveShell>
  );
}
