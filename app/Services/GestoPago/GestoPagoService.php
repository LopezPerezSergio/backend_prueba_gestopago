<?php

namespace App\Services\GestoPago;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GestoPagoService
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

    private function getToken()
    {
        // Check cache for existing token
        $token = Cache::get('gestopago_token');

        if ($token) {
            return $token;
        }

        try {
            $client = new Client([
                'base_uri' => $this->server,
                'timeout' => 60, // Timeout for response in seconds
                'connect_timeout' => 10, // Timeout for establishing connection in seconds
            ]);

            $response = $client->post($this->auth_endpoint, [
                'headers' => [
                    'X-API-Key' => $this->x_api_key,
                ],
                'form_params' => [
                    'idDistribuidor' => $this->distributor_ID,
                    'codigoDispositivo' => $this->code_dispositive,
                    'password' => $this->password,
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $responseBody = json_decode($response->getBody(), true);
                $token = $responseBody['token'] ?? null;

                if ($token) {
                    Cache::put('gestopago_token', $token, 24 * 60); // Cache for 24 hours (in minutes)
                    return $token;
                }
            }

            Log::channel('gestopago')->error('Solicitud de Gestopago fallida:', [
                'URL' => $this->server . $this->auth_endpoint,
                'status_code' => $response->getStatusCode(),
                'response_body' => $response->getBody(),
            ]);

            return null;
        } catch (ClientException $e) {
            Log::channel('gestopago')->error('Error en la solicitud de Gestopago:', [
                'URL' => $this->server . $this->auth_endpoint,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function validateMe(): JsonResponse
    {
        try {
            $client = new Client([
                'base_uri' => $this->server,
                'timeout' => 60, // Timeout for response in seconds
                'connect_timeout' => 10, // Timeout for establishing connection in seconds
            ]);

            // Retrieve token 
            $token = $this->getToken();

            $response = $client->get($this->context . '/validateMe.do', [
                'headers' => [
                    'X-API-Key' => $this->x_api_key,
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            Log::channel('gestopago')->info('Solicitud de Gestopago:', [
                'URL' => $this->server . $this->context . '/validateMe.do',
                'response_status' => $response->getStatusCode(),
            ]);

            if ($response->getStatusCode() === 200) {
                // Get content
                $responseBody = (string) $response->getBody()->getContents();
                // Conver of ISO-8859-1 to UTF-8
                $responseBody = mb_convert_encoding($responseBody, 'UTF-8', 'ISO-8859-1');

                $xml = simplexml_load_string($responseBody);

                if ($xml->MENSAJE->CODIGO == '10') {
                    $data = [
                        'MENSAJE' => [
                            'CODIGO' => (string) $xml->MENSAJE->CODIGO,
                            'TEXTO' => (string) $xml->MENSAJE->TEXTO,
                            'NOMBRECOMERCIO' => (string) $xml->MENSAJE->NOMBRECOMERCIO,
                            'DIRECCION' => (string) $xml->MENSAJE->DIRECCION,
                        ],
                        'IDENTIFYME' => [
                            'VALID' => (string) $xml->IDENTIFYME->VALID,
                        ],
                    ];

                    return response()->json([
                        'success' => true,
                        'data' => $data,
                    ], $response->getStatusCode());
                }

                return response()->json([
                    'success' => false,
                    'status' => $response->getStatusCode(),
                    'data' => [
                        'MENSAJE' => [
                            'CODIGO' => (string) $xml->MENSAJE->CODIGO,
                            'TEXTO' => (string) $xml->MENSAJE->TEXTO,
                        ],
                    ],
                ], $response->getStatusCode());
            }

            return response()->json([
                'success' => false,
                'status' => $response->getStatusCode(),
                'message' => 'API request failed',
            ], $response->getStatusCode());
        } catch (ClientException $e) {
            Log::channel('gestopago')->error('Error en la solicitud de Gestopago:', [
                'URL' => $this->server . $this->context . '/validateMe.do',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getProductList(): JsonResponse
    {
        try {
            $client = new Client([
                'base_uri' => $this->server,
                'timeout' => 60, // Timeout for response in seconds
                'connect_timeout' => 10, // Timeout for establishing connection in seconds
            ]);

            // Retrieve token 
            $token = $this->getToken();

            $response = $client->get($this->context . '/getProductList.do', [
                'headers' => [
                    'X-API-Key' => $this->x_api_key,
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            Log::channel('gestopago')->info('Solicitud de Gestopago:', [
                'URL' => $this->server . $this->context . '/getProductList.do',
                'response_status' => $response->getStatusCode(),
            ]);

            if ($response->getStatusCode() === 200) {
                // Get content
                $responseBody = (string) $response->getBody()->getContents();
                //return $response->getHeader('Content-Type');
                // Conver of ISO-8859-1 to UTF-8
                $responseBody = mb_convert_encoding($responseBody, 'UTF-8', 'ISO-8859-1');

                $xml = simplexml_load_string($responseBody);
                //return $xml->MENSAJE->CODIGO;
                if ($xml->MENSAJE->CODIGO == '01') {
                    $productos = []; // Initialize an empty array to store product data

                    foreach ($xml->PRODUCTOS->producto as $producto) {
                        $productoData = [
                            'servicio' => (string) $producto['servicio'],
                            'producto' => (string) $producto['producto'],
                            'idServicio' => (string) $producto['idServicio'],
                            'idProducto' => (string) $producto['idProducto'],
                            'idCatTipoServicio' => (string) $producto['idCatTipoServicio'],
                            'tipoFront' => (int) $producto['tipoFront'],
                            'hasDigitoVerificador' => (bool) $producto['hasDigitoVerificador'],
                            'precio' => (float) $producto['precio'],
                            'showAyuda' => (bool) $producto['showAyuda'],
                            'tipoReferencia' => (string) $producto['tipoReferencia'],
                            'legend' => (string) $producto->legend, // Decode the base64 encoded legend text
                        ];

                        $productos[] = $productoData;
                    }

                    $data = [
                        'MENSAJE' => [
                            'CODIGO' => (string) $xml->MENSAJE->CODIGO,
                            'TEXTO' => (string) $xml->MENSAJE->TEXTO,
                        ],
                        'PRODUCTOS' => $productos,
                    ];

                    return response()->json([
                        'success' => true,
                        'data' => $data,
                    ], $response->getStatusCode());
                }

                return response()->json([
                    'success' => false,
                    'status' => $response->getStatusCode(),
                    'data' => [
                        'MENSAJE' => [
                            'CODIGO' => (string) $xml->MENSAJE->CODIGO,
                            'TEXTO' => (string) $xml->MENSAJE->TEXTO,
                        ],
                    ],
                ], $response->getStatusCode());
            }

            return response()->json([
                'success' => false,
                'status' => $response->getStatusCode(),
                'message' => 'API request failed',
            ], $response->getStatusCode());
        } catch (ClientException $e) {
            Log::channel('gestopago')->error('Error en la solicitud de Gestopago:', [
                'URL' => $this->server . $this->context . 'getProductList.do',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function sendTx($encrypted_payload): JsonResponse
    {
        try {
            $client = new Client([
                'base_uri' => $this->server,
                'timeout' => 60, // Timeout for response in seconds
                'connect_timeout' => 10, // Timeout for establishing connection in seconds
            ]);

            // Retrieve token 
            $token = $this->getToken();

            $response = $client->post($this->context . '/sendTx.do', [
                'headers' => [
                    'X-API-Key' => $this->x_api_key,
                    'Authorization' => 'Bearer ' . $token,
                ],
                'form_params' => [
                    'random' => (string) Carbon::now()->timestamp,
                    'signed' => $encrypted_payload,
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                // Get content
                $responseBody = (string) $response->getBody()->getContents();
                // Conver of ISO-8859-1 to UTF-8
                $responseBody = mb_convert_encoding($responseBody, 'UTF-8', 'ISO-8859-1');

                $xml = simplexml_load_string($responseBody);

                if ($xml->MENSAJE->CODIGO == '01') {
                    $data = [
                        'MENSAJE' => [
                            'CODIGO' => (string) $xml->MENSAJE->CODIGO,
                            'SALDOCLIENTE' => (float) $xml->MENSAJE->SALDOCLIENTE ?? NULL,
                            'PIN' => (string) $xml->MENSAJE->PIN ?? NULL,
                            'legend' => (string) $xml->MENSAJE->legend[0] ?? NULL,
                            'TEXTO' => (string) $xml->MENSAJE->TEXTO,
                            'REFERENCIA' => (string) $xml->MENSAJE->REFERENCIA,
                        ],
                        'ID_TX' => (string) $xml->ID_TX,
                        'NUM_AUTORIZACION' => (string) $xml->NUM_AUTORIZACION,
                        'SALDO' => (float) $xml->SALDO,
                        'COMISION' => (float) $xml->COMISION,
                        'SALDO_F' => (float) $xml->SALDO_F,
                        'COMISION_F' => (float) $xml->COMISION_F,
                        'FECHA' => (string) $xml->FECHA,
                        'MONTO' => (float) $xml->MONTO,
                    ];

                    Log::channel('gestopago')->info('Solicitud de Gestopago:', [
                        'URL' => $this->server . $this->context . '/sendTx.do',
                        'response_status' => $response->getStatusCode(),
                        'response' => $data,
                    ]);
                    return response()->json([
                        'success' => true,
                        'data' => $data,
                    ], $response->getStatusCode());
                }

                /* aqui se validara si se hizo o el proceso */
                Log::channel('gestopago')->info('Solicitud de Gestopago:', [
                    'URL' => $this->server . $this->context . '/sendTx.do',
                    'response_status' => $response->getStatusCode(),
                    'response' => [
                        'NUM_AUTORIZACION' => (string) $xml->NUM_AUTORIZACION,
                        'MENSAJE' => [
                            'CODIGO' => (string) $xml->MENSAJE->CODIGO,
                            'TEXTO' => (string) $xml->MENSAJE->TEXTO,
                        ],
                    ],
                ]);

                return response()->json([
                    'success' => false,
                    'status' => $response->getStatusCode(),
                    'data' => [
                        'NUM_AUTORIZACION' => (string) $xml->NUM_AUTORIZACION,
                        'MENSAJE' => [
                            'CODIGO' => (string) $xml->MENSAJE->CODIGO,
                            'TEXTO' => (string) $xml->MENSAJE->TEXTO,
                        ],
                    ],
                ], $response->getStatusCode());
            }

            return response()->json([
                'success' => false,
                'status' => $response->getStatusCode(),
                'message' => 'API request failed',
            ], $response->getStatusCode());
        } catch (ClientException $e) {
            Log::channel('gestopago')->error('Error en la solicitud de Gestopago:', [
                'URL' => $this->server . $this->context . '/sendTx.do',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function confirmTx($encrypted_payload): JsonResponse
    {
        try {
            $client = new Client([
                'base_uri' => $this->server,
                'timeout' => 60, // Timeout for response in seconds
                'connect_timeout' => 10, // Timeout for establishing connection in seconds
            ]);

            // Retrieve token 
            $token = $this->getToken();

            $response = $client->post($this->context . '/confirmTx.do', [
                'headers' => [
                    'X-API-Key' => $this->x_api_key,
                    'Authorization' => 'Bearer ' . $token,
                ],
                'form_params' => [
                    'random' => (string) Carbon::now()->timestamp,
                    'signed' => $encrypted_payload,
                ],
            ]);

            Log::channel('gestopago')->info('Solicitud de Gestopago:', [
                'URL' => $this->server . $this->context . '/confirmTx.do',
                'response_status' => $response->getStatusCode(),
            ]);


            if ($response->getStatusCode() === 200) {
                // Get content
                $responseBody = (string) $response->getBody()->getContents();
                // Conver of ISO-8859-1 to UTF-8
                $responseBody = mb_convert_encoding($responseBody, 'UTF-8', 'ISO-8859-1');

                $xml = simplexml_load_string($responseBody);

                if ($xml->MENSAJE->CODIGO == '06') {
                    $data = [
                        'MENSAJE' => [
                            'CODIGO' => (string) $xml->MENSAJE->CODIGO,
                            'TEXTO' => (string) $xml->MENSAJE->TEXTO,
                            'ID_TX' => (string) $xml->MENSAJE->ID_TX,
                        ],
                        'NUM_AUTORIZACION' => (string) $xml->NUM_AUTORIZACION,
                        'COMISION' => (float) $xml->COMISION,
                        'FECHA' => (string) $xml->FECHA,
                    ];

                    return response()->json([
                        'success' => true,
                        'data' => $data,
                    ], $response->getStatusCode());
                }

                return response()->json([
                    'success' => false,
                    'status' => $response->getStatusCode(),
                    'data' => [
                        'NUM_AUTORIZACION' => (string) $xml->NUM_AUTORIZACION,
                        'MENSAJE' => [
                            'CODIGO' => (string) $xml->MENSAJE->CODIGO,
                            'TEXTO' => (string) $xml->MENSAJE->TEXTO,
                        ],
                    ],
                ], $response->getStatusCode());
            }

            return response()->json([
                'success' => false,
                'status' => $response->getStatusCode(),
                'message' => 'API request failed',
            ], $response->getStatusCode());
        } catch (ClientException $e) {
            Log::channel('gestopago')->error('Error en la solicitud de Gestopago:', [
                'URL' => $this->server . $this->context . '/confirmTx.do',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
