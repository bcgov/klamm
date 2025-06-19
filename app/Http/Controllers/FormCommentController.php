<?php

namespace App\Http\Controllers;

use App\Models\FormComment;
use App\Models\FormVersion;
use App\Models\FormInstanceField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FormCommentController extends Controller
{
    /**
     * Store a new form comment
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // Validate the request data
            $request->validate([
                'form_version_id' => 'required|exists:form_versions,id',
                'element_id' => 'nullable|string',
                'parent_comment_id' => 'nullable|exists:form_comments,id',
                'commenter' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'text' => 'required|string',
                'x' => 'nullable|numeric',
                'y' => 'nullable|numeric',
            ]);

            $resolvedElementId = null;
            if ($request->filled('element_id')) {
                // Lookup FormInstanceField by form_version_id and custom_instance_id
                $instanceField = FormInstanceField::where('form_version_id', $request->form_version_id)
                    ->where('custom_instance_id', $request->element_id)
                    ->first();
                if (!$instanceField) {
                    return response()->json([
                        'error' => 'Invalid element_id',
                        'message' => 'No field found for the provided element_id and form_version_id.'
                    ], 422);
                }
                $resolvedElementId = $instanceField->form_field_id;
            }

            // Create the new comment
            $comment = FormComment::create([
                'form_version_id' => $request->form_version_id,
                'parent_comment_id' => $request->parent_comment_id,
                'element_id' => $resolvedElementId,
                'commenter' => $request->commenter,
                'email' => $request->email,
                'text' => $request->text,
                'x' => $request->x,
                'y' => $request->y,
                'resolved' => false, // New comments are unresolved by default
            ]);

            // Load relationships if this is a reply or has a related element
            if ($comment->parent_comment_id) {
                $comment->load('parent');
            }

            if ($comment->element_id) {
                $comment->load('element');
            }

            return response()->json([
                'message' => 'Comment created successfully',
                'comment' => $comment
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create form comment: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->all()
            ]);

            return response()->json([
                'error' => 'Failed to create comment',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the resolved status of a form comment
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'resolved' => 'required|boolean',
            ]);

            $comment = FormComment::findOrFail($id);
            $comment->resolved = $request->resolved;
            $comment->save();

            return response()->json([
                'message' => 'Comment resolved status updated successfully',
                'comment' => $comment
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update form comment: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->all()
            ]);

            return response()->json([
                'error' => 'Failed to update comment',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
