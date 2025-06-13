<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FormVersionUpdateEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $formVersionId;
    public $formId;
    public $versionNumber;
    public $updatedComponents;
    public $updateType;
    public $isDraft;

    /**
     * Create a new event instance.
     *
     * @param int $formVersionId The ID of the form version that was updated
     * @param int $formId The ID of the parent form
     * @param int $versionNumber The version number
     * @param array|null $updatedComponents The updated components array (when applicable)
     * @param string $updateType Type of update: 'components', 'status', 'deployment', etc.
     * @param bool $isDraft Whether this is a draft update
     */
    public function __construct(
        int $formVersionId,
        int $formId,
        int $versionNumber,
        ?array $updatedComponents = null,
        string $updateType = 'components',
        bool $isDraft = false
    ) {
        $this->formVersionId = $formVersionId;
        $this->formId = $formId;
        $this->versionNumber = $versionNumber;
        $this->updatedComponents = $updatedComponents;
        $this->updateType = $updateType;
        $this->isDraft = $isDraft;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channelPrefix = $this->isDraft ? 'draft-' : '';

        return [
            new Channel($channelPrefix . 'form-version.' . $this->formVersionId),
            new Channel($channelPrefix . 'form.' . $this->formId),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'formVersionId' => $this->formVersionId,
            'formId' => $this->formId,
            'versionNumber' => $this->versionNumber,
            'updateType' => $this->updateType,
            'isDraft' => $this->isDraft,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
