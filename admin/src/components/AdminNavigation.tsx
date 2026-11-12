"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

import {
  filterNavigation,
} from "@/lib/admin-navigation";

interface AdminNavigationProps {
  permissions: string[];
}

export function AdminNavigation({
  permissions,
}: AdminNavigationProps) {
  const pathname =
    usePathname();

  const groups =
    filterNavigation(
      permissions,
    );

  return (
    <nav
      className="admin-nav"
      aria-label="ناوبری پنل مدیریت"
    >
      {groups.map((group) => (
        <div
          key={group.label}
          className="admin-nav-group"
        >
          <p className="admin-nav-group-label">
            {group.label}
          </p>

          <div className="admin-nav-items">
            {group.items.map(
              (item) => {
                const isActive =
                  pathname === item.href ||
                  pathname.startsWith(
                    `${item.href}/`,
                  );

                return (
                  <Link
                    key={item.href}
                    href={item.href}
                    className={
                      isActive
                        ? "admin-nav-link admin-nav-link-active"
                        : "admin-nav-link"
                    }
                  >
                    {item.label}
                  </Link>
                );
              },
            )}
          </div>
        </div>
      ))}
    </nav>
  );
}
