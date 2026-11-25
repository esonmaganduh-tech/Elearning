<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveLevelRequest;
use App\Models\MembershipLevel;
use App\Services\DeleteMembershipOption;

class LevelController extends Controller
{
    public function index()
    {
        return view('catalog.index', [
            'records' => MembershipLevel::withCount('members')
                ->orderBy('fee')
                ->orderBy('name')
                ->paginate(10),
            'title' => 'Membership Levels',
            'routePrefix' => 'levels',
            'isLevel' => true,
        ]);
    }

    public function create()
    {
        return $this->form(new MembershipLevel());
    }

    public function store(SaveLevelRequest $request)
    {
        $level = MembershipLevel::create(
            $request->validated()
        );

        return redirect()
            ->route('levels.show', $level)
            ->with('success', 'Level created successfully.');
    }

    public function show(MembershipLevel $level)
    {
        return view('catalog.show', [
            'record' => $level->loadCount('members'),
            'title' => 'Level Details',
            'routePrefix' => 'levels',
            'isLevel' => true,
        ]);
    }

    public function edit(MembershipLevel $level)
    {
        return $this->form($level);
    }

    public function update(
        SaveLevelRequest $request,
        MembershipLevel $level
    ) {
        $level->update($request->validated());

        return redirect()
            ->route('levels.show', $level)
            ->with('success', 'Level updated successfully.');
    }

    public function destroy(
        MembershipLevel $level,
        DeleteMembershipOption $deleteOption
    ) {
        $deleteOption->handle($level);

        return redirect()
            ->route('levels.index')
            ->with('success', 'Level deleted successfully.');
    }

    private function form(MembershipLevel $level)
    {
        return view('catalog.form', [
            'record' => $level,
            'title' => $level->exists
                ? 'Edit Level'
                : 'Add Level',
            'routePrefix' => 'levels',
            'isLevel' => true,
        ]);
    }
}