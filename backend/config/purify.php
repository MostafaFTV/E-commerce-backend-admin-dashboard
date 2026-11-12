<?php

use Stevebauman\Purify\Definitions\Html5Definition;

return [

    'default' => 'default',

    'configs' => [

        /*
        |--------------------------------------------------------------------------
        | Default
        |--------------------------------------------------------------------------
        */

        'default' => [
            'Core.Encoding' => 'utf-8',

            'HTML.Doctype' => 'HTML 4.01 Transitional',

            'HTML.Allowed' =>
                'h1,h2,h3,h4,h5,h6,' .
                'b,strong,i,em,u,s,del,' .
                'a[href|title|target|rel],' .
                'ul,ol,li,' .
                'p,' .
                'br,' .
                'span,' .
                'blockquote,' .
                'img[src|width|height|alt|title]',

            'HTML.ForbiddenElements' =>
                'script,style,iframe,object,embed,form,input,button,textarea,select,option,svg,math,link,meta,base',

            'CSS.AllowedProperties' =>
                'font,' .
                'font-size,' .
                'font-weight,' .
                'font-style,' .
                'font-family,' .
                'text-decoration,' .
                'padding-left,' .
                'color,' .
                'background-color,' .
                'text-align',

            'URI.AllowedSchemes' => [
                'http' => true,
                'https' => true,
                'mailto' => true,
            ],

            'URI.DisableExternalResources' => false,

            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Page
        |--------------------------------------------------------------------------
        */

        'page' => [
            'Core.Encoding' => 'utf-8',

            'HTML.Doctype' => 'HTML 4.01 Transitional',

            'HTML.Allowed' =>
                'h1,h2,h3,h4,h5,h6,' .
                'p,' .
                'br,' .
                'strong,b,' .
                'em,i,' .
                'u,' .
                's,del,' .
                'blockquote,' .
                'ul,ol,li,' .
                'span,' .
                'a[href|title|target|rel],' .
                'img[src|width|height|alt|title]',

            'HTML.ForbiddenElements' =>
                'script,style,iframe,object,embed,form,input,button,textarea,select,option,svg,math,link,meta,base',

            'CSS.AllowedProperties' =>
                'font,' .
                'font-size,' .
                'font-weight,' .
                'font-style,' .
                'font-family,' .
                'text-decoration,' .
                'padding-left,' .
                'color,' .
                'background-color,' .
                'text-align',

            'URI.AllowedSchemes' => [
                'http' => true,
                'https' => true,
                'mailto' => true,
            ],

            'URI.DisableExternalResources' => false,

            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Banner
        |--------------------------------------------------------------------------
        |
        | Banner textual fields are plain text and do not need HTML.
        |
        */

        'banner' => [
            'Core.Encoding' => 'utf-8',

            'HTML.Doctype' => 'HTML 4.01 Transitional',

            'HTML.Allowed' => '',

            'HTML.ForbiddenElements' =>
                'script,style,iframe,object,embed,form,input,button,textarea,select,option,svg,math,link,meta,base',

            'URI.AllowedSchemes' => [
                'http' => true,
                'https' => true,
            ],

            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Homepage
        |--------------------------------------------------------------------------
        |
        | Homepage section content may contain limited rich HTML.
        | Only safe presentation-oriented markup is allowed.
        |
        */

        'homepage' => [
            'Core.Encoding' => 'utf-8',

            'HTML.Doctype' => 'HTML 4.01 Transitional',

            'HTML.Allowed' =>
                'h1,h2,h3,h4,h5,h6,' .
                'p,' .
                'br,' .
                'strong,b,' .
                'em,i,' .
                'u,' .
                's,del,' .
                'blockquote,' .
                'ul,ol,li,' .
                'span,' .
                'a[href|title|target|rel],' .
                'img[src|width|height|alt|title]',

            'HTML.ForbiddenElements' =>
                'script,style,iframe,object,embed,form,input,button,textarea,select,option,svg,math,link,meta,base',

            'CSS.AllowedProperties' =>
                'font,' .
                'font-size,' .
                'font-weight,' .
                'font-style,' .
                'font-family,' .
                'text-decoration,' .
                'padding-left,' .
                'color,' .
                'background-color,' .
                'text-align',

            'URI.AllowedSchemes' => [
                'http' => true,
                'https' => true,
            ],

            'URI.DisableExternalResources' => false,

            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => false,
        ],

    ],

    'definitions' => Html5Definition::class,

    'css-definitions' => null,

    'serializer' => [
        'driver' => env(
            'CACHE_STORE',
            env(
                'CACHE_DRIVER',
                'file'
            )
        ),

        'cache' =>
            \Stevebauman\Purify\Cache\CacheDefinitionCache::class,
    ],

];
