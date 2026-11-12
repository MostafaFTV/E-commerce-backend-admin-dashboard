import "server-only";

import {
  adminBackendFetch,
  getAdminToken,
  readBackendPayload,
} from "@/lib/admin-server";

export type HomepageLocale =
  | "en"
  | "fa";

export interface HomepageLocalizedValue {
  en:
    string | null;

  fa:
    string | null;
}

interface HomepageSectionLocaleResponse {
  id:
    number;

  section_key:
    string;

  title:
    string | null;

  subtitle:
    string | null;

  content:
    string | null;

  image:
    string | null;

  mobile_image:
    string | null;

  link:
    string | null;

  button_text:
    string | null;

  is_active:
    boolean;

  sort_order:
    number;
}

export interface AdminHomepageSection {
  id:
    number;

  section_key:
    string;

  title:
    HomepageLocalizedValue;

  subtitle:
    HomepageLocalizedValue;

  content:
    HomepageLocalizedValue;

  button_text:
    HomepageLocalizedValue;

  image:
    string | null;

  mobile_image:
    string | null;

  link:
    string | null;

  is_active:
    boolean;

  sort_order:
    number;
}

export class AdminHomepageRequestError
  extends Error {
  constructor(
    public readonly status:
      number,
    message:
      string,
  ) {
    super(message);

    this.name =
      "AdminHomepageRequestError";
  }
}

function getMessage(
  payload:
    unknown,
): string | null {
  if (
    !payload ||
    typeof payload !==
      "object"
  ) {
    return null;
  }

  const message =
    (
      payload as {
        message?:
          unknown;
      }
    ).message;

  return typeof message ===
    "string"
    ? message
    : null;
}

async function getLocaleSections(
  locale:
    HomepageLocale,
): Promise<
  HomepageSectionLocaleResponse[]
> {
  const token =
    await getAdminToken();

  if (!token) {
    throw new AdminHomepageRequestError(
      401,
      "ابتدا وارد حساب مدیریت شوید.",
    );
  }

  const response =
    await adminBackendFetch(
      `/admin/homepage-sections?locale=${locale}&per_page=100`,
      {},
      token,
    );

  const payload =
    await readBackendPayload(
      response,
    );

  if (!response.ok) {
    throw new AdminHomepageRequestError(
      response.status,
      getMessage(
        payload,
      ) ??
        "دریافت بخش‌های صفحه اصلی انجام نشد.",
    );
  }

  const data =
    payload &&
    typeof payload ===
      "object"
      ? (
          payload as {
            data?:
              unknown;
          }
        ).data
      : null;

  if (
    !Array.isArray(
      data,
    )
  ) {
    throw new AdminHomepageRequestError(
      502,
      "پاسخ دریافتی نامعتبر است.",
    );
  }

  return data as
    HomepageSectionLocaleResponse[];
}

export async function getAdminHomepageSections():
Promise<AdminHomepageSection[]> {
  const [
    english,
    persian,
  ] =
    await Promise.all([
      getLocaleSections(
        "en",
      ),

      getLocaleSections(
        "fa",
      ),
    ]);

  const persianById =
    new Map(
      persian.map(
        (section) => [
          section.id,
          section,
        ],
      ),
    );

  return english.map(
    (section) => {
      const fa =
        persianById.get(
          section.id,
        );

      return {
        id:
          section.id,

        section_key:
          section.section_key,

        title: {
          en:
            section.title,

          fa:
            fa?.title ??
            null,
        },

        subtitle: {
          en:
            section.subtitle,

          fa:
            fa?.subtitle ??
            null,
        },

        content: {
          en:
            section.content,

          fa:
            fa?.content ??
            null,
        },

        button_text: {
          en:
            section.button_text,

          fa:
            fa?.button_text ??
            null,
        },

        image:
          section.image,

        mobile_image:
          section.mobile_image,

        link:
          section.link,

        is_active:
          section.is_active,

        sort_order:
          section.sort_order,
      };
    },
  );
}