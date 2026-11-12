"use client";

import styles from "./collections.module.css";

interface CollectionsErrorProps {
  reset: () => void;
}

export default function CollectionsError({
  reset,
}: CollectionsErrorProps) {
  return (
    <section
      className="admin-dashboard"
      dir="rtl"
      lang="fa"
    >
      <div
        className={styles.state}
        role="alert"
      >
        <strong>
          بارگذاری صفحه مجموعه‌ها با خطا روبه‌رو شد.
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
