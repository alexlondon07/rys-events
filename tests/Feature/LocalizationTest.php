<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_the_password_reset_email_is_in_spanish(): void
    {
        app()->setLocale('es');

        $mail = (new ResetPassword('token-demo'))->toMail(User::factory()->create());

        $this->assertSame('Restablezca su contraseña', $mail->subject);
        $this->assertSame('Restablecer contraseña', $mail->actionText);
    }
}
