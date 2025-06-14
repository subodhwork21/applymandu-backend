<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ForgetCacheHelper
{
    public static function forgetCacheByPrefix($prefix) 
    {
        $store = Cache::getStore();
        
        // Handle Redis cache driver
        if ($store instanceof \Illuminate\Cache\RedisStore) {
            $redis = $store->connection();
            $prefixedPattern = $store->getPrefix() . $prefix . '*';
            
            $keys = $redis->keys($prefixedPattern);
            
            if (!empty($keys)) {
                foreach ($keys as $key) {
                    // Remove the cache prefix to get the actual key
                    $unprefixedKey = str_replace($store->getPrefix(), '', $key);
                    Cache::forget($unprefixedKey);
                }
                
                Log::info('Cleared ' . count($keys) . ' Redis cache keys with prefix: ' . $prefix);
            }
            
            return;
        }
        
        // Handle Memcached cache driver
        if ($store instanceof \Illuminate\Cache\MemcachedStore) {
            // Memcached doesn't support searching by prefix
            // We would need to maintain a separate list of keys
            Log::warning('Clearing cache by prefix is not supported for Memcached. Consider using Redis instead.');
            return;
        }
        
        // Handle File cache driver
        if ($store instanceof \Illuminate\Cache\FileStore) {
            $directory = $store->getDirectory();
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            
            $prefixedPattern = '/^' . preg_quote($prefix, '/') . '/';
            $count = 0;
            
            foreach ($files as $file) {
                if ($file->isFile()) {
                    // Get the cache key from the filename
                    $filename = $file->getFilename();
                    // The cache key is stored in the file, we need to read it
                    $contents = @file_get_contents($file->getPathname());
                    if ($contents) {
                        $data = @unserialize($contents);
                        if (is_array($data) && isset($data['key'])) {
                            $key = $data['key'];
                            if (preg_match($prefixedPattern, $key)) {
                                Cache::forget($key);
                                $count++;
                            }
                        }
                    }
                }
            }
            
            Log::info('Cleared ' . $count . ' File cache keys with prefix: ' . $prefix);
            return;
        }
        
        // Handle other cache drivers or fallback
        Log::warning('Cache driver does not support getKeys(). Flushing entire cache instead.');
        Cache::flush();
    }
}
