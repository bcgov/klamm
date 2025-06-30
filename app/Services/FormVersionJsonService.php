<?php

namespace App\Services;

use App\Models\FormBuilding\FormVersion;
use App\Models\FormBuilding\FormElement;

class FormVersionJsonService
{
    public function generateJson(FormVersion $formVersion): array
    {
        // Load the form version with necessary relationships
        $formVersion->load([
            'form',
            'formElements.elementable',
            'webStyleSheet',
            'pdfStyleSheet',
            'webFormScript',
            'pdfFormScript'
        ]);

        return [
            'formversion' => [
                'uuid' => $formVersion->uuid ?? $formVersion->id,
                'name' => $formVersion->form->form_title ?? 'Unknown Form',
                'id' => $formVersion->form->form_id ?? '',
                'version' => $formVersion->version_number,
                'status' => $formVersion->status,
                'data' => $this->getFormVersionData($formVersion),
                'styles' => $this->getStyles($formVersion),
                'scripts' => $this->getScripts($formVersion),
                'elements' => $this->getElements($formVersion)
            ]
        ];
    }

    protected function getFormVersionData(FormVersion $formVersion): array
    {
        return [
            'footer' => $formVersion->footer,
            'comments' => $formVersion->comments,
            'created_at' => $formVersion->created_at?->toISOString(),
            'updated_at' => $formVersion->updated_at?->toISOString(),
        ];
    }

    protected function getStyles(FormVersion $formVersion): array
    {
        $styles = [];

        if ($formVersion->webStyleSheet) {
            $styles[] = [
                'type' => 'web',
                'content' => $formVersion->webStyleSheet->getCssContent()
            ];
        }

        if ($formVersion->pdfStyleSheet) {
            $styles[] = [
                'type' => 'pdf',
                'content' => $formVersion->pdfStyleSheet->getCssContent()
            ];
        }

        return $styles;
    }

    protected function getScripts(FormVersion $formVersion): array
    {
        $scripts = [];

        if ($formVersion->webFormScript) {
            $scripts[] = [
                'type' => 'web',
                'content' => $formVersion->webFormScript->getJsContent()
            ];
        }

        if ($formVersion->pdfFormScript) {
            $scripts[] = [
                'type' => 'pdf',
                'content' => $formVersion->pdfFormScript->getJsContent()
            ];
        }

        return $scripts;
    }

    protected function getElements(FormVersion $formVersion): array
    {
        // Get root elements (elements without a parent - parent_id is -1 for root elements)
        $rootElements = $formVersion->formElements()
            ->where(function ($query) {
                $query->whereNull('parent_id')
                    ->orWhere('parent_id', -1);
            })
            ->orderBy('order')
            ->get();

        return $rootElements->map(function (FormElement $element) {
            return $this->transformElement($element);
        })->toArray();
    }

    protected function transformElement(FormElement $element): array
    {
        $elementData = [
            'uuid' => $element->uuid ?? $element->id,
            'type' => $this->getElementType($element),
            'name' => $element->name,
            'description' => $element->description,
            'help_text' => $element->help_text,
            'is_visible' => $element->is_visible,
            'visible_web' => $element->visible_web,
            'visible_pdf' => $element->visible_pdf,
            'is_read_only' => $element->is_read_only,
            'save_on_submit' => $element->save_on_submit,
            'order' => $element->order,
            'parent_id' => $element->parent_id == -1 ? null : $element->parent_id,
            'attributes' => $this->getElementAttributes($element)
        ];

        // Load and add children if this element has any
        $children = FormElement::where('parent_id', $element->id)
            ->orderBy('order')
            ->with('elementable')
            ->get();

        if ($children->count() > 0) {
            $elementData['children'] = $children->map(function (FormElement $child) {
                return $this->transformElement($child);
            })->toArray();
        }

        return $elementData;
    }

    protected function getElementType(FormElement $element): string
    {
        if (!$element->elementable_type) {
            return 'unknown';
        }

        // Convert class name to kebab-case type
        $className = class_basename($element->elementable_type);

        // Remove "FormElement" suffix if present
        $typeName = str_replace('FormElement', '', $className);

        // Convert to kebab-case
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $typeName));
    }

    protected function getElementAttributes(FormElement $element): array
    {
        if (!$element->elementable) {
            return [];
        }

        $attributes = $element->elementable->toArray();

        // Remove Laravel model metadata
        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);

        return $attributes;
    }
}
