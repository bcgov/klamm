<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RenderedForm;
use App\Models\User;
use App\Models\Ministry;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RenderedFormSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = User::all();
        $ministries = Ministry::all();

        $structures = [
            [
                "version" => "0.0.1",
                "id" => (string) Str::uuid(),
                "lastModified" => Carbon::now()->toIso8601String(),
                "title" => "Personal Information Form",
                "data" => [
                    "items" => [
                        [
                            "type" => "text",
                            "text" => "Please fill out the form below:",
                            "id" => "1",
                            "codeContext" => ["name" => "intro-text"]
                        ],
                        [
                            "type" => "text-input",
                            "label" => "What is your name?",
                            "placeholder" => "Enter your name",
                            "helperText" => "Your full name, as it appears on official documents",
                            "inputType" => "text",
                            "id" => "2",
                            "codeContext" => ["name" => "name"]
                        ],
                        [
                            "type" => "dropdown",
                            "placeholder" => "Select your favorite color",
                            "isMulti" => false,
                            "isInline" => false,
                            "selectionFeedback" => "top-after-reopen",
                            "direction" => "bottom",
                            "size" => "md",
                            "label" => "Favorite Color",
                            "helperText" => "Choose one from the list",
                            "listItems" => [
                                ["text" => "Red"],
                                ["text" => "Blue"],
                                ["text" => "Green"],
                                ["text" => "Yellow"]
                            ],
                            "id" => "3",
                            "codeContext" => ["name" => "favorite-color"]
                        ],
                        [
                            "type" => "checkbox",
                            "label" => "I agree to the terms and conditions",
                            "id" => "4",
                            "codeContext" => ["name" => "terms-conditions"]
                        ],
                        [
                            "type" => "checkbox",
                            "label" => "Subscribe to our newsletter",
                            "id" => "5",
                            "codeContext" => ["name" => "newsletter"]
                        ],
                        [
                            "type" => "toggle",
                            "header" => "Enable notifications",
                            "offText" => "Off",
                            "onText" => "On",
                            "disabled" => false,
                            "checked" => false,
                            "size" => "md",
                            "id" => "6",
                            "codeContext" => ["name" => "notifications"]
                        ]
                    ],
                    "id" => 1
                ],
                "allCssClasses" => []
            ],
        ];

        // Seed 5 forms
        for ($i = 1; $i <= 5; $i++) {
            $structure = $structures[0];
            $structure['id'] = (string) Str::uuid();
            $structure['title'] = "Form Title $i";
            $structure['lastModified'] = Carbon::now()->toIso8601String();

            RenderedForm::create([
                'id' => (string) Str::uuid(),
                'created_by' => $users->random()->id,
                'name' => "Form Name $i",
                'description' => 'A simple form',
                'structure' => json_encode($structure),
                'ministry_id' => $ministries->random()->id,
            ]);
        }
    }
}
