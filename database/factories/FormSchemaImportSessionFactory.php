<?php

namespace Database\Factories;

use App\Models\FormSchemaImportSession;
use App\Models\User;
use App\Models\Ministry;
use App\Models\Form;
use Illuminate\Database\Eloquent\Factories\Factory;

class FormSchemaImportSessionFactory extends Factory
{
    protected $model = FormSchemaImportSession::class;

    public function definition(): array
    {
        $statuses = ['draft', 'in_progress', 'completed', 'failed', 'cancelled'];
        $status = $this->faker->randomElement($statuses);

        return [
            'session_name' => $this->faker->sentence(3) . ' Import',
            'description' => $this->faker->optional(0.6)->paragraph(),
            'status' => $status,
            'schema_content' => $this->generateSampleSchema(),
            'parsed_schema_summary' => [
                'form_id' => 'FORM_' . $this->faker->numberBetween(1000, 9999),
                'title' => $this->faker->sentence(3),
                'field_count' => $this->faker->numberBetween(5, 50),
                'container_count' => $this->faker->numberBetween(1, 5),
                'format' => $this->faker->randomElement(['adze-template', 'legacy']),
            ],
            'target_form_id' => 'FORM_' . $this->faker->numberBetween(1000, 9999),
            'target_form_title' => $this->faker->sentence(3),
            'target_ministry_id' => Ministry::factory(),
            'create_new_form' => $this->faker->boolean(70),
            'create_new_version' => $this->faker->boolean(80),
            'field_mappings' => $this->generateFieldMappings(),
            'total_fields' => $totalFields = $this->faker->numberBetween(5, 50),
            'mapped_fields' => $this->faker->numberBetween(0, $totalFields),
            'current_step' => $this->faker->numberBetween(1, 5),
            'user_id' => User::factory(),
            'session_token' => \Illuminate\Support\Str::random(40),
            'last_activity_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'completed_at' => $status === 'completed' ? $this->faker->dateTimeBetween('-7 days', 'now') : null,
            'error_message' => $status === 'failed' ? $this->faker->sentence() : null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'draft',
            'completed_at' => null,
            'error_message' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'in_progress',
            'completed_at' => null,
            'error_message' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'completed',
            'completed_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'error_message' => null,
            'result_form_id' => Form::factory(),
            'import_result' => [
                'success' => true,
                'message' => 'Import completed successfully',
                'containers_created' => $this->faker->numberBetween(1, 5),
                'fields_created' => $this->faker->numberBetween(5, 30),
            ],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'failed',
            'completed_at' => null,
            'error_message' => $this->faker->sentence(),
        ]);
    }

    public function withComplexSchema(): static
    {
        return $this->state(fn(array $attributes) => [
            'schema_content' => $this->generateComplexSchema(),
            'parsed_schema_summary' => [
                'form_id' => 'COMPLEX_FORM_' . $this->faker->numberBetween(1000, 9999),
                'title' => 'Complex Form with Multiple Containers',
                'field_count' => 75,
                'container_count' => 8,
                'format' => 'adze-template',
            ],
            'total_fields' => 75,
            'mapped_fields' => $this->faker->numberBetween(30, 70),
        ]);
    }

    private function generateSampleSchema(): string
    {
        $schema = [
            'form_id' => 'SAMPLE_' . $this->faker->numberBetween(1000, 9999),
            'title' => $this->faker->sentence(3),
            'data' => [
                'elements' => []
            ]
        ];

        // Add some sample fields
        for ($i = 0; $i < $this->faker->numberBetween(5, 15); $i++) {
            $schema['data']['elements'][] = [
                'token' => 'field_' . $i,
                'name' => 'field_' . $i,
                'label' => $this->faker->words(3, true),
                'elementType' => $this->faker->randomElement([
                    'TextInputFormElements',
                    'SelectInputFormElements',
                    'TextareaInputFormElements',
                    'CheckboxInputFormElements'
                ]),
                'isVisible' => true,
                'isEnabled' => true,
            ];
        }

        return json_encode($schema);
    }

    private function generateComplexSchema(): string
    {
        $schema = [
            'form_id' => 'COMPLEX_FORM_' . $this->faker->numberBetween(1000, 9999),
            'title' => 'Complex Form with Multiple Containers',
            'data' => [
                'elements' => []
            ]
        ];

        // Add containers with nested fields
        for ($container = 0; $container < 3; $container++) {
            $containerElement = [
                'token' => 'container_' . $container,
                'name' => 'container_' . $container,
                'label' => 'Container ' . ($container + 1),
                'elementType' => 'ContainerFormElements',
                'elements' => []
            ];

            // Add fields to container
            for ($field = 0; $field < $this->faker->numberBetween(10, 25); $field++) {
                $containerElement['elements'][] = [
                    'token' => "container_{$container}_field_{$field}",
                    'name' => "container_{$container}_field_{$field}",
                    'label' => $this->faker->words(3, true),
                    'elementType' => $this->faker->randomElement([
                        'TextInputFormElements',
                        'SelectInputFormElements',
                        'TextareaInputFormElements',
                        'CheckboxInputFormElements',
                        'RadioInputFormElements',
                        'DateInputFormElements'
                    ]),
                    'isVisible' => true,
                    'isEnabled' => true,
                ];
            }

            $schema['data']['elements'][] = $containerElement;
        }

        return json_encode($schema);
    }

    private function generateFieldMappings(): array
    {
        $mappings = [];
        $fieldCount = $this->faker->numberBetween(5, 20);

        for ($i = 0; $i < $fieldCount; $i++) {
            $fieldId = 'field_' . $i;
            $mappings[$fieldId] = $this->faker->randomElement([
                'new',
                'skip',
                $this->faker->numberBetween(1, 100), // Existing field ID
            ]);
        }

        return $mappings;
    }
}
