<?php

namespace Tests\Feature;

use Tests\TestCase;

class SeoTest extends TestCase
{
    public function test_login_page_contains_public_seo_metadata(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Portal Pelaporan Ekspedisi &amp; Dokumen Vendor', false)
            ->assertSee('name="description"', false)
            ->assertSee('name="robots" content="index, follow', false)
            ->assertSee('application/ld+json', false);
    }

    public function test_sitemap_only_exposes_the_public_login_page(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('/login', false)
            ->assertDontSee('/coordinator', false)
            ->assertDontSee('/vendor', false);
    }
}
