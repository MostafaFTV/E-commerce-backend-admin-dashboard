"use client";
import { useState } from "react";
import { AdminSelect } from "@/components/AdminSelect";
import { CashbackMultiPicker } from "@/components/CashbackMultiPicker";
import { CashbackJalaliDateTime } from "@/components/CashbackJalaliDateTime";
import { useRouter } from "next/navigation";
import type { CashbackRule } from "@/lib/admin-cashback";
import styles from "@/app/(panel)/cashback/cashback.module.css";

type Form = {name:string; scope:string; percentage:string; priority:string; expires_after_days:string; starts_at:string; ends_at:string; category_ids:string; product_ids:string; is_active:boolean};
const blank:Form = {name:"",scope:"all",percentage:"5",priority:"0",expires_after_days:"90",starts_at:"",ends_at:"",category_ids:"",product_ids:"",is_active:true};
function ids(value:string):number[] {return [...new Set(value.split(/[,\s]+/).filter(Boolean).map(Number))];}
function initialForm(rule?:CashbackRule):Form {
  if(!rule) return {...blank};
  return {name:rule.name ?? "",scope:rule.scope ?? "all",percentage:String(rule.percentage ?? rule.cashback_percentage ?? 0),priority:String(rule.priority ?? 0),expires_after_days:String(rule.expires_after_days ?? 90),starts_at:(rule.starts_at ?? "").slice(0,16),ends_at:(rule.ends_at ?? "").slice(0,16),category_ids:(rule.category_ids ?? rule.categories?.map(x=>x.id) ?? []).join(", "),product_ids:(rule.product_ids ?? rule.products?.map(x=>x.id) ?? []).join(", "),is_active:rule.is_active ?? false};
}
function errorMessage(payload:unknown) {
  if (!payload || typeof payload !== "object") return "عملیات انجام نشد.";
  const p=payload as {message?:string;errors?:Record<string,string[]>};
  return Object.values(p.errors ?? {}).flat()[0] ?? p.message ?? "عملیات انجام نشد.";
}
export function CashbackManager({initialRules,report,canManage}:{initialRules:CashbackRule[];report:unknown;canManage:boolean}) {
  const router=useRouter();
  const [editing,setEditing]=useState<number|null|"new">(null);
  const [form,setForm]=useState<Form>({...blank});
  const [busy,setBusy]=useState(false);
  const [message,setMessage]=useState<string|null>(null);
  function edit(rule?:CashbackRule){setMessage(null);setEditing(rule?.id ?? "new");setForm(initialForm(rule));}
  function change<K extends keyof Form>(key:K,value:Form[K]){setForm(prev=>({...prev,[key]:value}));}
  async function submit(e:React.FormEvent<HTMLFormElement>){
    e.preventDefault();setMessage(null);
    const percent=Number(form.percentage),priority=Number(form.priority),days=Number(form.expires_after_days);
    if(!Number.isFinite(percent)||percent<0||percent>100||!Number.isInteger(priority)||!Number.isInteger(days)||days<1){setMessage("مقادیر درصد، اولویت یا مدت اعتبار معتبر نیستند.");return;}
    const category_ids=ids(form.category_ids),product_ids=ids(form.product_ids);
    if([...category_ids,...product_ids].some(v=>!Number.isSafeInteger(v)||v<=0)){setMessage("شناسه محصولات و دسته‌بندی‌ها باید عدد صحیح مثبت باشند.");return;}
    const payload={name:form.name.trim(),scope:form.scope,percentage:percent,priority,expires_after_days:days,is_active:form.is_active,starts_at:form.starts_at||null,ends_at:form.ends_at||null,category_ids:form.scope==="categories"?category_ids:[],product_ids:form.scope==="products"?product_ids:[]};
    setBusy(true);
    try {
      const response=await fetch(editing==="new"?"/api/cashback":`/api/cashback/${editing}`,{method:editing==="new"?"POST":"PUT",headers:{"Content-Type":"application/json"},body:JSON.stringify(payload)});
      const result=await response.json().catch(()=>null);
      if(!response.ok)throw new Error(errorMessage(result));
      setEditing(null);setMessage("قانون ذخیره شد.");router.refresh();
    }catch(err){setMessage(err instanceof Error?err.message:"ذخیره انجام نشد.");}finally{setBusy(false);}
  }
  async function remove(rule:CashbackRule){
    if(!window.confirm(`قانون «${rule.name ?? rule.id}» حذف شود؟ برای قوانین استفاده‌شده، از غیرفعال‌سازی استفاده کنید.`))return;
    setBusy(true);setMessage(null);
    try {const res=await fetch(`/api/cashback/${rule.id}`,{method:"DELETE"});const data=await res.json().catch(()=>null);if(!res.ok)throw new Error(errorMessage(data));setMessage("قانون حذف شد.");router.refresh();}
    catch(e){setMessage(e instanceof Error?e.message:"حذف انجام نشد.");}finally{setBusy(false);}
  }
  const reportData=report && typeof report==="object" ? ("data" in report ? (report as {data:unknown}).data : report) : null;
  const financial = reportData && typeof reportData === "object"
    ? reportData as Record<string, unknown> : null;

  const groups = Array.isArray(financial?.credits_by_status)
    ? financial.credits_by_status as Array<Record<string, unknown>>
    : [];

  const transactionPage = financial?.transactions &&
    typeof financial.transactions === "object"
    ? financial.transactions as Record<string, unknown>
    : null;

  const transactions = Array.isArray(transactionPage?.data)
    ? transactionPage.data as Array<Record<string, unknown>>
    : [];

  const numeric = (value: unknown): number | null => {
    if (value === null || value === undefined || value === "") return null;
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
  };

  const field = (row: Record<string, unknown>, keys: string[]): number | null => {
    for (const key of keys) {
      const value = numeric(row[key]);
      if (value !== null) return value;
    }
    return null;
  };

  const creditCount = groups.reduce(
    (sum, row) => sum + (field(row, ["credits_count", "count", "total_count"]) ?? 0), 0
  );

  const originalTotal = groups.reduce(
    (sum, row) => sum + (field(row, ["total_original_amount", "original_amount", "total_original"]) ?? 0), 0
  );

  const remainingTotal = groups.reduce(
    (sum, row) => sum + (field(row, ["total_remaining_amount", "remaining_amount", "total_remaining"]) ?? 0), 0
  );

  const transactionCount = numeric(transactionPage?.total) ?? transactions.length;

  const fmt = (value: number) => new Intl.NumberFormat("fa-IR", {
    maximumFractionDigits: 2
  }).format(value);

  const statusText: Record<string, string> = {
    pending: "\u062f\u0631 \u0627\u0646\u062a\u0638\u0627\u0631",
    available: "\u0642\u0627\u0628\u0644 \u0645\u0635\u0631\u0641",
    exhausted: "\u0645\u0635\u0631\u0641\u200c\u0634\u062f\u0647",
    expired: "\u0645\u0646\u0642\u0636\u06cc\u200c\u0634\u062f\u0647",
    reversed: "\u0628\u0631\u06af\u0634\u062a\u200c\u062e\u0648\u0631\u062f\u0647"
  };
  return <div className={styles.content}>
    <section className={styles.card}>
  <div className={styles.sectionTitle}>
    <div>
      <h2>{"\u06af\u0632\u0627\u0631\u0634 \u0645\u0627\u0644\u06cc"}</h2>
      <span>{"\u062e\u0644\u0627\u0635\u0647 \u0627\u0639\u062a\u0628\u0627\u0631\u0647\u0627 \u0648 \u062a\u0631\u0627\u06a9\u0646\u0634\u200c\u0647\u0627"}</span>
    </div>
  </div>

  <div style={{
    display: "grid",
    gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))",
    gap: 12,
    marginBottom: 24
  }}>
    {[
      ["\u062a\u0639\u062f\u0627\u062f \u0627\u0639\u062a\u0628\u0627\u0631\u0647\u0627", fmt(creditCount)],
      ["\u06a9\u0644 \u0627\u0639\u062a\u0628\u0627\u0631 \u0635\u0627\u062f\u0631\u0634\u062f\u0647", `${fmt(originalTotal)} IRT`],
      ["\u0627\u0639\u062a\u0628\u0627\u0631 \u0628\u0627\u0642\u06cc\u200c\u0645\u0627\u0646\u062f\u0647", `${fmt(remainingTotal)} IRT`],
      ["\u062a\u0631\u0627\u06a9\u0646\u0634\u200c\u0647\u0627", fmt(transactionCount)]
    ].map(([label, value]) => (
      <div key={label} style={{
        padding: 18,
        borderRadius: 14,
        background: "#efe5ca",
        border: "1px solid rgba(54,39,28,.08)"
      }}>
        <div style={{fontSize: 11, opacity: .7, marginBottom: 12}}>{label}</div>
        <strong style={{fontSize: 20, direction: "ltr", display: "block", textAlign: "right"}}>
          {value}
        </strong>
      </div>
    ))}
  </div>

  <h3 style={{fontSize: 14, marginBottom: 14}}>
    {"\u0648\u0636\u0639\u06cc\u062a \u0627\u0639\u062a\u0628\u0627\u0631\u0647\u0627"}
  </h3>

  <div className={styles.tableWrap}>
    <table className={styles.table}>
      <thead>
        <tr>
          <th>{"\u0648\u0636\u0639\u06cc\u062a"}</th>
          <th>{"\u062a\u0639\u062f\u0627\u062f"}</th>
          <th>{"\u0645\u0628\u0644\u063a \u0627\u0648\u0644\u06cc\u0647"}</th>
          <th>{"\u0645\u0627\u0646\u062f\u0647"}</th>
        </tr>
      </thead>
      <tbody>
        {groups.map((row, index) => (
          <tr key={String(row.status ?? index)}>
            <td>{statusText[String(row.status)] ?? String(row.status ?? "-")}</td>
            <td>{fmt(field(row, ["credits_count", "count", "total_count"]) ?? 0)}</td>
            <td>{fmt(field(row, ["total_original_amount", "original_amount", "total_original"]) ?? 0)} IRT</td>
            <td>{fmt(field(row, ["total_remaining_amount", "remaining_amount", "total_remaining"]) ?? 0)} IRT</td>
          </tr>
        ))}
        {groups.length === 0 && (
          <tr>
            <td colSpan={4}>{"\u0647\u0646\u0648\u0632 \u0627\u0639\u062a\u0628\u0627\u0631\u06cc \u062b\u0628\u062a \u0646\u0634\u062f\u0647 \u0627\u0633\u062a."}</td>
          </tr>
        )}
      </tbody>
    </table>
  </div>

  <h3 style={{fontSize: 14, margin: "26px 0 14px"}}>
    {"\u0622\u062e\u0631\u06cc\u0646 \u062a\u0631\u0627\u06a9\u0646\u0634\u200c\u0647\u0627"}
  </h3>

  <div className={styles.tableWrap}>
    <table className={styles.table}>
      <thead>
        <tr>
          <th>ID</th>
          <th>{"\u0646\u0648\u0639"}</th>
          <th>{"\u0645\u0628\u0644\u063a"}</th>
          <th>{"\u062a\u0627\u0631\u06cc\u062e"}</th>
        </tr>
      </thead>
      <tbody>
        {transactions.map((row, index) => (
          <tr key={String(row.id ?? index)}>
            <td>{String(row.id ?? "-")}</td>
            <td dir="ltr">{String(row.type ?? "-")}</td>
            <td>{fmt(numeric(row.amount) ?? 0)} IRT</td>
            <td>{row.created_at ? new Date(String(row.created_at)).toLocaleString("fa-IR") : "-"}</td>
          </tr>
        ))}
        {transactions.length === 0 && (
          <tr>
            <td colSpan={4}>{"\u0647\u0646\u0648\u0632 \u062a\u0631\u0627\u06a9\u0646\u0634\u06cc \u062b\u0628\u062a \u0646\u0634\u062f\u0647 \u0627\u0633\u062a."}</td>
          </tr>
        )}
      </tbody>
    </table>
  </div>
</section>
    <section className={styles.card}>
      <div className={styles.sectionTitle}><div><h2>قوانین پاداش</h2><span>{initialRules.length} قانون در این صفحه</span></div>{canManage?<button className="admin-button admin-button-primary" type="button" onClick={()=>edit()}>ایجاد قانون</button>:null}</div>
      {message?<p role="status" className={styles.message}>{message}</p>:null}
      <div className={styles.tableWrap}><table className={styles.table}><thead><tr><th>عنوان</th><th>محدوده</th><th>درصد</th><th>اولویت</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>{initialRules.map(rule=><tr key={rule.id}><td>{rule.name||`قانون #${rule.id}`}</td><td dir="ltr">{rule.scope}</td><td>{String(rule.percentage ?? rule.cashback_percentage ?? "—")}%</td><td>{rule.priority ?? 0}</td><td>{rule.is_active?"فعال":"غیرفعال"}</td><td>{canManage?<div className={styles.actions}><button disabled={busy} onClick={()=>edit(rule)} type="button">ویرایش</button><button disabled={busy} onClick={()=>remove(rule)} type="button">حذف</button></div>:"فقط مشاهده"}</td></tr>)}{initialRules.length===0?<tr><td colSpan={6}>هنوز قانونی تعریف نشده است.</td></tr>:null}</tbody></table></div>
    </section>
    {canManage&&editing!==null?<section className={styles.card}><div className={styles.sectionTitle}><h2>{editing==="new"?"ایجاد قانون":"ویرایش قانون"}</h2><button type="button" onClick={()=>setEditing(null)}>بستن</button></div><form onSubmit={submit} className={styles.form}>
      <label>نام قانون<input required maxLength={150} value={form.name} onChange={e=>change("name",e.target.value)}/></label>
      <label>محدوده<AdminSelect
  dir="rtl"
  value={form.scope}
  onChange={e => change("scope", e.target.value)}
>
  <option value="all">تمام محصولات</option>
  <option value="categories">دسته‌بندی‌های مشخص</option>
  <option value="products">محصولات مشخص</option>
</AdminSelect></label>
      <label>درصد پاداش<input type="number" min="0" max="100" step="0.01" required value={form.percentage} onChange={e=>change("percentage",e.target.value)}/></label>
      <label>
  <span title="عدد بالاتر یعنی اولویت بیشتر. اگر چند قانون معتبر باشند، قانون با اولویت بالاتر اعمال می‌شود.">
    اولویت اعمال قانون ⓘ
  </span>
  <input
    type="number"
    step="1"
    required
    value={form.priority}
    onChange={e => change("priority", e.target.value)}
  />
</label>
      <label>اعتبار (روز)<input type="number" min="1" step="1" required value={form.expires_after_days} onChange={e=>change("expires_after_days",e.target.value)}/></label>
      <label>
  شروع
  <CashbackJalaliDateTime
    value={form.starts_at}
    onChange={value => change("starts_at", value)}
    disabled={busy}
  />
</label>
      <label>
  پایان
  <CashbackJalaliDateTime
    value={form.ends_at}
    onChange={value => change("ends_at", value)}
    disabled={busy}
  />
</label>
      {form.scope === "categories" ? (
  <CashbackMultiPicker
    kind="categories"
    value={form.category_ids}
    onChange={value => change("category_ids", value)}
    disabled={busy}
  />
) : null}
      {form.scope === "products" ? (
  <CashbackMultiPicker
    kind="products"
    value={form.product_ids}
    onChange={value => change("product_ids", value)}
    disabled={busy}
  />
) : null}
      <label className={styles.check}><input type="checkbox" checked={form.is_active} onChange={e=>change("is_active",e.target.checked)}/> فعال</label>
      <div className={styles.wide}><button className="admin-button admin-button-primary" disabled={busy} type="submit">{busy?"در حال ذخیره...":"ذخیره قانون"}</button></div>
    </form></section>:null}
  </div>;
}
