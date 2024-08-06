<div class="mt-6">
    <div class="mb-5 text-center">
        <h1 class="text-3xl font-bold">My Profile</h1>
    </div>
    <form wire:submit.prevent="updateProfile" class="mb-5">
        {{ $this->form }}
    </form>
</div>