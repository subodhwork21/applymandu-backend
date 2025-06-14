<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class StringHelper
{
    public static function findRelatedStrings(string $input, array $candidates,  int $threshold = 8): array
    {
        $related = [];

        foreach ($candidates as $candidate) {
            //lowecase the input and candidate strings
            $input = strtolower($input);
            $candidate[0] = strtolower($candidate[0]);
            similar_text($input, $candidate[0], $percent);  
    
            if ($percent >= 50) {
                $related[] = [
                    'id' => $candidate[1],
                    'string' => $candidate[0],
                    'similarity' => round($percent, 2)
                ];
            }
        }
        Log::info("Related strings: ", $related);
    
        usort($related, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return $related;
    }
}
