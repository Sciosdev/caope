<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BrandingAssetsTest extends TestCase
{
    private string $repositoryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryRoot = dirname(__DIR__, 3);
    }

    #[Test]
    public function favicon_assets_are_present_and_non_empty(): void
    {
        $assets = [
            'backend/public/favicon.ico',
            'backend/public/favicon-16x16.png',
            'backend/public/favicon-32x32.png',
            'backend/public/apple-touch-icon.png',
            'backend/public/favicon-192x192.png',
            'backend/public/favicon-512x512.png',
        ];

        foreach ($assets as $asset) {
            $path = $this->repositoryRoot.DIRECTORY_SEPARATOR.$asset;

            $this->assertFileExists($path);
            $this->assertGreaterThan(100, filesize($path), $asset.' must not be empty.');
        }

        $ico = file_get_contents($this->repositoryRoot.'/backend/public/favicon.ico');

        $this->assertIsString($ico);
        $this->assertStringStartsWith("\x00\x00\x01\x00", $ico);
    }

    #[Test]
    public function every_html_layout_includes_the_shared_favicon_metadata(): void
    {
        $partial = file_get_contents($this->repositoryRoot.'/backend/resources/views/partials/favicon.blade.php');

        $this->assertIsString($partial);
        $this->assertStringContainsString("asset('favicon.ico')", $partial);
        $this->assertStringContainsString("asset('favicon-32x32.png')", $partial);
        $this->assertStringContainsString("asset('apple-touch-icon.png')", $partial);

        foreach (['app', 'guest', 'noble'] as $layout) {
            $contents = file_get_contents(
                $this->repositoryRoot.'/backend/resources/views/layouts/'.$layout.'.blade.php'
            );

            $this->assertIsString($contents);
            $this->assertStringContainsString("@include('partials.favicon')", $contents);
        }
    }
}
