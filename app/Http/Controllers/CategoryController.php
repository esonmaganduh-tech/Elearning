<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCategoryRequest;
use App\Models\MembershipCategory;
use App\Services\DeleteMembershipOption;

class CategoryController extends Controller
{
    public function index()
    {
        return view('catalog.index', [
            'records' => MembershipCategory::withCount('members')
                ->orderBy('name')
                ->paginate(10),
            'title' => 'Membership Categories',
            'routePrefix' => 'categories',
            'isLevel' => false,
        ]);
    }

    public function create()
    {
        return $this->form(new MembershipCategory());
    }

    public function store(SaveCategoryRequest $request)
    {
        $category = MembershipCategory::create(
            $request->validated()
        );

        return redirect()
            ->route('categories.show', $category)
            ->with('success', 'Category created successfully.');
    }

    public function show(MembershipCategory $category)
    {
        return view('catalog.show', [
            'record' => $category->loadCount('members'),
            'title' => 'Category Details',
            'routePrefix' => 'categories',
            'isLevel' => false,
        ]);
    }

    public function edit(MembershipCategory $category)
    {
        return $this->form($category);
    }

    public function update(
        SaveCategoryRequest $request,
        MembershipCategory $category
    ) {
        $category->update($request->validated());

        return redirect()
            ->route('categories.show', $category)
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(
        MembershipCategory $category,
        DeleteMembershipOption $deleteOption
    ) {
        $deleteOption->handle($category);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category deleted successfully.');
    }

    private function form(MembershipCategory $category)
    {
        return view('catalog.form', [
            'record' => $category,
            'title' => $category->exists
                ? 'Edit Category'
                : 'Add Category',
            'routePrefix' => 'categories',
            'isLevel' => false,
        ]);
    }
}