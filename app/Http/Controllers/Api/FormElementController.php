<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormElement;
use App\Models\FormVersion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FormElementController extends Controller
{
    /**
     * Get form elements for a specific form version in tree structure
     */
    public function getFormVersionElements(FormVersion $formVersion): JsonResponse
    {
        $elements = FormElement::with(['elementable', 'children.elementable', 'children.children.elementable'])
            ->where('form_versions_id', $formVersion->id)
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get();

        return response()->json($this->buildTreeStructure($elements));
    }

    /**
     * Store a new form element
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'form_versions_id' => 'required|exists:form_versions,id',
            'parent_id' => 'nullable|exists:form_elements,id',
            'elementable_type' => 'required|string',
            'order' => 'integer|min:0',
        ]);

        // Create the specific element type first
        $elementableClass = $validated['elementable_type'];
        $elementable = $elementableClass::create([]);

        // Create the form element
        $formElement = FormElement::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'form_versions_id' => $validated['form_versions_id'],
            'parent_id' => $validated['parent_id'],
            'elementable_id' => $elementable->id,
            'elementable_type' => $validated['elementable_type'],
            'order' => $validated['order'] ?? 0,
        ]);

        return response()->json($formElement->load('elementable'), 201);
    }

    /**
     * Update a form element
     */
    public function update(Request $request, FormElement $formElement): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:form_elements,id',
            'order' => 'integer|min:0',
        ]);

        $formElement->update($validated);

        return response()->json($formElement->load('elementable'));
    }

    /**
     * Delete a form element
     */
    public function destroy(FormElement $formElement): JsonResponse
    {
        // Delete the elementable record first
        $formElement->elementable()->delete();

        // Delete the form element (this will cascade to children)
        $formElement->delete();

        return response()->json(['message' => 'Element deleted successfully']);
    }

    /**
     * Reorder form elements
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'elements' => 'required|array',
            'elements.*.id' => 'required|exists:form_elements,id',
            'elements.*.order' => 'required|integer|min:0',
            'elements.*.parent_id' => 'nullable|exists:form_elements,id',
        ]);

        foreach ($validated['elements'] as $elementData) {
            FormElement::where('id', $elementData['id'])
                ->update([
                    'order' => $elementData['order'],
                    'parent_id' => $elementData['parent_id'],
                ]);
        }

        return response()->json(['message' => 'Elements reordered successfully']);
    }

    /**
     * Build tree structure from flat collection
     */
    private function buildTreeStructure($elements)
    {
        return $elements->map(function ($element) {
            return [
                'id' => $element->id,
                'name' => $element->name,
                'description' => $element->description,
                'elementable_type' => $element->elementable_type,
                'order' => $element->order,
                'parent_id' => $element->parent_id,
                'children' => $this->buildTreeStructure($element->children),
            ];
        });
    }
}
