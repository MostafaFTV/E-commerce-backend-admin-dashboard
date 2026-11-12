import { notFound, redirect } from "next/navigation";

import { DiscountCodeManager } from "@/components/DiscountCodeManager";
import { getCurrentAdmin } from "@/lib/admin-server";

export default async function DiscountCodesPage() {
  const admin = await getCurrentAdmin();
  if (!admin) redirect("/login");
  if (!admin.permissions.includes("customers.view")) notFound();

  return (
    <main className="admin-dashboard" dir="rtl" lang="fa">
      <header className="admin-page-header">
        <div>
          <p className="admin-eyebrow">مدیریت مشتریان</p>
          <h1>کدهای تخفیف مشتریان</h1>
        </div>
        <p className="admin-muted">
          کدهای اختصاصی و اطلاع‌رسانی تخفیف مشتریان را مدیریت کنید.
        </p>
      </header>
      <DiscountCodeManager
        canManage={admin.permissions.includes("customers.manage")}
      />
    </main>
  );
}
