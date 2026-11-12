"use client";

import {
  useMemo,
  useState,
} from "react";

import type {
  AdminHomepageSection,
  HomepageLocale,
} from "@/lib/admin-homepage";

import styles from "./HomepageEditor.module.css";
import { InspirationVideoField } from "./InspirationVideoField";

type LocalizedField =
  | "title"
  | "subtitle"
  | "content"
  | "button_text";

type MediaField =
  | "image"
  | "mobile_image";

interface DraftSection
  extends AdminHomepageSection {
  image_preview:
    string | null;

  mobile_image_preview:
    string | null;

  image_dirty:
    boolean;

  mobile_image_dirty:
    boolean;
}

interface SectionSpec {
  label:
    string;

  description:
    string;

  title?:
    boolean;

  subtitle?:
    boolean;

  content?:
    boolean;

  button_text?:
    boolean;

  link?:
    boolean;

  image?:
    boolean;

  mobile_image?:
    boolean;

  folder?:
    "services" |
    "story" |
    "kitchen-family" |
    "general";
}

interface HomepageEditorProps {
  sections:
    AdminHomepageSection[];
}

interface SiteSectionMeta {
  key:
    string;

  order:
    string;

  title:
    string;

  description:
    string;
}

function getSiteSectionMeta(
  section:
    AdminHomepageSection,
): SiteSectionMeta | null {
  if (
    section.section_key ===
    "categories"
  ) {
    return {
      key:
        "categories",

      order:
        "01",

      title:
        "خرید بر اساس دسته‌بندی",

      description:
        "عنوان بالای دسته‌بندی‌های صفحه اصلی را تنظیم می‌کند.",
    };
  }

  if (
    section.section_key ===
    "best_sellers"
  ) {
    return {
      key:
        "best-sellers",

      order:
        "02",

      title:
        "پرفروش‌ترین‌ها",

      description:
        "عنوان و دکمه بخش محصولات پرفروش را تنظیم می‌کند.",
    };
  }

  if (
    section.section_key.startsWith(
      "services_item_",
    )
  ) {
    return {
      key:
        "services",

      order:
        "03",

      title:
        "خدمات",

      description:
        "این کارت‌ها محتوای بخش خدمات صفحه اصلی هستند.",
    };
  }

  if (
    section.section_key ===
      "kitchen_family_header" ||
    section.section_key.startsWith(
      "kitchen_family_item_",
    )
  ) {
    return {
      key:
        "kitchen-family",

      order:
        "04",

      title:
        "خانواده کیچن بوتیک",

      description:
        "عنوان و تصاویر گالری خانواده کیچن بوتیک را تنظیم می‌کند.",
    };
  }

  if (
    section.section_key ===
    "inspiration"
  ) {
    return {
      key:
        "our-story",

      order:
        "05",

      title:
        "داستان ما",

      description:
        "تصویر، متن و دکمه بخش داستان ما را تنظیم می‌کند.",
    };
  }

  return null;
}
function getSectionSpec(
  section:
    AdminHomepageSection,
): SectionSpec | null {
  if (
    section.section_key ===
    "categories"
  ) {
    return {
      label:
        "خرید بر اساس دسته‌بندی",

      description:
        "عنوان بالای دسته‌بندی‌های متحرک",

      title:
        true,
    };
  }

  if (
    section.section_key ===
    "best_sellers"
  ) {
    return {
      label:
        "پرفروش‌ترین‌ها",

      description:
        "عنوان و دکمه بالای محصولات؛ محصولات از کاتالوگ دریافت می‌شوند.",

      title:
        true,

      subtitle:
        true,

      button_text:
        true,

      link:
        true,
    };
  }

  if (
    section.section_key.startsWith(
      "services_item_",
    )
  ) {
    const number =
      section.section_key
        .replace(
          "services_item_",
          "",
        );

    return {
      label:
        `خدمت ${number}`,

      description:
        "محتوای این کارت خدمات",

      title:
        true,

      subtitle:
        true,

      content:
        true,

      button_text:
        true,

      link:
        true,

      image:
        true,

      folder:
        "services",
    };
  }

  if (
    section.section_key ===
    "kitchen_family_header"
  ) {
    return {
      label:
        "عنوان خانواده کیچن بوتیک",

      description:
        "عنوان و مقدمه بالای گالری تصاویر",

      title:
        true,

      subtitle:
        true,

      content:
        true,

      button_text:
        true,
    };
  }

  if (
    section.section_key.startsWith(
      "kitchen_family_item_",
    )
  ) {
    const number =
      section.section_key
        .replace(
          "kitchen_family_item_",
          "",
        );

    return {
      label:
        `تصویر خانواده کیچن بوتیک ${number}`,

      description:
        "تصویر، عنوان دسترس‌پذیری اختیاری و مقصد این بخش گالری",

      title:
        true,

      link:
        true,

      image:
        true,

      folder:
        "kitchen-family",
    };
  }

  if (
    section.section_key ===
    "inspiration"
  ) {
    return {
      label:
        "داستان ما",

      description:
        "محتوا و تصویر بخش داستان ما",

      title:
        true,

      subtitle:
        true,

      content:
        true,

      button_text:
        true,

      link:
        true,

      image:
        true,

      folder:
        "story",
    };
  }

  return null;
}

function errorMessage(
  payload:
    unknown,
): string {
  if (
    !payload ||
    typeof payload !==
      "object"
  ) {
    return "ذخیره تغییرات انجام نشد.";
  }

  const record =
    payload as {
      message?:
        unknown;

      errors?:
        unknown;
    };

  if (
    record.errors &&
    typeof record.errors ===
      "object"
  ) {
    const messages =
      Object.values(
        record.errors as
          Record<
            string,
            unknown
          >,
      )
        .flatMap(
          (value) =>
            Array.isArray(
              value,
            )
              ? value
              : [value],
        )
        .filter(
          (
            value,
          ): value is string =>
            typeof value ===
              "string",
        );

    if (
      messages.length >
      0
    ) {
      return messages[0];
    }
  }

  return typeof record.message ===
    "string"
    ? record.message
    : "ذخیره تغییرات انجام نشد.";
}

function normalizeLocalized(
  value:
    string,
): string | null {
  const trimmed =
    value.trim();

  return trimmed
    ? trimmed
    : null;
}

export function HomepageEditor({
  sections,
}: HomepageEditorProps) {
  const [
    drafts,
    setDrafts,
  ] =
    useState<
      Record<
        number,
        DraftSection
      >
    >(() =>
      Object.fromEntries(
        sections.map(
          (section) => [
            section.id,
            {
              ...section,

              image_preview:
                section.image,

              mobile_image_preview:
                section.mobile_image,

              image_dirty:
                false,

              mobile_image_dirty:
                false,
            },
          ],
        ),
      ),
    );

  const [
    saving,
    setSaving,
  ] =
    useState<
      number | null
    >(null);

  const [
    uploading,
    setUploading,
  ] =
    useState<
      string | null
    >(null);

  const [
    messages,
    setMessages,
  ] =
    useState<
      Record<
        number,
        {
          type:
            "success" |
            "error";

          text:
            string;
        }
      >
    >({});

  const visibleSections =
    useMemo(
      () =>
        sections
          .filter(
            (section) =>
              getSectionSpec(
                section,
              ) !== null,
          )
          .sort(
            (
              a,
              b,
            ) => {
              const rank = (
                section:
                  AdminHomepageSection,
              ) => {
                if (
                  section.section_key ===
                  "categories"
                ) {
                  return 10;
                }

                if (
                  section.section_key ===
                  "best_sellers"
                ) {
                  return 20;
                }

                if (
                  section.section_key.startsWith(
                    "services_item_",
                  )
                ) {
                  return 30 +
                    section.sort_order;
                }

                if (
                  section.section_key ===
                  "kitchen_family_header"
                ) {
                  return 40;
                }

                if (
                  section.section_key.startsWith(
                    "kitchen_family_item_",
                  )
                ) {
                  return 50 +
                    section.sort_order;
                }

                if (
                  section.section_key ===
                  "inspiration"
                ) {
                  return 70;
                }

                return 999;
              };

              return (
                rank(a) -
                rank(b)
              );
            },
          ),
      [
        sections,
      ],
    );

  function setLocalized(
    sectionId:
      number,
    field:
      LocalizedField,
    locale:
      HomepageLocale,
    value:
      string,
  ) {
    setDrafts(
      (current) => ({
        ...current,

        [sectionId]: {
          ...current[
            sectionId
          ],

          [field]: {
            ...current[
              sectionId
            ][field],

            [locale]:
              value,
          },
        },
      }),
    );
  }

  function setSimple(
    sectionId:
      number,
    field:
      "link" |
      "is_active",
    value:
      string | boolean,
  ) {
    setDrafts(
      (current) => ({
        ...current,

        [sectionId]: {
          ...current[
            sectionId
          ],

          [field]:
            value,
        },
      }),
    );
  }

  async function uploadImage(
    sectionId:
      number,
    field:
      MediaField,
    file:
      File,
    folder:
      SectionSpec["folder"],
  ) {
    const uploadKey =
      `${sectionId}:${field}`;

    setUploading(
      uploadKey,
    );

    setMessages(
      (current) => {
        const next = {
          ...current,
        };

        delete next[
          sectionId
        ];

        return next;
      },
    );

    try {
      const formData =
        new FormData();

      formData.append(
        "image",
        file,
      );

      formData.append(
        "folder",
        folder ??
          "general",
      );

      const response =
        await fetch(
          "/api/homepage-media",
          {
            method:
              "POST",

            body:
              formData,
          },
        );

      const payload =
        await response
          .json()
          .catch(
            () => null,
          );

      if (
        !response.ok
      ) {
        throw new Error(
          errorMessage(
            payload,
          ),
        );
      }

      const data =
        payload &&
        typeof payload ===
          "object"
          ? (
              payload as {
                data?: {
                  path?:
                    unknown;

                  url?:
                    unknown;
                };
              }
            ).data
          : null;

      if (
        !data ||
        typeof data.path !==
          "string" ||
        typeof data.url !==
          "string"
      ) {
        throw new Error(
          "پاسخ بارگذاری نامعتبر است.",
        );
      }


      const uploadedPath =
        data.path;

      const uploadedUrl =
        data.url;

      setDrafts(
        (current) => {
          const draft =
            current[
              sectionId
            ];

          if (
            field ===
            "image"
          ) {
            return {
              ...current,

              [sectionId]: {
                ...draft,

                image:

                  uploadedPath,

                image_preview:

                  uploadedUrl,

                image_dirty:
                  true,
              },
            };
          }

          return {
            ...current,

            [sectionId]: {
              ...draft,

              mobile_image:

                uploadedPath,

              mobile_image_preview:

                uploadedUrl,

              mobile_image_dirty:
                true,
            },
          };
        },
      );
    } catch (
      caught
    ) {
      setMessages(
        (current) => ({
          ...current,

          [sectionId]: {
            type:
              "error",

            text:
              caught instanceof
                Error
                ? caught.message
                : "بارگذاری تصویر انجام نشد.",
          },
        }),
      );
    } finally {
      setUploading(
        null,
      );
    }
  }

  function removeImage(
    sectionId:
      number,
    field:
      MediaField,
  ) {
    setDrafts(
      (current) => {
        const draft =
          current[
            sectionId
          ];

        if (
          field ===
          "image"
        ) {
          return {
            ...current,

            [sectionId]: {
              ...draft,

              image:
                null,

              image_preview:
                null,

              image_dirty:
                true,
            },
          };
        }

        return {
          ...current,

          [sectionId]: {
            ...draft,

            mobile_image:
              null,

            mobile_image_preview:
              null,

            mobile_image_dirty:
              true,
          },
        };
      },
    );
  }

  async function saveSection(
    section:
      AdminHomepageSection,
  ) {
    const spec =
      getSectionSpec(
        section,
      );

    if (!spec) {
      return;
    }

    const draft =
      drafts[
        section.id
      ];

    setSaving(
      section.id,
    );

    setMessages(
      (current) => {
        const next = {
          ...current,
        };

        delete next[
          section.id
        ];

        return next;
      },
    );

    try {
      const payload:
        Record<
          string,
          unknown
        > = {
          is_active:
            draft.is_active,
        };

      const addLocalized =
        (
          field:
            LocalizedField,
        ) => {
          payload[
            field
          ] = {
            en:
              normalizeLocalized(
                draft[
                  field
                ].en ??
                  "",
              ),

            fa:
              normalizeLocalized(
                draft[
                  field
                ].fa ??
                  "",
              ),
          };
        };

      if (
        spec.title
      ) {
        addLocalized(
          "title",
        );
      }

      if (
        spec.subtitle
      ) {
        addLocalized(
          "subtitle",
        );
      }

      if (
        spec.content
      ) {
        addLocalized(
          "content",
        );
      }

      if (
        spec.button_text
      ) {
        addLocalized(
          "button_text",
        );
      }

      if (
        spec.link
      ) {
        payload.link =
          draft.link
            ?.trim() ||
          null;
      }

      if (
        spec.image &&
        draft.image_dirty
      ) {
        payload.image =
          draft.image;
      }

      if (
        spec.mobile_image &&
        draft.mobile_image_dirty
      ) {
        payload.mobile_image =
          draft.mobile_image;
      }

      const response =
        await fetch(
          `/api/homepage-sections/${section.id}`,
          {
            method:
              "PATCH",

            headers: {
              "Content-Type":
                "application/json",
            },

            body:
              JSON.stringify(
                payload,
              ),
          },
        );

      const result =
        await response
          .json()
          .catch(
            () => null,
          );

      if (
        !response.ok
      ) {
        throw new Error(
          errorMessage(
            result,
          ),
        );
      }

      setDrafts(
        (current) => ({
          ...current,

          [section.id]: {
            ...current[
              section.id
            ],

            image_dirty:
              false,

            mobile_image_dirty:
              false,
          },
        }),
      );

      setMessages(
        (current) => ({
          ...current,

          [section.id]: {
            type:
              "success",

            text:
              "تغییرات ذخیره شد.",
          },
        }),
      );
    } catch (
      caught
    ) {
      setMessages(
        (current) => ({
          ...current,

          [section.id]: {
            type:
              "error",

            text:
              caught instanceof
                Error
                ? caught.message
                : "ذخیره تغییرات انجام نشد.",
          },
        }),
      );
    } finally {
      setSaving(
        null,
      );
    }
  }

  function localizedField(
    section:
      AdminHomepageSection,
    field:
      LocalizedField,
    label:
      string,
    multiline =
      false,
  ) {
    const draft =
      drafts[
        section.id
      ];

    return (
      <div
        className={
          styles.localizedGrid
        }
      >
        <label
          className={
            styles.field
          }
        >
          <span>
            {label} — انگلیسی
          </span>

          {multiline ? (
            <textarea
              value={
                draft[
                  field
                ].en ??
                ""
              }
              onChange={(
                event,
              ) =>
                setLocalized(
                  section.id,
                  field,
                  "en",
                  event.target
                    .value,
                )
              }
            />
          ) : (
            <input
              value={
                draft[
                  field
                ].en ??
                ""
              }
              onChange={(
                event,
              ) =>
                setLocalized(
                  section.id,
                  field,
                  "en",
                  event.target
                    .value,
                )
              }
            />
          )}
        </label>

        <label
          className={
            styles.field
          }
          dir="rtl"
        >
          <span>
            {label} — فارسی
          </span>

          {multiline ? (
            <textarea
              value={
                draft[
                  field
                ].fa ??
                ""
              }
              onChange={(
                event,
              ) =>
                setLocalized(
                  section.id,
                  field,
                  "fa",
                  event.target
                    .value,
                )
              }
            />
          ) : (
            <input
              value={
                draft[
                  field
                ].fa ??
                ""
              }
              onChange={(
                event,
              ) =>
                setLocalized(
                  section.id,
                  field,
                  "fa",
                  event.target
                    .value,
                )
              }
            />
          )}
        </label>
      </div>
    );
  }

  function mediaField(
    section:
      AdminHomepageSection,
    spec:
      SectionSpec,
    field:
      MediaField,
    label:
      string,
  ) {
    const draft =
      drafts[
        section.id
      ];

    const preview =
      field ===
        "image"
        ? draft.image_preview
        : draft.mobile_image_preview;

    const uploadKey =
      `${section.id}:${field}`;

    return (
      <div
        className={
          styles.mediaField
        }
      >
        <div
          className={
            styles.mediaPreview
          }
        >
          {preview ? (
            <img
              src={
                preview
              }
              alt=""
            />
          ) : (
            <span>
              بدون تصویر
            </span>
          )}
        </div>

        <div
          className={
            styles.mediaControls
          }
        >
          <strong>
            {label}
          </strong>

          <label
            className={
              styles.uploadButton
            }
          >
            {uploading ===
            uploadKey
              ? "Uploading..."
              : "تعویض تصویر"}

            <input
              type="file"
              accept="image/jpeg,image/png,image/webp"
              disabled={
                uploading !==
                  null
              }
              onChange={(
                event,
              ) => {
                const file =
                  event.target
                    .files?.[0];

                if (file) {
                  void uploadImage(
                    section.id,
                    field,
                    file,
                    spec.folder,
                  );
                }

                event.target.value =
                  "";
              }}
            />
          </label>

          {preview ? (
            <button
              type="button"
              className={
                styles.removeButton
              }
              onClick={() =>
                removeImage(
                  section.id,
                  field,
                )
              }
            >
              حذف
            </button>
          ) : null}

          <small>
            فرمت JPG، PNG یا WebP؛ حداکثر ۱۰ مگابایت
          </small>
        </div>
      </div>
    );
  }

  return (
    <div
      className={
        styles.editor
      }
    >
      {visibleSections.map(
        (section, index) => {
          const spec =
            getSectionSpec(
              section,
            );

          if (!spec) {
            return null;
          }

          const group =
            getSiteSectionMeta(
              section,
            );

          const previousGroup =
            index > 0
              ? getSiteSectionMeta(
                  visibleSections[
                    index - 1
                  ],
                )
              : null;

          const showGroupHeader =
            group !== null &&
            previousGroup?.key !==
              group.key;

          const draft =
            drafts[
              section.id
            ];

          const message =
            messages[
              section.id
            ];

          return (
            <div
              className={
                styles.sectionItem
              }
              key={
                section.id
              }
            >
              {showGroupHeader &&
              group ? (
                <div
                  className={
                    styles.siteSectionHeader
                  }
                >
                  <div
                    className={
                      styles.siteSectionIndex
                    }
                  >
                    {
                      group.order
                    }
                  </div>

                  <div
                    className={
                      styles.siteSectionCopy
                    }
                  >
                    <span
                      className={
                        styles.siteSectionLabel
                      }
                    >
                      بخش سایت
                    </span>

                    <h2>
                      {
                        group.title
                      }
                    </h2>

                    <p>
                      {
                        group.description
                      }
                    </p>
                  </div>
                </div>
              ) : null}

              <section
                className={
                  styles.card
                }
              >
              <header
                className={
                  styles.cardHeader
                }
              >
                <div>
                  <p
                    className="admin-eyebrow"
                  >
                    {
                      section.section_key
                    }
                  </p>

                  <h2>
                    {
                      spec.label
                    }
                  </h2>

                  <p>
                    {
                      spec.description
                    }
                  </p>
                </div>

                <label
                  className={
                    styles.visibility
                  }
                >
                  <input
                    type="checkbox"
                    checked={
                      draft.is_active
                    }
                    onChange={(
                      event,
                    ) =>
                      setSimple(
                        section.id,
                        "is_active",
                        event.target
                          .checked,
                      )
                    }
                  />

                  <span>
                    قابل نمایش
                  </span>
                </label>
              </header>

              <div
                className={
                  styles.fields
                }
              >
                {spec.subtitle
                  ? localizedField(
                      section,
                      "subtitle",
                      "عنوان کوچک",
                    )
                  : null}

                {spec.title
                  ? localizedField(
                      section,
                      "title",
                      "عنوان",
                    )
                  : null}

                {spec.content
                  ? localizedField(
                      section,
                      "content",
                      "توضیحات",
                      true,
                    )
                  : null}

                {spec.button_text
                  ? localizedField(
                      section,
                      "button_text",
                      "متن دکمه",
                    )
                  : null}

                {spec.link ? (
                  <label
                    className={
                      styles.field
                    }
                  >
                    <span>
                      مقصد / لینک
                    </span>

                    <input
                      value={
                        draft.link ??
                        ""
                      }
                      placeholder="/products"
                      onChange={(
                        event,
                      ) =>
                        setSimple(
                          section.id,
                          "link",
                          event.target
                            .value,
                        )
                      }
                    />
                  </label>
                ) : null}

                {section.section_key === "inspiration" ? (
                    <InspirationVideoField />
                  ) : spec.image ? mediaField(
                      section,
                      spec,
                      "image",
                      "تصویر",
                    ) : null}
              </div>

              <footer
                className={
                  styles.cardFooter
                }
              >
                <div>
                  {message ? (
                    <p
                      className={
                        message.type ===
                        "success"
                          ? styles.success
                          : styles.error
                      }
                    >
                      {
                        message.text
                      }
                    </p>
                  ) : null}
                </div>

                <button
                  type="button"
                  className={
                    styles.saveButton
                  }
                  disabled={
                    saving !==
                      null ||
                    uploading !==
                      null
                  }
                  onClick={() =>
                    void saveSection(
                      section,
                    )
                  }
                >
                  {saving ===
                  section.id
                    ? "در حال ذخیره…"
                    : "ذخیره بخش"}
                </button>
              </footer>
            </section>
            </div>
          );
        },
      )}
    </div>
  );
}