import { getCurrentAdmin } from "@/lib/admin-server";
import { cashbackGet, extractRules } from "@/lib/admin-cashback";
import { CashbackManager } from "@/components/CashbackManager";
import styles from "./cashback.module.css";

export default async function CashbackPage() {
  const admin = await getCurrentAdmin();
  const canView = admin?.permissions.includes("orders.view") ?? false;
  const canManage = admin?.permissions.includes("orders.manage") ?? false;
  if (!canView) return <main className={styles.page}><h1>کش‌بک</h1><p>مجوز مشاهده این بخش را ندارید.</p></main>;
  let rules = [] as ReturnType<typeof extractRules>;
  let report: unknown = null;
  let error: string | null = null;
  try {
    const [rulesPayload, reportPayload] = await Promise.all([
      cashbackGet("/admin/cashback/rules"), cashbackGet("/admin/cashback/report")
    ]);
    rules = extractRules(rulesPayload);
    report = reportPayload;
  } catch (caught) { error = caught instanceof Error ? caught.message : "اطلاعات دریافت نشد."; }
  return <main className={styles.page} dir="rtl">
    <header className={styles.header}><div><p className="admin-eyebrow">مدیریت وفاداری</p><h1>قوانین کش‌بک</h1><p>تعریف پاداش خرید، محدوده اعمال و نظارت بر اعتبارهای مشتریان</p></div></header>
    {error ? <div className={styles.error} role="alert">{error}</div> : <CashbackManager initialRules={rules} report={report} canManage={canManage} />}
  </main>;
}
