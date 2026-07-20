<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Llama a un modelo de visión para extraer los datos de una factura de compra
 * (foto o PDF) y devolverlos como JSON estructurado. Laravel lo llama DIRECTO
 * (sin n8n) porque el archivo se sube en la pantalla de Recepción.
 *
 * Dos drivers (config `services.vision.provider`):
 *  - 'google'     → API nativa de Gemini (gratis con key propia; lee PDF e imágenes).
 *  - 'openrouter' → gateway OpenAI-compatible (A/B de modelos; parsea PDF él mismo).
 *
 * El matching contra el catálogo lo hace MatcheoFactura.
 */
class FacturaScanner
{
    /**
     * @return array{cabecera:array<string,mixed>,lineas:array<int,array<string,mixed>>}
     */
    public function extraer(string $rutaAbsoluta, string $mime): array
    {
        if (! is_file($rutaAbsoluta)) {
            throw new RuntimeException("No se encontró el archivo de la factura: {$rutaAbsoluta}");
        }

        // Las fotos de celular pesan varios MB → se achican antes de enviar
        // (menos subida y procesado, sin perder legibilidad). Los PDF van nativos.
        if (str_starts_with($mime, 'image/')) {
            [$b64, $mime] = $this->optimizarImagen($rutaAbsoluta, $mime);
        } else {
            $b64 = base64_encode((string) file_get_contents($rutaAbsoluta));
        }

        $texto = config('services.vision.provider') === 'google'
            ? $this->llamarGoogle($mime, $b64)
            : $this->llamarOpenRouter($mime, $b64);

        return $this->parsearJson($texto);
    }

    /**
     * Reduce una foto a un máximo de ~1600px y la recodifica JPEG (calidad 85)
     * para acelerar el envío y el procesado. Si GD falla, usa el original.
     *
     * @return array{0:string,1:string}  [base64, mime]
     */
    private function optimizarImagen(string $ruta, string $mime): array
    {
        $original = (string) file_get_contents($ruta);
        if (! function_exists('imagecreatefromstring')) {
            return [base64_encode($original), $mime];
        }

        try {
            $img = @imagecreatefromstring($original);
            if (! $img) {
                return [base64_encode($original), $mime];
            }
            $w = imagesx($img);
            $h = imagesy($img);
            $max = 1600;
            $escala = min(1, $max / max($w, $h));
            if ($escala < 1) {
                $nw = (int) ($w * $escala);
                $nh = (int) ($h * $escala);
                $dst = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($img);
                $img = $dst;
            }
            ob_start();
            imagejpeg($img, null, 85);
            $data = (string) ob_get_clean();
            imagedestroy($img);

            // Sólo conviene si efectivamente achicó.
            return strlen($data) > 0 && strlen($data) < strlen($original)
                ? [base64_encode($data), 'image/jpeg']
                : [base64_encode($original), $mime];
        } catch (\Throwable) {
            return [base64_encode($original), $mime];
        }
    }

    /** Driver Google (Gemini API nativa). Acepta PDF e imágenes vía inline_data. */
    private function llamarGoogle(string $mime, string $b64): string
    {
        $key = config('services.google_ai.key');
        if (! $key) {
            throw new RuntimeException('Falta GOOGLE_AI_KEY en .env para escanear facturas.');
        }
        $model = config('services.google_ai.model');
        $url = rtrim((string) config('services.google_ai.base_url'), '/') . "/models/{$model}:generateContent";

        $resp = $this->enviarConReintento(fn () => Http::withHeaders(['x-goog-api-key' => $key])
            ->timeout(120)
            ->post($url, [
                'system_instruction' => ['parts' => [['text' => $this->instruccionSistema()]]],
                'contents' => [[
                    'role' => 'user',
                    'parts' => [
                        ['text' => $this->instruccionUsuario()],
                        ['inline_data' => ['mime_type' => $mime, 'data' => $b64]],
                    ],
                ]],
                'generationConfig' => [
                    'temperature' => 0,
                    'response_mime_type' => 'application/json',
                    // Extracción estructurada: el "thinking" no aporta y agrega latencia.
                    'thinkingConfig' => ['thinkingBudget' => 0],
                    'maxOutputTokens' => 8192,
                ],
            ]));

        if ($resp->failed()) {
            throw new RuntimeException('Error de Gemini: ' . $resp->status() . ' ' . $resp->body());
        }

        return (string) data_get($resp->json(), 'candidates.0.content.parts.0.text', '');
    }

    /** Driver OpenRouter (OpenAI-compatible). */
    private function llamarOpenRouter(string $mime, string $b64): string
    {
        $key = config('services.openrouter.key');
        if (! $key) {
            throw new RuntimeException('Falta OPENROUTER_API_KEY en .env para escanear facturas.');
        }

        $parteArchivo = str_starts_with($mime, 'image/')
            ? ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$b64}"]]
            : ['type' => 'file', 'file' => ['filename' => 'factura.pdf', 'file_data' => "data:{$mime};base64,{$b64}"]];

        $resp = $this->enviarConReintento(fn () => Http::withToken($key)
            ->withHeaders([
                'HTTP-Referer' => config('app.url'),
                'X-Title' => 'E.Comercial — Escaneo de facturas',
            ])
            ->timeout(120)
            ->post(rtrim((string) config('services.openrouter.base_url'), '/') . '/chat/completions', [
                'model' => config('services.openrouter.model'),
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->instruccionSistema()],
                    ['role' => 'user', 'content' => [
                        ['type' => 'text', 'text' => $this->instruccionUsuario()],
                        $parteArchivo,
                    ]],
                ],
            ]));

        if ($resp->failed()) {
            throw new RuntimeException('Error del modelo de visión: ' . $resp->status() . ' ' . $resp->body());
        }

        return (string) data_get($resp->json(), 'choices.0.message.content', '');
    }

    /**
     * Ejecuta la llamada HTTP con reintentos ante fallos transitorios:
     * cortes de conexión/DNS y respuestas 429 (rate-limit) o 503 (alta demanda),
     * frecuentes en los free tiers. Backoff lineal.
     */
    private function enviarConReintento(\Closure $hacer, int $maxIntentos = 4): Response
    {
        $ultimaConexion = null;
        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            try {
                $resp = $hacer();
            } catch (ConnectionException $e) {
                $ultimaConexion = $e;
                if ($intento < $maxIntentos) {
                    usleep(1_500_000 * $intento);
                    continue;
                }
                throw new RuntimeException('No se pudo conectar al modelo de visión: ' . $e->getMessage());
            }

            if (in_array($resp->status(), [429, 503], true) && $intento < $maxIntentos) {
                usleep(2_000_000 * $intento);
                continue;
            }

            return $resp;
        }

        throw new RuntimeException('No se pudo contactar al modelo de visión tras varios intentos.'
            . ($ultimaConexion ? ' ' . $ultimaConexion->getMessage() : ''));
    }

    private function instruccionSistema(): string
    {
        return <<<'TXT'
        Sos un extractor de datos de facturas de compra argentinas (proveedor → comercio).
        Las facturas NO tienen todas la misma estructura. Leé la imagen/PDF y devolvé
        EXCLUSIVAMENTE un objeto JSON válido (sin texto extra, sin markdown) con esta forma:

        {
          "cabecera": {
            "proveedor": string|null,
            "comprobante": { "tipo": string|null, "punto_venta": string|null, "numero": string|null },
            "fecha": "YYYY-MM-DD"|null,
            "remito": string|null,
            "cae": string|null,
            "totales": { "subtotal": number|null, "bonificacion": number|null,
                         "iva21": number|null, "iva105": number|null, "total": number|null }
          },
          "lineas": [
            {
              "tipo": "producto" | "gasto",
              "codigo": string|null,
              "descripcion": string,
              "cantidad": number,
              "p_unit": number,
              "total": number
            }
          ]
        }

        Reglas:
        - "tipo": "producto" para mercadería real; "gasto" para fletes, gestiones, transportes,
          comisiones, impuestos por línea o cargos administrativos (ej: "GESTION DE COMPRA 1%",
          "GESTIONES TRANSPORTES", "FLETE").
        - "codigo": si la descripción empieza con un código (ej "EVC330 EXHIBIDORA..."), poné ese
          código en "codigo" y el resto en "descripcion".
        - El "comprobante" es el del proveedor (suele estar arriba a la derecha, ej "A 0002-00107814":
          tipo="A", punto_venta="0002", numero="00107814"). NO lo confundas con el CUIT ni el remito.
        - Números con punto decimal, sin separador de miles, sin símbolo de moneda.
        - Si un dato no está, usá null. No inventes valores.
        TXT;
    }

    private function instruccionUsuario(): string
    {
        return 'Extraé los datos de esta factura de compra y devolvé el JSON pedido.';
    }

    /** @return array{cabecera:array<string,mixed>,lineas:array<int,array<string,mixed>>} */
    private function parsearJson(string $texto): array
    {
        $texto = trim($texto);
        // Por las dudas, quitar cercos de código si el modelo los agregó.
        $texto = preg_replace('/^```(?:json)?|```$/m', '', $texto);
        $data = json_decode(trim((string) $texto), true);

        if (! is_array($data)) {
            throw new RuntimeException('El modelo no devolvió un JSON válido: ' . mb_substr($texto, 0, 300));
        }

        return [
            'cabecera' => $data['cabecera'] ?? [],
            'lineas' => array_values(array_filter(
                $data['lineas'] ?? [],
                fn ($l) => is_array($l) && ($l['descripcion'] ?? '') !== '',
            )),
        ];
    }
}
