export const SYSTEM_ROLE_SLUGS = [
  "super_admin",
  "admin",
  "accountant",
  "support",
  "customer",
] as const;

export const SYSTEM_PERMISSION_SLUGS = [
  "users.view",
  "users.manage",
  "roles.manage",
  "permissions.manage",

  "categories.view",
  "categories.create",
  "categories.update",
  "categories.delete",

  "products.view",
  "products.create",
  "products.update",
  "products.delete",

  "collections.view",
  "collections.create",
  "collections.update",
  "collections.delete",

  "inventory.view",
  "inventory.manage",

  "orders.view",
  "orders.manage",
  "orders.update_status",
  "orders.cancel",
  "orders.return",

  "customers.view",
  "customers.manage",

  "cms.manage",
  "menus.manage",
  "homepage.manage",

  "stores.view",
  "stores.create",
  "stores.update",
  "stores.delete",

  "activity_logs.view",

  "shipping_methods.view",
  "shipping_methods.create",
  "shipping_methods.update",
  "shipping_methods.delete",
] as const;

export function isSystemRoleSlug(
  slug: string,
): boolean {
  return (
    SYSTEM_ROLE_SLUGS as
      readonly string[]
  ).includes(slug);
}

export function isSystemPermissionSlug(
  slug: string,
): boolean {
  return (
    SYSTEM_PERMISSION_SLUGS as
      readonly string[]
  ).includes(slug);
}