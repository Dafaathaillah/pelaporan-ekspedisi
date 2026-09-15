<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class LoginPageDesignTest extends TestCase
{
    public function test_login_page_keeps_the_authentication_contract_and_accessible_controls(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Auth/Login.vue'));

        $this->assertStringContainsString("username: ''", $page);
        $this->assertStringContainsString("password: ''", $page);
        $this->assertStringContainsString('remember: false', $page);
        $this->assertStringContainsString("form.post('/login'", $page);
        $this->assertStringContainsString('@submit.prevent="submit"', $page);
        $this->assertStringContainsString('autocomplete="username"', $page);
        $this->assertStringContainsString('autocomplete="current-password"', $page);
        $this->assertStringContainsString('type="submit"', $page);
        $this->assertStringContainsString(':disabled="form.processing"', $page);
        $this->assertStringContainsString('aria-live="polite"', $page);
        $this->assertStringContainsString(':aria-invalid="Boolean(form.errors.username)"', $page);
        $this->assertStringContainsString(':aria-invalid="Boolean(form.errors.password)"', $page);
    }

    public function test_login_page_uses_the_local_operational_background_and_real_password_reset_route(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Auth/Login.vue'));

        $this->assertFileExists(public_path('images/auth/login-background.webp'));
        $this->assertStringContainsString("url('/images/auth/login-background.webp')", $page);
        $this->assertStringContainsString('/images/logo-ekspedisi-ppa.png', $page);
        $this->assertStringContainsString("route('password.request')", $page);
        $this->assertStringContainsString('prefers-reduced-motion', $page);
        $this->assertStringNotContainsString('http://', $page);
        $this->assertStringNotContainsString('https://', $page);
    }

    public function test_desktop_login_shell_is_locked_to_the_viewport_without_page_scroll(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Auth/Login.vue'));

        $this->assertStringContainsString('h-[100dvh] overflow-hidden', $page);
        $this->assertStringContainsString('h-full min-h-0', $page);
        $this->assertStringContainsString('@media (max-height: 820px) and (min-width: 768px)', $page);
    }
}
