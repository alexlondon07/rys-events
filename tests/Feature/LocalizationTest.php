<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalizationTest extends TestCase
{
    public function test_validation_and_auth_messages_are_translated_to_spanish(): void
    {
        app()->setLocale('es');

        $this->assertSame(
            'El campo contraseña debe contener al menos una letra mayúscula y una minúscula.',
            __('validation.password.mixed', ['attribute' => __('validation.attributes.password')]),
        );

        $this->assertSame(
            'El campo correo electrónico es obligatorio.',
            __('validation.required', ['attribute' => __('validation.attributes.email')]),
        );

        $this->assertStringNotContainsString('validation.', __('auth.failed'));
        $this->assertStringNotContainsString('validation.', __('passwords.sent'));
    }
}
