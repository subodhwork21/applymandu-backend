<?php

namespace App\Constants;

class Skills
{
    public const PHP = 'php';
    public const JAVASCRIPT = 'javascript';
    public const HTML = 'html';
    public const CSS = 'css';
    public const LARAVEL = 'laravel';
    public const VUE = 'vue';
    public const REACT = 'react';
    public const MYSQL = 'mysql';
    
    /**
     * Get all skills as an array
     */
    public static function toArray(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return array_values($reflection->getConstants());
    }
    
    /**
     * Get all skills with names as keys and values as values
     */
    public static function toSelectArray(): array
    {
        $reflection = new \ReflectionClass(self::class);
        $constants = $reflection->getConstants();
        
        $result = [];
        foreach ($constants as $name => $value) {
            $result[$value] = ucfirst($value);
        }
        
        return $result;
    }
}