"use client";

import {
  FormEvent,
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

import type {
  AdminStore,
  AdminStoreOpeningHours,
} from "@/lib/admin-stores";

import styles from "./StoreForm.module.css";

interface StoreFormProps {
  initialStore?:
    AdminStore | null;
}

interface ErrorPayload {
  message?:
    string;

  errors?: {
    [key: string]:
      | string[]
      | undefined;
  };
}

function firstError(
  errors:
    ErrorPayload["errors"],
): string | null {
  if (!errors) {
    return null;
  }

  for (
    const messages
    of Object.values(
      errors,
    )
  ) {
    if (
      Array.isArray(
        messages,
      ) &&
      messages.length >
        0
    ) {
      return (
        messages[0] ??
        null
      );
    }
  }

  return null;
}

function openingHoursToText(
  value:
    AdminStoreOpeningHours,
): string {
  if (!value) {
    return "";
  }

  if (
    Array.isArray(
      value,
    )
  ) {
    return value.join(
      "\n",
    );
  }

  return Object.entries(
    value,
  )
    .map(
      ([day, period]) =>
        `${day}: ${period}`,
    )
    .join(
      "\n",
    );
}

function textToOpeningHours(
  value:
    string,
): string[] | null {
  const lines =
    value
      .split(/\r?\n/)
      .map(
        (line) =>
          line.trim(),
      )
      .filter(Boolean);

  return lines.length
    ? lines
    : null;
}

function nullableText(
  value:
    string,
): string | null {
  const cleaned =
    value.trim();

  return cleaned
    ? cleaned
    : null;
}

export function StoreForm({
  initialStore =
    null,
}: StoreFormProps) {
  const router =
    useRouter();

  const editing =
    Boolean(
      initialStore,
    );

  const [
    name,
    setName,
  ] =
    useState(
      initialStore?.name ??
      "",
    );

  const [
    nameEn,
    setNameEn,
  ] =
    useState(
      initialStore?.name_en ??
      "",
    );

  const [
    slug,
    setSlug,
  ] =
    useState(
      initialStore?.slug ??
      "",
    );

  const [
    description,
    setDescription,
  ] =
    useState(
      initialStore?.description ??
      "",
    );

  const [
    descriptionEn,
    setDescriptionEn,
  ] =
    useState(
      initialStore?.description_en ??
      "",
    );

  const [
    address,
    setAddress,
  ] =
    useState(
      initialStore?.address ??
      "",
    );

  const [
    addressEn,
    setAddressEn,
  ] =
    useState(
      initialStore?.address_en ??
      "",
    );

  const [
    city,
    setCity,
  ] =
    useState(
      initialStore?.city ??
      "",
    );

  const [
    cityEn,
    setCityEn,
  ] =
    useState(
      initialStore?.city_en ??
      "",
    );

  const [
    floorUnit,
    setFloorUnit,
  ] =
    useState(
      initialStore?.floor_unit ??
      "",
    );

  const [
    floorUnitEn,
    setFloorUnitEn,
  ] =
    useState(
      initialStore?.floor_unit_en ??
      "",
    );

  const [
    phone,
    setPhone,
  ] =
    useState(
      initialStore?.phone ??
      "",
    );

  const [
    email,
    setEmail,
  ] =
    useState(
      initialStore?.email ??
      "",
    );

  const [
    latitude,
    setLatitude,
  ] =
    useState(
      initialStore?.latitude
        ?.toString() ??
      "",
    );

  const [
    longitude,
    setLongitude,
  ] =
    useState(
      initialStore?.longitude
        ?.toString() ??
      "",
    );

  const [
    openingHours,
    setOpeningHours,
  ] =
    useState(
      openingHoursToText(
        initialStore?.opening_hours ??
        null,
      ),
    );

  const [
    openingHoursEn,
    setOpeningHoursEn,
  ] =
    useState(
      openingHoursToText(
        initialStore?.opening_hours_en ??
        null,
      ),
    );

  const [
    image,
    setImage,
  ] =
    useState(
      initialStore?.image ??
      "",
    );

  const [
    sortOrder,
    setSortOrder,
  ] =
    useState(
      (
        initialStore?.sort_order ??
        0
      ).toString(),
    );

  const [
    isActive,
    setIsActive,
  ] =
    useState(
      initialStore?.is_active ??
      true,
    );

  const [
    submitting,
    setSubmitting,
  ] =
    useState(false);

  const [
    error,
    setError,
  ] =
    useState<
      string | null
    >(null);

  async function handleSubmit(
    event:
      FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    if (submitting) {
      return;
    }

    setError(
      null,
    );

    const cleanName =
      name.trim();

    const cleanAddress =
      address.trim();

    const cleanCity =
      city.trim();

    const cleanSlug =
      slug.trim();

    if (!cleanName) {
      setError(
        "نام فارسی شعبه الزامی است.",
      );

      return;
    }

    if (!cleanAddress) {
      setError(
        "آدرس فارسی الزامی است.",
      );

      return;
    }

    if (!cleanCity) {
      setError(
        "نام فارسی شهر الزامی است.",
      );

      return;
    }

    if (
      cleanSlug &&
      !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(
        cleanSlug,
      )
    ) {
      setError(
        "شناسه متنی فقط می‌تواند شامل حروف کوچک انگلیسی، عدد و خط تیره باشد.",
      );

      return;
    }

    if (
      editing &&
      !cleanSlug
    ) {
      setError(
        "شناسه متنی هنگام ویرایش شعبه الزامی است.",
      );

      return;
    }

    const order =
      Number(
        sortOrder,
      );

    if (
      !Number.isInteger(
        order,
      ) ||
      order < 0 ||
      order > 10000
    ) {
      setError(
        "ترتیب نمایش باید عدد صحیح بین ۰ و ۱۰۰۰۰ باشد.",
      );

      return;
    }

    const lat =
      latitude.trim()
        ? Number(latitude)
        : null;

    const lng =
      longitude.trim()
        ? Number(longitude)
        : null;

    if (
      lat !== null &&
      (
        !Number.isFinite(
          lat,
        ) ||
        lat < -90 ||
        lat > 90
      )
    ) {
      setError(
        "عرض جغرافیایی باید بین ۹۰- و ۹۰ باشد.",
      );

      return;
    }

    if (
      lng !== null &&
      (
        !Number.isFinite(
          lng,
        ) ||
        lng < -180 ||
        lng > 180
      )
    ) {
      setError(
        "طول جغرافیایی باید بین ۱۸۰- و ۱۸۰ باشد.",
      );

      return;
    }

    const payload = {
      name:
        cleanName,

      name_en:
        nullableText(
          nameEn,
        ),

      slug:
        cleanSlug ||
        null,

      description:
        nullableText(
          description,
        ),

      description_en:
        nullableText(
          descriptionEn,
        ),

      address:
        cleanAddress,

      address_en:
        nullableText(
          addressEn,
        ),

      city:
        cleanCity,

      city_en:
        nullableText(
          cityEn,
        ),

      floor_unit:
        nullableText(
          floorUnit,
        ),

      floor_unit_en:
        nullableText(
          floorUnitEn,
        ),

      phone:
        nullableText(
          phone,
        ),

      email:
        nullableText(
          email,
        ),

      latitude:
        lat,

      longitude:
        lng,

      opening_hours:
        textToOpeningHours(
          openingHours,
        ),

      opening_hours_en:
        textToOpeningHours(
          openingHoursEn,
        ),

      image:
        nullableText(
          image,
        ),

      is_active:
        isActive,

      sort_order:
        order,
    };

    setSubmitting(
      true,
    );

    try {
      const url =
        editing &&
        initialStore
          ? `/api/stores/${initialStore.id}`
          : "/api/stores";

      const response =
        await fetch(
          url,
          {
            method:
              editing
                ? "PUT"
                : "POST",

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
        (
          await response
            .json()
            .catch(
              () => null,
            )
        ) as
          | ErrorPayload
          | null;

      if (!response.ok) {
        setError(
          firstError(
            result?.errors,
          ) ??
            result?.message ??
            "ذخیره شعبه انجام نشد.",
        );

        return;
      }

      router.push(
        "/stores",
      );

      router.refresh();
    } catch {
      setError(
        "ذخیره شعبه انجام نشد.",
      );
    } finally {
      setSubmitting(
        false,
      );
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
      {error ? (
        <div
          className={
            styles.errorBox
          }
        >
          {error}
        </div>
      ) : null}

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.sectionHead
          }
        >
          <div>
            <span>
              محتوای فارسی
            </span>

            <h2>
              اطلاعات شعبه
            </h2>
          </div>

          <p>
            محتوای نمایشی صفحه تماس فارسی
          </p>
        </div>

        <div
          className={
            styles.grid
          }
        >
          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="store-name"
            >
              نام
            </label>

            <input
              id="store-name"
              type="text"
              dir="rtl"
              maxLength={150}
              required
              value={
                name
              }
              onChange={
                (event) =>
                  setName(
                    event.target.value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="store-city"
            >
              شهر
            </label>

            <input
              id="store-city"
              type="text"
              dir="rtl"
              maxLength={100}
              required
              value={
                city
              }
              onChange={
                (event) =>
                  setCity(
                    event.target.value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="store-floor"
            >
              طبقه و واحد
            </label>

            <input
              id="store-floor"
              type="text"
              dir="rtl"
              maxLength={150}
              value={
                floorUnit
              }
              onChange={
                (event) =>
                  setFloorUnit(
                    event.target.value,
                  )
              }
            />
          </div>
        </div>

        <div
          className={
            styles.field
          }
        >
          <label
            htmlFor="store-address"
          >
            آدرس
          </label>

          <textarea
            id="store-address"
            dir="rtl"
            required
            value={
              address
            }
            onChange={
              (event) =>
                setAddress(
                  event.target.value,
                )
            }
          />
        </div>

        <div
          className={
            styles.field
          }
        >
          <label
            htmlFor="store-description"
          >
            توضیحات
          </label>

          <textarea
            id="store-description"
            dir="rtl"
            value={
              description
            }
            onChange={
              (event) =>
                setDescription(
                  event.target.value,
                )
            }
          />
        </div>

        <div
          className={
            styles.field
          }
        >
          <label
            htmlFor="store-hours"
          >
            ساعات کاری
          </label>

          <textarea
            id="store-hours"
            dir="rtl"
            value={
              openingHours
            }
            placeholder={"شنبه تا پنج‌شنبه: ۸:۰۰ تا ۲۰:۰۰\nجمعه: تعطیل"}
            onChange={
              (event) =>
                setOpeningHours(
                  event.target.value,
                )
            }
          />

          <small>
            هر خط نمایشی را در یک سطر وارد کنید.
          </small>
        </div>
      </section>

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.sectionHead
          }
        >
          <div>
            <span>
              محتوای انگلیسی
            </span>

            <h2>
              ترجمه
            </h2>
          </div>

          <p>
            این مقادیر در صفحه تماس انگلیسی استفاده می‌شوند.
          </p>
        </div>

        <div
          className={
            styles.grid
          }
        >
          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="store-name-en"
            >
              نام
            </label>

            <input
              id="store-name-en"
              type="text"
              maxLength={150}
              value={
                nameEn
              }
              onChange={
                (event) =>
                  setNameEn(
                    event.target.value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="store-city-en"
            >
              شهر
            </label>

            <input
              id="store-city-en"
              type="text"
              maxLength={100}
              value={
                cityEn
              }
              onChange={
                (event) =>
                  setCityEn(
                    event.target.value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="store-floor-en"
            >
              طبقه و واحد
            </label>

            <input
              id="store-floor-en"
              type="text"
              maxLength={150}
              value={
                floorUnitEn
              }
              onChange={
                (event) =>
                  setFloorUnitEn(
                    event.target.value,
                  )
              }
            />
          </div>
        </div>

        <div
          className={
            styles.field
          }
        >
          <label
            htmlFor="store-address-en"
          >
            آدرس
          </label>

          <textarea
            id="store-address-en"
            value={
              addressEn
            }
            onChange={
              (event) =>
                setAddressEn(
                  event.target.value,
                )
            }
          />
        </div>

        <div
          className={
            styles.field
          }
        >
          <label
            htmlFor="store-description-en"
          >
            توضیحات
          </label>

          <textarea
            id="store-description-en"
            value={
              descriptionEn
            }
            onChange={
              (event) =>
                setDescriptionEn(
                  event.target.value,
                )
            }
          />
        </div>

        <div
          className={
            styles.field
          }
        >
          <label
            htmlFor="store-hours-en"
          >
            ساعات کاری
          </label>

          <textarea
            id="store-hours-en"
            value={
              openingHoursEn
            }
            placeholder={"Saturday–Thursday: 08:00–20:00\nFriday: Closed"}
            onChange={
              (event) =>
                setOpeningHoursEn(
                  event.target.value,
                )
            }
          />

          <small>
            هر خط نمایشی را در یک سطر وارد کنید.
          </small>
        </div>
      </section>

      <section
        className={
          styles.section
        }
      >
        <div
          className={
            styles.sectionHead
          }
        >
          <div>
            <span>
              اطلاعات مشترک
            </span>

            <h2>
              تماس و موقعیت
            </h2>
          </div>

          <p>
            اطلاعات مشترک دو زبان و نقشه
          </p>
        </div>

        <div
          className={
            styles.grid
          }
        >
          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="store-slug"
            >
              شناسه متنی
            </label>

            <input
              id="store-slug"
              type="text"
              maxLength={180}
              value={
                slug
              }
              placeholder="tehran"
              onChange={
                (event) =>
                  setSlug(
                    event.target.value
                      .toLowerCase(),
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="store-phone"
            >
              شماره تماس
            </label>

            <input
              id="store-phone"
              type="text"
              maxLength={30}
              value={
                phone
              }
              onChange={
                (event) =>
                  setPhone(
                    event.target.value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="store-email"
            >
              ایمیل
            </label>

            <input
              id="store-email"
              type="email"
              maxLength={255}
              value={
                email
              }
              onChange={
                (event) =>
                  setEmail(
                    event.target.value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="store-latitude"
            >
              عرض جغرافیایی
            </label>

            <input
              id="store-latitude"
              type="number"
              step="any"
              min="-90"
              max="90"
              value={
                latitude
              }
              onChange={
                (event) =>
                  setLatitude(
                    event.target.value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="store-longitude"
            >
              طول جغرافیایی
            </label>

            <input
              id="store-longitude"
              type="number"
              step="any"
              min="-180"
              max="180"
              value={
                longitude
              }
              onChange={
                (event) =>
                  setLongitude(
                    event.target.value,
                  )
              }
            />
          </div>

          <div
            className={
              styles.field
            }
          >
            <label
              htmlFor="store-order"
            >
              ترتیب نمایش
            </label>

            <input
              id="store-order"
              type="number"
              min="0"
              max="10000"
              step="1"
              value={
                sortOrder
              }
              onChange={
                (event) =>
                  setSortOrder(
                    event.target.value,
                  )
              }
            />
          </div>
        </div>

        <div
          className={
            styles.field
          }
        >
          <label
            htmlFor="store-image"
          >
            آدرس تصویر
          </label>

          <input
            id="store-image"
            type="text"
            maxLength={500}
            value={
              image
            }
            placeholder="stores/tehran.webp"
            onChange={
              (event) =>
                setImage(
                  event.target.value,
                )
            }
          />
        </div>
      </section>

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
              شعبه فعال باشد
            </strong>

            <p>
              شعب غیرفعال در پنل باقی می‌مانند و در صفحه تماس نمایش داده نمی‌شوند.
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
              onChange={
                (event) =>
                  setIsActive(
                    event.target.checked,
                  )
              }
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
          type="button"
          className="admin-button"
          disabled={
            submitting
          }
          onClick={
            () =>
              router.push(
                "/stores",
              )
          }
        >
          انصراف
        </button>

        <button
          type="submit"
          className="admin-button admin-button-primary"
          disabled={
            submitting
          }
        >
          {submitting
            ? "در حال ذخیره…"
            : editing
              ? "ذخیره تغییرات"
              : "ایجاد شعبه"}
        </button>
      </div>
    </form>
  );
}