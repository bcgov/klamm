<?php

namespace App\Console\Commands;

use App\Models\Form;
use App\Models\FormMetadata\FormTag;
use Illuminate\Console\Command;

class AddTagToForms extends Command
{
    protected $signature = 'forms:add-tag';
    protected $description = 'Add a tag to multiple forms by form_id';

    public function handle()
    {
        $tagName = $this->ask('Enter the tag name you want to add to forms');

        if (!$tagName) {
            $this->error('Tag name cannot be empty.');
            return 1;
        }

        $tag = FormTag::where('name', $tagName)->first();

        if (!$tag) {
            $description = $this->ask('Tag does not exist. Enter a description for the new tag (optional)', '');

            $tag = FormTag::create([
                'name' => $tagName,
                'description' => $description ?: null,
            ]);

            $this->info("Created new tag: {$tagName}");
        } else {
            $this->info("Using existing tag: {$tagName}");
        }

        $formIdsInput = $this->ask('Enter the form_ids you want to add this tag to (comma-separated)');

        if (!$formIdsInput) {
            $this->error('No form IDs provided.');
            return 1;
        }

        $formIds = array_map('trim', explode(',', $formIdsInput));
        $formIds = array_filter($formIds);

        if (empty($formIds)) {
            $this->error('No valid form IDs provided.');
            return 1;
        }

        $this->info('Processing forms...');

        $successCount = 0;
        $skippedCount = 0;
        $alreadyTaggedCount = 0;

        foreach ($formIds as $formId) {
            $form = Form::where('form_id', $formId)->first();

            if (!$form) {
                $this->warn("Form with form_id '{$formId}' does not exist. Skipping.");
                $skippedCount++;
                continue;
            }

            if ($form->formTags()->where('form_tag_id', $tag->id)->exists()) {
                $this->info("Form '{$formId}' already has tag '{$tagName}'. Skipping.");
                $alreadyTaggedCount++;
                continue;
            }

            $form->formTags()->attach($tag->id);
            $this->info("Added tag '{$tagName}' to form '{$formId}'");
            $successCount++;
        }

        $this->info('');
        $this->info('=== Summary ===');
        $this->info("Successfully tagged: {$successCount} forms");
        if ($alreadyTaggedCount > 0) {
            $this->info("Already tagged: {$alreadyTaggedCount} forms");
        }
        if ($skippedCount > 0) {
            $this->warn("Skipped (not found): {$skippedCount} forms");
        }

        return 0;
    }
}
