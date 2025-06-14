<?php

namespace App\Http\Controllers\API\Employer;

use App\Http\Controllers\Controller;
use App\Models\ApplicationNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ApplicationNoteController extends Controller
{
    public function index(Request $request, $applicationId)
    {
        $notes = ApplicationNote::select('id', 'application_id', 'note', 'created_at', 'created_by')->where('application_id', $applicationId)->with('user')->orderBy('created_at', 'desc')->get();
        return response()->json(['success'=> true, 'data' => $notes]);
    }

    public function store(Request $request, $applicationId)
    {
        $validation = Validator::make($request->all(),[
            'note' => 'required|string',
        ]);

        if ($validation->fails()) {
            return response()->json(["error"=> true, 'errors' => $validation->errors(), 'message' => 'Validation failed'], 422);
        }

        $user_id = Auth::user()->id;

        $note = new ApplicationNote();
        $note->application_id = $applicationId;
        $note->note = $request->note;
        $note->created_by = $user_id;
        $note->save();

        return response()->json(['success'=> true, 'data' => $note]);
    }

    public function destroy($id)
    {
        $note = ApplicationNote::findOrFail($id);
        $note->delete();
        return response()->json(null, 204);
    }
}
