<?php

namespace App\Http\Controllers\App;

use App\Enums\DealStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\DealRequest;
use App\Http\Requests\App\MoveDealRequest;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Property;
use App\Models\Stage;
use App\Models\User;
use App\Support\ActivityPresenter;
use App\Support\Agents;
use App\Support\DealPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DealController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Deal::class);
        $user = $request->user();

        $pipelines = Pipeline::query()->orderBy('id')->with('stages')->get();
        $pipeline = $pipelines->firstWhere('slug', $request->query('pipeline')) ?? $pipelines->first();
        $view = $request->query('view') === 'list' ? 'list' : 'board';

        $stages = $pipeline->stages;
        $deals = Deal::query()->visibleTo($user)
            ->where('pipeline_id', $pipeline->id)
            ->with(DealPresenter::with())
            ->orderBy('position')->orderBy('id')
            ->get()->groupBy('stage_id');

        return Inertia::render('app/Deals/Board', [
            'pipeline' => $this->pipelineRow($pipeline),
            'pipelines' => $pipelines->map(fn ($p) => $this->pipelineRow($p))->values(),
            'view' => $view,
            'stages' => $stages->map(function (Stage $s) use ($deals) {
                $column = $deals->get($s->id, collect());

                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'position' => $s->position,
                    'probability' => $s->probability,
                    'is_won' => $s->is_won,
                    'is_lost' => $s->is_lost,
                    'count' => $column->count(),
                    'total_value' => (int) $column->sum('value'),
                    'deals' => $column->map(fn ($d) => DealPresenter::card($d))->values(),
                ];
            })->values(),
            ...$this->formProps($user, $pipelines),
        ]);
    }

    public function store(DealRequest $request): RedirectResponse
    {
        $user = $request->user();
        $stage = Stage::with('pipeline')->findOrFail($request->integer('stage_id'));

        $deal = new Deal($request->dealData());
        $deal->pipeline_id = $stage->pipeline_id;
        $deal->stage_id = $stage->id;
        $deal->owner_id = $request->ownerIdFor() ?? $user->id;
        $deal->commission_percent = $request->input('commission_percent')
            ?? Deal::defaultCommission($stage->pipeline->listing_type);
        if ($stage->is_won) {
            $deal->status = DealStatus::Won;
            $deal->closed_at = now();
        } elseif ($stage->is_lost) {
            $deal->status = DealStatus::Lost;
            $deal->closed_at = now();
        }

        DB::transaction(function () use ($deal) {
            $deal->appendToStage();
            $deal->save();
        });

        return to_route('app.deals.show', $deal);
    }

    public function show(Request $request, Deal $deal): Response
    {
        Gate::authorize('view', $deal);
        $user = $request->user();
        $deal->load([...DealPresenter::with(), 'pipeline.stages']);
        $counts = Deal::query()->where('pipeline_id', $deal->pipeline_id)
            ->groupBy('stage_id')->selectRaw('stage_id, count(*) as n')->pluck('n', 'stage_id');

        return Inertia::render('app/Deals/Show', [
            'deal' => DealPresenter::card($deal) + [
                'lost_reason' => $deal->lost_reason,
                'closed_at' => $deal->closed_at?->toIso8601String(),
                'created_at' => $deal->created_at?->toIso8601String(),
                'pipeline' => $this->pipelineRow($deal->pipeline),
                'stage_probability' => $deal->stage->probability ?? null,
            ],
            'can' => [
                'update' => $user->can('update', $deal),
                'delete' => $user->can('delete', $deal),
            ],
            // For the header's stage picker (the keyboard/mobile alternative to dragging).
            // `count` is the column size, so a move to position=count lands at the end.
            'stages' => $deal->pipeline->stages->map(fn (Stage $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'is_won' => $s->is_won,
                'is_lost' => $s->is_lost,
                'count' => $counts[$s->id] ?? 0,
            ])->values(),
            ...$this->formProps($user),
            'activities' => ActivityPresenter::timeline($user, 'deal_id', $deal->id),
            'tasks' => ActivityPresenter::tasksFor($user, 'deal_id', $deal->id),
            'link_options' => ActivityPresenter::linkOptions($user),
        ]);
    }

    public function update(DealRequest $request, Deal $deal): RedirectResponse
    {
        $deal->fill($request->dealData());
        if (($ownerId = $request->ownerIdFor()) !== null) {
            $deal->owner_id = $ownerId;
        }
        $deal->save();

        return back();
    }

    public function destroy(Deal $deal): RedirectResponse
    {
        Gate::authorize('delete', $deal);

        DB::transaction(function () use ($deal) {
            $deal->delete();
            Deal::renumber(
                Deal::query()->where('stage_id', $deal->stage_id)->orderBy('position')->orderBy('id')->pluck('id')->all(),
            );
        });

        return to_route('app.deals.index', ['pipeline' => $deal->pipeline->slug]);
    }

    public function move(MoveDealRequest $request, Deal $deal): RedirectResponse
    {
        $stage = Stage::findOrFail($request->integer('stage_id'));
        $deal->moveTo($stage, $request->integer('position'), $request->input('lost_reason'));

        return back();
    }

    private function pipelineRow(Pipeline $p): array
    {
        return ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'listing_type' => $p->listing_type->value];
    }

    /**
     * Data the create/edit Sheet needs.
     *
     * @param  Collection<int, Pipeline>|null  $pipelines  already loaded with stages, ordered by id
     */
    private function formProps(User $user, ?Collection $pipelines = null): array
    {
        $pipelines ??= Pipeline::query()->orderBy('id')->with('stages')->get();

        return [
            'form' => [
                'pipelines' => $pipelines->map(fn ($p) => $this->pipelineRow($p) + [
                    'stages' => $p->stages->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
                ])->values(),
                'contacts' => Contact::pickerOptions($user),
                'properties' => Property::pickerOptions(withListingType: true),
            ],
            ...Agents::prop($user),
        ];
    }
}
