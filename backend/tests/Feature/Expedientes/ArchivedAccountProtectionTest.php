<?php

namespace Tests\Feature\Expedientes;

use App\Models\Comentario;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArchivedAccountProtectionTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('heldClinicalTargets')]
    public function test_deleting_an_account_cannot_cascade_retained_clinical_comments(string $type, bool $archived): void
    {
        $author = User::factory()->create();
        $case = $this->fixtureCase(['retention_until' => now()->addYears(10)]);
        if ($archived) {
            $case->delete();
        }
        $target = $type === 'case' ? $case : Sesion::factory()->create([
            'expediente_id' => $case->id, 'realizada_por' => $case->creado_por, 'validada_por' => null,
        ]);
        $comment = new Comentario(['user_id' => $author->id, 'contenido' => 'Comentario clínico sintético protegido.']);
        $target->comentarios()->save($comment);

        try {
            $author->delete();
            $this->fail('Account deletion must not erase retained clinical history.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertDatabaseHas('users', ['id' => $author->id]);
        $this->assertDatabaseHas('comentarios', ['id' => $comment->id, 'user_id' => $author->id]);
        $this->assertSame(1, Expediente::withTrashed()->count());
    }

    public function test_unretained_comments_keep_the_existing_account_deletion_behavior(): void
    {
        $author = User::factory()->create();
        $case = $this->fixtureCase();
        $comment = new Comentario(['user_id' => $author->id, 'contenido' => 'Comentario sintético no sujeto a archivo.']);
        $case->comentarios()->save($comment);

        $author->delete();

        $this->assertDatabaseMissing('users', ['id' => $author->id]);
        $this->assertDatabaseMissing('comentarios', ['id' => $comment->id]);
        $this->assertDatabaseHas('expedientes', ['id' => $case->id]);
    }

    public function test_a_failed_self_deletion_keeps_the_account_session_and_retained_comment(): void
    {
        $author = User::factory()->create();
        $case = $this->fixtureCase(['retention_until' => now()->addYears(10)]);
        $case->delete();
        $comment = new Comentario(['user_id' => $author->id, 'contenido' => 'Comentario de conservación sintético.']);
        $case->comentarios()->save($comment);

        $this->actingAs($author)->deleteJson(route('profile.destroy'), ['password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('user');

        $this->assertAuthenticatedAs($author);
        $this->assertDatabaseHas('users', ['id' => $author->id]);
        $this->assertDatabaseHas('comentarios', ['id' => $comment->id]);
    }

    public static function heldClinicalTargets(): array
    {
        return [
            'archived case' => ['case', true],
            'archived session' => ['session', true],
            'restored case under retention' => ['case', false],
            'restored session under retention' => ['session', false],
        ];
    }

    private function fixtureCase(array $attributes = []): Expediente
    {
        $creator = User::factory()->create();

        return Expediente::factory()->create($attributes + [
            'creado_por' => $creator->id, 'facilitador_id' => $creator->id,
            'tutor_id' => null, 'coordinador_id' => null,
        ]);
    }
}
