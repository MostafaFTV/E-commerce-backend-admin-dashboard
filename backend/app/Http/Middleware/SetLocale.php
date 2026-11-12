<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $supportedLocales = [
            'en',
            'fa',
        ];

        /*
        |--------------------------------------------------------------------------
        | Locale Resolution Priority
        |--------------------------------------------------------------------------
        |
        | 1. Explicit query parameter: ?locale=fa
        | 2. First supported locale from Accept-Language
        | 3. Application fallback locale
        |
        | The Accept-Language header is intentionally resolved by the order
        | of the languages as provided by the client, not by q-values.
        |
        |--------------------------------------------------------------------------
        */

        $locale = $request->query('locale');

        /*
        |--------------------------------------------------------------------------
        | Query Parameter
        |--------------------------------------------------------------------------
        */

        if (
            $locale !== null
            && is_string($locale)
        ) {
            $locale = strtolower(
                trim($locale)
            );

            /*
             * Normalize values such as:
             * fa-IR -> fa
             * FA-ir -> fa
             * en-US -> en
             */
            foreach ($supportedLocales as $supportedLocale) {
                if (
                    $locale === $supportedLocale
                    || str_starts_with(
                        $locale,
                        $supportedLocale . '-'
                    )
                ) {
                    $locale = $supportedLocale;

                    break;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Accept-Language Header
        |--------------------------------------------------------------------------
        */

        if (
            ! in_array(
                $locale,
                $supportedLocales,
                true
            )
        ) {
            $locale = $this->resolveFromAcceptLanguage(
                $request->header('Accept-Language'),
                $supportedLocales
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Application Fallback
        |--------------------------------------------------------------------------
        */

        if (
            ! in_array(
                $locale,
                $supportedLocales,
                true
            )
        ) {
            $locale = config(
                'app.locale',
                'en'
            );
        }

        if (
            ! in_array(
                $locale,
                $supportedLocales,
                true
            )
        ) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        $direction = $locale === 'fa'
            ? 'rtl'
            : 'ltr';

        $request->attributes->set(
            'locale',
            $locale
        );

        $request->attributes->set(
            'direction',
            $direction
        );

        return $next($request);
    }

    /**
     * Resolve the first supported locale from Accept-Language.
     *
     * Examples:
     *
     * en-US;q=0.8,fa-IR;q=0.9 -> en
     * de-DE,fa-IR;q=0.9,en;q=0.8 -> fa
     * FA-ir -> fa
     */
    private function resolveFromAcceptLanguage(
        ?string $header,
        array $supportedLocales
    ): ?string {
        if (
            $header === null
            || trim($header) === ''
        ) {
            return null;
        }

        $languages = explode(
            ',',
            $header
        );

        foreach ($languages as $language) {
            $language = trim($language);

            if ($language === '') {
                continue;
            }

            /*
             * Remove quality value:
             * en-US;q=0.8 -> en-US
             */
            $language = explode(
                ';',
                $language,
                2
            )[0];

            $language = strtolower(
                trim($language)
            );

            foreach ($supportedLocales as $supportedLocale) {
                if (
                    $language === $supportedLocale
                    || str_starts_with(
                        $language,
                        $supportedLocale . '-'
                    )
                ) {
                    return $supportedLocale;
                }
            }
        }

        return null;
    }
}
