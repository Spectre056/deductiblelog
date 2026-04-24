<?php

declare(strict_types=1);

return [
    'routes' => [
        // Main SPA entry point
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // Charities
        ['name' => 'charity#index',   'url' => '/api/charities',      'verb' => 'GET'],
        ['name' => 'charity#create',  'url' => '/api/charities',      'verb' => 'POST'],
        ['name' => 'charity#update',  'url' => '/api/charities/{id}', 'verb' => 'PUT'],
        ['name' => 'charity#destroy', 'url' => '/api/charities/{id}', 'verb' => 'DELETE'],

        // Family members
        ['name' => 'family_member#index',   'url' => '/api/family-members',      'verb' => 'GET'],
        ['name' => 'family_member#create',  'url' => '/api/family-members',      'verb' => 'POST'],
        ['name' => 'family_member#update',  'url' => '/api/family-members/{id}', 'verb' => 'PUT'],
        ['name' => 'family_member#destroy', 'url' => '/api/family-members/{id}', 'verb' => 'DELETE'],

        // Cash donations
        ['name' => 'cash_donation#index',   'url' => '/api/cash-donations',      'verb' => 'GET'],
        ['name' => 'cash_donation#create',  'url' => '/api/cash-donations',      'verb' => 'POST'],
        ['name' => 'cash_donation#update',  'url' => '/api/cash-donations/{id}', 'verb' => 'PUT'],
        ['name' => 'cash_donation#destroy', 'url' => '/api/cash-donations/{id}', 'verb' => 'DELETE'],

        // Item donations
        ['name' => 'item_donation#index',   'url' => '/api/item-donations',      'verb' => 'GET'],
        ['name' => 'item_donation#create',  'url' => '/api/item-donations',      'verb' => 'POST'],
        ['name' => 'item_donation#update',  'url' => '/api/item-donations/{id}', 'verb' => 'PUT'],
        ['name' => 'item_donation#destroy', 'url' => '/api/item-donations/{id}', 'verb' => 'DELETE'],

        // Item valuation lookup
        ['name' => 'item_category#index',  'url' => '/api/item-categories',       'verb' => 'GET'],
        ['name' => 'item_category#search', 'url' => '/api/item-categories/search','verb' => 'GET'],

        // Mileage logs
        ['name' => 'mileage#index',   'url' => '/api/mileage',        'verb' => 'GET'],
        ['name' => 'mileage#rates',   'url' => '/api/mileage/rates',  'verb' => 'GET'],
        ['name' => 'mileage#create',  'url' => '/api/mileage',        'verb' => 'POST'],
        ['name' => 'mileage#update',  'url' => '/api/mileage/{id}',   'verb' => 'PUT'],
        ['name' => 'mileage#destroy', 'url' => '/api/mileage/{id}',   'verb' => 'DELETE'],

        // Medical expenses
        ['name' => 'medical_expense#index',   'url' => '/api/medical-expenses',      'verb' => 'GET'],
        ['name' => 'medical_expense#create',  'url' => '/api/medical-expenses',      'verb' => 'POST'],
        ['name' => 'medical_expense#update',  'url' => '/api/medical-expenses/{id}', 'verb' => 'PUT'],
        ['name' => 'medical_expense#destroy', 'url' => '/api/medical-expenses/{id}', 'verb' => 'DELETE'],

        // Business expenses
        ['name' => 'business_expense#index',   'url' => '/api/business-expenses',      'verb' => 'GET'],
        ['name' => 'business_expense#create',  'url' => '/api/business-expenses',      'verb' => 'POST'],
        ['name' => 'business_expense#update',  'url' => '/api/business-expenses/{id}', 'verb' => 'PUT'],
        ['name' => 'business_expense#destroy', 'url' => '/api/business-expenses/{id}', 'verb' => 'DELETE'],

        // Receipts
        ['name' => 'receipt#index',   'url' => '/api/receipts',      'verb' => 'GET'],
        ['name' => 'receipt#upload',  'url' => '/api/receipts',      'verb' => 'POST'],
        ['name' => 'receipt#show',    'url' => '/api/receipts/{id}', 'verb' => 'GET'],
        ['name' => 'receipt#destroy', 'url' => '/api/receipts/{id}', 'verb' => 'DELETE'],

        // Reports & exports
        ['name' => 'report#summary', 'url' => '/api/reports/summary', 'verb' => 'GET'],
        ['name' => 'report#pdf',     'url' => '/api/reports/pdf',     'verb' => 'GET'],
        ['name' => 'report#csv',     'url' => '/api/reports/csv',     'verb' => 'GET'],
        ['name' => 'report#txf',     'url' => '/api/reports/txf',     'verb' => 'GET'],

        // Settings & tax rate updates
        ['name' => 'settings#index',         'url' => '/api/settings',              'verb' => 'GET'],
        ['name' => 'settings#update',        'url' => '/api/settings',              'verb' => 'PUT'],
        ['name' => 'settings#check_updates', 'url' => '/api/settings/check-updates','verb' => 'POST'],
        ['name' => 'settings#apply_updates', 'url' => '/api/settings/apply-updates','verb' => 'POST'],
    ],
];
