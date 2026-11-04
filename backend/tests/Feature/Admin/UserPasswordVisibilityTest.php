<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserPasswordVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'alumno'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = User::factory()->create(['approved_at' => now()]);
        $admin->assignRole('admin');
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_create_form_has_two_independent_masked_password_controls(): void
    {
        $response = $this->get(route('admin.users.create'))->assertOk();

        $this->assertPasswordControls($response->getContent(), required: true);
    }

    public function test_edit_form_never_prefills_current_or_previously_submitted_passwords(): void
    {
        $user = User::factory()->create(['password' => 'Example-Test-Password-123!']);
        $user->assignRole('alumno');
        $passwordHash = $user->getRawOriginal('password');

        $response = $this->withSession(['_old_input' => [
            'password' => 'Example-Old-Password-456!',
            'password_confirmation' => 'Example-Old-Password-456!',
        ]])->get(route('admin.users.edit', $user))->assertOk();

        $response->assertDontSee('Example-Test-Password-123!', false)
            ->assertDontSee('Example-Old-Password-456!', false)
            ->assertDontSee($passwordHash, false)
            ->assertSee('Déjalo en blanco para mantener la contraseña actual.');
        $this->assertPasswordControls($response->getContent(), required: false);
    }

    public function test_leaving_edit_password_empty_preserves_the_stored_password(): void
    {
        $user = User::factory()->create();
        $user->assignRole('alumno');
        $passwordHash = $user->getRawOriginal('password');

        $this->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => ['alumno'],
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $this->assertSame($passwordHash, $user->fresh()->getRawOriginal('password'));
    }

    private function assertPasswordControls(string $html, bool $required): void
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);

        $this->assertSame(2, $xpath->query('//*[@data-password-toggle]')->length);
        $this->assertSame(1, $xpath->query('//script[@data-password-visibility]')->length);
        $this->assertSame(2, $xpath->query('//span[@data-password-hide-icon and @hidden]')->length);
        $this->assertSame(2, $xpath->query('//span[@data-password-show-icon and not(@hidden)]')->length);

        foreach ([
            'password' => 'Mostrar contraseña',
            'password_confirmation' => 'Mostrar confirmación de contraseña',
        ] as $id => $label) {
            $input = $xpath->query('//input[@id="'.$id.'"]')->item(0);
            $this->assertInstanceOf(DOMElement::class, $input);
            $this->assertSame('password', $input->getAttribute('type'));
            $this->assertSame('new-password', $input->getAttribute('autocomplete'));
            $this->assertFalse($input->hasAttribute('value'));
            $this->assertSame($required, $input->hasAttribute('required'));

            $button = $xpath->query('//button[@aria-controls="'.$id.'"]')->item(0);
            $this->assertInstanceOf(DOMElement::class, $button);
            $this->assertSame('button', $button->getAttribute('type'));
            $this->assertSame('false', $button->getAttribute('aria-pressed'));
            $this->assertSame($label, $button->getAttribute('aria-label'));
        }
    }
}
