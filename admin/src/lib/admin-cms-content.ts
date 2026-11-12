import "server-only";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export class AdminCmsContentRequestError
  extends Error {
  status: number;

  constructor(
    status: number,
    message: string,
  ) {
    super(message);

    this.name =
      "AdminCmsContentRequestError";

    this.status =
      status;
  }
}

export interface AdminContactPageContent {
  id: number;
  content_key: string;

  eyebrow: string;
  eyebrow_en: string;

  title: string;
  title_en: string;

  instagram_label: string;
  instagram_label_en: string;
  instagram_value:
    | string
    | null;
  instagram_url:
    | string
    | null;

  telegram_label: string;
  telegram_label_en: string;
  telegram_value:
    | string
    | null;
  telegram_url:
    | string
    | null;

  whatsapp_label: string;
  whatsapp_label_en: string;
  whatsapp_value:
    | string
    | null;
  whatsapp_url:
    | string
    | null;

  phone_label: string;
  phone_label_en: string;
  phone_value:
    | string
    | null;
  phone_url:
    | string
    | null;

  is_active: boolean;

  created_at?:
    | string
    | null;

  updated_at?:
    | string
    | null;
}

export interface AdminFaqQuestion {
  id?: number;

  question: string;
  question_en: string;

  answer: string;
  answer_en: string;

  sort_order: number;
  is_active: boolean;
}

export interface AdminFaqGroup {
  id?: number;

  title: string;
  title_en: string;

  sort_order: number;
  is_active: boolean;

  questions:
    AdminFaqQuestion[];
}

export interface AdminFaqContent {
  id: number;
  content_key: string;

  title: string;
  title_en: string;

  intro:
    | string
    | null;

  intro_en:
    | string
    | null;

  is_active: boolean;

  groups:
    AdminFaqGroup[];

  created_at?:
    | string
    | null;

  updated_at?:
    | string
    | null;
}

interface DataResponse<T> {
  data?: T;
  message?: string;
}

function payloadMessage(
  payload: unknown,
): string | null {
  if (
    payload &&
    typeof payload ===
      "object" &&
    "message" in payload &&
    typeof (
      payload as {
        message?: unknown;
      }
    ).message ===
      "string"
  ) {
    return (
      payload as {
        message: string;
      }
    ).message;
  }

  return null;
}

async function loadAdminCmsData<T>(
  path: string,
  fallbackMessage: string,
): Promise<T> {
  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminCmsContentRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  let response: Response;

  try {
    response =
      await adminBackendFetch(
        path,
        {},
        token,
      );
  } catch {
    throw new AdminCmsContentRequestError(
      503,
      "اتصال به بک‌اند برقرار نشد.",
    );
  }

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminCmsContentRequestError(
      response.status,
      payloadMessage(
        payload,
      ) ??
        fallbackMessage,
    );
  }

  if (
    !payload ||
    typeof payload !==
      "object"
  ) {
    throw new AdminCmsContentRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  const data =
    (
      payload as
        DataResponse<T>
    ).data;

  if (
    !data ||
    typeof data !==
      "object"
  ) {
    throw new AdminCmsContentRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return data;
}

export async function getAdminContactPageContent():
Promise<AdminContactPageContent> {
  return loadAdminCmsData<AdminContactPageContent>(
    "/admin/contact-content",
    "دریافت محتوای تماس انجام نشد.",
  );
}

export async function getAdminFaqContent():
Promise<AdminFaqContent> {
  const data =
    await loadAdminCmsData<AdminFaqContent>(
      "/admin/faqs",
      "دریافت پرسش‌های متداول انجام نشد.",
    );

  return {
    ...data,

    groups:
      Array.isArray(
        data.groups,
      )
        ? data.groups.map(
            (group) => ({
              ...group,

              questions:
                Array.isArray(
                  group.questions,
                )
                  ? group.questions
                  : [],
            }),
          )
        : [],
  };
}