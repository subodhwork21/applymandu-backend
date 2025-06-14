<?php

namespace App\Http\Controllers\API;

use App\Constants\Skills;
use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function index(Request $request){
        $formattedSkills = array_map(function ($skill) {
            return [
                'value' => $skill,
                'label' => ucfirst($skill)
            ];
        }, Skills::toArray());
        
        return response()->json($formattedSkills);
    }

    public function store(Request $request){
        $validation = validator($request->all(), [
            'skills' => 'required|array',
            // 'skills.*' => 'string|in:'.implode(',', Skills::toArray())
            'skill.*'=> 'string',
        ]);
        
        if($validation->fails()){
            return response()->json([
                'error' => true,
                'message' => 'Validation failed',
                'errors' => $validation->errors()
            ], 422);
        }
        
        $skills = $request->input('skills');
        
        // Rest of your code remains the same
        foreach ($skills as $skill) {
            if (!Skill::where('name', $skill)->exists()) {
                Skill::create([
                    'name' => $skill
                ]);
            }
        }
    
        return response()->json([
            'success' => true,
            'message' => 'Skills added successfully'
        ]);
    }


    public function update(Request $request, $id){
        $skill = Skill::find($id);
        if(!$skill){
            return response()->json([
                'error' => true,
                'message' => 'Skill not found'
            ], 404);
        }
        $validation = validator($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        if($validation->fails()){
            return response()->json([
                'error' => true,
                'message' => 'Validation failed',
                'errors' => $validation->errors()
            ], 422);
        }
        if (!Skill::where('name', $request->name)->exists()) {
            $skill->name = $request->input('name');
        }
        else{
            return response()->json([
                'error' => true,
                'message' => 'Skill already exists'
            ], 422);
        }
        $skill->save();
        return response()->json([
            'success' => true,
            'message' => 'Skill updated successfully'
        ]);
    }


    public function destroy($id){
        $skill = Skill::find($id);
        if(!$skill){
            return response()->json([
                'error' => true,
                'message' => 'Skill not found'
            ], 404);
        }
        $skill->delete();
        return response()->json([
            'success' => true,
            'message' => 'Skill deleted successfully'
        ]);
    }
    public function show($id){
        $skill = Skill::find($id);
        if(!$skill){
            return response()->json([
                'error' => true,
                'message' => 'Skill not found'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $skill
        ]);
    }

    public function allSkills(){
        $allSkills = Skill::all();
        return response()->json([
            'success'=> true,
            'data' => $allSkills,
        ]);
    }
}
