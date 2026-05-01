<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class Controller
{
    /**
     * Check which link tables have rows referencing $id and return structured data.
     *
     * Each $link entry: ['table' => '', 'column' => '', 'label' => '', 'unlinkable' => bool]
     *
     * @param  array<int, array{table: string, column: string, label: string, unlinkable: bool}>  $links
     * @return array<int, array{label: string, count: int, unlinkable: bool}>
     */
    protected function collectLinks(array $links, int $id): array
    {
        $found = [];
        foreach ($links as $link) {
            $count = (int) DB::table($link['table'])->where($link['column'], $id)->count();
            if ($count > 0) {
                $found[] = [
                    'label'      => $link['label'],
                    'count'      => $count,
                    'unlinkable' => $link['unlinkable'],
                ];
            }
        }
        return $found;
    }

    /**
     * Return 422 JSON with all linked items, or cascade-delete unlinkable ones.
     * Returns null if deletion may proceed; returns a JsonResponse if it must be blocked.
     *
     * @param  array<int, array{table: string, column: string, label: string, unlinkable: bool}>  $links
     */
    protected function handleLinkedOrCascade(
        Request $request,
        array $links,
        int $id,
        string $itemLabel,
    ): ?JsonResponse {
        $found = $this->collectLinks($links, $id);

        if (empty($found)) {
            return null;
        }

        $hasHardLinks = collect($found)->where('unlinkable', false)->isNotEmpty();

        if (! $request->boolean('cascade') || $hasHardLinks) {
            return response()->json([
                'message' => "Cannot delete: {$itemLabel} is linked to other records.",
                'linked'  => $found,
            ], 422);
        }

        // Cascade-delete only the unlinkable links
        foreach ($links as $link) {
            if ($link['unlinkable']) {
                DB::table($link['table'])->where($link['column'], $id)->delete();
            }
        }

        return null;
    }
}
