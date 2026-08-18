<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InstitutionalImageAssetsTest extends TestCase
{
    #[DataProvider('institutionalImages')]
    public function test_institutional_image_is_a_real_png_and_matches_the_distribution_copy(
        string $relativePath,
    ): void {
        $servedPath = dirname(__DIR__, 2).'/public/assets/images/'.$relativePath;
        $distributionPath = dirname(__DIR__, 3).'/public-assets/images/'.$relativePath;

        $this->assertFileExists($servedPath);
        $this->assertFileExists($distributionPath);
        $this->assertSame("\x89PNG\r\n\x1a\n", file_get_contents($servedPath, false, null, 0, 8));
        $this->assertSame(hash_file('sha256', $servedPath), hash_file('sha256', $distributionPath));
    }

    /** @return array<string, array{string}> */
    public static function institutionalImages(): array
    {
        return [
            'primary logo' => ['SDRI_V2_oro.png'],
            'navigation logo' => ['SDRI_oro.png'],
            'institutional shield' => ['escudo-unam.png'],
            'consent shield' => ['consentimientos/escudo-unam.png'],
            'black and gold consent logo' => ['consentimientos/SDRI_negro_oro.png'],
            'gold consent logo' => ['consentimientos/SDRI_oro.png'],
        ];
    }
}
