<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AtiService;
use App\Models\AtiServiceOption;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super admin: Data management → ATI services.
 * These are the choices for "Which services from the Agricultural
 * Training Institute have you availed?" on the registration form.
 */
class AtiServiceController extends Controller
{
        public function index(): Response
    {
        // Choices of the follow-up questions, grouped by list
        $options = AtiServiceOption::query()->ordered()->get()->groupBy('list');

        $listsFor = fn (?string $followUp) => collect(AtiServiceOption::LISTS)
            ->filter(fn ($list) => $followUp && $list['follow_up'] === $followUp)
            ->map(fn ($list, $key) => [
                'key' => $key,
                'label' => $list['label'],
                'question' => $list['question'],
                'options' => ($options[$key] ?? collect())->map(fn (AtiServiceOption $o) => [
                    'id' => $o->id,
                    'name' => $o->name,
                    'is_active' => $o->is_active,
                    'members' => $o->membersCount(),
                ])->values(),
            ])
            ->values();

        return Inertia::render('admin/AtiServices', [
            'services' => AtiService::query()->ordered()->get()->map(fn (AtiService $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description,
                'follow_up' => $s->follow_up,
                'is_locked' => $s->isLocked(),
                'is_active' => $s->is_active,
                'members' => $s->membersCount(),
                'lists' => $listsFor($s->follow_up),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        AtiService::create($data + [
            'is_active' => true,
            'sort_order' => (int) AtiService::max('sort_order') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$data['name']}\" was added to the registration form."]);

        return back();
    }

    public function update(Request $request, AtiService $atiService): RedirectResponse
    {
        $data = $request->validate($this->rules($atiService) + [
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Training, Scholarship and LSA keep their names (the form's extra questions depend on them)
        if ($atiService->isLocked()) {
            unset($data['name']);
        }

        DB::transaction(function () use ($atiService, $data) {
            $oldName = $atiService->name;
            $atiService->update($data);

            // Renamed: update the members who already ticked the old name
            if ($atiService->wasChanged('name')) {
                $this->renameInMembers($oldName, $atiService->name);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$atiService->name}\" was updated."]);

        return back();
    }

    /** Move a service one place up or down on the form. */
    public function move(Request $request, AtiService $atiService): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $services = AtiService::query()->ordered()->get()->values();
        $index = $services->search(fn (AtiService $s) => $s->is($atiService));
        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swap >= 0 && $swap < $services->count()) {
            $moved = $services->pull($index);
            $services->splice($swap, 0, [$moved]);

            DB::transaction(function () use ($services) {
                foreach ($services->values() as $i => $service) {
                    $service->update(['sort_order' => $i + 1]);
                }
            });
        }

        return back();
    }

    public function destroy(AtiService $atiService): RedirectResponse
    {
        if ($atiService->isLocked()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "\"{$atiService->name}\" has follow-up questions on the form and cannot be deleted. You can hide it instead."]);

            return back();
        }

        if ($count = $atiService->membersCount()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "\"{$atiService->name}\" was ticked by {$count} ".str('member')->plural($count).'. Hide it from the form instead, so their records stay complete.']);

            return back();
        }

        $atiService->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$atiService->name}\" was deleted."]);

        return back();
    }

    /** @return array<string, mixed> */
    private function rules(?AtiService $service = null): array
    {
        return [
            'name' => [$service ? 'sometimes' : 'required', 'string', 'max:100', Rule::unique('ati_services', 'name')->ignore($service)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function renameInMembers(string $from, string $to): void
    {
        Member::query()
            ->whereJsonContains('ati_services', $from)
            ->chunkById(500, function ($members) use ($from, $to) {
                foreach ($members as $member) {
                    $member->ati_services = collect($member->ati_services)
                        ->map(fn ($s) => $s === $from ? $to : $s)
                        ->unique()
                        ->values()
                        ->all();
                    $member->saveQuietly();
                }
            });
    }
}