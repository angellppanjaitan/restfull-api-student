<?php

namespace App\Http\Controllers;

use App\Http\Resources\StudentResource;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StudentController extends Controller
{
    public function index()
    {
        return StudentResource::collection(Student::latest()->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nim' => ['required', 'string', 'max:50', 'unique:students,nim'],
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:students,email'],
            'address' => ['required', 'string'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $student = Student::create($validated);

        return (new StudentResource($student))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Student $student)
    {
        return new StudentResource($student);
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'nim' => ['sometimes', 'required', 'string', 'max:50', 'unique:students,nim,' . $student->id],
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:students,email,' . $student->id],
            'address' => ['sometimes', 'required', 'string'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
        ]);

        $student->update($validated);

        return new StudentResource($student->fresh());
    }

    public function destroy(Student $student)
    {
        $student->delete();

        return response()->json([
            'message' => 'Student deleted successfully.',
        ]);
    }
}
