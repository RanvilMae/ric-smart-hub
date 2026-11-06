<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AtiServiceOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Super admin: the choices under an ATI service
 * (trainings under Training, scholarships under Scholarship, LSA levels and types under LSA).
 */
class AtiServiceOptionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'list' => ['required', Rule::in(array_keys(AtiServiceOption::LISTS))],
            'name' => ['required', 'string', 'max:255', Rule::unique('ati_service_options', 'name')->where('list', $request->input('list'))],
        ]);

        AtiServiceOption::create($data + [
            'is_active' => true,
            'sort_order' => (int) AtiServiceOption::where('list', $data['list'])->max('sort_order') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$data['name']}\" was added."]);

        return back();
    }

    public function update(Request $request, AtiServiceOption $option): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('ati_service_options', 'name')->where('list', $option->list)->ignore($option)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($option, $data) {
            $oldName = $option->name;
            $option->update($data);

            if ($option->wasChanged('name')) {
                $option->renameInMembers($oldName);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$option->name}\" was updated."]);

        return back();
    }

    public function destroy(AtiServiceOption $option): RedirectResponse
    {
        if ($count = $option->membersCount()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "\"{$option->name}\" was chosen by {$count} ".str('member')->plural($count).'. Hide it from the form instead, so their records stay complete.']);

            return back();
        }

        $option->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$option->name}\" was deleted."]);

        return back();
    }
}