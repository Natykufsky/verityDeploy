<?php

namespace Tests\Unit;

use App\Support\SiteEnvironmentPreview;
use Tests\TestCase;

class SiteEnvironmentPreviewTest extends TestCase
{
    public function test_it_prefers_the_generated_preview_when_no_override_exists(): void
    {
        $preview = SiteEnvironmentPreview::build([
            'APP_NAME' => 'Generated Site',
            'APP_DEBUG' => false,
        ], null);

        $this->assertSame('generated', $preview['mode']);
        $this->assertFalse($preview['has_override']);
        $this->assertSame($preview['generated_contents'], $preview['effective_contents']);
        $this->assertSame([], $preview['diff']['added']);
        $this->assertSame([], $preview['diff']['removed']);
        $this->assertSame([], $preview['diff']['changed']);
        $this->assertSame('No custom override is set. The generated preview becomes the effective .env file.', $preview['diff']['message']);
        $this->assertSame([
            'APP_NAME' => '"Generated Site"',
            'APP_DEBUG' => 'false',
        ], $preview['generated_pairs']);
    }

    public function test_it_detects_changes_between_generated_values_and_a_custom_override(): void
    {
        $preview = SiteEnvironmentPreview::build([
            'APP_NAME' => 'Generated Site',
            'APP_DEBUG' => false,
        ], "APP_NAME=Override Site\nAPP_EXTRA=1\nAPP_DEBUG=false\n");

        $this->assertSame('custom', $preview['mode']);
        $this->assertTrue($preview['has_override']);
        $this->assertSame("APP_NAME=Override Site\nAPP_EXTRA=1\nAPP_DEBUG=false", $preview['effective_contents']);
        $this->assertCount(1, $preview['diff']['added']);
        $this->assertCount(1, $preview['diff']['changed']);
        $this->assertCount(0, $preview['diff']['removed']);
        $this->assertSame('APP_EXTRA', $preview['diff']['added'][0]['key']);
        $this->assertSame('APP_NAME', $preview['diff']['changed'][0]['key']);
        $this->assertSame('"Generated Site"', $preview['diff']['changed'][0]['generated']);
        $this->assertSame('Override Site', $preview['diff']['changed'][0]['custom']);
    }
}
