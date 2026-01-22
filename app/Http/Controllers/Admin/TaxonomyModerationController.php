<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductType;
use App\Models\ApplicationDomain;
use Illuminate\Http\Request;

class TaxonomyModerationController extends Controller
{
    /**
     * Главная страница модерации (pending items)
     */
    public function index()
    {
        return view('admin.taxonomy.index');
    }

    /**
     * Управление доменами
     */
    public function domains()
    {
        return view('admin.taxonomy.domains');
    }

    /**
     * Управление типами товаров
     */
    public function productTypes()
    {
        return view('admin.taxonomy.product-types');
    }
}
