<?php

namespace App\Http\Controllers\Services;

use App\Helpers\GestoPago\AesCipher;
use App\Http\Controllers\Controller;
use App\Services\GestoPago\GestoPagoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GestoPagoController extends Controller
{
    protected $gestopagoService;
    protected $distributor_ID;


    public function __construct(GestoPagoService $gestopagoService)
    {
        $this->distributor_ID = ENV('GESTOPAGO_ID_DISTRIBUTOR');
        $this->gestopagoService = $gestopagoService;
    }

    /* public function authenticate()
    {
        return $this->gestopagoService->getToken();
    }  */

    public function validateMe(): JsonResponse
    {
        return $this->gestopagoService->validateMe();
    }

    public function getProductList(): JsonResponse
    {
        return $this->gestopagoService->getProductList();
    }

    public function sendTx(Request $request): JsonResponse
    {
        $now = Carbon::now(); // Get the current moment in time
        $horaLocal = $now->format('YmdHis'); // Format the time as "20220101235900"
        $telefono = '1111111111';

        if ($request->tipoFront == 1) {
            $telefono = ((string) $request->has('referencia')) ? (string) $request->referencia : $request->telefono;
        }

        if (in_array($request->idCatTipoServicio, ['10', '11'])) {
            $telefono = '#1111111111';
        }

        $payload = [
            'idProducto' => (int) $request->idProducto,
            'idServicio' => (int) $request->idServicio,

            'telefono' => ($request->tipoFront == 1 || $request->tipoFront == 2) ? $telefono : null,
            'horaLocal' => $horaLocal,

            'referencia' => $request->has('referencia') ? (string) $request->referencia : null, // Only for service payments.
            'montoPago' => $request->has('montoPago') ? (float) $request->montoPago : null, // Only for service payments.

            'upc' =>  $horaLocal . 'T',
            'unidad' => (string) $this->distributor_ID,
        ];

        $encrypted_payload = AesCipher::encrypt(json_encode($payload));

        return $this->gestopagoService->sendTx($encrypted_payload);
    }

    public function confirmTx(Request $request): JsonResponse
    {
        $now = Carbon::now(); // Get the current moment in time
        $horaLocal = $now->format('YmdHis'); // Format the time as "20220101235900"
        $telefono = '1111111111';

        if ($request->tipoFront == 1) {
            $telefono = ((string) $request->has('referencia')) ? (string) $request->referencia : $request->telefono;
        }

        if (in_array($request->idCatTipoServicio, ['10', '11'])) {
            $telefono = '#1111111111';
        }

        $payload = [
            'idProducto' => (int) $request->idProducto,
            'idServicio' => (int) $request->idServicio,

            'telefono' => ($request->tipoFront == 1 || $request->tipoFront == 2) ? $telefono : null,
            'horaLocal' => $horaLocal,

            'referencia' => $request->has('referencia') ? (string) $request->referencia : null, // Only for service payments.
            'montoPago' => $request->has('montoPago') ? (float) $request->montoPago : null, // Only for service payments.

            'upc' =>  $horaLocal . 'T',
            'unidad' => (string) $this->distributor_ID,
        ];

        $encrypted_payload = AesCipher::encrypt(json_encode($payload));

        return $this->gestopagoService->confirmTx   ($encrypted_payload);
    }
}
