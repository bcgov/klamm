<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class MailImageHelper
{
    public static function embedOrFallback(string $path, string $fallbackUrl): string
    {
        try {
            $fullPath = public_path($path);
            if (File::exists($fullPath)) {
                $imageData = base64_encode(File::get($fullPath));
                $mimeType = File::mimeType($fullPath);

                return "data:{$mimeType};base64,{$imageData}";
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to embed image: {$path}", ['exception' => $e]);
        }

        return asset($fallbackUrl); // fallback to the original public image URL
    }
}
