<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingUpdateRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => SettingResource::collection($this->visibleSettings(auth()->user())->get()),
        ]);
    }

    public function update(SettingUpdateRequest $request): JsonResponse
    {
        $user = $request->user();

        foreach ($request->validatedValues() as $key => $value) {
            $setting = Setting::where('key', $key)->first();

            if (! $setting) {
                continue;
            }

            if (! $this->mayEdit($user, $setting)) {
                continue;
            }

            // (string) false is "", which reads back as a blank rather than a
            // stored 0; normalise booleans explicitly.
            $stored = $setting->type === 'bool'
                ? (filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0')
                : (string) $value;

            $setting->forceFill(['value' => $stored])->save();
        }

        return response()->json([
            'message' => __('Settings updated successfully.'),
            'data' => SettingResource::collection($this->visibleSettings($user)->get()),
        ]);
    }

    /**
     * Feature switches belong to features.manage; everything else to settings.manage.
     */
    private function mayEdit(?User $user, Setting $setting): bool
    {
        return $user?->hasPermission($setting->group === 'features' ? 'features.manage' : 'settings.manage') ?? false;
    }

    private function visibleSettings(?User $user)
    {
        return Setting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->when(! $user?->hasPermission('features.manage'), fn ($q) => $q->where('group', '!=', 'features'))
            ->when(! $user?->hasPermission('settings.manage'), fn ($q) => $q->where('group', 'features'));
    }

    public function features(): JsonResponse
    {
        $features = Setting::query()
            ->where('group', 'features')
            ->pluck('value', 'key')
            ->map(fn ($val) => filter_var($val, FILTER_VALIDATE_BOOLEAN))
            ->all();

        $defaults = [
            'feature_repairs' => true,
            'feature_technicians' => true,
            'feature_purchases' => true,
            'feature_expenses' => true,
            'feature_reports' => true,
            'feature_services' => true,
            'feature_inventory' => true,
            'feature_sales' => true,
        ];

        return response()->json([
            'data' => array_merge($defaults, $features),
        ]);
    }
}
