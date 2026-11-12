"use client";

import {
  type CSSProperties,
  type KeyboardEvent,
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import { createPortal } from "react-dom";

import styles from "./AdminDatePicker.module.css";

interface AdminDatePickerProps {
  id?: string;
  name: string;
  defaultValue?: string;
  disabled?: boolean;
  min?: string;
  max?: string;
}

interface CalendarDay {
  date: Date;
  value: string;
  day: number;
  currentMonth: boolean;
  today: boolean;
  disabled: boolean;
}

const WEEKDAYS = [
  "ی",
  "د",
  "س",
  "چ",
  "پ",
  "ج",
  "ش",
];

const MONTHS = [
  "ژانویه",
  "فوریه",
  "مارس",
  "آوریل",
  "مه",
  "ژوئن",
  "ژوئیه",
  "اوت",
  "سپتامبر",
  "اکتبر",
  "نوامبر",
  "دسامبر",
];

function pad(value: number) {
  return String(value).padStart(
    2,
    "0",
  );
}

function toValue(date: Date) {
  return [
    date.getFullYear(),
    pad(date.getMonth() + 1),
    pad(date.getDate()),
  ].join("-");
}

function fromValue(
  value?: string,
): Date | null {
  if (
    !value ||
    !/^\d{4}-\d{2}-\d{2}$/.test(
      value,
    )
  ) {
    return null;
  }

  const [
    year,
    month,
    day,
  ] = value
    .split("-")
    .map(Number);

  const date =
    new Date(
      year,
      month - 1,
      day,
    );

  if (
    date.getFullYear() !==
      year ||
    date.getMonth() !==
      month - 1 ||
    date.getDate() !== day
  ) {
    return null;
  }

  return date;
}

function formatDisplay(
  value: string,
) {
  const date =
    fromValue(value);

  if (!date) {
    return "انتخاب تاریخ";
  }

  return `${pad(
    date.getDate(),
  )} ${
    MONTHS[
      date.getMonth()
    ].slice(0, 3)
  } ${date.getFullYear()}`;
}

function sameDay(
  first: Date,
  second: Date,
) {
  return (
    first.getFullYear() ===
      second.getFullYear() &&
    first.getMonth() ===
      second.getMonth() &&
    first.getDate() ===
      second.getDate()
  );
}

export function AdminDatePicker({
  id,
  name,
  defaultValue = "",
  disabled = false,
  min,
  max,
}: AdminDatePickerProps) {
  const triggerRef =
    useRef<HTMLButtonElement>(
      null,
    );

  const popupRef =
    useRef<HTMLDivElement>(
      null,
    );

  const initialDate =
    useMemo(
      () =>
        fromValue(
          defaultValue,
        ),
      [defaultValue],
    );

  const [
    value,
    setValue,
  ] =
    useState(
      defaultValue,
    );

  const [
    open,
    setOpen,
  ] =
    useState(false);

  const [
    mounted,
    setMounted,
  ] =
    useState(false);

  const [
    popupStyle,
    setPopupStyle,
  ] =
    useState<CSSProperties>(
      {},
    );

  const [
    viewDate,
    setViewDate,
  ] =
    useState<Date>(
      () => {
        const source =
          initialDate ??
          new Date();

        return new Date(
          source.getFullYear(),
          source.getMonth(),
          1,
        );
      },
    );

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    setValue(
      defaultValue,
    );

    const parsed =
      fromValue(
        defaultValue,
      );

    if (parsed) {
      setViewDate(
        new Date(
          parsed.getFullYear(),
          parsed.getMonth(),
          1,
        ),
      );
    }
  }, [defaultValue]);

  const days =
    useMemo<
      CalendarDay[]
    >(() => {
      const year =
        viewDate.getFullYear();

      const month =
        viewDate.getMonth();

      const first =
        new Date(
          year,
          month,
          1,
        );

      const gridStart =
        new Date(
          year,
          month,
          1 -
            first.getDay(),
        );

      const today =
        new Date();

      return Array.from(
        {
          length: 42,
        },
        (_, index) => {
          const date =
            new Date(
              gridStart
                .getFullYear(),
              gridStart
                .getMonth(),
              gridStart
                .getDate() +
                index,
            );

          const dateValue =
            toValue(date);

          return {
            date,
            value:
              dateValue,
            day:
              date.getDate(),
            currentMonth:
              date.getMonth() ===
                month,
            today:
              sameDay(
                date,
                today,
              ),
            disabled:
              Boolean(
                (min &&
                  dateValue <
                    min) ||
                  (max &&
                    dateValue >
                      max),
              ),
          };
        },
      );
    }, [
      viewDate,
      min,
      max,
    ]);

  const positionPopup =
    useCallback(() => {
      const trigger =
        triggerRef.current;

      if (!trigger) {
        return;
      }

      const rect =
        trigger
          .getBoundingClientRect();

      const width =
        Math.min(
          300,
          window.innerWidth -
            16,
        );

      const height =
        366;

      const below =
        window.innerHeight -
        rect.bottom -
        8;

      const above =
        rect.top - 8;

      const openAbove =
        below < 300 &&
        above > below;

      const left =
        Math.min(
          Math.max(
            8,
            rect.left,
          ),
          Math.max(
            8,
            window.innerWidth -
              width -
              8,
          ),
        );

      const top =
        openAbove
          ? Math.max(
              8,
              rect.top -
                height -
                6,
            )
          : Math.min(
              rect.bottom +
                6,
              window.innerHeight -
                height -
                8,
            );

      setPopupStyle({
        width,
        left,
        top:
          Math.max(
            8,
            top,
          ),
      });
    }, []);

  useEffect(() => {
    if (!open) {
      return;
    }

    positionPopup();

    const handleOutside = (
      event: PointerEvent,
    ) => {
      const target =
        event.target as Node;

      if (
        triggerRef.current
          ?.contains(target) ||
        popupRef.current
          ?.contains(target)
      ) {
        return;
      }

      setOpen(false);
    };

    const reposition =
      () => {
        positionPopup();
      };

    document.addEventListener(
      "pointerdown",
      handleOutside,
    );

    window.addEventListener(
      "resize",
      reposition,
    );

    window.addEventListener(
      "scroll",
      reposition,
      true,
    );

    return () => {
      document.removeEventListener(
        "pointerdown",
        handleOutside,
      );

      window.removeEventListener(
        "resize",
        reposition,
      );

      window.removeEventListener(
        "scroll",
        reposition,
        true,
      );
    };
  }, [
    open,
    positionPopup,
  ]);

  function changeMonth(
    offset: number,
  ) {
    setViewDate(
      (current) =>
        new Date(
          current
            .getFullYear(),
          current.getMonth() +
            offset,
          1,
        ),
    );
  }

  function selectDate(
    day: CalendarDay,
  ) {
    if (day.disabled) {
      return;
    }

    setValue(
      day.value,
    );

    setViewDate(
      new Date(
        day.date
          .getFullYear(),
        day.date.getMonth(),
        1,
      ),
    );

    setOpen(false);

    requestAnimationFrame(
      () =>
        triggerRef.current
          ?.focus(),
    );
  }

  function selectToday() {
    const today =
      new Date();

    const todayValue =
      toValue(today);

    if (
      (min &&
        todayValue < min) ||
      (max &&
        todayValue > max)
    ) {
      return;
    }

    setValue(
      todayValue,
    );

    setViewDate(
      new Date(
        today.getFullYear(),
        today.getMonth(),
        1,
      ),
    );

    setOpen(false);
  }

  function clearDate() {
    setValue("");
    setOpen(false);

    requestAnimationFrame(
      () =>
        triggerRef.current
          ?.focus(),
    );
  }

  function handleKeyDown(
    event:
      KeyboardEvent<HTMLButtonElement>,
  ) {
    if (
      event.key ===
        "Enter" ||
      event.key === " " ||
      event.key ===
        "ArrowDown"
    ) {
      event.preventDefault();

      if (!disabled) {
        setOpen(
          (current) =>
            !current,
        );
      }

      return;
    }

    if (
      event.key ===
        "Escape" &&
      open
    ) {
      event.preventDefault();
      setOpen(false);
    }
  }

  return (
    <div
      className={
        styles.root
      }
    >
      <input
        type="hidden"
        name={name}
        value={value}
        readOnly
      />

      <button
        ref={triggerRef}
        id={id}
        type="button"
        className={[
          styles.trigger,
          open
            ? styles.triggerOpen
            : "",
        ]
          .filter(Boolean)
          .join(" ")}
        disabled={disabled}
        aria-haspopup="dialog"
        aria-expanded={open}
        onClick={() =>
          setOpen(
            (current) =>
              !current,
          )
        }
        onKeyDown={
          handleKeyDown
        }
      >
        <span
          className={
            value
              ? styles.value
              : styles.placeholder
          }
        >
          {formatDisplay(
            value,
          )}
        </span>

        <span
          className={
            styles.calendarIcon
          }
          aria-hidden="true"
        >
          <span />
        </span>
      </button>

      {mounted &&
      open
        ? createPortal(
            <div
              ref={popupRef}
              className={
                styles.popup
              }
              style={
                popupStyle
              }
              role="dialog"
              aria-label="انتخاب تاریخ"
            >
              <div
                className={
                  styles.header
                }
              >
                <div>
                  <strong>
                    {
                      MONTHS[
                        viewDate
                          .getMonth()
                      ]
                    }
                  </strong>

                  <span>
                    {viewDate
                      .getFullYear()}
                  </span>
                </div>

                <div
                  className={
                    styles.navigation
                  }
                >
                  <button
                    type="button"
                    aria-label="ماه قبل"
                    onClick={() =>
                      changeMonth(
                        -1,
                      )
                    }
                  >
                    ‹
                  </button>

                  <button
                    type="button"
                    aria-label="ماه بعد"
                    onClick={() =>
                      changeMonth(
                        1,
                      )
                    }
                  >
                    ›
                  </button>
                </div>
              </div>

              <div
                className={
                  styles.weekdays
                }
              >
                {WEEKDAYS.map(
                  (day) => (
                    <span
                      key={day}
                    >
                      {day}
                    </span>
                  ),
                )}
              </div>

              <div
                className={
                  styles.grid
                }
              >
                {days.map(
                  (day) => {
                    const selected =
                      value ===
                      day.value;

                    return (
                      <button
                        key={
                          day.value
                        }
                        type="button"
                        className={[
                          styles.day,
                          !day.currentMonth
                            ? styles.outside
                            : "",
                          day.today
                            ? styles.today
                            : "",
                          selected
                            ? styles.selected
                            : "",
                        ]
                          .filter(
                            Boolean,
                          )
                          .join(
                            " ",
                          )}
                        disabled={
                          day.disabled
                        }
                        aria-pressed={
                          selected
                        }
                        aria-current={
                          day.today
                            ? "date"
                            : undefined
                        }
                        onClick={() =>
                          selectDate(
                            day,
                          )
                        }
                      >
                        {
                          day.day
                        }
                      </button>
                    );
                  },
                )}
              </div>

              <div
                className={
                  styles.footer
                }
              >
                <button
                  type="button"
                  className={
                    styles.textButton
                  }
                  disabled={
                    !value
                  }
                  onClick={
                    clearDate
                  }
                >
                  پاک کردن
                </button>

                <button
                  type="button"
                  className={
                    styles.todayButton
                  }
                  onClick={
                    selectToday
                  }
                >
                  امروز
                </button>
              </div>
            </div>,
            document.body,
          )
        : null}
    </div>
  );
}