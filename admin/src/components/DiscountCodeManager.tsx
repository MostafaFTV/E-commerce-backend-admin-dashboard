"use client";

import { useCallback, useEffect, useState, type FormEvent } from "react";

import styles from "@/app/(panel)/discount-codes/discount-codes.module.css";
import { AdminSelect } from "@/components/AdminSelect";
import { CashbackJalaliDateTime } from "@/components/CashbackJalaliDateTime";

interface DiscountCode {
  id: number;
  code: string;
  percentage: number;
  customer_id: number;
  customer: {
    id: number;
    name: string;
    phone: string | null;
  } | null;
  scope: "all" | "category";
  category_id: number | null;
  category: {
    id: number;
    name: string;
    slug: string;
  } | null;
  expires_at: string;
  is_active: boolean;
  status: "active" | "expired" | "inactive";
  status_label: string;
  description: string | null;
  created_at: string | null;
}

interface CustomerOption {
  id: number;
  name: string;
  phone: string | null;
}

interface CategoryOption {
  id: number;
  parent_id: number | null;
  name: string;
  depth: number;
}

interface CodeForm {
  code: string;
  percentage: string;
  customer_id: string;
  scope: "all" | "category";
  category_id: string;
  expires_at: string;
  is_active: boolean;
  description: string;
}

interface CodesResponse {
  data: DiscountCode[];
  meta: {
    current_page: number;
    last_page: number;
    total: number;
  };
}

interface OptionsResponse {
  data: {
    customers: CustomerOption[];
    categories: CategoryOption[];
  };
}

const blankForm: CodeForm = {
  code: "",
  percentage: "10",
  customer_id: "",
  scope: "all",
  category_id: "",
  expires_at: "",
  is_active: true,
  description: "",
};

function messageFrom(payload: unknown): string {
  if (!payload || typeof payload !== "object") {
    return "درخواست انجام نشد.";
  }

  const data = payload as {
    message?: string;
    errors?: Record<string, string[]>;
  };
  return Object.values(data.errors ?? {}).flat()[0]
    ?? data.message
    ?? "درخواست انجام نشد.";
}

function localDateTime(value: string | null | undefined): string {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
  return date.toISOString().slice(0, 16);
}

function displayDate(value: string): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "—";
  return new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

export function DiscountCodeManager({
  canManage,
}: {
  canManage: boolean;
}) {
  const [codes, setCodes] = useState<DiscountCode[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState("");
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [scopeFilter, setScopeFilter] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [editing, setEditing] = useState<DiscountCode | null | "new">(null);
  const [form, setForm] = useState<CodeForm>({ ...blankForm });
  const [customers, setCustomers] = useState<CustomerOption[]>([]);
  const [categories, setCategories] = useState<CategoryOption[]>([]);
  const [customerSearch, setCustomerSearch] = useState("");
  const [optionsError, setOptionsError] = useState("");
  const [customersLoading, setCustomersLoading] = useState(false);
  const [activeCustomerIndex, setActiveCustomerIndex] = useState(-1);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setSearchTerm(search.trim());
      setPage(1);
    }, 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  const loadCodes = useCallback(async () => {
    setLoading(true);
    setError("");

    const params = new URLSearchParams({
      page: String(page),
      per_page: "20",
    });
    if (searchTerm) params.set("search", searchTerm);
    if (statusFilter) params.set("status", statusFilter);
    if (scopeFilter) params.set("scope", scopeFilter);

    try {
      const response = await fetch(`/api/discount-codes?${params}`, {
        cache: "no-store",
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok) throw new Error(messageFrom(payload));
      const result = payload as CodesResponse;
      setCodes(result.data ?? []);
      setLastPage(result.meta?.last_page ?? 1);
      setTotal(result.meta?.total ?? 0);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "دریافت کدها انجام نشد.");
    } finally {
      setLoading(false);
    }
  }, [page, searchTerm, statusFilter, scopeFilter]);

  useEffect(() => {
    void loadCodes();
  }, [loadCodes]);

  useEffect(() => {
    if (editing === null) return;

    const query = customerSearch.trim();
    const shouldSearchCustomers = query.length >= 2;
    const needsOptions =
      shouldSearchCustomers ||
      form.scope === "category" ||
      Boolean(form.customer_id);

    if (!needsOptions) {
      setCustomersLoading(false);
      setOptionsError("");
      return;
    }

    const controller = new AbortController();
    setCustomersLoading(shouldSearchCustomers);
    const timer = window.setTimeout(() => {
      const params = new URLSearchParams();
      if (shouldSearchCustomers) params.set("search", query);
      if (form.customer_id) params.set("customer_id", form.customer_id);

      void fetch(`/api/discount-codes/options?${params}`, {
        cache: "no-store",
        signal: controller.signal,
      })
        .then(async (response) => {
          const payload = await response.json().catch(() => null);
          if (!response.ok) throw new Error(messageFrom(payload));
          const result = payload as OptionsResponse;
          setCustomers(result.data?.customers ?? []);
          setCategories(result.data?.categories ?? []);
          setOptionsError("");
        })
        .catch((caught: unknown) => {
          if (caught instanceof DOMException && caught.name === "AbortError") {
            return;
          }
          setOptionsError(
            caught instanceof Error
              ? caught.message
              : "دریافت فهرست مشتریان و دسته‌بندی‌ها انجام نشد.",
          );
        })
        .finally(() => {
          if (!controller.signal.aborted) setCustomersLoading(false);
        });
    }, shouldSearchCustomers ? 250 : 0);
    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [customerSearch, editing, form.customer_id, form.scope]);

  function openNew() {
    setError("");
    setMessage("");
    setForm({ ...blankForm });
    setCustomerSearch("");
    setCustomers([]);
    setCategories([]);
    setActiveCustomerIndex(-1);
    setEditing("new");
  }

  function openEdit(code: DiscountCode) {
    setError("");
    setMessage("");
    setForm({
      code: code.code,
      percentage: String(code.percentage),
      customer_id: String(code.customer_id),
      scope: code.scope,
      category_id: code.category_id ? String(code.category_id) : "",
      expires_at: localDateTime(code.expires_at),
      is_active: code.is_active,
      description: code.description ?? "",
    });
    setCustomerSearch(code.customer?.name ?? "");
    setActiveCustomerIndex(-1);
    if (code.customer) {
      setCustomers((current) => [
        code.customer!,
        ...current.filter((item) => item.id !== code.customer?.id),
      ]);
    }
    setEditing(code);
  }

  function closeForm() {
    if (busy) return;
    setEditing(null);
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (busy || editing === null) return;

    const percentage = Number(form.percentage);
    const expiresAt = new Date(form.expires_at);
    if (!Number.isInteger(percentage) || percentage < 1 || percentage > 100) {
      setError("درصد تخفیف باید عدد صحیحی بین ۱ تا ۱۰۰ باشد.");
      return;
    }
    if (!form.customer_id || !Number.isInteger(Number(form.customer_id))) {
      setError("یک مشتری معتبر انتخاب کنید.");
      return;
    }
    if (form.scope === "category" && !form.category_id) {
      setError("برای محدوده دسته‌بندی، یک دسته‌بندی انتخاب کنید.");
      return;
    }
    if (!form.expires_at || Number.isNaN(expiresAt.getTime())) {
      setError("تاریخ انقضای معتبری وارد کنید.");
      return;
    }

    setBusy(true);
    setError("");
    setMessage("");

    const payload = {
      code: form.code.trim().toUpperCase(),
      percentage,
      customer_id: Number(form.customer_id),
      scope: form.scope,
      category_id: form.scope === "category" ? Number(form.category_id) : null,
      expires_at: expiresAt.toISOString(),
      is_active: form.is_active,
      description: form.description.trim() || null,
    };

    try {
      const isNew = editing === "new";
      const endpoint = isNew
        ? "/api/discount-codes"
        : `/api/discount-codes/${editing.id}`;
      const response = await fetch(endpoint, {
        method: isNew ? "POST" : "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const result = await response.json().catch(() => null);
      if (!response.ok) throw new Error(messageFrom(result));

      setEditing(null);
      setMessage(isNew ? "کد تخفیف ایجاد شد." : "تغییرات ذخیره شد.");
      await loadCodes();
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "ذخیره کد انجام نشد.");
    } finally {
      setBusy(false);
    }
  }

  async function deactivate(code: DiscountCode) {
    if (busy || !window.confirm(`کد «${code.code}» غیرفعال شود؟`)) return;
    setBusy(true);
    setError("");
    setMessage("");

    try {
      const response = await fetch(
        `/api/discount-codes/${code.id}/deactivate`,
        {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({}),
        },
      );
      const payload = await response.json().catch(() => null);
      if (!response.ok) throw new Error(messageFrom(payload));
      setMessage(`کد «${code.code}» غیرفعال شد.`);
      await loadCodes();
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "غیرفعال‌سازی انجام نشد.");
    } finally {
      setBusy(false);
    }
  }

  function change<K extends keyof CodeForm>(key: K, value: CodeForm[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  const selectedCustomer = customers.find(
    (customer) => String(customer.id) === form.customer_id,
  );
  const customerSuggestionsVisible =
    form.customer_id === "" && customerSearch.trim().length >= 2;

  function chooseCustomer(customer: CustomerOption) {
    change("customer_id", String(customer.id));
    setCustomerSearch(
      `${customer.name}${customer.phone ? ` — ${customer.phone}` : ""}`,
    );
    setCustomers((current) => [
      customer,
      ...current.filter((item) => item.id !== customer.id),
    ]);
    setActiveCustomerIndex(-1);
  }

  return (
    <section className={styles.page} dir="rtl" lang="fa">
      {message && <p className={styles.success} role="status">{message}</p>}
      {error && <p className={styles.error} role="alert">{error}</p>}
      {optionsError && <p className={styles.error} role="alert">{optionsError}</p>}

      {editing !== null && canManage && (
        <form className={styles.formCard} onSubmit={(event) => void submit(event)}>
          <div className={styles.formHead}>
            <h2>{editing === "new" ? "ایجاد کد تخفیف" : "ویرایش کد تخفیف"}</h2>
            <button type="button" onClick={closeForm} disabled={busy}>بستن</button>
          </div>
          <div className={styles.formGrid}>
            <label className={styles.field}>
              کد یکتا
              <input
                dir="ltr"
                required
                maxLength={64}
                value={form.code}
                onChange={(event) => change("code", event.target.value.toUpperCase())}
                placeholder="WELCOME20"
              />
            </label>
            <label className={styles.field}>
              درصد تخفیف
              <input
                type="number"
                min="1"
                max="100"
                step="1"
                required
                value={form.percentage}
                onChange={(event) => change("percentage", event.target.value)}
              />
            </label>
            <div className={`${styles.field} ${styles.customerField}`}>
              <label htmlFor="discount-customer-search">جست‌وجوی مشتری</label>
              <div className={styles.customerSearch}>
                <input
                  id="discount-customer-search"
                  type="search"
                  role="combobox"
                  aria-autocomplete="list"
                  aria-expanded={customerSuggestionsVisible || customersLoading}
                  aria-controls={
                    customerSuggestionsVisible
                      ? "discount-customer-options"
                      : undefined
                  }
                  aria-activedescendant={
                    activeCustomerIndex >= 0
                      ? `discount-customer-option-${customers[activeCustomerIndex]?.id}`
                      : undefined
                  }
                  aria-required="true"
                  aria-invalid={!form.customer_id}
                  autoComplete="off"
                  value={customerSearch}
                  onChange={(event) => {
                    setCustomerSearch(event.target.value);
                    change("customer_id", "");
                    setCustomers([]);
                    setActiveCustomerIndex(-1);
                    setOptionsError("");
                  }}
                  onKeyDown={(event) => {
                    if (event.key === "Escape" && customerSuggestionsVisible) {
                      setCustomerSearch("");
                      setCustomers([]);
                      setActiveCustomerIndex(-1);
                      return;
                    }
                    if (!customerSuggestionsVisible || !customers.length) return;
                    if (event.key === "ArrowDown") {
                      event.preventDefault();
                      setActiveCustomerIndex((current) =>
                        Math.min(current + 1, customers.length - 1),
                      );
                    } else if (event.key === "ArrowUp") {
                      event.preventDefault();
                      setActiveCustomerIndex((current) => Math.max(current - 1, 0));
                    } else if (
                      event.key === "Enter"
                    ) {
                      event.preventDefault();
                      if (activeCustomerIndex >= 0 && customers[activeCustomerIndex]) {
                        chooseCustomer(customers[activeCustomerIndex]);
                      }
                    }
                  }}
                  placeholder="نام یا شماره تماس"
                />
                {selectedCustomer && (
                  <button
                    className={styles.clearCustomer}
                    type="button"
                    aria-label="تغییر مشتری انتخاب‌شده"
                    onClick={() => {
                      change("customer_id", "");
                      setCustomerSearch("");
                      setActiveCustomerIndex(-1);
                    }}
                  >
                    تغییر
                  </button>
                )}
                {customersLoading && (
                  <span className={styles.customerHint} role="status">
                    در حال جست‌وجو…
                  </span>
                )}
                {customerSuggestionsVisible && !customersLoading && (
                  <div
                    className={styles.customerOptions}
                    id="discount-customer-options"
                    role="listbox"
                    aria-label="نتایج جست‌وجوی مشتری"
                  >
                    {customers.length ? customers.map((customer, index) => (
                      <button
                        key={customer.id}
                        id={`discount-customer-option-${customer.id}`}
                        type="button"
                        role="option"
                        aria-selected={index === activeCustomerIndex}
                        className={
                          index === activeCustomerIndex
                            ? styles.customerOptionActive
                            : styles.customerOption
                        }
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={() => chooseCustomer(customer)}
                      >
                        <span>{customer.name}</span>
                        {customer.phone && <span dir="ltr">{customer.phone}</span>}
                      </button>
                    )) : (
                      <p className={styles.customerHint}>مشتری‌ای با این مشخصات پیدا نشد.</p>
                    )}
                  </div>
                )}
              </div>
            </div>
            <label className={styles.field}>
              محدوده
              <AdminSelect
                dir="rtl"
                aria-label="محدوده"
                value={form.scope}
                onChange={(event) => {
                  const scope = event.target.value as CodeForm["scope"];
                  change("scope", scope);
                  if (scope === "all") change("category_id", "");
                }}
              >
                <option value="all">تمام محصولات مجاز</option>
                <option value="category">یک دسته‌بندی و زیر‌دسته‌ها</option>
              </AdminSelect>
            </label>
            {form.scope === "category" && (
              <label className={styles.field}>
                دسته‌بندی
                <AdminSelect
                  dir="rtl"
                  aria-label="دسته‌بندی"
                  required
                  value={form.category_id}
                  onChange={(event) => change("category_id", event.target.value)}
                >
                  <option value="">انتخاب دسته‌بندی</option>
                  {categories.map((category) => (
                    <option key={category.id} value={category.id}>
                      {"　".repeat(category.depth)}{category.name}
                    </option>
                  ))}
                </AdminSelect>
              </label>
            )}
            <label className={styles.field}>
              تاریخ و ساعت انقضا
              <div className={styles.datePicker}>
                <CashbackJalaliDateTime
                  value={form.expires_at}
                  onChange={(value) => change("expires_at", value)}
                  disabled={busy}
                />
              </div>
            </label>
            <label className={`${styles.field} ${styles.descriptionField}`}>
              توضیح برای مدیر
              <textarea
                maxLength={2000}
                value={form.description}
                onChange={(event) => change("description", event.target.value)}
              />
            </label>
            <label className={styles.field}>
              وضعیت کد
              <AdminSelect
                dir="rtl"
                aria-label="وضعیت کد"
                value={form.is_active ? "1" : "0"}
                onChange={(event) => change("is_active", event.target.value === "1")}
              >
                <option value="1">فعال</option>
                <option value="0">غیرفعال</option>
              </AdminSelect>
            </label>
          </div>
          <div className={styles.actions}>
            <button className={styles.primary} type="submit" disabled={busy}>
              {busy ? "در حال ذخیره…" : "ذخیره"}
            </button>
            <button type="button" onClick={closeForm} disabled={busy}>انصراف</button>
          </div>
        </form>
      )}

      <div className={styles.toolbar}>
        <label className={styles.field}>
          جست‌وجوی کد، مشتری یا توضیح
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="کد، نام یا شماره مشتری"
          />
        </label>
        {canManage && (
          <button type="button" onClick={openNew}>ایجاد کد جدید</button>
        )}
      </div>

      <div className={styles.filters}>
        <label className={styles.field}>
          وضعیت
          <AdminSelect
            dir="rtl"
            aria-label="فیلتر وضعیت"
            value={statusFilter}
            onChange={(event) => {
              setStatusFilter(event.target.value);
              setPage(1);
            }}
          >
            <option value="">همه وضعیت‌ها</option>
            <option value="active">فعال</option>
            <option value="expired">منقضی‌شده</option>
            <option value="inactive">غیرفعال</option>
          </AdminSelect>
        </label>
        <label className={styles.field}>
          محدوده
          <AdminSelect
            dir="rtl"
            aria-label="فیلتر محدوده"
            value={scopeFilter}
            onChange={(event) => {
              setScopeFilter(event.target.value);
              setPage(1);
            }}
          >
            <option value="">همه محدوده‌ها</option>
            <option value="all">تمام محصولات</option>
            <option value="category">دسته‌بندی</option>
          </AdminSelect>
        </label>
        <p className={styles.state}>{total.toLocaleString("fa-IR")} کد</p>
      </div>

      {loading ? (
        <p className={styles.state} role="status">در حال دریافت کدها…</p>
      ) : codes.length ? (
        <div className={styles.list}>
          {codes.map((code) => (
            <article className={styles.codeCard} key={code.id}>
              <div className={styles.codeHead}>
                <strong>{code.code}</strong>
                <span className={styles.status} data-status={code.status}>
                  {code.status_label}
                </span>
              </div>
              <div className={styles.codeMeta}>
                <span>{code.percentage.toLocaleString("fa-IR")}%</span>
                <span>
                  مشتری: {code.customer?.name ?? "—"}
                  {code.customer?.phone ? ` (${code.customer.phone})` : ""}
                </span>
                <span>
                  محدوده: {code.scope === "all" ? "تمام محصولات" : code.category?.name ?? "دسته‌بندی"}
                </span>
                <span>انقضا: {displayDate(code.expires_at)}</span>
                {code.description && <span>توضیح: {code.description}</span>}
              </div>
              {canManage && (
                <div className={styles.actions}>
                  <button type="button" onClick={() => openEdit(code)}>ویرایش</button>
                  {code.is_active && (
                    <button
                      type="button"
                      disabled={busy}
                      onClick={() => void deactivate(code)}
                    >
                      غیرفعال‌سازی
                    </button>
                  )}
                </div>
              )}
            </article>
          ))}
          <div className={styles.pagination}>
            <button
              type="button"
              disabled={page <= 1 || loading}
              onClick={() => setPage((current) => current - 1)}
            >
              قبلی
            </button>
            <span>{page.toLocaleString("fa-IR")} از {lastPage.toLocaleString("fa-IR")}</span>
            <button
              type="button"
              disabled={page >= lastPage || loading}
              onClick={() => setPage((current) => current + 1)}
            >
              بعدی
            </button>
          </div>
        </div>
      ) : (
        <p className={styles.state}>
          {error ? "فهرست کدها در دسترس نیست." : "کدی با این مشخصات پیدا نشد."}
        </p>
      )}
    </section>
  );
}
