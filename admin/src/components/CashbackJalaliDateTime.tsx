"use client";

import { useEffect, useMemo, useRef, useState } from "react";

interface Props {
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
}

const formatter = new Intl.DateTimeFormat("en-US-u-ca-persian-nu-latn", {
  year: "numeric",
  month: "numeric",
  day: "numeric",
});

const monthNames = [
  "فروردین", "اردیبهشت", "خرداد", "تیر",
  "مرداد", "شهریور", "مهر", "آبان",
  "آذر", "دی", "بهمن", "اسفند",
];

const weekdays = ["ش", "ی", "د", "س", "چ", "پ", "ج"];

function parts(date: Date) {
  const map = Object.fromEntries(
    formatter.formatToParts(date).map(p => [p.type, p.value])
  );
  return {
    year: Number(map.year),
    month: Number(map.month),
    day: Number(map.day),
  };
}

function pad(number: number) {
  return String(number).padStart(2, "0");
}

function parse(value: string): Date | null {
  const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(value);
  if (!match) return null;

  const [year, month, day, hour, minute] = match.slice(1).map(Number);
  const result = new Date(year, month - 1, day, hour, minute);

  return result.getFullYear() === year &&
    result.getMonth() === month - 1 &&
    result.getDate() === day &&
    result.getHours() === hour &&
    result.getMinutes() === minute
    ? result : null;
}

function serialize(date: Date) {
  return [
    date.getFullYear(),
    "-",
    pad(date.getMonth() + 1),
    "-",
    pad(date.getDate()),
    "T",
    pad(date.getHours()),
    ":",
    pad(date.getMinutes()),
  ].join("");
}

function firstDay(date: Date) {
  const start = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  for (let i = 0; i < 35 && parts(start).day !== 1; i++) {
    start.setDate(start.getDate() - 1);
  }
  return start;
}

export function CashbackJalaliDateTime({
  value,
  onChange,
  disabled = false,
}: Props) {
  const root = useRef<HTMLDivElement>(null);
  const selected = parse(value);
  const [open, setOpen] = useState(false);
  const [view, setView] = useState(() => selected ?? new Date());

  useEffect(() => {
    if (selected) setView(selected);
    // Sync only when external value changes.
  }, [value]);

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

  const current = parts(view);
  const start = firstDay(view);

  const calendarDays = useMemo(() => {
    const first = firstDay(view);
    const shift = (first.getDay() + 1) % 7;
    first.setDate(first.getDate() - shift);

    return Array.from({ length: 42 }, (_, index) => {
      const date = new Date(first);
      date.setDate(date.getDate() + index);
      return { date, jalali: parts(date) };
    });
  }, [view]);

  function shiftMonth(offset: number) {
    const start = firstDay(view);
    start.setDate(start.getDate() + (offset > 0 ? 35 : -1));
    setView(start);
  }

  function chooseDay(date: Date) {
    const next = new Date(date);
    next.setHours(selected?.getHours() ?? 0);
    next.setMinutes(selected?.getMinutes() ?? 0);
    onChange(serialize(next));
    setView(next);
  }

  function changeTime(part: "hour" | "minute", raw: string) {
    if (raw === "" || !/^\d{1,2}$/.test(raw)) return;

    const number = Number(raw);
    if (part === "hour" && number > 23) return;
    if (part === "minute" && number > 59) return;

    const next = new Date(selected ?? view);
    if (part === "hour") next.setHours(number);
    else next.setMinutes(number);

    onChange(serialize(next));
  }

  const display = selected
    ? `${parts(selected).year}/${pad(parts(selected).month)}/${pad(parts(selected).day)}`
    : "انتخاب تاریخ شمسی";

  return (
    <div ref={root} className="cashback-jalali" dir="rtl">
      <button
        type="button"
        className="cashback-jalali-trigger"
        disabled={disabled}
        aria-expanded={open}
        aria-haspopup="dialog"
        onClick={() => setOpen(current => !current)}
      >
        <span>{display}</span>
        <span>{selected
          ? `${pad(selected.getHours())}:${pad(selected.getMinutes())}`
          : "ساعت"}</span>
        <span aria-hidden="true">▦</span>
      </button>

      {open && !disabled && (
        <div className="cashback-jalali-popup" role="dialog"
          aria-label="تقویم شمسی">
          <div className="cashback-jalali-heading">
            <button type="button" onClick={() => shiftMonth(-1)}
              aria-label="ماه قبل">‹</button>
            <strong>{monthNames[current.month - 1]} {current.year}</strong>
            <button type="button" onClick={() => shiftMonth(1)}
              aria-label="ماه بعد">›</button>
          </div>

          <div className="cashback-jalali-grid">
            {weekdays.map(day => <b key={day}>{day}</b>)}
            {calendarDays.map(({ date, jalali }) => {
              const active = selected &&
                date.toDateString() === selected.toDateString();

              return (
                <button
                  type="button"
                  key={date.toISOString()}
                  className={[
                    jalali.month !== current.month ||
                    jalali.year !== current.year ? "outside" : "",
                    active ? "selected" : "",
                  ].join(" ")}
                  onClick={() => chooseDay(date)}
                >
                  {jalali.day.toLocaleString("fa-IR")}
                </button>
              );
            })}
          </div>

          <div className="cashback-jalali-time">
            <label>
              ساعت
              <input
                type="number" min="0" max="23"
                value={selected ? selected.getHours() : ""}
                onChange={event => changeTime("hour", event.target.value)}
              />
            </label>
            <label>
              دقیقه
              <input
                type="number" min="0" max="59"
                value={selected ? selected.getMinutes() : ""}
                onChange={event => changeTime("minute", event.target.value)}
              />
            </label>
          </div>

          <div className="cashback-jalali-actions">
            <button type="button" onClick={() => onChange("")}>پاک کردن</button>
            <button type="button" onClick={() => setOpen(false)}>تأیید</button>
          </div>
        </div>
      )}
    </div>
  );
}