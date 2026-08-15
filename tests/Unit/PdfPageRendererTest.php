<?php

namespace EasyAI\LaravelAI\Tests\Unit;

use Dompdf\Dompdf;
use EasyAI\LaravelAI\Chat\Support\PdfPageRenderer;
use EasyAI\LaravelAI\Tests\TestCase;

/**
 * PdfPageRenderer needs the PHP `imagick` extension (with a Ghostscript
 * delegate) — a real system-level dependency, not something composer alone
 * can guarantee. Two mutually exclusive paths below, each skipped when its
 * own precondition isn't met, rather than one test that would only ever be
 * honest on whichever machine happens to run it: local dev here has no
 * imagick, CI does (see .github/workflows/tests.yml) — so the "really
 * renders a page" case is only ever exercised for real in CI, and the
 * "fails clearly without it" case only locally. Both are real coverage,
 * just never both on the same machine.
 */
class PdfPageRendererTest extends TestCase
{
    private function tinyTestPdf(): string
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml('<h1 style="color:red">Test Page</h1><p>Rendered for PdfPageRendererTest.</p>');
        $dompdf->render();

        $path = tempnam(sys_get_temp_dir(), 'laraveleasyai-test-') . '.pdf';
        file_put_contents($path, $dompdf->output());
        return $path;
    }

    public function test_throws_a_clear_message_when_imagick_is_not_installed(): void
    {
        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            $this->markTestSkipped('imagick is installed on this machine — see test_actually_renders_a_page_when_imagick_is_available() instead.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('imagick');
        PdfPageRenderer::render($this->tinyTestPdf(), 1);
    }

    public function test_throws_when_the_pdf_file_does_not_exist(): void
    {
        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            $this->markTestSkipped('Requires the imagick extension to reach the file-existence check past the extension guard.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found');
        PdfPageRenderer::render('/no/such/file.pdf', 1);
    }

    public function test_actually_renders_a_page_when_imagick_is_available(): void
    {
        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            $this->markTestSkipped('imagick is not installed on this machine — see .github/workflows/tests.yml, where it is.');
        }

        $images = PdfPageRenderer::render($this->tinyTestPdf(), 5);

        $this->assertCount(1, $images, 'The single-page test PDF should render exactly one image.');
        $this->assertStringStartsWith("\x89PNG", $images[0], 'Output should be real PNG bytes (PNG magic number).');
    }

    public function test_respects_the_max_pages_cap(): void
    {
        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            $this->markTestSkipped('imagick is not installed on this machine — see .github/workflows/tests.yml, where it is.');
        }

        $dompdf = new Dompdf();
        // Three distinct pages via CSS page breaks.
        $dompdf->loadHtml('<div style="page-break-after:always">Page 1</div><div style="page-break-after:always">Page 2</div><div>Page 3</div>');
        $dompdf->render();
        $path = tempnam(sys_get_temp_dir(), 'laraveleasyai-test-') . '.pdf';
        file_put_contents($path, $dompdf->output());

        $images = PdfPageRenderer::render($path, 2);

        $this->assertCount(2, $images, 'A 2-page cap on a 3-page PDF should render only the first 2 pages.');
    }
}
