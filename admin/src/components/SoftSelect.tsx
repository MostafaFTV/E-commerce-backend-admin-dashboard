"use client";

import {
  useEffect,
  useRef,
  useState,
} from "react";

import styles from "./SoftSelect.module.css";

export interface SoftSelectOption {
  value: string;
  label: string;
  disabled?: boolean;
}

interface SoftSelectProps {
  id?: string;
  value: string;
  options: SoftSelectOption[];
  onChange: (value: string) => void;
  ariaLabel: string;
  disabled?: boolean;
}

export function SoftSelect({
  id,
  value,
  options,
  onChange,
  ariaLabel,
  disabled = false,
}: SoftSelectProps) {
  const [open, setOpen] =
    useState(false);

  const rootRef =
    useRef<HTMLDivElement>(
      null,
    );

  const selected =
    options.find(
      (option) =>
        option.value === value,
    );

  useEffect(() => {
    function closeOutside(
      event: PointerEvent,
    ) {
      if (
        rootRef.current &&
        !rootRef.current.contains(
          event.target as Node,
        )
      ) {
        setOpen(false);
      }
    }

    document.addEventListener(
      "pointerdown",
      closeOutside,
    );

    return () =>
      document.removeEventListener(
        "pointerdown",
        closeOutside,
      );
  }, []);

  return (
    <div
      ref={rootRef}
      className={
        open
          ? `${styles.root} ${styles.open}`
          : styles.root
      }
    >
      <button
        id={id}
        type="button"
        className={
          styles.trigger
        }
        aria-label={
          ariaLabel
        }
        aria-haspopup="listbox"
        aria-expanded={open}
        disabled={disabled}
        onClick={() =>
          setOpen(
            (current) =>
              !current,
          )
        }
        onKeyDown={
          (event) => {
            if (
              event.key ===
              "Escape"
            ) {
              setOpen(false);
            }
          }
        }
      >
        <span>
          {selected?.label ??
            "انتخاب کنید"}
        </span>

        <svg
          className={
            styles.chevron
          }
          viewBox="0 0 20 20"
          aria-hidden="true"
        >
          <path
            d="m6 8 4 4 4-4"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.5"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </svg>
      </button>

      {open &&
      !disabled ? (
        <div
          className={
            styles.menu
          }
          role="listbox"
        >
          {options.map(
            (option) => {
              const active =
                option.value ===
                value;

              return (
                <button
                  key={
                    option.value
                  }
                  type="button"
                  role="option"
                  aria-selected={
                    active
                  }
                  disabled={
                    option.disabled
                  }
                  className={
                    active
                      ? `${styles.option} ${styles.selected}`
                      : styles.option
                  }
                  onClick={() => {
                    onChange(
                      option.value,
                    );

                    setOpen(
                      false,
                    );
                  }}
                >
                  <span>
                    {
                      option.label
                    }
                  </span>

                  {active ? (
                    <span
                      className={
                        styles.check
                      }
                      aria-hidden="true"
                    >
                      ✓
                    </span>
                  ) : null}
                </button>
              );
            },
          )}
        </div>
      ) : null}
    </div>
  );
}