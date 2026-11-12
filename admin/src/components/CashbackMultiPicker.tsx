"use client";

import { useEffect, useMemo, useRef, useState } from "react";

type Item = { id: number; name: string };
type Kind = "categories" | "products";

interface Props {
  kind: Kind;
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
}

function parseIds(value: string): number[] {
  return [...new Set(
    value.split(/[,\s]+/)
      .filter(Boolean)
      .map(Number)
      .filter(id => Number.isSafeInteger(id) && id > 0)
  )];
}

export function CashbackMultiPicker({
  kind,
  value,
  onChange,
  disabled = false,
}: Props) {
  const root = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [items, setItems] = useState<Item[]>([]);
  const [names, setNames] = useState<Record<number, string>>({});
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const selected = useMemo(() => parseIds(value), [value]);
  const selectedKey = selected.join(",");
  const title = kind === "categories" ? "دسته‌بندی" : "محصول";

  useEffect(() => {
    function outside(event: PointerEvent) {
      if (!root.current?.contains(event.target as Node)) setOpen(false);
    }
    function keydown(event: KeyboardEvent) {
      if (event.key === "Escape") setOpen(false);
    }
    document.addEventListener("pointerdown", outside);
    document.addEventListener("keydown", keydown);
    return () => {
      document.removeEventListener("pointerdown", outside);
      document.removeEventListener("keydown", keydown);
    };
  }, []);

  useEffect(() => {
    if (!open) return;

    const controller = new AbortController();
    const timer = window.setTimeout(async () => {
      setLoading(true);
      setError(null);

      try {
        const params = new URLSearchParams({ kind, q: query.trim() });
        const response = await fetch(
          `/api/cashback/options?${params}`,
          { signal: controller.signal, cache: "no-store" }
        );
        const payload = await response.json();

        if (!response.ok) {
          throw new Error(payload.message ?? "جست‌وجو انجام نشد.");
        }

        const found: Item[] = Array.isArray(payload.items)
          ? payload.items
          : [];

        if (!controller.signal.aborted) {
          setItems(found);
          setNames(previous => ({
            ...previous,
            ...Object.fromEntries(found.map(item => [item.id, item.name])),
          }));
        }
      } catch (caught) {
        if (!controller.signal.aborted) {
          setError(
            caught instanceof Error ? caught.message : "خطا در جست‌وجو"
          );
        }
      } finally {
        if (!controller.signal.aborted) setLoading(false);
      }
    }, 220);

    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [kind, query, open]);

  useEffect(() => {
    if (!selectedKey) return;

    const controller = new AbortController();

    async function resolveNames() {
      try {
        const params = new URLSearchParams({
          kind,
          ...(kind === "products"
            ? { ids: selectedKey }
            : { q: "" }),
        });

        const response = await fetch(
          `/api/cashback/options?${params}`,
          { signal: controller.signal }
        );

        if (!response.ok) return;

        const payload = await response.json();
        const found: Item[] = Array.isArray(payload.items)
          ? payload.items
          : [];

        if (!controller.signal.aborted) {
          setNames(previous => ({
            ...previous,
            ...Object.fromEntries(found.map(item => [item.id, item.name])),
          }));
        }
      } catch {
        // Selected IDs are preserved.
      }
    }

    resolveNames();
    return () => controller.abort();
  }, [kind, selectedKey]);

  function toggle(id: number) {
    if (disabled) return;

    const next = selected.includes(id)
      ? selected.filter(item => item !== id)
      : [...selected, id];

    onChange(next.join(", "));
  }

  return (
    <div ref={root} className="cashback-search-picker" dir="rtl">
      <div className="cashback-search-control">
        <span aria-hidden="true">⌕</span>
        <input
          type="search"
          value={query}
          disabled={disabled}
          placeholder={`جست‌وجو و انتخاب ${title}...`}
          aria-label={`جست‌وجوی ${title}`}
          aria-expanded={open}
          onFocus={() => setOpen(true)}
          onClick={() => setOpen(true)}
          onChange={event => {
            setQuery(event.target.value);
            setOpen(true);
          }}
        />
        {open && (
          <button
            type="button"
            aria-label="بستن نتایج"
            onClick={() => setOpen(false)}
          >
            ×
          </button>
        )}
      </div>

      {open && !disabled && (
        <div className="cashback-search-results">
          {loading && <p>در حال جست‌وجو...</p>}
          {error && <p role="alert">{error}</p>}
          {!loading && !error && items.length === 0 && (
            <p>موردی یافت نشد.</p>
          )}
          {!loading && !error && items.map(item => {
            const checked = selected.includes(item.id);
            return (
              <button
                key={item.id}
                type="button"
                aria-pressed={checked}
                onClick={() => toggle(item.id)}
              >
                <span>{item.name}</span>
                <span aria-hidden="true">{checked ? "✓" : "+"}</span>
              </button>
            );
          })}
        </div>
      )}

      {selected.length > 0 && (
        <div className="cashback-search-chips">
          {selected.map(id => (
            <button
              key={id}
              type="button"
              disabled={disabled}
              onClick={() => toggle(id)}
              aria-label={`حذف ${names[id] ?? id}`}
            >
              {names[id] ?? `شناسه ${id}`}
              <span aria-hidden="true">×</span>
            </button>
          ))}
        </div>
      )}

      <small>{selected.length} مورد انتخاب شده</small>
    </div>
  );
}