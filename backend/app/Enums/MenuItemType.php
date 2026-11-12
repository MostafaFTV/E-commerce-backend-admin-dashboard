<?php

namespace App\Enums;

enum MenuItemType: string
{
    case URL = 'url';
    case CATEGORY = 'category';
    case PRODUCT = 'product';
    case COLLECTION = 'collection';
    case PAGE = 'page';

    public function label(): string
    {
        return match ($this) {
            self::URL => 'URL',
            self::CATEGORY => 'Category',
            self::PRODUCT => 'Product',
            self::COLLECTION => 'Collection',
            self::PAGE => 'Page',
        };
    }
}