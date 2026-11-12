export interface AdminNavItem {
  label: string;
  href: string;
  permission?: string;
}

export interface AdminNavGroup {
  label: string;
  items: AdminNavItem[];
}

export const adminNavigation: AdminNavGroup[] = [
  {
    label: "نمای کلی",
    items: [
      {
        label: "داشبورد",
        href: "/dashboard",
      },
    ],
  },

  {
    label: "کاتالوگ",
    items: [
      {
        label: "محصولات",
        href: "/products",
        permission: "products.view",
      },
      {
        label: "دسته‌بندی‌ها",
        href: "/categories",
        permission: "categories.view",
      },
      {
        label: "کالکشن‌ها",
        href: "/collections",
        permission: "collections.view",
      },
      {
        label: "موجودی انبار",
        href: "/inventory",
        permission: "inventory.view",
      },
    ],
  },

  {
    label: "فروش",
    items: [
      {
        label: "سفارش‌ها",
        href: "/orders",
        permission: "orders.view",
      },
      {
        label: "کش‌بک و کیف پول",
        href: "/cashback",
        permission: "orders.view",
      },
      {
        label: "مشتریان",
        href: "/customers",
        permission: "customers.view",
      },
      {
        label: "کدهای تخفیف مشتریان",
        href: "/discount-codes",
        permission: "customers.view",
      },
      {
        label: "پیام‌های تماس",
        href: "/contact-messages",
        permission: "contact_messages.view",
      },
      {
        label: "تیکت‌های پشتیبانی",
        href: "/support-tickets",
        permission: "contact_messages.view",
      },
    ],
  },

  {
    label: "محتوا",
    items: [
      {
        label: "صفحه اصلی",
        href: "/homepage",
        permission: "homepage.manage",
      },
      {
        label: "محتوای تماس با ما",
        href: "/contact-content",
        permission: "cms.manage",
      },
      {
        label: "پرسش‌های متداول",
        href: "/faqs",
        permission: "cms.manage",
      },
      {
        label: "منوها",
        href: "/menus",
        permission: "menus.manage",
      },
      {
        label: "صفحات",
        href: "/pages",
        permission: "cms.manage",
      },
      {
        label: "بنرها",
        href: "/banners",
        permission: "cms.manage",
      },
    ],
  },

  {
    label: "عملیات",
    items: [
      {
        label: "شعب و فروشگاه‌ها",
        href: "/stores",
        permission: "stores.view",
      },
      {
        label: "روش‌های ارسال",
        href: "/shipping-methods",
        permission: "shipping_methods.view",
      },
    ],
  },

  {
    label: "مدیریت سیستم",
    items: [
      {
        label: "کاربران پنل",
        href: "/staff",
        permission: "users.view",
      },
      {
        label: "نقش‌ها",
        href: "/roles",
        permission: "roles.manage",
      },
      {
        label: "مجوزهای دسترسی",
        href: "/permissions",
        permission: "permissions.manage",
      },
      {
        label: "تاریخچه فعالیت‌ها",
        href: "/activity-logs",
        permission: "activity_logs.view",
      },

    ],
  },
];

export function filterNavigation(
  permissions: string[],
): AdminNavGroup[] {
  const permissionSet =
    new Set(permissions);

  return adminNavigation
    .map((group) => ({
      ...group,

      items: group.items.filter(
        (item) =>
          !item.permission ||
          permissionSet.has(
            item.permission,
          ),
      ),
    }))
    .filter(
      (group) =>
        group.items.length > 0,
    );
}
