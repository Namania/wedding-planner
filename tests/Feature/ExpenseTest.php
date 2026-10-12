<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_it_lists_expenses_most_recent_first(): void
    {
        $older = Expense::factory()->create(['created_at' => now()->subDay()]);
        $newer = Expense::factory()->create();

        $this->actingAs($this->admin)
            ->getJson('/api/expenses')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $newer->id)
            ->assertJsonPath('1.id', $older->id);
    }

    public function test_it_creates_an_expense(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/expenses', [
                'amount' => 1250.5,
                'note' => 'Acompte traiteur',
                'paid_by' => 'Margaux',
            ])
            ->assertCreated()
            ->assertJsonPath('amount', 1250.5)
            ->assertJsonPath('note', 'Acompte traiteur')
            ->assertJsonPath('paid_by', 'Margaux');

        $this->assertDatabaseHas('expenses', ['note' => 'Acompte traiteur', 'paid_by' => 'Margaux']);
    }

    public function test_the_payer_can_be_anyone_not_only_the_spouses(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/expenses', [
                'amount' => 80,
                'note' => 'Fleurs boutonnières',
                'paid_by' => 'Tante Hélène',
            ])
            ->assertCreated()
            ->assertJsonPath('paid_by', 'Tante Hélène');
    }

    public function test_it_requires_a_positive_amount_a_note_and_a_payer(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/expenses', ['amount' => 0, 'note' => '', 'paid_by' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'note', 'paid_by']);
    }

    public function test_it_updates_an_expense(): void
    {
        $expense = Expense::factory()->create(['paid_by' => 'Mael']);

        $this->actingAs($this->admin)
            ->putJson('/api/expenses/'.$expense->id, [
                'amount' => 300,
                'note' => 'Solde photographe',
                'paid_by' => 'Margaux',
            ])
            ->assertOk()
            ->assertJsonPath('paid_by', 'Margaux');

        $this->assertSame('Margaux', $expense->fresh()->paid_by);
    }

    public function test_it_deletes_an_expense(): void
    {
        $expense = Expense::factory()->create();

        $this->actingAs($this->admin)
            ->deleteJson('/api/expenses/'.$expense->id)
            ->assertOk();

        $this->assertModelMissing($expense);
    }

    public function test_a_gallery_guest_cannot_see_the_expenses(): void
    {
        $this->actingAs(User::factory()->guest()->create())
            ->getJson('/api/expenses')
            ->assertForbidden();
    }
}
