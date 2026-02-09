<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PressKitController extends Controller
{
    /**
     * Отображение страницы "Для СМИ"
     */
    public function index()
    {
        return view('press.index');
    }
}
