<?php

namespace Tests\Feature;

use App\Services\TranslateService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TranslateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => null]);
    }

    public function test_it_returns_the_khmer_translation_from_the_provider(): void
    {
        config(['services.groq.key' => null]);
        config(['services.google_translate.key' => null]);

        Http::fake([
            'api.mymemory.translated.net/*' => Http::response([
                'responseStatus' => 200,
                'responseData' => ['translatedText' => '&#x179F;&#x17BD;&#x179F;&#x17D2;&#x178F;&#x17B8;'],
            ]),
        ]);

        $translation = (new TranslateService())->translateToKhmer('你好');

        $this->assertSame('សួស្តី', $translation);
        Http::assertSent(fn ($request) => $request['langpair'] === 'zh|km');
    }

    public function test_it_fails_instead_of_using_the_chinese_source_when_translation_fails(): void
    {
        config(['services.groq.key' => null]);
        config(['services.google_translate.key' => null]);

        Http::fake([
            'api.mymemory.translated.net/*' => Http::response([
                'responseStatus' => 403,
                'responseDetails' => 'Quota exceeded',
            ]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Quota exceeded');

        (new TranslateService())->translateToKhmer('你好');
    }

    public function test_it_translates_multiple_segments_in_one_google_request(): void
    {
        config(['services.groq.key' => null]);
        config(['services.google_translate.key' => 'test-key']);

        Http::fake([
            'translation.googleapis.com/*' => Http::response([
                'data' => [
                    'translations' => [
                        ['translatedText' => 'សួស្តី'],
                        ['translatedText' => 'លាហើយ'],
                    ],
                ],
            ]),
        ]);

        $translations = (new TranslateService())->translateBatch(['你好', '再见']);

        $this->assertSame(['សួស្តី', 'លាហើយ'], array_column($translations, 'translated'));
        $this->assertCount(1, Http::recorded());
        Http::assertSent(fn ($request) => count($request['q']) === 2
            && $request->hasHeader('X-Goog-Api-Key', 'test-key'));
    }

    public function test_it_reports_google_translation_quota_errors_clearly(): void
    {
        config(['services.groq.key' => null]);
        config(['services.google_translate.key' => 'test-key']);

        Http::fake([
            'translation.googleapis.com/*' => Http::response([
                'error' => ['message' => 'Quota exceeded'],
            ], 429),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Google Translate quota/rate limit reached');

        (new TranslateService())->translateToKhmer('你好');
    }

    public function test_it_translates_numbered_segments_together_using_groq(): void
    {
        config([
            'services.groq.key' => 'test-key',
            'services.groq.translation_model' => 'llama-test',
        ]);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => "1. សួស្តី\n2. លាហើយ"]],
                ],
            ]),
        ]);

        $translations = (new TranslateService())->translateBatch(['你好', '再见']);

        $this->assertSame(['សួស្តី', 'លាហើយ'], array_column($translations, 'translated'));
        $this->assertCount(1, Http::recorded());
        Http::assertSent(fn ($request) => $request['model'] === 'llama-test'
            && $request['max_tokens'] === 32
            && !isset($request['response_format'])
            && str_contains($request['messages'][1]['content'], '2. 再见')
            && $request->hasHeader('Authorization', 'Bearer test-key'));
    }

    public function test_it_reports_groq_rate_limit_errors_for_automatic_queue_retry(): void
    {
        config(['services.groq.key' => 'test-key']);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'error' => ['message' => 'Rate limit exceeded'],
            ], 429, ['Retry-After' => '1']),
        ]);

        $this->expectException(\App\Exceptions\TranslationRateLimitException::class);
        $this->expectExceptionMessage('Groq translation rate limit reached');

        (new TranslateService())->translateToKhmer('你好');
    }

    public function test_it_uses_gemini_when_groq_is_rate_limited(): void
    {
        config([
            'services.groq.key' => 'groq-test-key',
            'services.gemini.key' => 'gemini-test-key',
            'services.gemini.translation_model' => 'gemini-test-model',
        ]);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'error' => ['message' => 'Rate limit exceeded'],
            ], 429),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => "1. សួស្តី\n2. លាហើយ"]],
                    ],
                ]],
            ]),
        ]);

        $translations = (new TranslateService())->translateBatch(['你好', '再见']);

        $this->assertSame(['សួស្តី', 'លាហើយ'], array_column($translations, 'translated'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-test-model:generateContent')
            && $request->hasHeader('x-goog-api-key', 'gemini-test-key')
            && str_contains($request['contents'][0]['parts'][0]['text'], '2. 再见'));
    }

    public function test_it_uses_gemini_directly_when_groq_is_not_configured(): void
    {
        config([
            'services.groq.key' => null,
            'services.gemini.key' => 'gemini-test-key',
            'services.google_translate.key' => 'google-test-key',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'សួស្តី']]],
                ]],
            ]),
        ]);

        $this->assertSame('សួស្តី', (new TranslateService())->translateToKhmer('你好'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'generateContent')
            && $request->hasHeader('x-goog-api-key', 'gemini-test-key'));
    }

    public function test_it_retries_invalid_gemini_batch_lines_individually(): void
    {
        config([
            'services.groq.key' => null,
            'services.gemini.key' => 'gemini-test-key',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'text' => "1. សួស្តី\n2. លាហើយ\n3. 好啊\n4. អរគុណ",
                        ]]],
                    ]],
                ])
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => '1. បាន']]],
                    ]],
                ]),
        ]);

        $translations = (new TranslateService())->translateBatch(['你好', '再见', '好啊', '谢谢']);

        $this->assertSame(
            ['សួស្តី', 'លាហើយ', 'បាន', 'អរគុណ'],
            array_column($translations, 'translated')
        );
        $this->assertCount(2, Http::recorded());
    }

    public function test_it_retries_when_gemini_free_tier_is_rate_limited(): void
    {
        config([
            'services.groq.key' => null,
            'services.gemini.key' => 'gemini-test-key',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'Resource exhausted'],
            ], 429, ['Retry-After' => '12']),
        ]);

        try {
            (new TranslateService())->translateBatch(['你好']);
            $this->fail('Expected Gemini rate limit to be released for queue retry.');
        } catch (\App\Exceptions\TranslationRateLimitException $exception) {
            $this->assertStringContainsString('Gemini translation free-tier rate limit reached', $exception->getMessage());
            $this->assertSame(12, $exception->retryAfterSeconds);
        }
    }

    public function test_it_waits_for_the_token_window_when_groq_reports_output_token_limit(): void
    {
        config(['services.groq.key' => 'test-key']);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'error' => [
                    'message' => 'Rate limit reached on output tokens per minute. Please try again in 3 seconds.',
                ],
            ], 429),
        ]);

        try {
            (new TranslateService())->translateToKhmer('你好');
            $this->fail('Expected the translation to be released for retry.');
        } catch (\App\Exceptions\TranslationRateLimitException $exception) {
            $this->assertGreaterThanOrEqual(60, $exception->retryAfterSeconds);
        }
    }

    public function test_it_splits_groq_segments_into_batches_of_eight(): void
    {
        config(['services.groq.key' => 'test-key']);

        Http::fake([
            'api.groq.com/*' => Http::sequence()
                ->push([
                    'choices' => [
                        ['message' => ['content' => "1. មួយ\n2. ពីរ\n3. បី\n4. បួន\n5. ប្រាំ\n6. ប្រាំមួយ\n7. ប្រាំពីរ\n8. ប្រាំបី"]],
                    ],
                ])
                ->push([
                    'choices' => [
                        ['message' => ['content' => '1. ប្រាំបួន']],
                    ],
                ]),
        ]);

        $translations = (new TranslateService())->translateBatch([
            '一', '二', '三', '四', '五', '六', '七', '八', '九',
        ]);

        $this->assertSame(
            ['មួយ', 'ពីរ', 'បី', 'បួន', 'ប្រាំ', 'ប្រាំមួយ', 'ប្រាំពីរ', 'ប្រាំបី', 'ប្រាំបួន'],
            array_column($translations, 'translated')
        );
        $this->assertCount(2, Http::recorded());
        Http::assertSent(fn ($request) => $request['max_tokens'] === 88
            && str_contains($request['messages'][1]['content'], '8. 八'));
    }

    public function test_it_splits_groq_batches_when_output_token_rate_limited(): void
    {
        config(['services.groq.key' => 'test-key']);

        Http::fake([
            'api.groq.com/*' => Http::sequence()
                ->push([
                    'error' => [
                        'message' => 'Rate limit reached on output tokens per minute. Please try again in 3 seconds.',
                    ],
                ], 429)
                ->push([
                    'choices' => [
                        ['message' => ['content' => "1. មួយ\n2. ពីរ"]],
                    ],
                ])
                ->push([
                    'choices' => [
                        ['message' => ['content' => "1. បី\n2. បួន"]],
                    ],
                ]),
        ]);

        $translations = (new TranslateService())->translateBatch(['一', '二', '三', '四']);

        $this->assertSame(['មួយ', 'ពីរ', 'បី', 'បួន'], array_column($translations, 'translated'));
        $this->assertCount(3, Http::recorded());
    }

    public function test_it_retries_incomplete_groq_batches_as_smaller_requests(): void
    {
        config(['services.groq.key' => 'test-key']);

        Http::fake([
            'api.groq.com/*' => Http::sequence()
                ->push([
                    'choices' => [
                        ['message' => ['content' => "1. មួយ\n2. ពីរ"]],
                    ],
                ])
                ->push([
                    'choices' => [
                        ['message' => ['content' => "1. មួយ\n2. ពីរ"]],
                    ],
                ])
                ->push([
                    'choices' => [
                        ['message' => ['content' => "1. បី\n2. បួន"]],
                    ],
                ]),
        ]);

        $translations = (new TranslateService())->translateBatch(['一', '二', '三', '四']);

        $this->assertSame(['មួយ', 'ពីរ', 'បី', 'បួន'], array_column($translations, 'translated'));
        $this->assertCount(3, Http::recorded());
    }

    public function test_it_retranslates_lines_that_are_not_in_khmer_script(): void
    {
        config(['services.groq.key' => 'test-key']);

        Http::fake([
            'api.groq.com/*' => Http::sequence()
                ->push([
                    'choices' => [
                        ['message' => ['content' => "1. សួស្តី\n2. สวัสดี"]],
                    ],
                ])
                ->push([
                    'choices' => [
                        ['message' => ['content' => '1. លាហើយ']],
                    ],
                ]),
        ]);

        $translations = (new TranslateService())->translateBatch(['你好', '再见']);

        $this->assertSame(['សួស្តី', 'លាហើយ'], array_column($translations, 'translated'));
        $this->assertCount(2, Http::recorded());
    }
}
