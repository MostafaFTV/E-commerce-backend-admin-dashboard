<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AddressLocationController extends Controller
{
    public function provinces(): JsonResponse
    {
        $items = array_map(
            fn (array $item): array => [
                'id' => $item['id'],
                'name' => $item['name'],
            ],
            $this->readData('provinces')
        );

        return response()->json([
            'data' => $this->sortByName($items),
        ]);
    }

    public function cities(string $province): JsonResponse
    {
        $provinceId = (int) $province;

        $exists = array_any(
            $this->readData('provinces'),
            fn (array $item): bool =>
                $item['id'] === $provinceId
        );

        abort_unless($exists, 404);

        $items = [];

        foreach ($this->readData('cities') as $city) {
            if ($city['province_id'] !== $provinceId) {
                continue;
            }

            $items[] = [
                'id' => $city['id'],
                'province_id' => $provinceId,
                'name' => $city['name'],
            ];
        }

        return response()->json([
            'data' => $this->sortByName($items),
        ]);
    }

    private function readData(string $name): array
    {
        return json_decode(
            file_get_contents(
                resource_path('data/iran/' . $name . '.json')
            ),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function sortByName(array $items): array
    {
        if (class_exists(\Collator::class)) {
            $collator = new \Collator('fa_IR');

            usort(
                $items,
                fn (array $a, array $b): int =>
                    $collator->compare($a['name'], $b['name'])
            );
        } else {
            usort(
                $items,
                fn (array $a, array $b): int =>
                    strcmp($a['name'], $b['name'])
            );
        }

        return $items;
    }
}