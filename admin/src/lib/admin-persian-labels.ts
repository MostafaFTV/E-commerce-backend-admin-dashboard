const statusLabels: Record<string, string> = {
  new: "جدید",
  read: "خوانده‌شده",
  resolved: "رسیدگی‌شده",
  active: "فعال",
  inactive: "غیرفعال",
  suspended: "تعلیق‌شده",
  pending_payment: "در انتظار پرداخت",
  paid: "پرداخت‌شده",
  processing: "در حال پردازش",
  shipped: "ارسال‌شده",
  delivered: "تحویل‌شده",
  cancelled: "لغوشده",
  returned: "مرجوع‌شده",
};

const permissionGroups: Record<string, string> = {
  users: "کاربران پنل",
  roles: "نقش‌ها",
  permissions: "مجوزها",
  categories: "دسته‌بندی‌ها",
  products: "محصولات",
  collections: "کالکشن‌ها",
  inventory: "موجودی انبار",
  orders: "سفارش‌ها",
  customers: "مشتریان",
  cms: "محتوا",
  menus: "منوها",
  homepage: "صفحه اصلی",
  stores: "شعب",
  activity_logs: "تاریخچه فعالیت‌ها",
  shipping_methods: "روش‌های ارسال",
  general: "عمومی",
};

const permissionActions: Record<string, string> = {
  view: "مشاهده",
  create: "ایجاد",
  update: "ویرایش",
  delete: "حذف",
  manage: "مدیریت",
  update_status: "تغییر وضعیت",
  cancel: "لغو",
  return: "مرجوع کردن",
};

export function adminStatusLabel(value: string): string {
  return statusLabels[value] ?? value;
}

export function adminPermissionGroup(value: string): string {
  const group = value.split(".")[0] || "general";
  return permissionGroups[group] ?? group;
}

export function adminPermissionLabel(
  slug: string,
  fallback: string,
): string {
  const [group, action] = slug.split(".");

  return permissionGroups[group] && permissionActions[action]
    ? permissionActions[action] + " " + permissionGroups[group]
    : fallback;
}

export function adminRoleLabel(
  slug: string,
  fallback: string,
): string {
  const names: Record<string, string> = {
    super_admin: "مدیر ارشد",
    admin: "مدیر",
    accountant: "حسابدار",
    support: "پشتیبانی",
    customer: "مشتری",
  };

  return names[slug] ?? fallback;
}
