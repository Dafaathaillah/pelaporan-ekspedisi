<?php

namespace Tests\Feature;

use Tests\TestCase;

class ModernGlassmorphismStyleTest extends TestCase
{
    public function test_authenticated_shell_exposes_the_glass_theme_scope(): void
    {
        $shell = file_get_contents(resource_path('js/Components/AppShell.vue'));

        $this->assertStringContainsString('class="app-shell min-h-screen', $shell);
        $this->assertStringContainsString('class="app-shell-backdrop"', $shell);
    }

    public function test_shared_operational_surfaces_use_the_glass_contract(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('--glass-surface:', $css);
        $this->assertStringContainsString('.app-shell-backdrop', $css);
        $this->assertStringContainsString('backdrop-filter: blur(22px)', $css);
        $this->assertStringContainsString('.app-shell .panel,', $css);
        $this->assertStringContainsString('.app-shell .data-table thead', $css);
        $this->assertStringContainsString('.app-shell .field,', $css);
        $this->assertStringContainsString('.app-shell .mobile-bottom-nav', $css);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
    }

    public function test_login_page_remains_outside_the_authenticated_glass_scope(): void
    {
        $login = file_get_contents(resource_path('js/Pages/Auth/Login.vue'));

        $this->assertStringContainsString('class="auth-page', $login);
        $this->assertStringNotContainsString('class="app-shell', $login);
    }
}
