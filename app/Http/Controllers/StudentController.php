<?php

namespace App\Http\Controllers;

use App\Models\RequestType;
use App\Models\StudentRequest;
use Illuminate\Support\Facades\Auth;

class StudentController extends Controller
{
    public function dashboard()
    {
        $student = Auth::user()->student;
        $typeEtudiant = Auth::user()->type_etudiant;

        $activeTab = request('tab', 'depot');
        if ($typeEtudiant === 'nouveau') {
            $activeTab = 'depot';
        }

        $categories = array_keys(RequestType::categoriesFor($typeEtudiant));

        $requestsByCategory = [];
        foreach ($categories as $cat) {
            // On prend TOUTES les demandes (tous statuts) triées par date décroissante
            $requestsByCategory[$cat] = StudentRequest::with('files.requestType')
                ->where('student_id', $student->id)
                ->where('category', $cat)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return view('student.dashboard', compact(
            'student',
            'typeEtudiant',
            'requestsByCategory',
            'activeTab'
        ));
    }
}
