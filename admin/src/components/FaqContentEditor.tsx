"use client";

import {
  FormEvent,
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

import type {
  AdminFaqContent,
  AdminFaqGroup,
  AdminFaqQuestion,
} from "@/lib/admin-cms-content";

import type {
  ApiErrorPayload,
  ApiValidationErrors,
} from "@/lib/admin-types";

import styles from "./FaqContentEditor.module.css";

interface Props {
  initialContent:
    AdminFaqContent;
}

interface FaqMutationResponse
  extends ApiErrorPayload {
  data?: AdminFaqContent;
}

function firstError(
  errors: ApiValidationErrors,
  key: string,
): string | null {
  const values =
    errors[key];

  if (
    !values ||
    values.length === 0
  ) {
    return null;
  }

  return values[0] ?? null;
}

function normalizeGroups(
  groups: AdminFaqGroup[],
): AdminFaqGroup[] {
  return groups.map(
    (
      group,
      groupIndex,
    ) => ({
      ...group,

      sort_order:
        groupIndex + 1,

      questions:
        group.questions.map(
          (
            question,
            questionIndex,
          ) => ({
            ...question,

            sort_order:
              questionIndex + 1,
          }),
        ),
    }),
  );
}

function cleanGroups(
  groups: AdminFaqGroup[],
) {
  return normalizeGroups(
    groups,
  ).map(
    (group) => ({
      ...(group.id
        ? {
            id:
              group.id,
          }
        : {}),

      title:
        group.title,

      title_en:
        group.title_en,

      sort_order:
        group.sort_order,

      is_active:
        group.is_active,

      questions:
        group.questions.map(
          (question) => ({
            ...(question.id
              ? {
                  id:
                    question.id,
                }
              : {}),

            question:
              question.question,

            question_en:
              question.question_en,

            answer:
              question.answer,

            answer_en:
              question.answer_en,

            sort_order:
              question.sort_order,

            is_active:
              question.is_active,
          }),
        ),
    }),
  );
}

export function FaqContentEditor({
  initialContent,
}: Props) {
  const router =
    useRouter();

  const [
    title,
    setTitle,
  ] =
    useState(
      initialContent.title ?? "",
    );

  const [
    titleEn,
    setTitleEn,
  ] =
    useState(
      initialContent.title_en ?? "",
    );

  const [
    intro,
    setIntro,
  ] =
    useState(
      initialContent.intro ?? "",
    );

  const [
    introEn,
    setIntroEn,
  ] =
    useState(
      initialContent.intro_en ?? "",
    );

  const [
    isActive,
    setIsActive,
  ] =
    useState(
      Boolean(
        initialContent.is_active,
      ),
    );

  const [
    groups,
    setGroups,
  ] =
    useState<AdminFaqGroup[]>(
      () =>
        normalizeGroups(
          initialContent.groups ?? [],
        ),
    );

  const [
    errors,
    setErrors,
  ] =
    useState<ApiValidationErrors>(
      {},
    );

  const [
    message,
    setMessage,
  ] =
    useState("");

  const [
    submitting,
    setSubmitting,
  ] =
    useState(false);

  function clearMessage() {
    setMessage("");
    setErrors({});
  }

  function updateGroup(
    groupIndex: number,
    patch:
      Partial<AdminFaqGroup>,
  ) {
    clearMessage();

    setGroups(
      (current) =>
        current.map(
          (
            group,
            index,
          ) =>
            index ===
            groupIndex
              ? {
                  ...group,
                  ...patch,
                }
              : group,
        ),
    );
  }

  function addGroup() {
    clearMessage();

    setGroups(
      (current) =>
        normalizeGroups([
          ...current,

          {
            title: "",
            title_en: "",
            sort_order:
              current.length + 1,
            is_active: true,
            questions: [],
          },
        ]),
    );
  }

  function removeGroup(
    groupIndex: number,
  ) {
    if (
      !window.confirm(
        "این گروه و تمام پرسش‌های آن حذف شوند؟",
      )
    ) {
      return;
    }

    clearMessage();

    setGroups(
      (current) =>
        normalizeGroups(
          current.filter(
            (
              _group,
              index,
            ) =>
              index !==
              groupIndex,
          ),
        ),
    );
  }

  function moveGroup(
    groupIndex: number,
    offset: number,
  ) {
    setGroups(
      (current) => {
        const target =
          groupIndex +
          offset;

        if (
          target < 0 ||
          target >=
            current.length
        ) {
          return current;
        }

        const next =
          [...current];

        const [
          selected,
        ] =
          next.splice(
            groupIndex,
            1,
          );

        if (!selected) {
          return current;
        }

        next.splice(
          target,
          0,
          selected,
        );

        return normalizeGroups(
          next,
        );
      },
    );

    clearMessage();
  }

  function updateQuestion(
    groupIndex: number,
    questionIndex: number,
    patch:
      Partial<AdminFaqQuestion>,
  ) {
    clearMessage();

    setGroups(
      (current) =>
        current.map(
          (
            group,
            index,
          ) => {
            if (
              index !==
              groupIndex
            ) {
              return group;
            }

            return {
              ...group,

              questions:
                group.questions.map(
                  (
                    question,
                    qIndex,
                  ) =>
                    qIndex ===
                    questionIndex
                      ? {
                          ...question,
                          ...patch,
                        }
                      : question,
                ),
            };
          },
        ),
    );
  }

  function addQuestion(
    groupIndex: number,
  ) {
    clearMessage();

    setGroups(
      (current) =>
        current.map(
          (
            group,
            index,
          ) => {
            if (
              index !==
              groupIndex
            ) {
              return group;
            }

            return {
              ...group,

              questions:
                normalizeGroups([
                  {
                    ...group,

                    questions: [
                      ...group.questions,

                      {
                        question:
                          "",

                        question_en:
                          "",

                        answer:
                          "",

                        answer_en:
                          "",

                        sort_order:
                          group.questions
                            .length +
                          1,

                        is_active:
                          true,
                      },
                    ],
                  },
                ])[0]
                  ?.questions ??
                group.questions,
            };
          },
        ),
    );
  }

  function removeQuestion(
    groupIndex: number,
    questionIndex: number,
  ) {
    if (
      !window.confirm(
        "این پرسش حذف شود؟",
      )
    ) {
      return;
    }

    clearMessage();

    setGroups(
      (current) =>
        normalizeGroups(
          current.map(
            (
              group,
              index,
            ) => {
              if (
                index !==
                groupIndex
              ) {
                return group;
              }

              return {
                ...group,

                questions:
                  group.questions.filter(
                    (
                      _question,
                      qIndex,
                    ) =>
                      qIndex !==
                      questionIndex,
                  ),
              };
            },
          ),
        ),
    );
  }

  function moveQuestion(
    groupIndex: number,
    questionIndex: number,
    offset: number,
  ) {
    setGroups(
      (current) =>
        normalizeGroups(
          current.map(
            (
              group,
              index,
            ) => {
              if (
                index !==
                groupIndex
              ) {
                return group;
              }

              const target =
                questionIndex +
                offset;

              if (
                target < 0 ||
                target >=
                  group.questions
                    .length
              ) {
                return group;
              }

              const questions =
                [
                  ...group.questions,
                ];

              const [
                selected,
              ] =
                questions.splice(
                  questionIndex,
                  1,
                );

              if (!selected) {
                return group;
              }

              questions.splice(
                target,
                0,
                selected,
              );

              return {
                ...group,
                questions,
              };
            },
          ),
        ),
    );

    clearMessage();
  }

  async function handleSubmit(
    event:
      FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    if (submitting) {
      return;
    }

    setSubmitting(true);
    setMessage("");
    setErrors({});

    try {
      const response =
        await fetch(
          "/api/faqs",
          {
            method: "PUT",

            headers: {
              Accept:
                "application/json",

              "Content-Type":
                "application/json",
            },

            body:
              JSON.stringify({
                title,
                title_en:
                  titleEn,

                intro:
                  intro.trim() ===
                  ""
                    ? null
                    : intro,

                intro_en:
                  introEn.trim() ===
                  ""
                    ? null
                    : introEn,

                is_active:
                  isActive,

                groups:
                  cleanGroups(
                    groups,
                  ),
              }),
          },
        );

      const payload =
        await response
          .json()
          .catch(
            () => null,
          ) as
            FaqMutationResponse |
            null;

      if (!response.ok) {
        if (
          payload?.errors &&
          typeof payload.errors ===
            "object"
        ) {
          setErrors(
            payload.errors,
          );
        }

        setMessage(
          payload?.message ??
            "ذخیره پرسش‌های متداول انجام نشد.",
        );

        return;
      }

      if (payload?.data) {
        setTitle(
          payload.data.title ??
            "",
        );

        setTitleEn(
          payload.data
            .title_en ??
            "",
        );

        setIntro(
          payload.data.intro ??
            "",
        );

        setIntroEn(
          payload.data
            .intro_en ??
            "",
        );

        setIsActive(
          Boolean(
            payload.data
              .is_active,
          ),
        );

        setGroups(
          normalizeGroups(
            payload.data
              .groups ??
              [],
          ),
        );
      }

      setMessage(
        payload?.message ??
          "پرسش‌های متداول ذخیره شد.",
      );

      router.refresh();
    } catch {
      setMessage(
        "اتصال به سرور برقرار نشد.",
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form
      className={
        styles.form
      }
      onSubmit={
        handleSubmit
      }
    >
      {message ? (
        <div
          className={
            styles.notice
          }
          data-error={
            Object.keys(
              errors,
            ).length > 0
          }
        >
          {message}
        </div>
      ) : null}

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.sectionHeader
          }
        >
          <div>
            <p>
              بخش پرسش‌های متداول
            </p>

            <h2>
              محتوای اصلی
            </h2>
          </div>

          <span>
            این محتوا در صفحه پرسش‌های متداول و بخش پرسش‌های صفحه تماس استفاده می‌شود.
          </span>
        </div>

        <div
          className={
            styles.grid
          }
        >
          <label
            className={
              styles.field
            }
          >
            <span>
              عنوان — فارسی
            </span>

            <input
              dir="rtl"
              value={title}
              onChange={(
                event,
              ) => {
                setTitle(
                  event.target
                    .value,
                );

                clearMessage();
              }}
            />

            {firstError(
              errors,
              "title",
            ) ? (
              <small
                className={
                  styles.fieldError
                }
              >
                {firstError(
                  errors,
                  "title",
                )}
              </small>
            ) : null}
          </label>

          <label
            className={
              styles.field
            }
          >
            <span>
              عنوان — انگلیسی
            </span>

            <input
              value={
                titleEn
              }
              onChange={(
                event,
              ) => {
                setTitleEn(
                  event.target
                    .value,
                );

                clearMessage();
              }}
            />

            {firstError(
              errors,
              "title_en",
            ) ? (
              <small
                className={
                  styles.fieldError
                }
              >
                {firstError(
                  errors,
                  "title_en",
                )}
              </small>
            ) : null}
          </label>

          <label
            className={
              styles.field
            }
          >
            <span>
              مقدمه — فارسی
            </span>

            <textarea
              dir="rtl"
              rows={4}
              value={intro}
              onChange={(
                event,
              ) => {
                setIntro(
                  event.target
                    .value,
                );

                clearMessage();
              }}
            />
          </label>

          <label
            className={
              styles.field
            }
          >
            <span>
              مقدمه — انگلیسی
            </span>

            <textarea
              rows={4}
              value={
                introEn
              }
              onChange={(
                event,
              ) => {
                setIntroEn(
                  event.target
                    .value,
                );

                clearMessage();
              }}
            />
          </label>
        </div>
      </section>

      <div
        className={
          styles.groupsHeader
        }
      >
        <div>
          <p
            className={
              styles.kicker
            }
          >
            ساختار
          </p>

          <h2>
            گروه‌های پرسش‌ها
          </h2>
        </div>

        <button
          type="button"
          className="admin-button"
          onClick={
            addGroup
          }
        >
          افزودن گروه
        </button>
      </div>

      {groups.length ===
      0 ? (
        <div
          className={
            styles.empty
          }
        >
          گروهی وجود ندارد؛ برای شروع گروه اضافه کنید.
        </div>
      ) : null}

      {groups.map(
        (
          group,
          groupIndex,
        ) => (
          <section
            key={
              group.id ??
              `new-group-${groupIndex}`
            }
            className={
              styles.group
            }
          >
            <div
              className={
                styles.groupToolbar
              }
            >
              <div>
                <span
                  className={
                    styles.order
                  }
                >
                  گروه{" "}
                  {groupIndex +
                    1}
                </span>

                <strong>
                  {group.title_en ||
                    group.title ||
                    "گروه بدون عنوان"}
                </strong>
              </div>

              <div
                className={
                  styles.actions
                }
              >
                <button
                  type="button"
                  className="admin-button"
                  disabled={
                    groupIndex ===
                    0
                  }
                  onClick={() =>
                    moveGroup(
                      groupIndex,
                      -1,
                    )
                  }
                >
                  بالا
                </button>

                <button
                  type="button"
                  className="admin-button"
                  disabled={
                    groupIndex ===
                    groups.length -
                      1
                  }
                  onClick={() =>
                    moveGroup(
                      groupIndex,
                      1,
                    )
                  }
                >
                  پایین
                </button>

                <button
                  type="button"
                  className={
                    styles.dangerButton
                  }
                  onClick={() =>
                    removeGroup(
                      groupIndex,
                    )
                  }
                >
                  حذف
                </button>
              </div>
            </div>

            <div
              className={
                styles.grid
              }
            >
              <label
                className={
                  styles.field
                }
              >
                <span>
                  عنوان گروه — فارسی
                </span>

                <input
                  dir="rtl"
                  value={
                    group.title
                  }
                  onChange={(
                    event,
                  ) =>
                    updateGroup(
                      groupIndex,
                      {
                        title:
                          event
                            .target
                            .value,
                      },
                    )
                  }
                />

                {firstError(
                  errors,
                  `groups.${groupIndex}.title`,
                ) ? (
                  <small
                    className={
                      styles.fieldError
                    }
                  >
                    {firstError(
                      errors,
                      `groups.${groupIndex}.title`,
                    )}
                  </small>
                ) : null}
              </label>

              <label
                className={
                  styles.field
                }
              >
                <span>
                  عنوان گروه — انگلیسی
                </span>

                <input
                  value={
                    group.title_en
                  }
                  onChange={(
                    event,
                  ) =>
                    updateGroup(
                      groupIndex,
                      {
                        title_en:
                          event
                            .target
                            .value,
                      },
                    )
                  }
                />

                {firstError(
                  errors,
                  `groups.${groupIndex}.title_en`,
                ) ? (
                  <small
                    className={
                      styles.fieldError
                    }
                  >
                    {firstError(
                      errors,
                      `groups.${groupIndex}.title_en`,
                    )}
                  </small>
                ) : null}
              </label>
            </div>

            <div
              className={
                styles.activeRow
              }
            >
              <label
                className={
                  styles.switch
                }
              >
                <input
                  type="checkbox"
                  checked={
                    group.is_active
                  }
                  onChange={(
                    event,
                  ) =>
                    updateGroup(
                      groupIndex,
                      {
                        is_active:
                          event
                            .target
                            .checked,
                      },
                    )
                  }
                />

                <span>
                  {group.is_active
                    ? "گروه فعال"
                    : "گروه غیرفعال"}
                </span>
              </label>

              <button
                type="button"
                className="admin-button admin-button-primary"
                onClick={() =>
                  addQuestion(
                    groupIndex,
                  )
                }
              >
                افزودن پرسش
              </button>
            </div>

            <div
              className={
                styles.questions
              }
            >
              {group.questions
                .length ===
              0 ? (
                <div
                  className={
                    styles.emptyQuestion
                  }
                >
                  این گروه پرسشی ندارد.
                </div>
              ) : null}

              {group.questions.map(
                (
                  question,
                  questionIndex,
                ) => (
                  <article
                    key={
                      question.id ??
                      `new-question-${groupIndex}-${questionIndex}`
                    }
                    className={
                      styles.question
                    }
                  >
                    <div
                      className={
                        styles.questionHeader
                      }
                    >
                      <strong>
                        پرسش{" "}
                        {questionIndex +
                          1}
                      </strong>

                      <div
                        className={
                          styles.actions
                        }
                      >
                        <button
                          type="button"
                          className="admin-button"
                          disabled={
                            questionIndex ===
                            0
                          }
                          onClick={() =>
                            moveQuestion(
                              groupIndex,
                              questionIndex,
                              -1,
                            )
                          }
                        >
                          بالا
                        </button>

                        <button
                          type="button"
                          className="admin-button"
                          disabled={
                            questionIndex ===
                            group
                              .questions
                              .length -
                              1
                          }
                          onClick={() =>
                            moveQuestion(
                              groupIndex,
                              questionIndex,
                              1,
                            )
                          }
                        >
                          پایین
                        </button>

                        <button
                          type="button"
                          className={
                            styles.dangerButton
                          }
                          onClick={() =>
                            removeQuestion(
                              groupIndex,
                              questionIndex,
                            )
                          }
                        >
                          حذف
                        </button>
                      </div>
                    </div>

                    <div
                      className={
                        styles.grid
                      }
                    >
                      <label
                        className={
                          styles.field
                        }
                      >
                        <span>
                          پرسش — فارسی
                        </span>

                        <input
                          dir="rtl"
                          value={
                            question.question
                          }
                          onChange={(
                            event,
                          ) =>
                            updateQuestion(
                              groupIndex,
                              questionIndex,
                              {
                                question:
                                  event
                                    .target
                                    .value,
                              },
                            )
                          }
                        />

                        {firstError(
                          errors,
                          `groups.${groupIndex}.questions.${questionIndex}.question`,
                        ) ? (
                          <small
                            className={
                              styles.fieldError
                            }
                          >
                            {firstError(
                              errors,
                              `groups.${groupIndex}.questions.${questionIndex}.question`,
                            )}
                          </small>
                        ) : null}
                      </label>

                      <label
                        className={
                          styles.field
                        }
                      >
                        <span>
                          پرسش — انگلیسی
                        </span>

                        <input
                          value={
                            question.question_en
                          }
                          onChange={(
                            event,
                          ) =>
                            updateQuestion(
                              groupIndex,
                              questionIndex,
                              {
                                question_en:
                                  event
                                    .target
                                    .value,
                              },
                            )
                          }
                        />

                        {firstError(
                          errors,
                          `groups.${groupIndex}.questions.${questionIndex}.question_en`,
                        ) ? (
                          <small
                            className={
                              styles.fieldError
                            }
                          >
                            {firstError(
                              errors,
                              `groups.${groupIndex}.questions.${questionIndex}.question_en`,
                            )}
                          </small>
                        ) : null}
                      </label>

                      <label
                        className={
                          styles.field
                        }
                      >
                        <span>
                          پاسخ — فارسی
                        </span>

                        <textarea
                          dir="rtl"
                          rows={5}
                          value={
                            question.answer
                          }
                          onChange={(
                            event,
                          ) =>
                            updateQuestion(
                              groupIndex,
                              questionIndex,
                              {
                                answer:
                                  event
                                    .target
                                    .value,
                              },
                            )
                          }
                        />

                        {firstError(
                          errors,
                          `groups.${groupIndex}.questions.${questionIndex}.answer`,
                        ) ? (
                          <small
                            className={
                              styles.fieldError
                            }
                          >
                            {firstError(
                              errors,
                              `groups.${groupIndex}.questions.${questionIndex}.answer`,
                            )}
                          </small>
                        ) : null}
                      </label>

                      <label
                        className={
                          styles.field
                        }
                      >
                        <span>
                          پاسخ — انگلیسی
                        </span>

                        <textarea
                          rows={5}
                          value={
                            question.answer_en
                          }
                          onChange={(
                            event,
                          ) =>
                            updateQuestion(
                              groupIndex,
                              questionIndex,
                              {
                                answer_en:
                                  event
                                    .target
                                    .value,
                              },
                            )
                          }
                        />

                        {firstError(
                          errors,
                          `groups.${groupIndex}.questions.${questionIndex}.answer_en`,
                        ) ? (
                          <small
                            className={
                              styles.fieldError
                            }
                          >
                            {firstError(
                              errors,
                              `groups.${groupIndex}.questions.${questionIndex}.answer_en`,
                            )}
                          </small>
                        ) : null}
                      </label>
                    </div>

                    <label
                      className={
                        styles.switch
                      }
                    >
                      <input
                        type="checkbox"
                        checked={
                          question.is_active
                        }
                        onChange={(
                          event,
                        ) =>
                          updateQuestion(
                            groupIndex,
                            questionIndex,
                            {
                              is_active:
                                event
                                  .target
                                  .checked,
                            },
                          )
                        }
                      />

                      <span>
                        {question.is_active
                          ? "پرسش فعال"
                          : "پرسش غیرفعال"}
                      </span>
                    </label>
                  </article>
                ),
              )}
            </div>
          </section>
        ),
      )}

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.statusRow
          }
        >
          <div>
            <strong>
              انتشار پرسش‌های متداول فعال باشد
            </strong>

            <p>
              با غیرفعال کردن این گزینه، این محتوا در سایت منتشر نمی‌شود.
            </p>
          </div>

          <label
            className={
              styles.switch
            }
          >
            <input
              type="checkbox"
              checked={
                isActive
              }
              onChange={(
                event,
              ) => {
                setIsActive(
                  event.target
                    .checked,
                );

                clearMessage();
              }}
            />

            <span>
              {isActive
                ? "فعال"
                : "غیرفعال"}
            </span>
          </label>
        </div>
      </section>

      <div
        className={
          styles.footer
        }
      >
        <button
          type="submit"
          className="admin-button admin-button-primary"
          disabled={
            submitting
          }
        >
          {submitting
            ? "در حال ذخیره…"
            : "ذخیره پرسش‌های متداول"}
        </button>
      </div>
    </form>
  );
}