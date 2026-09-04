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
}
