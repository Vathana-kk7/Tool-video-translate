<?php

namespace App\Services;

use App\Exceptions\TranslationRateLimitException;
use Illuminate\Support\Facades\Http;

class TranslateService
{
    private string $apiUrl = 'https://api.mymemory.translated.net/get';
    private string $googleApiUrl = 'https://translation.googleapis.com/language/translate/v2';

    /**
     * Main translate function
     */
    public function translateToKhmer(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        if (config('services.groq.key')) {
            return $this->translateGroqBatch([$text])[0];
        }

        if (config('services.gemini.key')) {
            return $this->translateGeminiBatch([$text])[0];
        }

        if (config('services.google_translate.key')) {
            return $this->translateGoogleBatch([$text])[0];
        }

        return mb_strlen($text, 'UTF-8') > 450
            ? $this->translateLongText($text)
            : $this->translateChunk($text);
    }

    private function translateGoogleBatch(array $sentences): array
    {
        return array_column($this->translateGoogleSegments($sentences), 'translated');
    }

    private function translateGroqBatch(array $sentences): array
    {
        return array_column($this->translateGroqSegments($sentences), 'translated');
    }

    private function translateGeminiBatch(array $sentences): array
    {
        return array_column($this->translateGeminiSegments($sentences), 'translated');
    }

    private function translateGroqSegments(array $sentences): array
    {
        $result = [];
        $batch = [];

        foreach ($sentences as $index => $sentence) {
            $sentence = trim((string) $sentence);
            if ($sentence === '') {
                $result[$index] = '';
                continue;
            }

            if (mb_strlen($sentence, 'UTF-8') > 6000) {
                throw new \RuntimeException('A speech segment is too long for Groq translation.');
            }

            if (count($batch) >= 8) {
                $this->translateGroqChunk($batch, $result);
                $batch = [];
            }

            $batch[] = ['index' => $index, 'text' => $sentence];
        }

        if ($batch) {
            $this->translateGroqChunk($batch, $result);
        }

        ksort($result);

        return array_map(
            static fn ($index, $text) => [
                'index' => $index,
                'original' => $sentences[$index],
                'translated' => $text,
            ],
            array_keys($result),
            array_values($result)
        );
    }

    private function translateGroqChunk(array $batch, array &$result): void
    {
        $numberedTexts = [];
        foreach ($batch as $index => $item) {
            $numberedTexts[] = ($index + 1) . '. ' . preg_replace('/\s+/u', ' ', $item['text']);
        }

        $maxOutputTokens = array_sum(array_map(
            static fn ($item) => mb_strlen($item['text'], 'UTF-8') * 3 + 8,
            $batch
        ));

        $payload = [
                'model' => config('services.groq.translation_model'),
                'temperature' => 0,
                'max_tokens' => min(768, max(32, $maxOutputTokens)),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Translate each numbered Chinese speech line into natural Khmer written in Khmer script (Unicode U+1780-U+17FF), never Thai. Reply with exactly one line per input in the same order, keeping the same number followed by a period. Do not add explanations or omit any line.',
                    ],
                    ['role' => 'user', 'content' => implode("\n", $numberedTexts)],
                ],
            ];

        $response = Http::timeout(90)
            ->withToken(config('services.groq.key'))
            ->post('https://api.groq.com/openai/v1/chat/completions', $payload);

        if (!$response->successful()) {
            $message = $response->json('error.message', 'Unknown Groq translation error');
            if ($response->status() === 429) {
                if (config('services.gemini.key')) {
                    $this->translateGeminiChunk($batch, $result);

                    return;
                }

                $isOutputTokenLimit = str_contains(strtolower($message), 'output tokens per minute')
                    || str_contains(strtolower($message), 'otpm');

                if ($isOutputTokenLimit && count($batch) > 1) {
                    foreach (array_chunk($batch, (int) ceil(count($batch) / 2)) as $smallerBatch) {
                        $this->translateGroqChunk($smallerBatch, $result);
                    }

                    return;
                }

                $retryAfter = $this->retryAfterSeconds($response);
                if ($isOutputTokenLimit) {
                    $retryAfter = max(60, $retryAfter);
                }

                throw new TranslationRateLimitException(
                    'Groq translation rate limit reached. Processing will retry automatically. ' . $message,
                    max(5, $retryAfter)
                );
            }

            throw new \RuntimeException(
                'Groq translation returned HTTP ' . $response->status() . ': ' . $message
            );
        }

        $content = $response->json('choices.0.message.content');

        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Groq returned an empty translation.');
        }

        $translations = $this->parseGroqTranslations($content, count($batch));
        if ($translations === null) {
            if (count($batch) === 1) {
                $translations = [trim(preg_replace('/^\s*1[.)]\s*/u', '', trim($content)))];
            } else {
                foreach (array_chunk($batch, (int) ceil(count($batch) / 2)) as $smallerBatch) {
                    $this->translateGroqChunk($smallerBatch, $result);
                }
                return;
            }
        }

        foreach ($batch as $position => $item) {
            if ($translations[$position] === '') {
                throw new \RuntimeException('Groq returned an empty translation.');
            }

            if (!$this->containsKhmerScript($translations[$position])) {
                if (count($batch) === 1) {
                    throw new \RuntimeException('Groq returned a translation outside Khmer script.');
                }

                $this->translateGroqChunk([$item], $result);
                continue;
            }

            $result[$item['index']] = $translations[$position];
        }
    }

    private function translateGeminiSegments(array $sentences): array
    {
        $result = [];
        $batch = [];

        foreach ($sentences as $index => $sentence) {
            $sentence = trim((string) $sentence);
            if ($sentence === '') {
                $result[$index] = '';
                continue;
            }

            if (count($batch) >= 4) {
                $this->translateGeminiChunk($batch, $result);
                $batch = [];
            }

            $batch[] = ['index' => $index, 'text' => $sentence];
        }

        if ($batch) {
            $this->translateGeminiChunk($batch, $result);
        }

        ksort($result);

        return array_map(
            static fn ($index, $text) => [
                'index' => $index,
                'original' => $sentences[$index],
                'translated' => $text,
            ],
            array_keys($result),
            array_values($result)
        );
    }

    private function translateGeminiChunk(array $batch, array &$result): void
    {
        $numberedTexts = [];
        foreach ($batch as $index => $item) {
            $numberedTexts[] = ($index + 1) . '. ' . preg_replace('/\s+/u', ' ', $item['text']);
        }

        $maxOutputTokens = array_sum(array_map(
            static fn ($item) => mb_strlen($item['text'], 'UTF-8') * 3 + 8,
            $batch
        ));
        $model = config('services.gemini.translation_model');
        $response = Http::timeout(90)
            ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->post(
                'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent',
                [
                    'systemInstruction' => [
                        'parts' => [[
                            'text' => 'Translate each numbered Chinese speech line into natural Khmer written in Khmer script (Unicode U+1780-U+17FF), never Thai. Reply with exactly one line per input in the same order, keeping the same number followed by a period. Do not add explanations or omit any line.',
                        ]],
                    ],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => implode("\n", $numberedTexts)]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0,
                        'maxOutputTokens' => min(768, max(32, $maxOutputTokens)),
                    ],
                ]
            );

        if (!$response->successful()) {
            $message = $response->json('error.message', 'Unknown Gemini translation error');
            if ($response->status() === 429) {
                throw new TranslationRateLimitException(
                    'Gemini translation free-tier rate limit reached. Processing will retry automatically. ' . $message,
                    max(5, $this->retryAfterSeconds($response))
                );
            }

            throw new \RuntimeException(
                'Gemini translation returned HTTP ' . $response->status() . ': ' . $message
            );
        }

        $parts = $response->json('candidates.0.content.parts', []);
        $content = implode('', array_map(
            static fn ($part) => is_string($part['text'] ?? null) ? $part['text'] : '',
            is_array($parts) ? $parts : []
        ));
        if (trim($content) === '') {
            throw new \RuntimeException('Gemini returned an empty translation.');
        }

        $translations = $this->parseGroqTranslations($content, count($batch));
        if ($translations === null) {
            if (count($batch) === 1) {
                $translations = [trim(preg_replace('/^\s*1[.)]\s*/u', '', trim($content)))];
            } else {
                foreach (array_chunk($batch, (int) ceil(count($batch) / 2)) as $smallerBatch) {
                    $this->translateGeminiChunk($smallerBatch, $result);
                }

                return;
            }
        }

        foreach ($batch as $position => $item) {
            if ($translations[$position] === '' || !$this->containsKhmerScript($translations[$position])) {
                throw new \RuntimeException('Gemini returned an empty translation or text outside Khmer script.');
            }

            $result[$item['index']] = $translations[$position];
        }
    }

    private function containsKhmerScript(string $text): bool
    {
        return preg_match('/[\x{1780}-\x{17FF}]/u', $text) === 1;
    }

    private function parseGroqTranslations(string $content, int $expectedCount): ?array
    {
        $content = trim($content);
        preg_match_all('/(?:^|\s)(\d+)[.)]\s+/u', $content, $matches, PREG_OFFSET_CAPTURE);

        if (count($matches[0]) !== $expectedCount) {
            return null;
        }

        $translations = [];
        foreach ($matches[0] as $position => [$prefix, $offset]) {
            if ((int) $matches[1][$position][0] !== $position + 1) {
                return null;
            }

            $start = $offset + strlen($prefix);
            $end = $matches[0][$position + 1][1] ?? strlen($content);
            $translation = trim(substr($content, $start, $end - $start));
            if ($translation === '') {
                return null;
            }

            $translations[] = $translation;
        }

        return $translations;
    }

    private function retryAfterSeconds($response): int
    {
        $header = $response->header('Retry-After');
        if (is_numeric($header)) {
            return min(120, max(1, (int) ceil((float) $header)));
        }

        $message = (string) $response->json('error.message', '');
        if (preg_match('/try again in\s+([\d.]+)\s*seconds?/i', $message, $matches)) {
            return min(120, max(1, (int) ceil((float) $matches[1])));
        }

        return 5;
    }

    /**
     * Translate single chunk
     */
    private function translateChunk(string $text): string
    {
        $response = Http::timeout(30)->get($this->apiUrl, [
            'q'        => $text,
            'langpair' => 'zh|km',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Translation provider returned HTTP ' . $response->status() . '.');
        }

        $responseStatus = $response->json('responseStatus');
        $translatedText = $response->json('responseData.translatedText');

        if ((int) $responseStatus !== 200) {
            throw new \RuntimeException('Translation provider rejected the request: ' . $response->json('responseDetails', 'Unknown error'));
        }

        if (!is_string($translatedText) || trim($translatedText) === '') {
            throw new \RuntimeException('Translation provider returned an empty translation.');
        }

        return html_entity_decode($translatedText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Handle long text safely
     */
    private function translateLongText(string $text): string
    {
        $sentences = preg_split('/(?<=[。！？；\.\!\?])/u', $text);

        $chunks = [];
        $current = '';

        foreach ($sentences as $sentence) {
            if (mb_strlen($current . $sentence, 'UTF-8') <= 450) {
                $current .= $sentence;
            } else {
                if ($current !== '') {
                    $chunks[] = $current;
                }

                while (mb_strlen($sentence, 'UTF-8') > 450) {
                    $chunks[] = mb_substr($sentence, 0, 450, 'UTF-8');
                    $sentence = mb_substr($sentence, 450, null, 'UTF-8');
                }
                $current = $sentence;
            }
        }

        if ($current) $chunks[] = $current;

        $translated = [];

        foreach ($chunks as $index => $chunk) {
            $translated[] = $this->translateChunk(trim($chunk));
            if ($index < count($chunks) - 1) {
                usleep(500000);
            }
        }

        return implode(' ', $translated);
    }

    /**
     * Batch translate
     */
    public function translateBatch(array $sentences): array
    {
        if (config('services.groq.key')) {
            return $this->translateGroqSegments($sentences);
        }

        if (config('services.gemini.key')) {
            return $this->translateGeminiSegments($sentences);
        }

        if (config('services.google_translate.key')) {
            return $this->translateGoogleSegments($sentences);
        }

        $result = [];

        foreach ($sentences as $i => $sentence) {
            $result[] = [
                'index'      => $i,
                'original'   => $sentence,
                'translated' => $this->translateToKhmer($sentence),
            ];

            if ($i < count($sentences) - 1) {
                usleep(300000);
            }
        }

        return $result;
    }

    private function translateGoogleSegments(array $sentences): array
    {
        $result = [];
        $batch = [];
        $batchLength = 0;

        foreach ($sentences as $index => $sentence) {
            $sentence = trim((string) $sentence);
            if ($sentence === '') {
                $result[$index] = '';
                continue;
            }

            $sentenceLength = mb_strlen($sentence, 'UTF-8');
            if ($sentenceLength > 30000) {
                throw new \RuntimeException('A speech segment is too long for Google Translate.');
            }

            if ($batch && (count($batch) >= 100 || $batchLength + $sentenceLength > 30000)) {
                $this->translateGoogleChunk($batch, $result);
                $batch = [];
                $batchLength = 0;
            }

            $batch[] = ['index' => $index, 'text' => $sentence];
            $batchLength += $sentenceLength;
        }

        if ($batch) {
            $this->translateGoogleChunk($batch, $result);
        }

        ksort($result);

        return array_map(
            static fn ($index, $text) => [
                'index' => $index,
                'original' => $sentences[$index],
                'translated' => $text,
            ],
            array_keys($result),
            array_values($result)
        );
    }

    private function translateGoogleChunk(array $batch, array &$result): void
    {
        $response = Http::timeout(60)
            ->withHeaders(['X-Goog-Api-Key' => config('services.google_translate.key')])
            ->post($this->googleApiUrl, [
                'q' => array_column($batch, 'text'),
                'source' => 'zh',
                'target' => 'km',
                'format' => 'text',
            ]);

        if (!$response->successful()) {
            $message = $response->json('error.message', 'Unknown Google Translate error');
            if ($response->status() === 429) {
                throw new \RuntimeException(
                    'Google Translate quota/rate limit reached. Wait for the quota to reset or increase the Google Cloud Translation quota. ' . $message
                );
            }

            throw new \RuntimeException(
                'Google Translate returned HTTP ' . $response->status() . ': ' . $message
            );
        }

        $translations = $response->json('data.translations');
        if (!is_array($translations) || count($translations) !== count($batch)) {
            throw new \RuntimeException('Google Translate returned an incomplete batch response.');
        }

        foreach ($batch as $position => $item) {
            $translation = $translations[$position]['translatedText'] ?? null;
            if (!is_string($translation) || trim($translation) === '') {
                throw new \RuntimeException('Google Translate returned an empty translation.');
            }

            $result[$item['index']] = html_entity_decode($translation, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
}
