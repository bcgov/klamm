<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SystemMessage;
use App\Http\Resources\SystemMessageResource;
use App\Http\Resources\SystemMessageSummaryResource;

class SystemMessageController extends Controller
{
    public function index(Request $request)
    {
        $query = SystemMessage::query();

        if ($request->has('error_code')) {
            $errorCode = $request->input('error_code');
            $query->where('error_code', 'like', "{$errorCode}%");
        }

        $systemMessages = $query->select('id', 'error_code', 'error_message', 'error_data_group_id')->get();

        return SystemMessageSummaryResource::collection($systemMessages);
    }

    public function show($id)
    {
        $systemMessage = SystemMessage::findOrFail($id);
        return new SystemMessageResource($systemMessage);
    }

    public function getLastUpdated()
    {
        $lastUpdated = SystemMessage::max('updated_at');
        return response()->json(['last_updated' => $lastUpdated]);
    }
}
