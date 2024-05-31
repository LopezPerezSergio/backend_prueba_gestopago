<?php

namespace App\Services\GestoPago;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GerstopagoService
{
    protected $secret_key;
    protected $secret_IV;

    protected $server;
    protected $context;
    protected $auth_endpoint;
    protected $x_api_key;

    protected $distributor_ID;
    protected $code_dispositive;
    protected $password;

    protected $client;
    protected $token;

    public function __construct()
    {
        $this->server = ENV('GESTOPAGO_SERVER');
        $this->context = ENV('GESTOPAGO_CONTEXT');
        $this->auth_endpoint = ENV('GESTOPAGO_AUTH_ENDPOINT');
        $this->x_api_key = ENV('X_API_Key');

        $this->distributor_ID = ENV('GESTOPAGO_ID_DISTRIBUTOR');
        $this->code_dispositive = ENV('GESTOPAGO_CODE_DISPOSITIVE');
        $this->password = ENV('GESTOPAGO_PASSWORD');

        $this->token = $this->getToken();
    }

    /* public function authenticate(): JsonResponse
    {
        try {
            $client = new Client([
                'timeout' => 60, // Tiempo de espera para la respuesta en segundos
                'connect_timeout' => 10, // Tiempo de espera para establecer la conexión en segundos
                'headers' => [
                    'X-API-Key' => $this->x_api_key,
                ]
            ]);

            $request = new Request(
                'POST',
                $this->server . $this->auth_endpoint . '?idDistribuidor=' . $this->distributor_ID . '&codigoDispositivo=' . $this->code_dispositive . '&password=' . $this->password
            );

            $response = $client->sendAsync($request)->wait();

            Log::channel('gestopago')->info('Solicitud de Gestopago:', ['request' => $request, 'response' => $res]);

            // Aquí puedes realizar cualquier otra operación que necesite el token
            return response()->json(['data' => $res->getBody()], $res->getStatusCode());
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    } */

    private  function getToken()
    {
        $token = Cache::get('gestopago_token');
        $response = null;

        if (!$token) {
            try {
                $response = Http::withHeaders([
                    'X-API-Key' => $this->x_api_key,
                ])
                    ->timeout(60) // Timeout for response in seconds
                    ->connectTimeout(10) // Timeout for establishing connection in seconds
                    ->post($this->server . $this->auth_endpoint, [
                        'idDistribuidor' => $this->distributor_ID,
                        'codigoDispositivo' => $this->code_dispositive,
                        'password' => $this->password,
                    ]);

                // Verificar si la respuesta tiene éxito
                if ($response->successful()) {
                    Cache::put('gestopago_token', $response, 24 * 60); // Cache for 24 hours (in minutes)

                    $token = $response->json()['token'];
                }

                Log::channel('gestopago')->info('Solicitud de Gestopago:', [
                    'URL' => $$response->effectiveUrl,
                    'response_status' => $response->status(),
                ]);

                return $token;
            } catch (Exception $e) {
                Log::channel('gestopago')->info('Solicitud de Gestopago:', [
                    'URL' => $this->server . $this->auth_endpoint,
                    'error' => $e->getMessage(),
                ]);

                // Manejar respuestas no exitosas
                return response()->json([
                    'success' => false,
                    'data' => $response,
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        return $token;
    }

    public function validateMe(): JsonResponse
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->x_api_key,
                'Authorization' => Cache::get('gestopago_token'),
            ])
                ->timeout(60) // Timeout for response in seconds
                ->connectTimeout(10) // Timeout for establishing connection in seconds
                ->get($this->server . $this->context . 'validateMe.do');

            Log::channel('gestopago')->info('Solicitud de Gestopago:', [
                'URL' => $$response->effectiveUrl,
                'response_status' => $response->status(),
            ]);

            return $response->json()['token'];
        } catch (Exception $e) {
            Log::channel('gestopago')->info('Solicitud de Gestopago:', [
                'URL' => $this->server . $this->auth_endpoint,
                'error' => $e->getMessage(),
            ]);

            // Manejar respuestas no exitosas
            return response()->json([
                'success' => false,
                'data' => $response,
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $response->json(),
        ], $response->status());
    }



    private function responseJson($response): JsonResponse
    {
        // Verificar si la respuesta tiene éxito
        if ($response->successful()) {
            // La respuesta fue exitosa
            return response()->json([
                'success' => true,
                'data' => $response->json(),
            ], $response->status());
        }

        // Manejar respuestas no exitosas
        return response()->json([
            'success' => false,
            'status' => $response->status(),
            'message' => $response->json()['message'] ?? 'Unknown error',
            'data' => $response->json(),
        ], $response->status());
    }
}
