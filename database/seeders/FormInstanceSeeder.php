<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\FormVersion;
use App\Models\FormField;
use App\Models\FormInstanceField;

class FormInstanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $formVersion = FormVersion::create([
            'form_id' => 1,
            'version_number' => '1.0',
            'status' => 'Active',
            'form_requester_name' => 'John Doe',
            'form_requester_email' => 'john@example.com',
            'form_developer_name' => 'Jane Developer',
            'form_developer_email' => 'jane@example.com',
            'form_approver_name' => 'Approver Name',
            'form_approver_email' => 'approver@example.com',
        ]);

        $fields = FormField::whereIn('name', ['first_name', 'last_name', 'date_of_birth', 'canadian_citizen', 'comments'])->get();
        $order = 1;

        foreach ($fields as $field) {
            FormInstanceField::create([
                'form_version_id' => $formVersion->id,
                'order' => $order++,
                'form_field_id' =>  $field->id,
                'label' => $field->label,
                'data_binding' => NULL,
                'validation' => NULL,
                'styles' => NULL,
                'conditional_logic' => NULL,
            ]);
        }
    }
}
