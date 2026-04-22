<?php

use App\Filament\Resources\AuditResultResource;
use App\Filament\Resources\ChunkResource;
use App\Filament\Resources\FindingResource;
use App\Filament\Resources\GutenbergBlacklistResource;
use App\Filament\Resources\MetaConceptResource;
use App\Filament\Resources\NodeResource;
use App\Filament\Resources\RunResource;
use App\Models\AuditResult;
use App\Models\Chunk;
use App\Models\Finding;
use App\Models\GutenbergBlacklist;
use App\Models\MetaConcept;
use App\Models\Node;
use App\Models\Run;
use App\Models\User;

use Livewire\Livewire;

function livewire(string $component, array $params = []): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test($component, $params);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

// --- RunResource ---

it('renders the runs list', function () {
    Run::factory()->count(3)->create();

    livewire(RunResource\Pages\ListRuns::class)
        ->assertSuccessful();
});

it('renders the run view page', function () {
    $run = Run::factory()->create();

    livewire(RunResource\Pages\ViewRun::class, ['record' => $run->getRouteKey()])
        ->assertSuccessful();
});

// --- ChunkResource ---

it('renders the chunks list', function () {
    Chunk::factory()->count(3)->create();

    livewire(ChunkResource\Pages\ListChunks::class)
        ->assertSuccessful();
});

// --- NodeResource ---

it('renders the nodes list', function () {
    Node::factory()->count(3)->create();

    livewire(NodeResource\Pages\ListNodes::class)
        ->assertSuccessful();
});

// --- MetaConceptResource ---

it('renders the meta concepts list', function () {
    MetaConcept::factory()->count(3)->create();

    livewire(MetaConceptResource\Pages\ListMetaConcepts::class)
        ->assertSuccessful();
});

// --- FindingResource ---

it('renders the findings list', function () {
    Finding::factory()->count(3)->create();

    livewire(FindingResource\Pages\ListFindings::class)
        ->assertSuccessful();
});

// --- GutenbergBlacklistResource (CRUD) ---

it('renders the gutenberg blacklist list', function () {
    GutenbergBlacklist::factory()->count(3)->create();

    livewire(GutenbergBlacklistResource\Pages\ListGutenbergBlacklists::class)
        ->assertSuccessful();
});

it('renders the gutenberg blacklist create form', function () {
    livewire(GutenbergBlacklistResource\Pages\CreateGutenbergBlacklist::class)
        ->assertSuccessful();
});

it('creates a gutenberg blacklist entry', function () {
    livewire(GutenbergBlacklistResource\Pages\CreateGutenbergBlacklist::class)
        ->fillForm([
            'title'  => 'The Canterbury Tales',
            'active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(GutenbergBlacklist::where('title', 'The Canterbury Tales')->exists())->toBeTrue();
});

it('renders the gutenberg blacklist edit form', function () {
    $entry = GutenbergBlacklist::factory()->create();

    livewire(GutenbergBlacklistResource\Pages\EditGutenbergBlacklist::class, ['record' => $entry->getRouteKey()])
        ->assertSuccessful();
});

// --- AuditResultResource ---

it('renders the audit results list', function () {
    AuditResult::factory()->count(3)->create();

    livewire(AuditResultResource\Pages\ListAuditResults::class)
        ->assertSuccessful();
});

it('renders the audit result view page', function () {
    $audit = AuditResult::factory()->create();

    livewire(AuditResultResource\Pages\ViewAuditResult::class, ['record' => $audit->getRouteKey()])
        ->assertSuccessful();
});
