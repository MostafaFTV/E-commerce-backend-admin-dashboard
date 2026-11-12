import styles from "./inventory.module.css";

export default function InventoryLoading() {
  return (
    <section
      className={`admin-dashboard ${styles.page}`}
      dir="rtl"
      lang="fa"
      aria-live="polite"
    >
      <div
        className={styles.empty}
        role="status"
      >
        در حال بارگذاری موجودی انبار...
      </div>
    </section>
  );
}
