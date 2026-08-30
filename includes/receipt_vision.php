<?php

/*
 * Reads a supplier receipt/invoice photo and extracts structured
 * expense data using Claude's vision capability, via the same
 * Anthropic API credentials already configured for the assistant.
 */
function extract_receipt_data(string $image_base64, string $media_type): array
{
    $system_prompt = 'You are extracting structured data from a photo of a ' .
        'supplier receipt or invoice for a South African business. Respond ' .
        'ONLY with valid JSON, no markdown formatting, no code fences, no ' .
        'explanation - just the raw JSON object, using exactly this structure: ' .
        '{"supplier_name": string, "invoice_number": string or null, ' .
        '"expense_date": "YYYY-MM-DD" or null, "subtotal": number or null, ' .
        '"vat_amount": number or null, "total": number, "notes": string or null}. ' .
        'If a field cannot be determined from the image, use null (except ' .
        'total - always make your best estimate from what is visible). ' .
        'Convert any date format seen into YYYY-MM-DD. All monetary values ' .
        'must be plain numbers with no currency symbols, letters, or ' .
        'thousands separators.';

    $payload = [
        'model'      => CLAUDE_MODEL,
        'max_tokens' => 1024,
        'system'     => $system_prompt,
        'messages'   => [
            [
                'role'    => 'user',
                'content' => [
                    [
                        'type'   => 'image',
                        'source' => [
                            'type'       => 'base64',
                            'media_type' => $media_type,
                            'data'       => $image_base64,
                        ],
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Extract the receipt/invoice data from this image as JSON.',
                    ],
                ],
            ],
        ],
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT    => 60,
    ]);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['error' => 'Could not reach Claude API: ' . $curl_error];
    }

    $data = json_decode($response, true);

    if (isset($data['error'])) {
        return ['error' => $data['error']['message'] ?? 'Unknown API error'];
    }

    $text = '';

    foreach ($data['content'] ?? [] as $block) {
        if ($block['type'] === 'text') {
            $text .= $block['text'];
        }
    }

    $text = trim($text);
    $text = preg_replace('/^```(json)?\s*|\s*```$/m', '', $text);

    $extracted = json_decode($text, true);

    if (!is_array($extracted)) {
        return ['error' => 'Could not read this receipt clearly. Please try a clearer photo, or enter the details manually below.'];
    }

    return $extracted;
}


/*
 * Normalizes an uploaded image to JPEG bytes, resizing if it's very
 * large. Falls back to the original bytes untouched if GD isn't
 * available or the format isn't supported.
 */
function normalize_receipt_image(string $source_path, string $mime_type): array
{
    if (!function_exists('imagecreatetruecolor')) {
        return ['bytes' => file_get_contents($source_path), 'mime_type' => $mime_type];
    }

    $image = null;

    if ($mime_type === 'image/jpeg') {
        $image = @imagecreatefromjpeg($source_path);
    } elseif ($mime_type === 'image/png') {
        $image = @imagecreatefrompng($source_path);
    } elseif ($mime_type === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $image = @imagecreatefromwebp($source_path);
    }

    if (!$image) {
        return ['bytes' => file_get_contents($source_path), 'mime_type' => $mime_type];
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $max_dimension = 1600;

    if ($width > $max_dimension || $height > $max_dimension) {

        $ratio = min($max_dimension / $width, $max_dimension / $height);
        $new_width = (int)($width * $ratio);
        $new_height = (int)($height * $ratio);

        $resized = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        imagedestroy($image);
        $image = $resized;

    }

    ob_start();
    imagejpeg($image, null, 85);
    $bytes = ob_get_clean();

    imagedestroy($image);

    return ['bytes' => $bytes, 'mime_type' => 'image/jpeg'];
}
