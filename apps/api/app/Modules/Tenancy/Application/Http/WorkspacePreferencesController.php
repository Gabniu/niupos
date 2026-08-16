<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\Http;

use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class WorkspacePreferencesController
{
    public function __construct(private TenantContext $context) {}

    public function show(): JsonResponse
    {
        $row = DB::table('tenant_workspace_preferences')
            ->where('tenant_id', (string) $this->context->id())
            ->first(['side_panel_visible', 'kiosk_mode']);

        return new JsonResponse(['data' => [
            'sidePanelVisible' => $row === null ? true : (bool) $row->side_panel_visible,
            'kioskMode' => $row !== null && (bool) $row->kiosk_mode,
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sidePanelVisible' => ['required', 'boolean'],
            'kioskMode' => ['required', 'boolean'],
        ]);
        $tenantId = (string) $this->context->id();

        $values = [
            'side_panel_visible' => (bool) $validated['sidePanelVisible'],
            'kiosk_mode' => (bool) $validated['kioskMode'],
            'updated_at' => now(),
        ];
        if (DB::table('tenant_workspace_preferences')->where('tenant_id', $tenantId)->exists()) {
            DB::table('tenant_workspace_preferences')->where('tenant_id', $tenantId)->update($values);
        } else {
            DB::table('tenant_workspace_preferences')->insert($values + ['tenant_id' => $tenantId, 'created_at' => now()]);
        }

        return $this->show();
    }
}
