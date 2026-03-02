<?php

class TamaraConfiguration
{
    private const PREFIX = 'TAMARAPRESTASHOP_';

    private const MAP = [
        'enable_plugin'       => 'ENABLE_PLUGIN',
        'mode'                => 'MODE',
        'public_key'          => 'PUBLIC_KEY',
        'api_token'           => 'API_TOKEN',
        'not_url'             => 'NOT_URL',
        'product_widget_pos'  => 'PRODUCT_WIDGET_POS',
        'cart_widget_pos'     => 'CART_WIDGET_POS',
    ];

    public static function get(string $key, $default = null)
    {
        $prefixed = self::PREFIX . (self::MAP[$key] ?? strtoupper($key));

        // auto-migrate legacy keys
        if (Configuration::hasKey($key) && !Configuration::hasKey($prefixed)) {
            Configuration::updateValue($prefixed, Configuration::get($key));
            Configuration::deleteByName($key);
        }

        return Configuration::get($prefixed, null, null, null, $default);
    }

    public static function set(string $key, $value): void
    {
        $prefixed = self::PREFIX . (self::MAP[$key] ?? strtoupper($key));
        Configuration::updateValue($prefixed, $value);
    }
}
