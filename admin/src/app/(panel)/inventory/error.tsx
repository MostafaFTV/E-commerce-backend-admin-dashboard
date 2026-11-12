"use client";

import styles from "./inventory.module.css";

interface InventoryErrorProps {
  reset: () => void;
}

export default function InventoryError({
  reset,
}: InventoryErrorProps) {
  return (
    <section
      className={`admin-dashboard ${styles.page}`}
      dir="rtl"
      lang="fa"
    >
      <div
        className={styles.empty}
        role="alert"
      >
        <strong>
          بارگذاری اطلاعات موجودی با خطا روبه‌رو شد.
        </strong>

        <p>
          لطفاً دوباره تلاش کنید.
        </p>

        <button
          type="button"
          className="admin-button admin-button-primary"
          onClick={reset}
        >
          تلاش دوباره
        </button>
      </div>
    </section>
  );
}
