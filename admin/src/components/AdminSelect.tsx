"use client";

import {
  Children,
  isValidElement,
  type CSSProperties,
  type ChangeEvent,
  type KeyboardEvent,
  type ReactNode,
  type SelectHTMLAttributes,
  useCallback,
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
} from "react";
import { createPortal } from "react-dom";

import styles from "./AdminSelect.module.css";

interface AdminSelectOption {
  value: string;
  label: string;
  disabled: boolean;
}

interface OptionElementProps {
  value?: string | number;
  disabled?: boolean;
  children?: ReactNode;
}

type AdminSelectProps =
  Omit<
    SelectHTMLAttributes<HTMLSelectElement>,
    "multiple" | "size"
  > & {
    emptyLabel?: string;
  };

function nodeText(
  node: ReactNode,
): string {
  if (
    typeof node === "string" ||
    typeof node === "number"
  ) {
    return String(node);
  }

  if (Array.isArray(node)) {
    return node
      .map(nodeText)
      .join("");
  }

  if (
    isValidElement<{
      children?: ReactNode;
    }>(node)
  ) {
    return nodeText(
      node.props.children,
    );
  }

  return "";
}

function optionsFromChildren(
  children: ReactNode,
): AdminSelectOption[] {
  const result:
    AdminSelectOption[] = [];

  function visit(
    nodes: ReactNode,
  ) {
    Children.forEach(
      nodes,
      (child) => {
        if (
          !isValidElement<
            OptionElementProps
          >(child)
        ) {
          return;
        }

        if (
          child.type === "option"
        ) {
          const label =
            nodeText(
              child.props.children,
            );

          const rawValue =
            child.props.value ??
            label;

          result.push({
            value:
              String(rawValue),
            label,
            disabled:
              Boolean(
                child.props
                  .disabled,
              ),
          });

          return;
        }

        visit(
          child.props.children,
        );
      },
    );
  }

  visit(children);

  return result;
}

function normalizeValue(
  value:
    | string
    | readonly string[]
    | number
    | undefined,
): string {
  if (
    Array.isArray(value)
  ) {
    return value.length
      ? String(value[0])
      : "";
  }

  if (
    value === undefined
  ) {
    return "";
  }

  return String(value);
}

export function AdminSelect({
  id,
  className,
  children,
  value,
  defaultValue,
  disabled = false,
  dir,
  emptyLabel = "انتخاب کنید",
  onChange,
  name,
  required,
  form,
  ...selectProps
}: AdminSelectProps) {
  const selectRef =
    useRef<HTMLSelectElement>(
      null,
    );

  const triggerRef =
    useRef<HTMLButtonElement>(
      null,
    );

  const menuRef =
    useRef<HTMLDivElement>(
      null,
    );

  const listboxId =
    useId();

  const staticOptions =
    useMemo(
      () =>
        optionsFromChildren(
          children,
        ),
      [children],
    );

  const controlled =
    value !== undefined;

  const initialValue =
    controlled
      ? normalizeValue(value)
      : defaultValue !==
          undefined
        ? normalizeValue(
            defaultValue,
          )
        : staticOptions[0]
            ?.value ?? "";

  const [
    options,
    setOptions,
  ] =
    useState<
      AdminSelectOption[]
    >(staticOptions);

  const [
    selectedValue,
    setSelectedValue,
  ] =
    useState<string>(
      initialValue,
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
    activeIndex,
    setActiveIndex,
  ] =
    useState(-1);

  const [
    menuStyle,
    setMenuStyle,
  ] =
    useState<CSSProperties>(
      {},
    );

  const syncFromNative =
    useCallback(() => {
      const select =
        selectRef.current;

      if (!select) {
        return;
      }

      const nextOptions =
        Array.from(
          select.options,
        ).map(
          (option) => ({
            value:
              option.value,
            label:
              option.label ||
              option.text,
            disabled:
              option.disabled,
          }),
        );

      setOptions(
        nextOptions,
      );

      setSelectedValue(
        select.value,
      );
    }, []);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    syncFromNative();
  }, [
    children,
    syncFromNative,
  ]);

  useEffect(() => {
    if (!controlled) {
      return;
    }

    setSelectedValue(
      normalizeValue(value),
    );
  }, [
    controlled,
    value,
  ]);

  const selectedOption =
    options.find(
      (option) =>
        option.value ===
        selectedValue,
    ) ??
    staticOptions.find(
      (option) =>
        option.value ===
        selectedValue,
    );

  const positionMenu =
    useCallback(() => {
      const trigger =
        triggerRef.current;

      if (!trigger) {
        return;
      }

      const rect =
        trigger
          .getBoundingClientRect();

      const viewportWidth =
        window.innerWidth;

      const viewportHeight =
        window.innerHeight;

      const width =
        Math.min(
          Math.max(
            rect.width,
            180,
          ),
          viewportWidth - 16,
        );

      const estimatedHeight =
        Math.min(
          300,
          Math.max(
            54,
            options.length *
              36 +
              12,
          ),
        );

      const below =
        viewportHeight -
        rect.bottom -
        10;

      const above =
        rect.top - 10;

      const openAbove =
        below <
          Math.min(
            180,
            estimatedHeight,
          ) &&
        above > below;

      const available =
        openAbove
          ? above
          : below;

      const maxHeight =
        Math.max(
          120,
          Math.min(
            300,
            available - 6,
          ),
        );

      const alignedLeft =
        dir === "rtl"
          ? rect.right - width
          : rect.left;

      const left =
        Math.min(
          Math.max(
            8,
            alignedLeft,
          ),
          Math.max(
            8,
            viewportWidth -
              width -
              8,
          ),
        );

      const visibleHeight =
        Math.min(
          estimatedHeight,
          maxHeight,
        );

      const top =
        openAbove
          ? Math.max(
              8,
              rect.top -
                visibleHeight -
                6,
            )
          : rect.bottom + 6;

      setMenuStyle({
        left,
        top,
        width,
        maxHeight,
      });
    }, [dir, options.length]);

  useEffect(() => {
    if (!open) {
      return;
    }

    positionMenu();

    const handleOutside = (
      event: PointerEvent,
    ) => {
      const target =
        event.target as Node;

      if (
        triggerRef.current
          ?.contains(target) ||
        menuRef.current
          ?.contains(target)
      ) {
        return;
      }

      setOpen(false);
    };

    const handlePosition =
      () => {
        positionMenu();
      };

    document.addEventListener(
      "pointerdown",
      handleOutside,
    );

    window.addEventListener(
      "resize",
      handlePosition,
    );

    window.addEventListener(
      "scroll",
      handlePosition,
      true,
    );

    return () => {
      document.removeEventListener(
        "pointerdown",
        handleOutside,
      );

      window.removeEventListener(
        "resize",
        handlePosition,
      );

      window.removeEventListener(
        "scroll",
        handlePosition,
        true,
      );
    };
  }, [
    open,
    positionMenu,
  ]);

  function openMenu() {
    if (disabled) {
      return;
    }

    syncFromNative();

    const source =
      options.length
        ? options
        : staticOptions;

    let index =
      source.findIndex(
        (option) =>
          option.value ===
            selectedValue &&
          !option.disabled,
      );

    if (index < 0) {
      index =
        source.findIndex(
          (option) =>
            !option.disabled,
        );
    }

    setActiveIndex(index);
    setOpen(true);
  }

  function chooseOption(
    option:
      AdminSelectOption,
  ) {
    if (
      option.disabled ||
      disabled
    ) {
      return;
    }

    const select =
      selectRef.current;

    if (!select) {
      return;
    }

    const descriptor =
      Object
        .getOwnPropertyDescriptor(
          HTMLSelectElement
            .prototype,
          "value",
        );

    if (descriptor?.set) {
      descriptor.set.call(
        select,
        option.value,
      );
    } else {
      select.value =
        option.value;
    }

    setSelectedValue(
      option.value,
    );

    select.dispatchEvent(
      new Event(
        "change",
        {
          bubbles: true,
        },
      ),
    );

    setOpen(false);

    requestAnimationFrame(
      () => {
        triggerRef.current
          ?.focus();
      },
    );
  }

  function moveActive(
    direction: 1 | -1,
  ) {
    if (!options.length) {
      return;
    }

    let next =
      activeIndex;

    for (
      let count = 0;
      count <
      options.length;
      count++
    ) {
      next =
        (
          next +
          direction +
          options.length
        ) %
        options.length;

      if (
        !options[next]
          .disabled
      ) {
        setActiveIndex(
          next,
        );

        return;
      }
    }
  }

  function handleKeyDown(
    event:
      KeyboardEvent<HTMLButtonElement>,
  ) {
    if (
      event.key ===
      "ArrowDown"
    ) {
      event.preventDefault();

      if (!open) {
        openMenu();
      } else {
        moveActive(1);
      }

      return;
    }

    if (
      event.key ===
      "ArrowUp"
    ) {
      event.preventDefault();

      if (!open) {
        openMenu();
      } else {
        moveActive(-1);
      }

      return;
    }

    if (
      event.key ===
        "Enter" ||
      event.key === " "
    ) {
      event.preventDefault();

      if (!open) {
        openMenu();

        return;
      }

      if (
        activeIndex >= 0 &&
        options[
          activeIndex
        ]
      ) {
        chooseOption(
          options[
            activeIndex
          ],
        );
      }

      return;
    }

    if (
      event.key ===
      "Escape"
    ) {
      if (open) {
        event.preventDefault();
        setOpen(false);
      }

      return;
    }

    if (
      event.key ===
      "Home" &&
      open
    ) {
      event.preventDefault();

      const index =
        options.findIndex(
          (option) =>
            !option.disabled,
        );

      if (index >= 0) {
        setActiveIndex(
          index,
        );
      }

      return;
    }

    if (
      event.key ===
      "End" &&
      open
    ) {
      event.preventDefault();

      for (
        let index =
          options.length - 1;
        index >= 0;
        index--
      ) {
        if (
          !options[index]
            .disabled
        ) {
          setActiveIndex(
            index,
          );

          break;
        }
      }
    }
  }

  function handleNativeChange(
    event:
      ChangeEvent<HTMLSelectElement>,
  ) {
    setSelectedValue(
      event.currentTarget
        .value,
    );

    onChange?.(event);
  }

  const nativeValueProps =
    controlled
      ? {
          value,
        }
      : {
          defaultValue,
        };

  return (
    <div
      className={
        styles.root
      }
      dir={dir}
      data-admin-select=""
    >
      <button
        ref={triggerRef}
        id={id}
        type="button"
        className={[
          styles.trigger,
          open
            ? styles.triggerOpen
            : "",
          className ?? "",
        ]
          .filter(Boolean)
          .join(" ")}
        disabled={disabled}
        role="combobox"
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-controls={
          listboxId
        }
        onClick={() => {
          if (open) {
            setOpen(false);
          } else {
            openMenu();
          }
        }}
        onKeyDown={
          handleKeyDown
        }
      >
        <span
          className={
            styles.value
          }
        >
          {selectedOption
            ?.label ??
            emptyLabel}
        </span>

        <span
          className={
            styles.chevron
          }
          aria-hidden="true"
        />
      </button>

      <select
        {...selectProps}
        {...nativeValueProps}
        dir={dir}
        ref={selectRef}
        id={
          id
            ? `${id}__native`
            : undefined
        }
        className={
          styles.native
        }
        name={name}
        form={form}
        required={required}
        disabled={disabled}
        tabIndex={-1}
        aria-hidden="true"
        onChange={
          handleNativeChange
        }
      >
        {children}
      </select>

      {mounted &&
      open
        ? createPortal(
            <div
              ref={menuRef}
              id={listboxId}
              className={
                styles.menu
              }
              dir={dir}
              style={
                menuStyle
              }
              role="listbox"
            >
              {options.map(
                (
                  option,
                  index,
                ) => {
                  const selected =
                    option.value ===
                    selectedValue;

                  const active =
                    index ===
                    activeIndex;

                  return (
                    <button
                      key={`${option.value}-${index}`}
                      type="button"
                      role="option"
                      aria-selected={
                        selected
                      }
                      disabled={
                        option.disabled
                      }
                      className={[
                        styles.option,
                        selected
                          ? styles.optionSelected
                          : "",
                        active
                          ? styles.optionActive
                          : "",
                      ]
                        .filter(
                          Boolean,
                        )
                        .join(
                          " ",
                        )}
                      onPointerDown={(
                        event,
                      ) => {
                        event
                          .preventDefault();
                      }}
                      onMouseEnter={() => {
                        if (
                          !option.disabled
                        ) {
                          setActiveIndex(
                            index,
                          );
                        }
                      }}
                      onClick={() =>
                        chooseOption(
                          option,
                        )
                      }
                    >
                      <span>
                        {
                          option.label
                        }
                      </span>

                      {selected ? (
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
            </div>,
            document.body,
          )
        : null}
    </div>
  );
}