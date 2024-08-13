<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BREField;
use App\Http\Resources\BREFieldResource;
use Illuminate\Support\Facades\Validator;

class BREFieldController extends Controller
// {
//     /**
//      * Display a listing of the resource.
//      */
//     public function index()
//     {
//         // Return all BREFields
//         return BREField::all();
//     }


//     public function store(Request $request)
//     {
//         // Validate the request
//         $validated = $request->validate([
//             'name' => 'required|string|max:255',
//             'label' => 'nullable|string',
//             'help_text' => 'nullable|string',
//             'data_type_id' => 'required|integer|exists:bre_data_types,id',
//             'description' => 'nullable|string',
//         ]);

//         // Create the BREField record
//         $breField = BREField::create($validated);

//         // Return the created resource
//         return response()->json($breField, 201);
//     }


//     /**
//      * Display the specified resource.
//      */
//     public function show($id)
//     {
//         // Find the BREField by ID
//         $breField = BREField::findOrFail($id);

//         // Return the resource
//         return response()->json($breField);
//     }

//     /**
//      * Update the specified resource in storage.
//      */
//     public function update(Request $request, $id)
//     {
//         // Find the BREField by ID
//         $breField = BREField::findOrFail($id);

//         // Validate the request
//         $validated = $request->validate([
//             'name' => 'required|string|max:255',
//             'label' => 'nullable|string',
//             'help_text' => 'nullable|string',
//             'data_type_id' => 'required|integer|exists:bre_data_types,id',
//             'description' => 'nullable|string',
//         ]);

//         // Update the BREField record
//         $breField->update($validated);

//         // Return the updated resource
//         return response()->json($breField);
//     }


//     /**
//      * Remove the specified resource from storage.
//      */
//     public function destroy($id)
//     {
//         // Find the BREField by ID
//         $breField = BREField::findOrFail($id);

//         // Delete the BREField record
//         $breField->delete();

//         // Return a no-content response
//         return response()->json(null, 204);
//     }
// }
{
    public function index()
    {
        return BREFieldResource::collection(BREField::all());
    }

    public function show($id)
    {
        $breField = BREField::findOrFail($id);
        return new BREFieldResource($breField);
    }

    public function store(Request $request)
    {
        // Validation code
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'label' => 'nullable|string|max:255',
            'help_text' => 'nullable|string|max:1000',
            'data_type_id' => 'required|integer|exists:bre_data_types,id',
            'description' => 'nullable|string',
        ]);

        // Create a new BREField
        $breField = BREField::create($validated);

        return new BREFieldResource($breField);
    }

    public function update(Request $request, $id)
    {
        // Validation code
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'label' => 'nullable|string|max:255',
            'help_text' => 'nullable|string|max:1000',
            'data_type_id' => 'required|integer|exists:bre_data_types,id',
            'description' => 'nullable|string',
        ]);

        // Find the BREField by ID
        $breField = BREField::findOrFail($id);

        // Update the BREField
        $breField->update($validated);

        return new BREFieldResource($breField);
    }

    public function destroy($id)
    {
        $breField = BREField::findOrFail($id);
        $breField->delete();

        return response()->json(null, 204);
    }
}
