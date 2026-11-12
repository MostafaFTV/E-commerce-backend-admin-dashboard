import { notFound, redirect } from "next/navigation";

import {
  getCurrentAdmin,
} from "@/lib/admin-server";

interface SectionDefinition {
  title: string;
  description: string;
  permission: string;
}

const sections: Record<
  string,
  SectionDefinition
> = {
  products: {
    title: "محصولات",
    description:
      "محصولات، تنوع‌ها و اطلاعات آن‌ها را مدیریت کنید.",
    permission:
      "products.view",
  },

  categories: {
    title: "دسته‌بندی‌ها",
    description:
      "ساختار دسته‌بندی محصولات را مدیریت کنید.",
    permission:
      "categories.view",
  },

  collections: {
    title: "کالکشن‌ها",
    description:
      "کالکشن‌های محصولات را مدیریت کنید.",
    permission:
      "collections.view",
  },

  inventory: {
    title: "موجودی انبار",
    description:
      "موجودی انبار را بررسی و مدیریت کنید.",
    permission:
      "inventory.view",
  },

  orders: {
    title: "سفارش‌ها",
    description:
      "سفارش‌های مشتریان را بررسی و مدیریت کنید.",
    permission:
      "orders.view",
  },

  customers: {
    title: "مشتریان",
    description:
      "حساب مشتریان را بررسی کنید.",
    permission:
      "customers.view",
  },

  homepage: {
    title: "صفحه اصلی",
    description:
      "محتوا و بخش‌های صفحه اصلی را مدیریت کنید.",
    permission:
      "homepage.manage",
  },

  menus: {
    title: "منوها",
    description:
      "منوهای سایت را مدیریت کنید.",
    permission:
      "menus.manage",
  },

  pages: {
    title: "صفحات",
    description:
      "صفحات محتوایی سایت را مدیریت کنید.",
    permission:
      "cms.manage",
  },

  banners: {
    title: "بنرها",
    description:
      "بنرهای سایت را مدیریت کنید.",
    permission:
      "cms.manage",
  },

  stores: {
    title: "شعب و فروشگاه‌ها",
    description:
      "موقعیت شعب را مدیریت کنید.",
    permission:
      "stores.view",
  },

  "shipping-methods": {
    title: "روش‌های ارسال",
    description:
      "روش‌های ارسال را مدیریت کنید.",
    permission:
      "shipping_methods.view",
  },

  staff: {
    title: "کاربران پنل",
    description:
      "حساب کاربران پنل را مدیریت کنید.",
    permission:
      "users.view",
  },

  roles: {
    title: "نقش‌ها",
    description:
      "نقش‌های پنل را مدیریت کنید.",
    permission:
      "roles.manage",
  },

  permissions: {
    title: "مجوزهای دسترسی",
    description:
      "مجوزهای سیستم را مدیریت کنید.",
    permission:
      "permissions.manage",
  },

  "activity-logs": {
    title: "تاریخچه فعالیت‌ها",
    description:
      "تاریخچه فعالیت‌های پنل را بررسی کنید.",
    permission:
      "activity_logs.view",
  },

};

export default async function SectionPage({
  params,
}: {
  params: Promise<{
    section: string;
  }>;
}) {
  const {
    section,
  } = await params;

  const definition =
    sections[section];

  if (!definition) {
    notFound();
  }

  const admin =
    await getCurrentAdmin();

  if (!admin) {
    redirect("/login");
  }

  if (
    !admin.permissions.includes(
      definition.permission,
    )
  ) {
    notFound();
  }

  return (
    <section className="admin-dashboard">
      <header className="admin-page-header">
        <div>
          <p className="admin-eyebrow">
            مدیریت سیستم
          </p>

          <h1>
            {definition.title}
          </h1>
        </div>

        <p className="admin-muted">
          {definition.description}
        </p>
      </header>

      <div className="admin-placeholder-card">
        <p className="admin-eyebrow">
          بخش
        </p>

        <h2>
          {definition.title}
        </h2>

        <p className="admin-muted">
          این بخش برای اتصال به بک‌اند آماده است.
        </p>
      </div>
    </section>
  );
}
