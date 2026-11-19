<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ShiftBrief
{
    public function __construct(private DashboardQuery $dashboard) {}

    public function make(int $warehouseId, ?string $note = null): array
    {
        $data = $this->dashboard->forWarehouse($warehouseId);
        $prompt = $this->prompt($data, $note);

        try {
            $response = Http::connectTimeout(2)
                ->timeout(12)
                ->post(rtrim((string) config('services.ollama.url'), '/').'/api/generate', [
                    'model' => config('services.ollama.model'),
                    'prompt' => $prompt,
                    'stream' => false,
                ]);

            $text = trim((string) $response->json('response'));
            if ($response->successful() && $text !== '') {
                return ['brief' => $text, 'source' => 'ollama'];
            }
        } catch (\Throwable) {
            // El turno sigue con la regla local si Ollama no responde.
        }

        return ['brief' => $this->local($data, $note), 'source' => 'local'];
    }

    private function prompt(array $data, ?string $note): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $extra = $note ? "\nNota del integrador:\n{$note}\n" : '';

        return <<<TXT
Eres el jefe de turno de un almacén. Escribe en español, en seis líneas cortas, qué debe atender el operador ahora.
Usa solo cifras de este JSON. No inventes pedidos, placas ni cantidades.
{$extra}
JSON:
{$json}
TXT;
    }

    private function local(array $data, ?string $note): string
    {
        $kpis = $data['kpis'];
        $lines = [
            "{$data['warehouse']['name']}: {$kpis['open_inbound']} recepciones abiertas y {$kpis['pending_pick_lines']} líneas de picking pendientes.",
            "Hay {$kpis['in_transit']} despachos en ruta y {$kpis['below_min']} SKU bajo mínimo.",
        ];

        foreach (array_slice($data['below_min'], 0, 3) as $sku) {
            $lines[] = "Reponer {$sku['code']}: disponible {$sku['available']}, mínimo {$sku['min_qty']}.";
        }

        $next = $data['inbound'][0] ?? null;
        if ($next) {
            $lines[] = "Sigue la recepción {$next['number']} ({$next['received']} de {$next['expected']}).";
        }

        $trip = collect($data['shipments'])->first(fn ($item) => in_array($item['status'], ['loading', 'planned'], true));
        if ($trip) {
            $lines[] = "En muelle, el viaje {$trip['number']} hacia {$trip['destination_city']} está {$trip['status']}.";
        }

        if ($note) {
            $lines[] = 'Nota recibida: '.mb_strimwidth($note, 0, 180, '…');
        }

        return implode("\n", array_slice($lines, 0, 6));
    }
}
