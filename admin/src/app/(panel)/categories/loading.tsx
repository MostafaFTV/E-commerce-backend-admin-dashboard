import styles from "./categories.module.css";

export default function CategoriesLoading() {
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
          در حال بارگذاری دسته‌بندی‌ها...
        </strong>
      </div>
    </section>
  );
}
