import styles from "./collections.module.css";

export default function CollectionsLoading() {
  return (
    <section
      className="admin-dashboard"
      dir="rtl"
      lang="fa"
      aria-live="polite"
    >
      <div
        className={styles.state}
        role="status"
      >
        <strong>
          در حال بارگذاری مجموعه‌ها...
        </strong>
      </div>
    </section>
  );
}
