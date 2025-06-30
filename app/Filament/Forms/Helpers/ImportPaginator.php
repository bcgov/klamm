<?php

namespace App\Filament\Forms\Helpers;

use Illuminate\Support\Facades\Log;

/**
 * Import Paginator
 *
 * Handles pagination logic for the schema import field mapping interface.
 * This class manages page navigation, per-page settings, and field slicing
 * to optimize UI performance when dealing with large schemas.
 */
class ImportPaginator
{
    private int $currentPage;
    private int $perPage;
    private int $totalFields;
    private array $paginatedFields;

    public function __construct(int $currentPage = 1, int $perPage = 10)
    {
        $this->currentPage = $currentPage;
        $this->perPage = $perPage;
        $this->totalFields = 0;
        $this->paginatedFields = [];
    }

    /**
     * Set the total number of fields for pagination calculations
     *
     * @param int $totalFields Total number of fields
     * @return self
     */
    public function setTotalFields(int $totalFields): self
    {
        $this->totalFields = $totalFields;
        return $this;
    }

    /**
     * Get the current page number
     *
     * @return int Current page number
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Get the per-page setting
     *
     * @return int Number of items per page
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Get the total number of fields
     *
     * @return int Total fields count
     */
    public function getTotalFields(): int
    {
        return $this->totalFields;
    }

    /**
     * Get the paginated fields array
     *
     * @return array Current page's fields
     */
    public function getPaginatedFields(): array
    {
        return $this->paginatedFields;
    }

    /**
     * Get the maximum page number
     *
     * @return int Maximum page number
     */
    public function getMaxPage(): int
    {
        return $this->totalFields > 0 ? (int) ceil($this->totalFields / $this->perPage) : 1;
    }

    /**
     * Get pagination display information
     *
     * @return array Pagination info for UI display
     */
    public function getPaginationInfo(): array
    {
        $start = ($this->currentPage - 1) * $this->perPage + 1;
        $end = min($this->currentPage * $this->perPage, $this->totalFields);
        $totalPages = $this->getMaxPage();

        return [
            'start' => $start,
            'end' => $end,
            'total' => $this->totalFields,
            'current_page' => $this->currentPage,
            'total_pages' => $totalPages,
            'has_previous' => $this->currentPage > 1,
            'has_next' => $this->currentPage < $totalPages,
        ];
    }

    /**
     * Paginate an array of fields
     *
     * @param array $fields All fields to paginate
     * @return array Paginated subset of fields
     */
    public function paginateFields(array $fields): array
    {
        $this->setTotalFields(count($fields));

        // Ensure currentPage is valid
        $this->validateCurrentPage();

        // Apply pagination
        $start = ($this->currentPage - 1) * $this->perPage;
        $this->paginatedFields = array_slice($fields, $start, $this->perPage);

        Log::debug('PaginateFields called', [
            'total_fields' => count($fields),
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'start_index' => $start,
            'paginated_count' => count($this->paginatedFields),
            'first_field_token' => $this->paginatedFields[0]['token'] ?? 'N/A',
            'last_field_token' => end($this->paginatedFields)['token'] ?? 'N/A'
        ]);

        return $this->paginatedFields;
    }

    /**
     * Go to the next page
     *
     * @return bool True if page was changed, false if already at last page
     */
    public function nextPage(): bool
    {
        $maxPage = $this->getMaxPage();

        Log::debug('Paginator nextPage called', [
            'current_page' => $this->currentPage,
            'max_page' => $maxPage,
            'total_fields' => $this->totalFields,
            'per_page' => $this->perPage
        ]);

        if ($this->currentPage < $maxPage) {
            $this->currentPage++;

            Log::debug('Paginator nextPage successful', [
                'new_current_page' => $this->currentPage
            ]);

            return true;
        }

        Log::debug('Paginator nextPage failed - already at max page');
        return false;
    }

    /**
     * Go to the previous page
     *
     * @return bool True if page was changed, false if already at first page
     */
    public function prevPage(): bool
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
            return true;
        }
        return false;
    }

    /**
     * Change the number of items per page
     *
     * @param int $perPage New per-page setting
     * @return array Change information including whether a refresh is needed
     */
    public function changePerPage(int $perPage): array
    {
        $oldPerPage = $this->perPage;

        // Validate perPage
        if ($perPage < 1) {
            $perPage = 10;
        }
        if ($perPage > 100) {
            $perPage = 100; // Reasonable upper limit
        }

        $this->perPage = $perPage;

        // Reset to first page when changing per-page setting
        $this->currentPage = 1;

        return [
            'changed' => $oldPerPage !== $this->perPage,
            'old_per_page' => $oldPerPage,
            'new_per_page' => $this->perPage,
        ];
    }

    /**
     * Reset pagination to initial state
     *
     * @return void
     */
    public function resetPagination(): void
    {
        $this->currentPage = 1;
        // Don't reset perPage as user may have set their preference
    }

    /**
     * Update current page with validation
     *
     * @param int $page New page number
     * @return bool True if page was changed, false if invalid
     */
    public function setCurrentPage(int $page): bool
    {
        $oldPage = $this->currentPage;
        $this->currentPage = $page;
        $this->validateCurrentPage();

        return $oldPage !== $this->currentPage;
    }

    /**
     * Update per-page setting with validation
     *
     * @param int $perPage New per-page setting
     * @return bool True if per-page was changed, false if invalid
     */
    public function setPerPage(int $perPage): bool
    {
        $oldPerPage = $this->perPage;

        // Validate perPage
        if ($perPage < 1) {
            $perPage = 10;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $this->perPage = $perPage;
        $this->currentPage = 1; // Reset to first page

        return $oldPerPage !== $this->perPage;
    }

    /**
     * Validate and fix current page if out of bounds
     *
     * @return void
     */
    private function validateCurrentPage(): void
    {
        $oldPage = $this->currentPage;

        if ($this->currentPage < 1) {
            $this->currentPage = 1;
        }

        $maxPage = $this->getMaxPage();
        if ($this->currentPage > $maxPage) {
            $this->currentPage = $maxPage;
        }

        if ($oldPage !== $this->currentPage) {
            Log::debug('ValidateCurrentPage changed page', [
                'old_page' => $oldPage,
                'new_page' => $this->currentPage,
                'max_page' => $maxPage,
                'total_fields' => $this->totalFields,
                'per_page' => $this->perPage
            ]);
        }
    }

    /**
     * Check if pagination is needed (more than one page)
     *
     * @return bool True if pagination controls should be shown
     */
    public function isPaginationNeeded(): bool
    {
        return $this->totalFields > $this->perPage;
    }

    /**
     * Get state array for session storage
     *
     * @return array State data for persistence
     */
    public function getState(): array
    {
        return [
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'total_fields' => $this->totalFields,
        ];
    }

    /**
     * Restore state from session data
     *
     * @param array $state Saved state data
     * @return void
     */
    public function restoreState(array $state): void
    {
        $this->currentPage = $state['current_page'] ?? 1;
        $this->perPage = $state['per_page'] ?? 10;
        $this->totalFields = $state['total_fields'] ?? 0;

        $this->validateCurrentPage();
    }

    /**
     * Create paginated schema for field mapping
     *
     * @param array $parsedSchema Full parsed schema
     * @param array $allFields All extracted fields
     * @return array Modified schema with paginated fields
     */
    public function createPaginatedSchema(array $parsedSchema, array $allFields): array
    {
        $paginatedFields = $this->paginateFields($allFields);

        // Create a mini-schema with just the paginated fields
        $paginatedSchema = $parsedSchema;

        if (isset($paginatedSchema['data']) && isset($paginatedSchema['data']['elements'])) {
            $paginatedSchema['data']['elements'] = $paginatedFields;
        } elseif (isset($paginatedSchema['fields'])) {
            $paginatedSchema['fields'] = $paginatedFields;
        }

        return $paginatedSchema;
    }
}
