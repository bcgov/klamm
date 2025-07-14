<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\FormBuilding\FormVersion;
use App\Models\Form;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Form Version channel - Returns the form version
Broadcast::channel('form-version.{formVersionId}', function ($user = null, $formVersionId) {
    return FormVersion::find($formVersionId);
});

// Form channel - Returns the form
Broadcast::channel('form.{formId}', function ($user = null, $formId) {
    return Form::find($formId);
});

// Draft Form Version channel - Returns the form version
Broadcast::channel('draft-form-version.{formVersionId}', function ($user = null, $formVersionId) {
    return FormVersion::find($formVersionId);
});

// Draft Form channel - Returns the form
Broadcast::channel('draft-form.{formId}', function ($user = null, $formId) {
    return Form::find($formId);
});
