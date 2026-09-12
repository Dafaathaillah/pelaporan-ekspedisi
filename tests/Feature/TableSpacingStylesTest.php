<?php

namespace Tests\Feature;

use Tests\TestCase;

class TableSpacingStylesTest extends TestCase
{
    public function test_data_tables_use_the_shared_comfortable_spacing_contract(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('--table-cell-inline-space: 1.5rem;', $css);
        $this->assertStringContainsString('--table-cell-block-space: 1.25rem;', $css);
        $this->assertStringContainsString('padding-inline: var(--table-cell-inline-space);', $css);
        $this->assertStringContainsString('padding-block: var(--table-cell-block-space);', $css);
        $this->assertStringContainsString('min-width: 1900px;', $css);
    }

    public function test_arrival_batch_action_clears_the_mobile_bottom_navigation(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Expedition/Index.vue'));

        $this->assertStringContainsString('bottom-[calc(5rem+env(safe-area-inset-bottom))]', $page);
        $this->assertStringContainsString('lg:bottom-4', $page);
    }

    public function test_p5m_capture_offers_a_gallery_fallback_without_forcing_camera_capture(): void
    {
        $component = file_get_contents(resource_path('js/Components/P5mCameraCapture.vue'));

        $this->assertStringContainsString('Buka Kamera', $component);
        $this->assertStringContainsString('Pilih / Upload Foto', $component);
        $this->assertStringContainsString('ref="cameraInput" type="file" accept="image/*" capture="environment"', $component);
        $this->assertStringContainsString('ref="galleryInput" type="file" accept="image/*" class="hidden"', $component);
    }
}
