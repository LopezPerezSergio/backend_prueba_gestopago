<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Services\GestoPago\GerstopagoService;
use Illuminate\Http\Request;

class GestoPagoController extends Controller
{
    protected $gerstopagoService;

    public function __construct(GerstopagoService $gerstopagoService)
    {
        $this->gerstopagoService = $gerstopagoService;
    }

    public function authenticate()
    {
        return $this->gerstopagoService->authenticate();
    }
}
